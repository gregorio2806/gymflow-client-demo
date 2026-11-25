<?php

namespace App\Http\Controllers\GymSite;

use App\Http\Controllers\Controller;
use App\Models\GymWebsite;
use App\Tenancy\CurrentGym;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        abort_unless(GymWebsite::whereNotNull('published_at')->whereNotNull('published_content')->exists(), 404);
        $slug = app(CurrentGym::class)->get()->slug;
        $urls = [route('gym-site.home', ['gym' => $slug])];

        return response()->view('gym-site.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
