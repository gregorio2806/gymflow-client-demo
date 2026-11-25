<?php

namespace App\Http\Requests\Dashboard;

use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ConfirmPlanSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageStaff', app(CurrentGym::class)->get());
    }

    public function rules(): array
    {
        return ['selection' => ['required', Rule::in(['staff', 'branches'])]];
    }
}
