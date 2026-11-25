<?php

namespace App\Http\Requests\Dashboard;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', AttendanceRecord::class);
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'min:1'],
            'token' => ['required_without:member_id', 'prohibits:member_id', 'nullable', 'string', 'regex:/\Agym-attendance:[A-Za-z0-9]{64}\z/'],
            'member_id' => ['required_without:token', 'prohibits:token', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return ['token.regex' => __('Scan a valid member attendance QR.')];
    }
}
