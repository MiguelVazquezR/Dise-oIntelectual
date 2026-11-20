<?php

namespace App\Support\Branches;

use App\Models\Branch;
use App\Models\User;

/**
 * Catálogo de sucursales visibles para un usuario, usado en los `options` de
 * los formularios y filtros (§7.1).
 */
final class BranchOptions
{
    /**
     * @return array<int, array{id: int, name: string}>
     */
    public static function forUser(User $user): array
    {
        $query = Branch::query()->orderBy('name');
        $branchIds = $user->allowedBranchIds();

        if ($branchIds !== null) {
            $query->whereIn('id', $branchIds);
        }

        return $query->get(['id', 'name'])
            ->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
            ])
            ->values()
            ->all();
    }
}
