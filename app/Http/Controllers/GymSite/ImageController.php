<?php

namespace App\Http\Controllers\GymSite;

use App\Http\Controllers\Controller;
use App\Models\GymWebsite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    public function __invoke(Request $request)
    {
        $content = GymWebsite::whereNotNull('published_at')->firstOrFail()->published_content;
        $asset = $request->query('asset', 'image');
        abort_unless(in_array($asset, ['image', 'logo', 'plans'], true), 404);
        $path = $content[$asset.'_path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Content-Type' => 'image/webp', 'Cache-Control' => 'no-cache', 'X-Content-Type-Options' => 'nosniff']);
    }
}
