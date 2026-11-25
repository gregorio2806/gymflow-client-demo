<?php

namespace App\Http\Requests\Platform;

use App\Enums\GymFeature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateGymFeaturesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('access-platform-admin');
    }

    public function rules(): array
    {
        return [
            'features' => ['sometimes', 'array'],
            'features.*' => [Rule::enum(GymFeature::class)],
            'overrides' => ['sometimes', 'array:'.implode(',', array_column(GymFeature::cases(), 'value'))],
            'overrides.*' => ['required', Rule::in(['default', 'on', 'off'])],
        ];
    }
}
