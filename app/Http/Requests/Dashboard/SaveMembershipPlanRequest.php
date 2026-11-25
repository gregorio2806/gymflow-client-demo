<?php

namespace App\Http\Requests\Dashboard;

use App\Models\MembershipPlan;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveMembershipPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('membershipPlan') ? Gate::allows('update', $this->route('membershipPlan')) : Gate::allows('create', MembershipPlan::class);
    }

    public function rules(): array
    {
        return [
            'kind' => ['sometimes', Rule::in(['subscription', 'benefit', 'package']), ...($this->route('membershipPlan') ? [Rule::in([$this->route('membershipPlan')->kind])] : [])],
            'branch_ids' => ['nullable', 'array', 'max:100'],
            'branch_ids.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->where('gym_id', app(CurrentGym::class)->id())],
            'configuration' => ['nullable', 'array:discount_unit,discount_input,eligible_plan_ids,applies_to_packages,applies_to_renewals,allow_renewal,deferred_start_allowed,duration_mode,service,sessions,unlimited_validity,coach_ids'],
            'configuration.discount_unit' => ['required_if:kind,benefit', Rule::in(['percentage', 'peso'])],
            'configuration.discount_input' => ['required_if:kind,benefit', 'string', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/', ...($this->input('configuration.discount_unit') === 'percentage' ? ['numeric', 'max:100'] : [])],
            'configuration.eligible_plan_ids' => ['nullable', 'array', 'max:100'],
            'configuration.eligible_plan_ids.*' => ['integer', 'distinct', Rule::exists('membership_plans', 'id')->where('gym_id', app(CurrentGym::class)->id())->where('kind', 'subscription')],
            'configuration.applies_to_packages' => ['sometimes', 'boolean'],
            'configuration.applies_to_renewals' => ['sometimes', 'boolean'],
            'configuration.allow_renewal' => ['sometimes', 'boolean'],
            'configuration.deferred_start_allowed' => ['sometimes', 'boolean'],
            'configuration.service' => ['sometimes', 'nullable', 'string', 'max:100'],
            'configuration.sessions' => ['required_if:kind,package', 'integer', 'min:1', 'max:10000'],
            'configuration.duration_mode' => ['sometimes', Rule::in(['days', 'sessions'])],
            'configuration.unlimited_validity' => ['sometimes', 'boolean'],
            'configuration.coach_ids' => ['nullable', 'array', 'max:100'],
            'configuration.coach_ids.*' => ['integer', 'distinct', Rule::exists('gym_user', 'user_id')->where('gym_id', app(CurrentGym::class)->id())->where('role', 'coach')->where('is_active', true)],
            'name' => ['required', 'string', 'max:255'],
            'duration_amount' => ['required', 'integer', 'min:1', 'max:'.($this->input('duration_unit') === 'months' ? 120 : 3650)],
            'duration_unit' => ['required', Rule::in(['days', 'months'])],
            'price_php' => ['required', 'string', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'],
            'inclusions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['price_php.regex' => __('Enter a peso amount with at most two decimal places, such as 1500.50.')];
    }
}
