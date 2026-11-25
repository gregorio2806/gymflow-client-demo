<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ChangeMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('member'));
    }

    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['suspend', 'resume', 'cancel'])], 'reason' => ['nullable', 'string', 'max:500']];
    }
}
