<?php

namespace App\Http\Requests\Dashboard;

use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('branch')
            ? Gate::allows('update', $this->route('branch'))
            : Gate::allows('manageBranches', app(CurrentGym::class)->get());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
