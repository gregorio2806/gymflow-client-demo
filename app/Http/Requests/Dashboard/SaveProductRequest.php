<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Product;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product ? Gate::allows('update', $product) : Gate::allows('create', Product::class);
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $initialStockRules = $product ? ['exclude'] : ['sometimes', 'integer', 'min:0', 'max:1000000'];
        $initialBranchRules = $product ? ['exclude'] : ['required', 'integer', 'min:1', Rule::exists('branches', 'id')->where('gym_id', app(CurrentGym::class)->id())->where('is_active', true)];

        return [
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->where('gym_id', app(CurrentGym::class)->id())->ignore($product?->id)],
            'category' => ['nullable', 'string', 'max:100'],
            'cost_php' => ['required', 'string', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'],
            'price_php' => ['required', 'string', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'out_of_stock'])],
            'initial_stock' => $initialStockRules,
            'initial_branch_id' => $initialBranchRules,
        ];
    }

    public function messages(): array
    {
        return [
            'cost_php.regex' => __('Enter a peso amount with up to two decimal places.'),
            'price_php.regex' => __('Enter a peso amount with up to two decimal places.'),
        ];
    }
}
