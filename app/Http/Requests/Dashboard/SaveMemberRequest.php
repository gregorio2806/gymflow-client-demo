<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Member;
use App\Support\PricingCheckout;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('member') ? Gate::allows('update', $this->route('member')) : Gate::allows('create', Member::class);
    }

    public function rules(): array
    {
        $context = app(CurrentGym::class);

        return [
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9][0-9 ()-]{6,19}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'home_branch_id' => $this->route('member') ? ['required', 'integer', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('gym_id', $context->id())->when($context->branchId(), fn ($query, $id) => $query->where('id', $id)))] : ['exclude'],
            'privacy_consent' => ['sometimes', 'nullable', 'boolean'],
            ...($this->route('member') ? [] : ($this->boolean('pricing_checkout') ? PricingCheckout::rules() : [
                'membership_plan_id' => ['required', 'integer', Rule::exists('membership_plans', 'id')->where('gym_id', $context->id())->where('is_active', true)],
                'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'],
                'request_key' => ['required', 'uuid'],
                'discount_mode' => ['sometimes', 'required', Rule::in(['percentage', 'peso'])],
                'discount_percent' => ['exclude_if:discount_mode,peso', 'nullable', 'string', 'regex:/^\d{1,3}(?:\.\d{1,2})?$/'],
                'discount_php' => ['exclude_unless:discount_mode,peso', 'required', 'string', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'],
                'discount_type' => ['sometimes', 'required', Rule::in(['regular', 'student', 'pwd', 'senior', 'others'])],
                'discount_other' => ['exclude_unless:discount_type,others', 'required', 'string', 'max:100'],
            ])),
        ];
    }

    public function messages(): array
    {
        return ['membership_plan_id.required' => __('Choose a membership plan or subscription.'), 'membership_plan_id.exists' => __('Choose an active plan from this gym.'),
            'discount_other.required' => __('Describe the other discount type.')];
    }
}
