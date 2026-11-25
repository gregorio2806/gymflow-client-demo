<?php

namespace App\Http\Requests\Dashboard;

use App\Models\CoachSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CoachCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', CoachSchedule::class);
    }

    public function rules(): array
    {
        return ['coach_id' => ['nullable', 'integer', 'min:1'], 'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:1000-01', 'before_or_equal:9998-12'], 'day' => ['nullable', 'date_format:Y-m-d'], 'view' => ['nullable', 'in:month,week,day,list']];
    }
}
