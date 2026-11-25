<?php

namespace App\Support;

use App\Models\GymWebsite;
use App\Tenancy\CurrentGym;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;
use Throwable;

class WebsitePageCache
{
    public function render(GymWebsite $website, Request $request): string
    {
        // A live form contains session-bound CSRF/proof and visitor feedback. Never cache it.
        if ($website->contact_form_enabled) {
            return $this->html($website);
        }
        $platformScheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $platformPort = parse_url(config('app.url'), PHP_URL_PORT) ?: ($platformScheme === 'https' ? 443 : 80);
        // Alternate Host ports/schemes must not create unbounded cache variants.
        if ($request->getScheme() !== $platformScheme || $request->getPort() !== $platformPort) {
            return $this->html($website);
        }
        // Only the first page is cached: arbitrary pagination cannot create unlimited entries.
        foreach (WebsitePage::paginationQuery() as $page) {
            if ($page > 1) {
                return $this->html($website);
            }
        }
        $ttl = max(0, (int) config('website.cache_ttl_seconds', 300));
        if ($ttl === 0) {
            return $this->html($website);
        }
        $gym = app(CurrentGym::class)->get();
        $hot = public_path('hot');
        $manifest = public_path('build/manifest.json');
        $assets = [is_file($hot) ? hash_file('sha256', $hot) : null, is_file($manifest) ? hash_file('sha256', $manifest) : null];
        $identity = hash('sha256', json_encode([$website->id, $website->published_revision, $request->getSchemeAndHttpHost(), $gym->name, $gym->slug, $assets, Feature::for($gym)->active('member-portal')]));
        // One slot per gym bounds database-cache storage even across many publishes/builds.
        $key = 'website-page:'.$gym->id;
        try {
            $cached = Cache::get($key);
            if (is_array($cached) && ($cached['version'] ?? null) === $identity && is_string($cached['html'] ?? null)) {
                return $cached['html'];
            }
        } catch (Throwable) {
            Log::warning('Public website cache read failed.', ['gym_id' => $gym->id]);

            return $this->html($website);
        }
        $html = $this->html($website);
        try {
            Cache::put($key, ['version' => $identity, 'html' => $html], min($ttl, 3600));
        } catch (Throwable) {
            Log::warning('Public website cache write failed.', ['gym_id' => $gym->id]);
        }

        return $html;
    }

    public static function forget(int $gymId): void
    {
        try {
            Cache::forget('website-page:'.$gymId);
        } catch (Throwable) {
            Log::warning('Public website cache cleanup failed.', ['gym_id' => $gymId]);
        }
    }

    private function html(GymWebsite $website): string
    {
        // Never retrieve draft content on public requests. Keep this query explicitly tenant scoped.
        $content = GymWebsite::whereKey($website->id)->whereNotNull('published_at')->whereNotNull('published_content')->firstOrFail(['published_content'])->published_content;

        return WebsitePage::render($content, 'home')->render();
    }
}
