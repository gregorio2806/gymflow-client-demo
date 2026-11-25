<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\AppointmentService;
use App\Enums\GymRole;
use App\Support\CoachCalendarAccess;
use Illuminate\Validation\Rule;

class SaveAppointmentRequest extends StoreCoachScheduleRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && CoachCalendarAccess::actor($this->user())?->pivot->role !== GymRole::Coach;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['title'], $rules['month'], $rules['day']);
        if ($this->isMethod('patch')) {
            unset($rules['request_key']);
            $rules['revision'] = ['required', 'integer', 'min:1'];
        }

        return [...$rules, 'member_purchase_id' => ['nullable', 'integer', 'min:1', 'prohibited_if:member_id,null'], 'member_id' => ['nullable', 'integer', 'min:1'], 'customer_name' => ['required_without:member_id', 'nullable', 'string', 'max:255'], 'service' => ['required', Rule::enum(AppointmentService::class)], 'location' => ['nullable', 'string', 'max:100']];
    }
}
