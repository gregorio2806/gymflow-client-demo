<?php

namespace App\Http\Requests\Dashboard;

use App\Models\WorkoutRoutine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveWorkoutRoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('routine') ? Gate::allows('update', $this->route('routine')) : Gate::allows('create', WorkoutRoutine::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000'],
            'branch_id' => ['required', 'integer'], 'request_key' => [Rule::requiredIf(! $this->route('routine')), 'nullable', 'uuid'], 'revision' => [Rule::requiredIf((bool) $this->route('routine')), 'nullable', 'integer', 'min:1'],
            'days' => ['required', 'array', 'min:1', 'max:7'], 'days.*' => ['array:name,exercises'], 'days.*.name' => ['required', 'string', 'max:100'],
            'days.*.exercises' => ['required', 'array', 'min:1', 'max:20'], 'days.*.exercises.*' => ['array:name,sets,reps,notes'],
            'days.*.exercises.*.name' => ['required', 'string', 'max:100'], 'days.*.exercises.*.sets' => ['required', 'integer', 'min:1', 'max:100'],
            'days.*.exercises.*.reps' => ['required', 'string', 'max:50'], 'days.*.exercises.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
