<?php

namespace App\Actions;

use App\Models\Gym;
use App\Models\GymWebsite;
use App\Models\WebsiteInquiry;
use App\Support\WebsitePrivacy;
use App\Tenancy\CurrentGym;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReceiveWebsiteInquiry
{
    public function handle(array $data, Request $request): void
    {
        DB::transaction(function () use ($data, $request) {
            $gym = Gym::whereKey(app(CurrentGym::class)->id())->lockForUpdate()->firstOrFail();
            $site = GymWebsite::whereNotNull('published_at')->whereNotNull('published_content')->firstOrFail();
            abort_unless(WebsitePrivacy::enabled($site->published_content), 404);
            try {
                $proof = json_decode(Crypt::decryptString($data['form_token']), true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $proof = [];
            }
            $valid = is_array($proof) && ($proof['gym_id'] ?? null) === $gym->id
                && is_string($proof['session'] ?? null) && hash_equals(hash('sha256', $request->session()->getId()), $proof['session'])
                && is_string($proof['nonce'] ?? null) && preg_match('/\A[0-9a-f-]{36}\z/', $proof['nonce'])
                && is_int($proof['issued_at'] ?? null) && $proof['issued_at'] <= now()->timestamp - 3 && $proof['issued_at'] > now()->timestamp - 1800
                && ($proof['privacy_revision'] ?? null) === WebsitePrivacy::revision($site->published_content);
            if (! $valid) {
                throw ValidationException::withMessages(['form_token' => 'Please refresh this page, review the privacy notice, and try again.'])->redirectTo(route('gym-site.home', ['gym' => $gym->slug]).'#contact');
            }
            if (! empty($data['website'])) {
                return;
            }
            $fields = ['name' => trim($data['name']), 'email' => strtolower(trim($data['email'])), 'message' => trim($data['message'])];
            $hash = hash('sha256', json_encode($fields));
            $requestHash = hash('sha256', $proof['nonce']);
            $existing = WebsiteInquiry::where('request_hash', $requestHash)->first();
            if ($existing) {
                if (! hash_equals($existing->content_hash, $hash)) {
                    throw ValidationException::withMessages(['form_token' => 'This form was already submitted. Refresh before sending a new message.'])->redirectTo(route('gym-site.home', ['gym' => $gym->slug]).'#contact');
                }

                return;
            }
            (new WebsiteInquiry)->forceFill([...$fields, 'request_hash' => $requestHash, 'content_hash' => $hash,
                'privacy_version' => WebsitePrivacy::VERSION, 'privacy_revision' => $proof['privacy_revision'], 'consented_at' => now()->utc(),
            ])->save();
        });
    }
}
