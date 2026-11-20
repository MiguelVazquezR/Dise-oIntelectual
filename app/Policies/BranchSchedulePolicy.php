<?php

namespace App\Policies;

use App\Models\BranchSchedule;
use App\Models\User;
use App\Support\BranchAccess;

class BranchSchedulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('schedules.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BranchSchedule $schedule): bool
    {
        return $user->hasPermission('schedules.view')
            && BranchAccess::allows($user, $schedule->branch_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('schedules.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BranchSchedule $schedule): bool
    {
        return $user->hasPermission('schedules.manage')
            && BranchAccess::allows($user, $schedule->branch_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BranchSchedule $schedule): bool
    {
        return $user->hasPermission('schedules.manage')
            && BranchAccess::allows($user, $schedule->branch_id);
    }
}
