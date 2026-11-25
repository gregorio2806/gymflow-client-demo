<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AssignWorkoutRoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('routine'));
    }

    public function rules(): array
    {
        return ['member_id' => ['required', 'integer'], 'revision' => ['required', 'integer', 'min:1']];
    }
}
