<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\GymRole;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Tenancy\CurrentGym;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchViewController extends Controller
{
    public function update(Request $request, CurrentGym $current): RedirectResponse
    {
        abort_unless(in_array($current->role(), [GymRole::Owner, GymRole::Staff, GymRole::Coach], true), 403);
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'return_to' => ['required', 'string', 'max:2048'],
        ]);
        $target = $data['return_to'];
        abort_unless(str_starts_with($target, '/') && ! str_starts_with($target, '//') && ! preg_match('/[\\\\\x00-\x20\x7f]/', $target), 422);
        $branch = isset($data['branch_id']) ? Branch::findOrFail($data['branch_id']) : null;
        $request->session()->put('view_branch.'.$current->id(), $branch?->id);

        return redirect($target, 303);
    }
}
