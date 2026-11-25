<?php

namespace App\Support;

use App\Tenancy\CurrentGym;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class WebsitePrivacy
{
    public const VERSION = '2026-10-06';

    public const RETENTION_DAYS = 365;

    public static function defaults(): array
    {
        return ['contact_name' => '', 'contact_email' => '', 'details' => '', 'reviewed' => false, 'contact_form_enabled' => false];
    }

    public static function enabled(array $content): bool
    {
        $privacy = $content['privacy'] ?? [];

        return ! empty($privacy['contact_form_enabled']) && ! empty($privacy['reviewed'])
            && ! empty(trim($privacy['contact_name'] ?? '')) && filter_var($privacy['contact_email'] ?? '', FILTER_VALIDATE_EMAIL)
            && ! empty(trim($privacy['details'] ?? ''));
    }

    public static function revision(array $content): string
    {
        $gym = app(CurrentGym::class)->get();

        return hash('sha256', json_encode([self::VERSION, $gym->name, $content['privacy'] ?? self::defaults()]));
    }

    public static function formToken(Request $request, array $content): string
    {
        return Crypt::encryptString(json_encode([
            'gym_id' => app(CurrentGym::class)->id(), 'session' => hash('sha256', $request->session()->getId()),
            'nonce' => (string) Str::uuid(), 'issued_at' => now()->timestamp, 'privacy_revision' => self::revision($content),
        ]));
    }
}
