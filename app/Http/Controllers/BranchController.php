<?php

namespace App\Http\Controllers;

use App\Http\Requests\Branches\IndexBranchRequest;
use App\Http\Requests\Branches\StoreBranchRequest;
use App\Http\Requests\Branches\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\User;
use App\Support\Pagination\PaginatedPayload;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    /**
     * Listado de sucursales visibles para el usuario.
     */
    public function index(IndexBranchRequest $request): Response
    {
        $this->authorize('viewAny', Branch::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();
        $branchIds = $user->allowedBranchIds();
        $status = $filters['status'] ?? null;

        $branches = Branch::query()
            ->withCount(['employees', 'schedules'])
            ->when($branchIds !== null, fn (Builder $query) => $query->whereIn('id', $branchIds))
            ->when(
                $filters['search'] ?? null,
                function (Builder $query, string $search): void {
                    $query->where(function (Builder $inner) use ($search): void {
                        $inner->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%");
                    });
                },
            )
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Branch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'address' => $branch->address,
                'timezone' => $branch->timezone,
                'is_active' => $branch->is_active,
                'employees_count' => (int) ($branch->employees_count ?? 0),
                'schedules_count' => (int) ($branch->schedules_count ?? 0),
            ]);

        return Inertia::render('branches/Index', [
            'filters' => [
                'search' => $filters['search'] ?? null,
                'status' => $status,
            ],
            'branches' => PaginatedPayload::from($branches),
            'can' => [
                'manage' => $user->hasPermission('branches.manage'),
            ],
        ]);
    }

    /**
     * Formulario de alta de sucursal.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Branch::class);

        return Inertia::render('branches/Create', [
            'branch' => null,
            'timezones' => DateTimeZone::listIdentifiers(),
            'can' => [
                'manage' => $this->canManage($request),
            ],
        ]);
    }

    /**
     * Crea una sucursal.
     */
    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $this->authorize('create', Branch::class);

        $branch = Branch::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return to_route('branches.index')
            ->with('success', "Sucursal «{$branch->name}» creada correctamente.");
    }

    /**
     * Formulario de edición de sucursal.
     */
    public function edit(Request $request, Branch $branch): Response
    {
        $this->authorize('update', $branch);

        return Inertia::render('branches/Edit', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'address' => $branch->address,
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
                'geofence_radius_m' => $branch->geofence_radius_m,
                'timezone' => $branch->timezone,
                'is_active' => $branch->is_active,
            ],
            'timezones' => DateTimeZone::listIdentifiers(),
            'can' => [
                'manage' => $this->canManage($request),
            ],
        ]);
    }

    /**
     * Actualiza una sucursal.
     */
    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        $branch->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', $branch->is_active),
        ]);

        return to_route('branches.index')
            ->with('success', "Sucursal «{$branch->name}» actualizada correctamente.");
    }

    /**
     * Elimina una sucursal sin colaboradores ni horarios asociados.
     */
    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);

        $employees = $branch->employees()->count();
        $schedules = $branch->schedules()->count();

        if ($employees > 0 || $schedules > 0) {
            return back()->with(
                'error',
                "No se puede eliminar la sucursal «{$branch->name}»: tiene {$employees} colaborador(es) y {$schedules} horario(s) asociados.",
            );
        }

        $name = $branch->name;
        $branch->delete();

        return to_route('branches.index')
            ->with('success', "Sucursal «{$name}» eliminada correctamente.");
    }

    private function canManage(Request $request): bool
    {
        return $request->user()?->hasPermission('branches.manage') === true;
    }
}
