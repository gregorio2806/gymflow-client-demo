<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\SetPlanActivation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ConfirmPlanSelectionRequest;
use App\Http\Requests\Dashboard\SelectActiveBranchesRequest;
use App\Http\Requests\Dashboard\SetActivationRequest;
use App\Models\Branch;
use App\Models\Gym;
use App\Support\Audit;
use App\Tenancy\CurrentGym;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PlanSelectionController extends Controller
{
    public function __construct(private CurrentGym $currentGym) {}

    public function staff(SetActivationRequest $request, int $member, SetPlanActivation $action): RedirectResponse
    {
        $action->staff($this->currentGym->get(), $member, $request->boolean('is_active'));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Staff access updated.')]);

        return back();
    }

    public function branch(SetActivationRequest $request, Branch $branch, SetPlanActivation $action): RedirectResponse
    {
        $action->branch($this->currentGym->get(), $branch, $request->boolean('is_active'));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branch access updated.')]);

        return back();
    }

    public function confirm(ConfirmPlanSelectionRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $gym = Gym::whereKey($this->currentGym->id())->lockForUpdate()->firstOrFail();
            $field = $request->validated('selection') === 'staff' ? 'needs_staff_selection' : 'needs_branch_selection';
            if ($gym->$field) {
                $gym->forceFill([$field => false])->save();
                Audit::log('plan.selection_confirmed', $gym, ['after' => ['selection' => $request->validated('selection')]]);
            }
        });

        return back();
    }

    public function selectBranches(SelectActiveBranchesRequest $request, SetPlanActivation $action): RedirectResponse
    {
        $action->selectBranches($this->currentGym->get(), $request->validated('branch_ids'));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Active branches saved.')]);

        return back();
    }
}
