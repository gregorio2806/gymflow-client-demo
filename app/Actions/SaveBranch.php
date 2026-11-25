<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\Gym;
use App\Support\Audit;
use App\Support\PlanLimits;
use App\Tenancy\CurrentGym;
use Illuminate\Support\Facades\DB;

class SaveBranch
{
    public function handle(Gym $gym, array $data, ?Branch $branch = null): Branch
    {
        return app(CurrentGym::class)->runAs($gym, fn () => DB::transaction(function () use ($gym, $data, $branch) {
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            $gym->subscription()->lockForUpdate()->firstOrFail();
            if ($branch === null) {
                app(PlanLimits::class)->ensureBranchAvailable($gym);
                $branch = Branch::create($data);
                Audit::log('branch.created', $branch, ['after' => $data]);
            } else {
                $branch = $gym->branches()->lockForUpdate()->findOrFail($branch->id);
                app(PlanLimits::class)->ensureBranchWritable($gym, $branch->id, 'name');
                $before = $branch->only(['name', 'address', 'phone']);
                $branch->update($data);
                if ($branch->wasChanged()) {
                    Audit::log('branch.updated', $branch, ['before' => $before, 'after' => $branch->only(['name', 'address', 'phone'])]);
                }
            }

            return $branch;
        }));
    }
}
