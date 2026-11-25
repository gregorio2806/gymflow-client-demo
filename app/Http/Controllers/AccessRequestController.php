<?php

namespace App\Http\Controllers;

use App\Enums\Plan;
use App\Models\AccessRequest;
use App\Notifications\AccessRequestReceived;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Public "Request access" form (Blade, works without JavaScript or an email app).
 *
 * Abuse protection (each submission emails the platform admin):
 * - rate limits: 3/hour per IP + 30/hour overall (AppServiceProvider, `access-request`)
 * - honeypot field (`website`) and a too-fast check (`form_started`, encrypted) — bots are ignored quietly
 * - the same email can't send another request within 24 hours
 * - old requests are pruned (AccessRequest::prunable)
 */
class AccessRequestController extends Controller
{
    /**
     * Real people need at least this long to fill in the form.
     */
    public const MIN_SECONDS_TO_FILL = 3;

    public function create(Request $request): View
    {
        return view('marketing.request-access', [
            'plans' => Plan::cases(),
            'selectedPlan' => Plan::tryFrom((string) $request->query('plan')) ?? Plan::Pro,
            'formStarted' => Crypt::encryptString((string) now()->timestamp),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Bots: pretend it worked, so they learn nothing.
        if ($this->looksLikeABot($request)) {
            return $this->sent();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gym_name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'plan' => ['required', Rule::enum(Plan::class)],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ]);

        $email = mb_strtolower($validated['email']);

        // Already asked recently: don't save or email again.
        $recentDuplicate = AccessRequest::where('email', $email)
            ->where('status', 'new')
            ->where('created_at', '>', now()->subDay())
            ->exists();

        if ($recentDuplicate) {
            return $this->sent();
        }

        $accessRequest = new AccessRequest(collect($validated)->except('consent')->all());
        $accessRequest->email = $email;
        $accessRequest->ip_address = $request->ip();
        $accessRequest->save();

        Notification::route('mail', config('platform.access_email'))->notify(new AccessRequestReceived($accessRequest));

        return $this->sent();
    }

    private function looksLikeABot(Request $request): bool
    {
        if (filled($request->input('website'))) {
            return true; // honeypot: hidden from people
        }

        try {
            $startedAt = (int) Crypt::decryptString((string) $request->input('form_started'));
        } catch (DecryptException) {
            return true; // missing or forged
        }

        return now()->timestamp - $startedAt < self::MIN_SECONDS_TO_FILL;
    }

    private function sent(): RedirectResponse
    {
        return to_route('access-requests.create')->with('status', 'sent');
    }
}
