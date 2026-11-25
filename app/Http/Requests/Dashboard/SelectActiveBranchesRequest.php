<?php

namespace App\Http\Requests\Dashboard;

use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SelectActiveBranchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageStaff', app(CurrentGym::class)->get());
    }

    public function rules(): array
    {
        return [
            'branch_ids' => ['required', 'array', 'min:1', 'max:'.app(CurrentGym::class)->get()->subscription->branchLimit()],
            'branch_ids.*' => ['required', 'integer', 'distinct', Rule::exists('branches', 'id')->where('gym_id', app(CurrentGym::class)->id())],
        ];
    }
}
