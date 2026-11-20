<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Support\BranchAccess;

class BranchPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('branches.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches.view')
            && BranchAccess::allows($user, $branch->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('branches.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches.manage')
            && BranchAccess::allows($user, $branch->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches.manage')
            && BranchAccess::allows($user, $branch->id);
    }
}
