<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Support\BranchAccess;

class EmployeePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Employee $employee): bool
    {
        return $user->hasPermission('employees.view')
            && BranchAccess::allows($user, $employee->branch_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('employees.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermission('employees.manage')
            && BranchAccess::allows($user, $employee->branch_id);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * La baja de colaboradores es lógica (`status=terminated`); no se expone
     * borrado físico en las rutas.
     */
    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasPermission('employees.manage')
            && BranchAccess::allows($user, $employee->branch_id);
    }
}
