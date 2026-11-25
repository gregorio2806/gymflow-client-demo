<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Support\Facades\Gate;

class CancelCoachScheduleRequest extends StoreCoachScheduleRequest
{
    public function authorize(): bool
    {
        return Gate::allows('cancel', $this->route('schedule'));
    }

    public function rules(): array
    {
        $rules = parent::rules();

        return ['month' => $rules['month'], 'day' => $rules['day'], 'revision' => ['required', 'integer', 'min:1']];
    }
}
