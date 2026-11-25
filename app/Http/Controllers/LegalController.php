<?php

namespace App\Http\Controllers;

use App\Support\LegalDocuments;

class LegalController extends Controller
{
    public function show(string $document)
    {
        $bundle = LegalDocuments::bundle();
        $page = collect($bundle['documents'])->firstWhere('slug', $document);
        abort_unless($page, 404);

        return response()->view('marketing.legal', ['page' => $page, 'legal' => $bundle]);
    }
}
