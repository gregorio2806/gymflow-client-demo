<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\GymRole;
use App\Tenancy\CurrentGym;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InviteStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageStaff', app(CurrentGym::class)->get());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => [Rule::requiredIf(fn () => $this->input('role') === 'branch-manager' && ! $this->has('branch_ids')), 'nullable', 'integer', Rule::exists('branches', 'id')->where('gym_id', app(CurrentGym::class)->id())],
            'branch_ids' => ['sometimes', 'required', 'array', 'min:1', 'max:100'],
            'branch_ids.*' => ['required', 'integer', 'distinct', Rule::exists('branches', 'id')->where('gym_id', app(CurrentGym::class)->id())],
            'email' => ['required', 'email', 'max:255'],
            // Owners can't be invited — ownership transfer is a separate, deliberate action.
            'role' => ['required', Rule::in([GymRole::Staff->value, GymRole::Coach->value, GymRole::BranchManager->value])],
        ];
    }
}
