<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Role> $roles
 * @property-read Collection<int, Branch> $branches
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Rol con acceso total; `Gate::before` lo usa para permitir cualquier ability.
     */
    public const ADMIN_ROLE = 'admin';

    /**
     * Permiso que desactiva el aislamiento por sucursal (D-02).
     */
    public const ALL_BRANCHES_PERMISSION = 'data.all_branches';

    /**
     * Caché en memoria de los slugs de permisos durante la petición.
     *
     * @var array<int, string>|null
     */
    private ?array $permissionSlugCache = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * Sucursales asignadas al usuario. El pivote `is_primary` marca la principal.
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branches')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ADMIN_ROLE);
    }

    public function hasRole(string $slug): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains(fn (Role $role): bool => $role->slug === $slug);
    }

    /**
     * El rol `admin` pasa cualquier verificación de permiso.
     */
    public function hasPermission(string $slug): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($slug, $this->permissionSlugs(), true);
    }

    /**
     * Slugs de permisos efectivos (union de todos los roles del usuario).
     *
     * @return array<int, string>
     */
    public function permissionSlugs(): array
    {
        if ($this->permissionSlugCache !== null) {
            return $this->permissionSlugCache;
        }

        $this->loadMissing('roles.permissions');

        return $this->permissionSlugCache = $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function roleSlugs(): array
    {
        $this->loadMissing('roles');

        return $this->roles->pluck('slug')->values()->all();
    }

    /**
     * IDs de sucursal visibles para el usuario; `null` significa sin filtro.
     *
     * @return array<int, int>|null
     */
    public function allowedBranchIds(): ?array
    {
        if ($this->hasPermission(self::ALL_BRANCHES_PERMISSION)) {
            return null;
        }

        $ids = $this->relationLoaded('branches')
            ? $this->branches->pluck('id')->all()
            : $this->branches()->pluck('branches.id')->all();

        return array_values(array_map('intval', $ids));
    }

    /**
     * Sucursal principal del usuario (la marcada con `is_primary` o la primera asignada).
     */
    public function primaryBranch(): ?Branch
    {
        return $this->branches()->orderByDesc('user_branches.is_primary')->first();
    }
}
