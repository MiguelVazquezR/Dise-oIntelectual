<?php

namespace App\Policies;

use App\Models\AttendanceIncident;
use App\Models\User;
use App\Support\BranchAccess;

class AttendanceIncidentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('incidents.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AttendanceIncident $incident): bool
    {
        return $user->hasPermission('incidents.view')
            && BranchAccess::allows($user, $incident->branch_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('incidents.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AttendanceIncident $incident): bool
    {
        return $user->hasPermission('incidents.manage')
            && BranchAccess::allows($user, $incident->branch_id);
    }

    /**
     * Aprobar o rechazar incidencias (permiso distinto de administrarlas).
     */
    public function decide(User $user, AttendanceIncident $incident): bool
    {
        return $user->hasPermission('incidents.approve')
            && BranchAccess::allows($user, $incident->branch_id);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Las incidencias se cancelan (`status=cancelled`), no se eliminan.
     */
    public function delete(User $user, AttendanceIncident $incident): bool
    {
        return false;
    }
}
