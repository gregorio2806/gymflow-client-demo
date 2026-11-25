<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Support\Facades\Gate;

class UpdateCoachScheduleRequest extends StoreCoachScheduleRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('schedule'));
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['request_key']);
        $rules['coach_id'][] = 'in:'.$this->route('schedule')->coach_id;
        $rules['revision'] = ['required', 'integer', 'min:1'];

        return $rules;
    }
}
