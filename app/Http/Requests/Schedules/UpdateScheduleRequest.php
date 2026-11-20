<?php

namespace App\Http\Requests\Schedules;

use App\Models\BranchSchedule;
use App\Models\User;
use App\Support\BranchAccess;

class UpdateScheduleRequest extends StoreScheduleRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $schedule = $this->route('schedule');

        if (! $user instanceof User || ! $user->hasPermission('schedules.manage')) {
            return false;
        }

        if ($schedule instanceof BranchSchedule && ! BranchAccess::allows($user, $schedule->branch_id)) {
            return false;
        }

        $branchId = $this->integer('branch_id');

        return $branchId <= 0 || BranchAccess::allows($user, $branchId);
    }
}
