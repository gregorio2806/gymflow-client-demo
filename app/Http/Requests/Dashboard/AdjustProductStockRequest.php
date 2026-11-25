<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Product;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdjustProductStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Product::class);
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'min:1', Rule::exists('branches', 'id')->where('gym_id', app(CurrentGym::class)->id())->where('is_active', true)],
            'type' => ['required', Rule::in(['received', 'removed'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
