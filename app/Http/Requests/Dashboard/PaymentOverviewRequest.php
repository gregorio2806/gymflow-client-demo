<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PaymentOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Payment::class);
    }

    public function rules(): array
    {
        return [
            'view' => ['nullable', Rule::in(['day', 'month'])],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'branch_id' => ['nullable', 'integer'],
            'balance' => ['nullable', Rule::in(['unpaid', 'overdue'])],
        ];
    }
}
