<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class MembershipPlanActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('membershipPlan'));
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
