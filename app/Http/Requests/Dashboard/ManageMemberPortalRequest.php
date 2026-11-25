<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ManageMemberPortalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('member'));
    }

    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::in(['create-login', 'reset-password', 'disable-login', 'generate-qr', 'reissue-qr', 'revoke-qr', 'issue-status-code', 'revoke-status-code'])],
            'login' => ['nullable', 'required_if:operation,create-login', 'string', 'max:255', 'regex:/\A[a-zA-Z0-9@._+-]{3,255}\z/'],
        ];
    }
}
