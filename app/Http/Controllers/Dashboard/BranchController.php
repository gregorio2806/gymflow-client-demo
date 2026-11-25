<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\SaveBranch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveBranchRequest;
use App\Models\Branch;
use App\Tenancy\CurrentGym;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function __construct(private CurrentGym $currentGym) {}

    public function index(): Response
    {
        $gym = $this->currentGym->get();
        Gate::authorize('viewBranches', $gym);

        return Inertia::render('dashboard/branches/Index', [
            'branches' => Branch::orderByDesc('is_main')->orderBy('name')->orderBy('id')->paginate(25)->through(fn (Branch $branch) => [...$branch->only(['id', 'name', 'address', 'phone', 'is_main', 'is_active']), 'canEdit' => Gate::allows('update', $branch)]),
            'canSelect' => Gate::allows('manageStaff', $gym),
            'activeBranchIds' => $gym->branches()->where('is_active', true)->pluck('id'),
            'activeBranches' => Gate::allows('manageStaff', $gym) ? $gym->branches()->where('is_active', true)->get(['id', 'name'])->map(fn (Branch $branch) => $branch->only(['id', 'name'])) : [],
            'activeCount' => $gym->branches()->where('is_active', true)->count(),
            'needsSelection' => $gym->needs_branch_selection,
            'limit' => $gym->subscription->branchLimit(),
            'plan' => $gym->plan()->label(),
            'canAdd' => Gate::allows('manageBranches', $gym) && $gym->branches()->where('is_active', true)->count() < $gym->subscription->branchLimit(),
        ]);
    }

    public function store(SaveBranchRequest $request, SaveBranch $save): RedirectResponse
    {
        $save->handle($this->currentGym->get(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branch added.')]);

        return to_route('branches.index');
    }

    public function update(SaveBranchRequest $request, Branch $branch, SaveBranch $save): RedirectResponse
    {
        $save->handle($this->currentGym->get(), $request->validated(), $branch);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branch updated.')]);

        return to_route('branches.index');
    }
}
