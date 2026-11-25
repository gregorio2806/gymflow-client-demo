<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\GymRole;
use App\Tenancy\CurrentGym;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
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
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('gym_id', app(CurrentGym::class)->id())],
            'branch_ids' => ['sometimes', 'required', 'array', 'min:1', 'max:100'],
            'branch_ids.*' => ['required', 'integer', 'distinct', Rule::exists('branches', 'id')->where('gym_id', app(CurrentGym::class)->id())],
            'name' => ['required', 'string', 'max:255'],
            // Omitted for owners (their role can't change). Never "owner": ownership transfer is separate.
            'role' => ['nullable', Rule::in([GymRole::Staff->value, GymRole::Coach->value, GymRole::BranchManager->value])],
        ];
    }
}
