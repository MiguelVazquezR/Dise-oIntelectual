<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Aislamiento de consultas por sucursal (D-02).
 *
 * Debe aplicarse a toda consulta de datos operativos antes de devolver filas
 * al cliente: `BranchAccess::scope(Employee::query())`.
 */
class BranchAccess
{
    /**
     * Aplica el filtro de sucursal según `User::allowedBranchIds()`.
     *
     * - Sin usuario autenticado: no devuelve filas.
     * - `allowedBranchIds()` null (permiso `data.all_branches`): sin filtro.
     * - Lista vacía: no devuelve filas.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scope(Builder $query, string $column = 'branch_id'): Builder
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        $branchIds = $user->allowedBranchIds();

        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($query->qualifyColumn($column), $branchIds);
    }

    /**
     * Aplica el filtro de sucursal a una consulta de usuarios: solo se listan
     * las cuentas asignadas a alguna sucursal visible para el actor.
     *
     * @template TUser of User
     *
     * @param  Builder<TUser>  $query
     * @return Builder<TUser>
     */
    public static function scopeUsers(Builder $query): Builder
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        $branchIds = $user->allowedBranchIds();

        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('branches', function (Builder $inner) use ($branchIds): void {
            $inner->whereIn('branches.id', $branchIds);
        });
    }

    /**
     * Verifica si el usuario puede operar sobre una sucursal concreta.
     */
    public static function allows(User $user, int $branchId): bool
    {
        $branchIds = $user->allowedBranchIds();

        return $branchIds === null || in_array($branchId, $branchIds, true);
    }

    /**
     * Verifica si el actor comparte al menos una sucursal con la cuenta objetivo.
     * Un usuario con `data.all_branches` siempre puede.
     */
    public static function sharesBranchWith(User $actor, User $target): bool
    {
        $branchIds = $actor->allowedBranchIds();

        if ($branchIds === null) {
            return true;
        }

        if ($branchIds === []) {
            return false;
        }

        return $target->branches()
            ->whereIn('branches.id', $branchIds)
            ->exists();
    }
}
