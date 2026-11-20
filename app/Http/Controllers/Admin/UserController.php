<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexUserRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Support\BranchAccess;
use App\Support\Branches\BranchOptions;
use App\Support\Pagination\PaginatedPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Listado de usuarios visibles para el administrador.
     */
    public function index(IndexUserRequest $request): Response
    {
        $this->authorize('viewAny', User::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $request->validated();

        $users = BranchAccess::scopeUsers(
            User::query()->with([
                'roles:id,name,slug',
                'branches:id,name',
            ]),
        )
            ->when(
                $filters['search'] ?? null,
                function (Builder $query, string $search): void {
                    $query->where(function (Builder $inner) use ($search): void {
                        $inner->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                },
            )
            ->when(
                $filters['role_slug'] ?? null,
                fn (Builder $query, string $slug) => $query->whereHas(
                    'roles',
                    fn (Builder $roles) => $roles->where('slug', $slug),
                ),
            )
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $row): array => [
                'id' => $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'roles' => $row->roles
                    ->map(fn (Role $role): array => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                    ])
                    ->values()
                    ->all(),
                'branches' => $row->branches
                    ->map(fn (Branch $branch): array => [
                        'id' => $branch->id,
                        'name' => $branch->name,
                    ])
                    ->values()
                    ->all(),
                'created_at' => $row->created_at?->toIso8601String(),
            ]);

        return Inertia::render('users/Index', [
            'filters' => [
                'search' => $filters['search'] ?? null,
                'role_slug' => $filters['role_slug'] ?? null,
            ],
            'users' => PaginatedPayload::from($users),
            'options' => [
                'roles' => $this->roleOptions(),
                'branches' => BranchOptions::forUser($user),
            ],
            'can' => [
                'manage' => $user->hasPermission('users.manage'),
            ],
        ]);
    }

    /**
     * Formulario de alta de usuario.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('users/Create', [
            'user' => null,
            'options' => [
                'roles' => $this->roleOptions(),
                'branches' => BranchOptions::forUser($user),
            ],
            'can' => [
                'manage' => $user->hasPermission('users.manage'),
            ],
        ]);
    }

    /**
     * Crea un usuario con roles y sucursales.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();

        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            $user->roles()->sync($data['role_ids']);

            $this->syncBranches($user, $data['branch_ids'] ?? []);

            return $user;
        });

        return to_route('users.index')
            ->with('success', "Usuario «{$user->name}» creado correctamente.");
    }

    /**
     * Formulario de edición de usuario.
     */
    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        /** @var User $actor */
        $actor = $request->user();
        $user->load(['roles:id,name,slug', 'branches:id,name']);

        return Inertia::render('users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_ids' => $user->roles->pluck('id')->values()->all(),
                'branch_ids' => $user->branches->pluck('id')->values()->all(),
            ],
            'options' => [
                'roles' => $this->roleOptions(),
                'branches' => BranchOptions::forUser($actor),
            ],
            'can' => [
                'manage' => $actor->hasPermission('users.manage'),
            ],
        ]);
    }

    /**
     * Actualiza usuario, roles y sucursales.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();
        $roleIds = array_map('intval', $data['role_ids']);
        $adminRoleId = Role::query()->where('slug', User::ADMIN_ROLE)->value('id');

        abort_if(
            $user->is($request->user())
                && $adminRoleId !== null
                && ! in_array((int) $adminRoleId, $roleIds, true),
            403,
            'No puedes quitarte el rol de administrador.',
        );

        DB::transaction(function () use ($user, $data, $roleIds): void {
            $attributes = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            if (is_string($data['password'] ?? null) && $data['password'] !== '') {
                $attributes['password'] = $data['password'];
            }

            $user->update($attributes);
            $user->roles()->sync($roleIds);

            $this->syncBranches($user, $data['branch_ids'] ?? []);
        });

        return to_route('users.index')
            ->with('success', "Usuario «{$user->name}» actualizado correctamente.");
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string}>
     */
    private function roleOptions(): array
    {
        return Role::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int|string>  $branchIds
     */
    private function syncBranches(User $user, array $branchIds): void
    {
        $sync = [];

        foreach (array_values($branchIds) as $index => $branchId) {
            $sync[(int) $branchId] = ['is_primary' => $index === 0];
        }

        $user->branches()->sync($sync);
    }
}
