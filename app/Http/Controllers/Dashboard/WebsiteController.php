<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\ManageGymWebsite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveWebsiteRequest;
use App\Models\GymWebsite;
use App\Support\WebsitePage;
use App\Support\WebsitePrivacy;
use App\Tenancy\CurrentGym;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class WebsiteController extends Controller
{
    public function edit()
    {
        $gym = app(CurrentGym::class)->get();
        Gate::authorize('manageWebsite', $gym);
        $site = GymWebsite::first();
        $content = $site?->draft_content ?? $site?->published_content ?? [];
        $draft = [
            'gym_name' => $content['gym_name'] ?? $gym->name,
            'plans_mode' => $content['plans_mode'] ?? 'cards',
            'plans_alt' => $content['plans_alt'] ?? '',
            'description' => $content['description'] ?? '',
            'about' => $content['about_html'] ?? htmlspecialchars($content['about'] ?? '', ENT_QUOTES, 'UTF-8'),
            'contact' => array_merge(['phone' => '', 'email' => '', 'address' => '', 'hours' => ''], array_intersect_key($content['contact'] ?? [], array_flip(['phone', 'email', 'address', 'hours']))),
            'plans' => array_map(function ($plan) {
                $cents = (int) ($plan['price_centavos'] ?? 0);

                return ['name' => $plan['name'], 'price_php' => intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT), 'duration' => $plan['duration'] ?? '', 'description' => $plan['description'] ?? ''];
            }, $content['plans'] ?? []),
            'facilities' => array_map(fn ($item) => ['name' => $item['name'], 'description' => $item['description'] ?? ''], $content['facilities'] ?? []),
            'announcements' => array_map(fn ($item) => ['title' => $item['title'], 'body' => $item['body'], 'date' => $item['date']], $content['announcements'] ?? []),
            'image_alt' => $content['image_alt'] ?? '',
            'privacy' => array_merge(WebsitePrivacy::defaults(), $content['privacy'] ?? []),
        ];

        return Inertia::render('dashboard/website/Edit', [
            'draft' => $draft, 'revision' => $site?->draft_revision ?? 0, 'hasDraft' => $site?->draft_content !== null,
            'imageUrl' => empty($content['image_path']) ? null : route('website.image'),
            'logoUrl' => empty($content['logo_path']) ? null : route('website.image', ['asset' => 'logo', 'revision' => $site?->draft_revision]),
            'plansImageUrl' => empty($content['plans_path']) ? null : route('website.image', ['asset' => 'plans', 'revision' => $site?->draft_revision]),
            'publishedAt' => $site?->published_at?->setTimezone($gym->timezone)->format('M j, Y g:i A'),
            'publicUrl' => route('gym-site.home', ['gym' => $gym->slug]),
        ]);
    }

    public function update(SaveWebsiteRequest $request, ManageGymWebsite $action)
    {
        $action->save($request->validated(), $request->file('image'), $request->file('logo'), $request->file('plans_image'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Website draft saved. Publish it when ready.']);

        return to_route('website.edit');
    }

    public function preview(Request $request)
    {
        Gate::authorize('manageWebsite', app(CurrentGym::class)->get());
        $site = GymWebsite::whereNotNull('draft_content')->firstOrFail();
        $view = WebsitePage::render($site->draft_content, (string) $request->query('section', 'home'), true);

        return response($view)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function publish(Request $request, ManageGymWebsite $action)
    {
        Gate::authorize('manageWebsite', app(CurrentGym::class)->get());
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $action->publish((int) $data['revision']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gym website published.']);

        return to_route('website.edit');
    }

    public function image(Request $request)
    {
        Gate::authorize('manageWebsite', app(CurrentGym::class)->get());
        $site = GymWebsite::firstOrFail();
        $content = $site->draft_content ?? $site->published_content;
        $asset = $request->query('asset', 'image');
        abort_unless(in_array($asset, ['image', 'logo', 'plans'], true), 404);
        $path = $content[$asset.'_path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $response = response()->file(Storage::disk('local')->path($path), ['Content-Type' => 'image/webp', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
        $response->setPrivate();

        return $response;
    }
}
