<?php

namespace App\Http\Requests\Dashboard;

use App\Models\CoachSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCoachScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', CoachSchedule::class);
    }

    public function rules(): array
    {
        return [
            'coach_id' => ['required', 'integer', 'min:1'],
            'branch_id' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:100'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:1000-01-01', 'before:9999-01-01'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at', 'before:9999-01-01'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'request_key' => ['required', 'uuid'],
            'month' => ['required', 'date_format:Y-m', 'after_or_equal:1000-01', 'before_or_equal:9998-12'],
            'day' => ['bail', 'required', 'date_format:Y-m-d', function ($attribute, $value, $fail) {
                if (substr($value, 0, 7) !== $this->input('month')) {
                    $fail('Choose a day in the displayed month.');
                }
            }],
        ];
    }
}
