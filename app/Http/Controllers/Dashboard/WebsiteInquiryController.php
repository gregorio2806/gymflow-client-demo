<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\WebsiteInquiry;
use App\Support\WebsitePrivacy;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class WebsiteInquiryController extends Controller
{
    public function index()
    {
        $gym = app(CurrentGym::class)->get();
        Gate::authorize('manageWebsite', $gym);
        Inertia::encryptHistory();

        return Inertia::render('dashboard/website/Inquiries', [
            'retentionDays' => WebsitePrivacy::RETENTION_DAYS,
            'inquiries' => WebsiteInquiry::where('created_at', '>', now()->utc()->subDays(WebsitePrivacy::RETENTION_DAYS))
                ->orderByDesc('id')->paginate(10)->through(fn ($item) => [
                    'name' => $item->name, 'email' => $item->email, 'message' => $item->message,
                    'receivedAt' => $item->created_at->setTimezone($gym->timezone)->format('M j, Y g:i A'),
                ]),
        ])->toResponse(request())->header('Cache-Control', 'private, no-store');
    }
}
