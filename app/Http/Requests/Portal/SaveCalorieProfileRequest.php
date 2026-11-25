<?php

namespace App\Http\Requests\Portal;

use App\Support\CalorieEstimate;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SaveCalorieProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->attributes->get('portalMember');
    }

    public function rules(): array
    {
        return [
            'age' => ['required', 'integer', 'between:18,100'], 'height_cm' => ['required', 'numeric', 'between:120,230'], 'weight_kg' => ['required', 'numeric', 'between:35,250'],
            'equation' => ['required', Rule::in(['male', 'female'])], 'activity' => ['required', Rule::in(array_keys(CalorieEstimate::ACTIVITY))], 'goal' => ['required', Rule::in(['maintain', 'lose', 'gain'])],
            'consent_version' => ['required', Rule::in([CalorieEstimate::CONSENT_VERSION])],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['errors' => $validator->errors()], 422));
    }
}
