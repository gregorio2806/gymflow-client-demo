<?php

namespace App\Actions;

use App\Enums\GymRole;
use App\Jobs\GenerateReportExport;
use App\Models\Gym;
use App\Models\ReportExport;
use App\Support\Audit;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestReportExport
{
    public function handle(array $filters, string $key): void
    {
        DB::transaction(function () use ($filters, $key) {
            $context = app(CurrentGym::class);
            $gym = Gym::whereKey($context->id())->lockForUpdate()->firstOrFail();
            $pivot = $gym->users()->whereKey(auth()->id())->wherePivot('is_active', true)->first()?->pivot;
            abort_unless($pivot && $pivot->role === $context->role() && in_array($pivot->role, [GymRole::Owner, GymRole::Staff, GymRole::BranchManager], true) && ($pivot->role !== GymRole::BranchManager || $pivot->branch_id === $context->branchId()), 403);
            $existing = ReportExport::where('request_key', $key)->first();
            if ($existing) {
                if ($existing->user_id !== auth()->id() || $existing->from !== $filters['from'] || $existing->through !== $filters['through'] || $existing->branch_id !== $filters['branch_id'] || $existing->requested_role !== $context->role()->value) {
                    throw ValidationException::withMessages(['request_key' => 'This submission was already used for another export.']);
                }

                return;
            }
            if (ReportExport::where('user_id', auth()->id())->where('expires_at', '>', now()->utc())->count() >= 20) {
                throw ValidationException::withMessages(['request_key' => 'You have reached 20 exports in 24 hours. Try again after older exports expire.']);
            }
            $export = (new ReportExport)->forceFill([...$filters, 'user_id' => auth()->id(), 'requested_role' => $context->role()->value, 'request_key' => $key, 'expires_at' => now()->utc()->addDay()]);
            $export->save();
            Audit::log('report-export.requested', $export, ['after' => $filters]);
            // The existing database queue shares this transaction: row and job commit together.
            GenerateReportExport::dispatch($gym->id, $export->id)->onConnection('database');
        });
    }
}
