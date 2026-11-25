<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class CheckMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact' => ['nullable', 'required_without:code', 'string', 'max:255', 'prohibits:member_id'],
            'member_id' => ['prohibited'],
            'full_name' => ['prohibited'],
            'code' => ['nullable', 'required_without:contact', 'string', 'max:1000', 'prohibits:contact'],
        ];
    }
}
