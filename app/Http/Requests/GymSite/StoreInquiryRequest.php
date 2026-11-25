<?php

namespace App\Http\Requests\GymSite;

use App\Models\GymWebsite;
use App\Support\WebsitePrivacy;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Http\FormRequest;

class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $content = GymWebsite::whereNotNull('published_at')->whereNotNull('published_content')->firstOrFail()->published_content;
        abort_unless(WebsitePrivacy::enabled($content), 404);
        $this->session()->put('inquiry_gym', app(CurrentGym::class)->id());

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:2000'], 'consent' => ['required', 'accepted'],
            'form_token' => ['required', 'string', 'max:1500'], 'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('gym-site.home', ['gym' => $this->route('gym')]).'#contact';
    }
}
