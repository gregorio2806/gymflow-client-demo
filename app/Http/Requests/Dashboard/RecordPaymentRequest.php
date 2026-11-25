<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('member'));
    }

    public function rules(): array
    {
        return [
            'member_subscription_id' => ['nullable', 'required_without:member_purchase_id', 'prohibits:member_purchase_id', 'integer'],
            'member_purchase_id' => ['nullable', 'required_without:member_subscription_id', 'prohibits:member_subscription_id', 'integer'],
            'amount_php' => ['required', 'string', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'],
            'method' => ['required', Rule::in(['cash', 'gcash', 'maya', 'bank-transfer'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'request_key' => ['required', 'uuid'],
            'return_to_profile' => ['sometimes', 'boolean'],
            'return_to_purchases' => ['sometimes', 'boolean'],
            'discount_percent' => ['nullable', 'string', 'regex:/^\d{1,3}(?:\.\d{1,2})?$/'],
        ];
    }

    public function messages(): array
    {
        return ['amount_php.regex' => __('Enter a peso amount with at most two decimal places, such as 1500.50.')];
    }
}
