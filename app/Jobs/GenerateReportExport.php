<?php

namespace App\Jobs;

use App\Enums\GymRole;
use App\Enums\Plan;
use App\Models\Gym;
use App\Models\ReportExport;
use App\Support\GymReport;
use App\Tenancy\CurrentGym;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $gymId, public int $exportId) {}

    public function handle(GymReport $report): void
    {
        $gym = Gym::find($this->gymId);
        if (! $gym) {
            return;
        }
        app(CurrentGym::class)->runAs($gym, function () use ($gym, $report) {
            DB::transaction(function () use ($gym, $report) {
                $export = ReportExport::whereKey($this->exportId)->lockForUpdate()->first();
                if (! $export || $export->status !== 'queued') {
                    return;
                }
                $pivot = $gym->users()->whereKey($export->user_id)->wherePivot('is_active', true)->first()?->pivot;
                if (! $pivot || $pivot->role->value !== $export->requested_role || ! in_array($pivot->role, [GymRole::Owner, GymRole::Staff, GymRole::BranchManager], true) || ($pivot->role === GymRole::BranchManager && ((int) $pivot->branch_id !== $export->branch_id || $gym->plan() !== Plan::MultiBranch)) || ! $export->expires_at->isFuture() || ! Feature::for($gym)->active('reports') || ! Feature::for($gym)->active('full-reports')) {
                    $export->forceFill(['status' => 'failed'])->save();

                    return;
                }
                $c = app(CurrentGym::class);
                $c->set($gym, $pivot->role, $pivot->branch_id ? (int) $pivot->branch_id : null);
                $rows = $report->build(['from' => $export->from, 'through' => $export->through, 'branch_id' => $export->branch_id])['days'];
                $stream = fopen('php://temp', 'w+');
                fputcsv($stream, ['Date (Manila)', 'Check-ins', 'Unique members per day', 'Renewals created', 'Revenue (PHP)'], ',', '"', '');
                foreach ($rows as $row) {
                    $amount = $row['revenueCentavos'];
                    fputcsv($stream, [$row['date'], $row['checkIns'], $row['uniqueMembers'], $row['renewals'], intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT)], ',', '"', '');
                }
                rewind($stream);
                $csv = stream_get_contents($stream);
                fclose($stream);
                $export->forceFill(['csv' => $csv, 'status' => 'ready'])->save();
            });
        });
    }

    public function failed(?\Throwable $error): void
    {
        $gym = Gym::find($this->gymId);
        if ($gym) {
            app(CurrentGym::class)->runAs($gym, fn () => ReportExport::whereKey($this->exportId)->where('status', 'queued')->update(['status' => 'failed']));
        }
    }
}
