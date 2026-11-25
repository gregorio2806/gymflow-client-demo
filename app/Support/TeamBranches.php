<?php

namespace App\Support;

use App\Enums\GymRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TeamBranches
{
    public static function ids(Model $membership): array
    {
        return array_values(array_unique(array_map('intval', $membership->branch_ids ?? ($membership->branch_id ? [$membership->branch_id] : []))));
    }

    public static function whereAssigned($query, int $branchId, string $prefix = ''): void
    {
        $query->where(fn ($q) => $q->where($prefix.'branch_id', $branchId)->orWhereJsonContains($prefix.'branch_ids', $branchId));
    }

    public static function validateRole(GymRole $role, array $ids, string $field = 'branch_ids'): void
    {
        if ($ids === [] || ($role === GymRole::BranchManager && count($ids) !== 1) || $role === GymRole::Owner) {
            throw ValidationException::withMessages([$field => $role === GymRole::BranchManager ? 'Choose exactly one branch for a branch manager.' : 'Choose at least one branch for staff or coaches. Owners manage all branches.']);
        }
    }
}
