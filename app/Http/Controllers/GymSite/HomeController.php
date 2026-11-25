<?php

namespace App\Http\Controllers\GymSite;

use App\Http\Controllers\Controller;
use App\Models\GymWebsite;
use App\Support\WebsitePageCache;
use App\Tenancy\CurrentGym;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, WebsitePageCache $cache): View|RedirectResponse|Response
    {
        $gym = app(CurrentGym::class)->get();
        $page = str_replace('gym-site.', '', $request->route()->getName());
        $website = GymWebsite::whereNotNull('published_at')->whereNotNull('published_content')->first(['id', 'gym_id', 'published_revision', 'contact_form_enabled']);
        if (! $website) {
            abort_unless($page === 'home', 404);

            return view('gym-site.pending', ['gymName' => $gym->name]);
        }

        if ($page !== 'home') {
            $parameters = ['gym' => $gym->slug];
            if (in_array($page, ['plans', 'facilities', 'announcements'], true) && $request->has('page')) {
                $parameters[$page.'_page'] = filter_var($request->query('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) ?: 1;
            }

            return redirect()->to(route('gym-site.home', $parameters).'#'.$page, 301);
        }

        // Cache only HTML, never a response carrying a visitor's cookies or session headers.
        return response($cache->render($website, $request))->header('Cache-Control', $website->contact_form_enabled ? 'private, no-store' : 'private, no-cache');
    }
}
