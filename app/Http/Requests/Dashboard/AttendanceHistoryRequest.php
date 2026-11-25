<?php

namespace App\Http\Requests\Dashboard;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AttendanceHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', AttendanceRecord::class);
    }

    public function rules(): array
    {
        return [
            'view' => ['sometimes', Rule::in(['day', 'week', 'month'])],
            'date' => ['sometimes', 'required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', Rule::in(['manual', 'qr'])],
            'per_page' => ['sometimes', 'integer', Rule::in([5, 10, 25, 50])],
            'export' => ['sometimes', Rule::in(['csv'])],
        ];
    }
}
