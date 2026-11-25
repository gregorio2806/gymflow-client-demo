<?php

namespace App\Http\Requests\Dashboard;

use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveWebsiteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Multipart form data cannot encode an empty array. Missing sections mean no entries.
        foreach (['plans', 'facilities', 'announcements'] as $section) {
            if (! $this->exists($section)) {
                $this->merge([$section => []]);
            }
        }
    }

    public function authorize(): bool
    {
        return Gate::allows('manageWebsite', app(CurrentGym::class)->get());
    }

    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:0'],
            'gym_name' => ['sometimes', 'required', 'string', 'max:255'],
            'plans_mode' => ['sometimes', 'required', Rule::in(['cards', 'image'])],
            'plans_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=5000,max_height=5000'],
            'plans_alt' => ['nullable', 'required_with:plans_image', 'string', 'max:2000'],
            'remove_plans_image' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:300'],
            'about' => ['nullable', 'string', 'max:12000'],
            'contact' => ['required', 'array:phone,email,address,hours'],
            'contact.phone' => ['nullable', 'string', 'max:30'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.address' => ['nullable', 'string', 'max:500'],
            'contact.hours' => ['nullable', 'string', 'max:500'],
            'plans' => [Rule::excludeIf(fn () => $this->input('plans_mode') === 'image'), 'present', 'array', 'max:12'],
            'plans.*' => ['array:name,price_php,duration,description'],
            'plans.*.name' => ['required', 'string', 'max:100'],
            'plans.*.price_php' => ['required', 'string', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'plans.*.duration' => ['required', 'string', 'max:100'],
            'plans.*.description' => ['nullable', 'string', 'max:1000'],
            'facilities' => ['present', 'array', 'max:12'],
            'facilities.*' => ['array:name,description'],
            'facilities.*.name' => ['required', 'string', 'max:100'],
            'facilities.*.description' => ['nullable', 'string', 'max:1000'],
            'announcements' => ['present', 'array', 'max:12'],
            'announcements.*' => ['array:title,body,date'],
            'announcements.*.title' => ['required', 'string', 'max:150'],
            'announcements.*.body' => ['required', 'string', 'max:3000'],
            'announcements.*.date' => ['required', 'date_format:Y-m-d'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=5000,max_height=5000'],
            'image_alt' => ['nullable', 'required_with:image', 'string', 'max:200'],
            'remove_image' => ['sometimes', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=5000,max_height=5000'],
            'remove_logo' => ['sometimes', 'boolean'],
            'privacy' => ['sometimes', 'array:contact_name,contact_email,details,reviewed,contact_form_enabled'],
            'privacy.contact_form_enabled' => ['sometimes', 'boolean'],
            'privacy.contact_name' => [Rule::requiredIf(fn () => $this->boolean('privacy.contact_form_enabled')), 'nullable', 'string', 'max:100'],
            'privacy.contact_email' => [Rule::requiredIf(fn () => $this->boolean('privacy.contact_form_enabled')), 'nullable', 'email', 'max:255'],
            'privacy.details' => [Rule::requiredIf(fn () => $this->boolean('privacy.contact_form_enabled')), 'nullable', 'string', 'max:2000'],
            'privacy.reviewed' => [Rule::requiredIf(fn () => $this->boolean('privacy.contact_form_enabled')), Rule::when(fn () => $this->boolean('privacy.contact_form_enabled'), 'accepted'), 'nullable', 'boolean'],
        ];
    }
}
