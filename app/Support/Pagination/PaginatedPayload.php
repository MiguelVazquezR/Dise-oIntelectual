<?php

namespace App\Support\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Forma común de los listados paginados que consume Inertia (§7.1):
 * `{data, links, meta}`.
 *
 * El paginador de Laravel expone `data` y `links`, pero no `meta`; este helper
 * añade el resumen que usan las tablas y el componente `Pagination.vue`.
 */
final class PaginatedPayload
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array{
     *     data: array<int, mixed>,
     *     links: array<int, array{url: string|null, label: string, active: bool}>,
     *     meta: array{current_page: int, last_page: int, per_page: int, from: int|null, to: int|null, total: int}
     * }
     */
    public static function from(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'links' => $paginator->linkCollection()->toArray(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
