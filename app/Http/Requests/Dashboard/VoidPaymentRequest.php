<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class VoidPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('void', $this->route('payment'));
    }

    public function rules(): array
    {
        return ['void_reason' => ['required', 'string', 'max:500']];
    }
}
