<?php

namespace App\Http\Requests\Dashboard;

use App\Models\ReportExport;
use App\Support\GymReport;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Pennant\Feature;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', ReportExport::class);
    }

    public function rules(): array
    {
        return ['period' => ['required', Rule::in(['this-year', 'this-month', 'last-month', 'month', 'custom'])], 'month' => [Rule::requiredIf($this->input('period') === 'month'), 'nullable', 'date_format:Y-m', 'after_or_equal:2000-01', 'before_or_equal:2100-12'],
            'from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'], 'through' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'], 'branch_id' => ['nullable', 'integer'], 'request_key' => [$this->isMethod('post') ? 'required' : 'nullable', 'uuid'],
            'report' => ['sometimes', Rule::in(['revenue', 'membership', 'attendance', 'payments', 'appointments', 'exports'])],
            'kind' => ['nullable', Rule::in(['subscription', 'benefit', 'package'])], 'payment_state' => ['nullable', Rule::in(['paid', 'partial', 'unpaid'])], 'search' => ['nullable', 'string', 'max:100'], 'source' => ['nullable', Rule::in(['manual', 'qr'])], 'method' => ['nullable', Rule::in(['cash', 'gcash', 'maya', 'bank-transfer'])], 'status' => ['nullable', Rule::in($this->input('report') === 'appointments' ? ['scheduled', 'completed', 'cancelled', 'no-show'] : ['recorded', 'voided'])], 'per_page' => ['sometimes', 'integer', Rule::in([7, 10, 15, 25, 50])], 'days_sort' => ['sometimes', Rule::in(['asc', 'desc'])], 'export' => ['sometimes', Rule::in(['csv'])]];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['period' => $this->input('period') ?? ($this->filled('from') || $this->filled('through') ? 'custom' : 'this-month')]);
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $gym = app(CurrentGym::class)->get();
            $dates = app(GymReport::class)->dateRange($validator->getData());
            $from = CarbonImmutable::parse($dates['from'], $gym->timezone);
            $through = CarbonImmutable::parse($dates['through'], $gym->timezone);
            $limit = Feature::for($gym)->active('full-reports') ? 366 : 31;
            if ($through->lessThan($from) || $from->diffInDays($through) + 1 > $limit) {
                $validator->errors()->add('through', "Choose a date range of 1–{$limit} days.");
            }
        }];
    }
}
