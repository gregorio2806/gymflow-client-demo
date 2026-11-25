<?php

namespace App\Http\Requests\Dashboard;

use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssignMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('member'));
    }

    public function rules(): array
    {
        return [
            'membership_plan_id' => ['required', 'integer', Rule::exists('membership_plans', 'id')->where('gym_id', app(CurrentGym::class)->id())],
            'operation' => ['required', Rule::in(['assign', 'renew'])],
            'starts_on' => ['required_if:operation,assign', 'nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'],
            'request_key' => ['required', 'uuid'],
        ];
    }
}
