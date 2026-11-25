<?php

namespace App\Support;

use App\Tenancy\CurrentGym;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Pennant\Feature;

class WebsitePage
{
    public static function render(array $content, string $page, bool $preview = false): View
    {
        $gym = app(CurrentGym::class)->get();
        $titles = ['home' => 'Home', 'about' => 'About', 'plans' => 'Membership plans', 'facilities' => 'Facilities', 'announcements' => 'Announcements', 'contact' => 'Contact'];
        abort_unless(isset($titles[$page]), 404);
        $query = self::paginationQuery();
        $lists = [];
        foreach (['plans', 'facilities', 'announcements'] as $section) {
            $list = collect($content[$section] ?? []);
            $pageName = $section.'_page';
            $currentPage = max(1, (int) ($query[$pageName] ?? 1));
            $lists[$section] = (new LengthAwarePaginator($list->forPage($currentPage, 12)->values(), $list->count(), 12, $currentPage, [
                'path' => $preview ? route('website.preview') : route('gym-site.home', ['gym' => $gym->slug]),
                'query' => $query, 'pageName' => $pageName,
            ]))->fragment($section);
        }

        return view('gym-site.page', [
            'gymName' => $content['gym_name'] ?? $gym->name, 'slug' => $gym->slug, 'pageTitle' => 'Home', 'preview' => $preview,
            'embedded' => $preview && request()->boolean('embedded'),
            'description' => $content['description'] ?? '', 'about' => $content['about'] ?? '',
            'aboutHtml' => isset($content['about_html']) ? WebsiteRichText::sanitize($content['about_html']) : null,
            'contact' => array_intersect_key($content['contact'] ?? [], array_flip(['phone', 'email', 'address', 'hours'])),
            'lists' => $lists, 'canonical' => route('gym-site.home', ['gym' => $gym->slug]),
            'imageUrl' => empty($content['image_path']) ? null : ($preview ? route('website.image') : route('gym-site.image', ['gym' => $gym->slug])),
            'imageAlt' => $content['image_alt'] ?? '',
            'plansMode' => $content['plans_mode'] ?? 'cards',
            'plansAlt' => $content['plans_alt'] ?? '',
            'plansImageUrl' => empty($content['plans_path']) ? null : ($preview ? route('website.image', ['asset' => 'plans', 'v' => substr(hash('sha256', $content['plans_path']), 0, 16)]) : route('gym-site.image', ['gym' => $gym->slug, 'asset' => 'plans', 'v' => substr(hash('sha256', $content['plans_path']), 0, 16)])),
            'logoUrl' => empty($content['logo_path']) ? null : ($preview ? route('website.image', ['asset' => 'logo', 'v' => substr(hash('sha256', $content['logo_path']), 0, 16)]) : route('gym-site.image', ['gym' => $gym->slug, 'asset' => 'logo', 'v' => substr(hash('sha256', $content['logo_path']), 0, 16)])),
            'membershipCheckUrl' => Feature::for($gym)->active('member-portal') ? route('portal.check', ['gym' => $gym->slug]) : null,
            'privacy' => array_merge(WebsitePrivacy::defaults(), $content['privacy'] ?? []),
            'contactFormEnabled' => WebsitePrivacy::enabled($content),
            'inquiryFeedback' => ! $preview && session('inquiry_gym') === $gym->id,
            'formToken' => ! $preview && WebsitePrivacy::enabled($content) ? WebsitePrivacy::formToken(request(), $content) : null,
        ]);
    }

    public static function paginationQuery(): array
    {
        $query = [];
        foreach (['plans_page', 'facilities_page', 'announcements_page'] as $pageName) {
            if (request()->has($pageName)) {
                $query[$pageName] = filter_var(request()->query($pageName), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) ?: 1;
            }
        }

        return $query;
    }
}
