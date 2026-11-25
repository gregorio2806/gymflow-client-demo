<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAttendanceQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact' => ['required', 'string', 'max:255'],
            'member_id' => ['prohibited'],
            'full_name' => ['prohibited'],
            'code' => ['prohibited'],
        ];
    }
}
