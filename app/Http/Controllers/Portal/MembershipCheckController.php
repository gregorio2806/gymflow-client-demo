<?php

namespace App\Http\Controllers\Portal;

use App\Actions\ManageAttendanceQr;
use App\Enums\MemberQrPurpose;
use App\Enums\MemberSubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\CheckMembershipRequest;
use App\Http\Requests\Portal\GenerateAttendanceQrRequest;
use App\Models\GymWebsite;
use App\Models\Member;
use App\Models\MemberQrToken;
use App\Tenancy\CurrentGym;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Pennant\Feature;

class MembershipCheckController extends Controller
{
    public function create()
    {
        $gym = app(CurrentGym::class)->get();
        $websiteEnabled = Feature::for($gym)->active('website');
        $content = $websiteEnabled ? (GymWebsite::whereNotNull('published_at')->value('published_content') ?? []) : [];

        return Inertia::render('portal/CheckMembership', [
            'gymName' => $content['gym_name'] ?? $gym->name, 'gymSlug' => $gym->slug,
            'memberAuthAvailable' => Feature::for($gym)->active('member-auth'),
            'qrAttendanceAvailable' => Feature::for($gym)->active('qr-attendance'),
            'homeUrl' => $websiteEnabled ? route('gym-site.home', ['gym' => $gym->slug], false) : null,
            'logoUrl' => empty($content['logo_path']) ? null : route('gym-site.image', ['gym' => $gym->slug, 'asset' => 'logo', 'v' => substr(hash('sha256', $content['logo_path']), 0, 16)], false),
            'imageUrl' => empty($content['image_path']) ? null : route('gym-site.image', ['gym' => $gym->slug, 'v' => substr(hash('sha256', $content['image_path']), 0, 16)], false),
        ]);
    }

    public function store(CheckMembershipRequest $request)
    {
        $gym = app(CurrentGym::class)->get();
        $data = $request->validated();
        $publicSearch = $request->filled('contact');
        $value = $data['code'] ?? null;
        $member = null;
        if ($publicSearch) {
            $member = $this->memberForContact($data['contact']);
        } elseif (is_string($value)) {
            $value = trim($value);
            if (str_starts_with($value, 'gym-attendance:')) {
                $value = substr($value, 15);
            } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
                $url = parse_url($value);
                $value = ($url['host'] ?? '') === $request->getHost() && ($url['path'] ?? '') === '/portal/access' ? ($url['fragment'] ?? '') : '';
            }
            if (preg_match('/\A[A-Za-z0-9]{64}\z/', $value)) {
                $token = MemberQrToken::whereIn('purpose', [MemberQrPurpose::Attendance, MemberQrPurpose::Portal])->where('token_hash', hash('sha256', $value))->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()->utc()))->first();
                $member = $token?->member;
            } else {
                $code = strtoupper(str_replace(['-', ' '], '', $value));
                if (preg_match('/\A[A-F0-9]{20}\z/', $code)) {
                    $member = Member::where('status_code_hash', hash('sha256', $code))->first();
                }
            }
        }
        if (! $member || ! $member->is_active) {
            if ($publicSearch) {
                return response()->json(['errors' => ['lookup' => ['No matching member was found. Check your phone number or email, or ask the gym.']]], 422);
            }

            return response()->json(['errors' => ['code' => ['This QR or code is unavailable. Ask the gym to check or replace it.']]], 422);
        }
        $at = now()->utc();
        $period = $member->subscriptions()->where('status', '!=', MemberSubscriptionStatus::Cancelled)->where('starts_at', '<=', $at)->where('ends_at', '>', $at)->orderByDesc('starts_at')->orderByDesc('id')->first()
            ?? $member->subscriptions()->where('status', '!=', MemberSubscriptionStatus::Cancelled)->where('starts_at', '>', $at)->orderBy('starts_at')->orderBy('id')->first()
            ?? $member->subscriptions()->orderByDesc('starts_at')->orderByDesc('id')->first();

        $status = $period ? $period->displayStatus($at, $gym->timezone) : 'No membership assigned';

        // A lookup never grants portal sign-in or attendance access.
        return response()->json($publicSearch ? [
            'maskedName' => preg_replace_callback('/\S+/u', function ($match) {
                $length = mb_strlen($match[0]);

                return Str::mask($match[0], '*', min(1, $length - 1), max(1, $length - 2));
            }, $member->name) ?? '*',
            'planName' => $period?->plan_name,
            'startsOn' => $period?->starts_at->setTimezone($gym->timezone)->toDateString(),
            'validThrough' => $period?->ends_at->subSecond()->setTimezone($gym->timezone)->toDateString(),
            'status' => $status,
        ] : ['status' => $status]);
    }

    public function attendanceQr(GenerateAttendanceQrRequest $request, ManageAttendanceQr $action): JsonResponse
    {
        $member = $this->memberForContact($request->validated('contact'));
        if (! $member) {
            return response()->json(['errors' => ['lookup' => ['No matching member was found. Check your phone number or email, or ask the gym.']]], 422);
        }

        return response()->json(['payload' => $action->handle($member, 'generate', false)])->header('Cache-Control', 'no-store, private');
    }

    private function memberForContact(string $value): ?Member
    {
        $contact = trim($value);
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            return Member::where('is_active', true)->whereRaw('LOWER(email) = ?', [mb_strtolower($contact)])->first(['id', 'name', 'is_active']);
        }

        $digits = preg_replace('/\D+/', '', $contact);

        return $digits !== ''
            ? Member::where('is_active', true)->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mobile, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = ?", [$digits])->first(['id', 'name', 'is_active'])
            : null;
    }
}
