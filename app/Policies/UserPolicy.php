<?php

namespace App\Policies;

use App\Models\User;
use App\Support\BranchAccess;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('users.view')
            && BranchAccess::sharesBranchWith($user, $model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasPermission('users.manage')
            && BranchAccess::sharesBranchWith($user, $model);
    }
}
