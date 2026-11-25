<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('login'))) {
            $this->merge(['login' => strtolower(trim($this->input('login')))]);
        }
    }

    public function rules(): array
    {
        return ['login' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'max:200']];
    }
}
