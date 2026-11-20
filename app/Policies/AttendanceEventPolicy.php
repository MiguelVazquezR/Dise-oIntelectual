<?php

namespace App\Policies;

use App\Models\AttendanceEvent;
use App\Models\User;
use App\Support\BranchAccess;

class AttendanceEventPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AttendanceEvent $event): bool
    {
        return $user->hasPermission('attendance.view')
            && BranchAccess::allows($user, $event->branch_id);
    }

    /**
     * Registrar marcajes manualmente desde la operación (no es el checador).
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('attendance.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AttendanceEvent $event): bool
    {
        return $user->hasPermission('attendance.manage')
            && BranchAccess::allows($user, $event->branch_id);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * No se expone borrado físico de marcajes: la corrección es por marca
     * (`validation_status`) y queda auditada.
     */
    public function delete(User $user, AttendanceEvent $event): bool
    {
        return false;
    }
}
