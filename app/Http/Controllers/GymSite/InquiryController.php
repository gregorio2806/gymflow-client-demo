<?php

namespace App\Http\Controllers\GymSite;

use App\Actions\ReceiveWebsiteInquiry;
use App\Http\Controllers\Controller;
use App\Http\Requests\GymSite\StoreInquiryRequest;
use App\Tenancy\CurrentGym;

class InquiryController extends Controller
{
    public function store(StoreInquiryRequest $request, ReceiveWebsiteInquiry $action)
    {
        $action->handle($request->validated(), $request);

        return redirect()->to(route('gym-site.home', ['gym' => $request->route('gym')]).'#contact')->with('inquiry_sent', app(CurrentGym::class)->id())->header('Cache-Control', 'private, no-store');
    }
}
