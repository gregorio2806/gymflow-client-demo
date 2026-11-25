<?php

namespace App\Http\Requests\Dashboard;

use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SetActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageStaff', app(CurrentGym::class)->get());
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
