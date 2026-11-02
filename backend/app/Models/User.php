<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;
use Laravel\Sanctum\HasApiTokens;
use LogicException;

#[Fillable([
    'name',
    'email',
    'phone',
    'password',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get addresses belonging to this user.
     *
     * @return HasMany<UserAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    /**
     * Get roles assigned to this user.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withTimestamps();
    }

    /**
     * Assign a role to this user without removing existing roles.
     *
     * Accepts a persisted Role model or its unique slug.
     * Repeating the assignment does not create another pivot record.
     *
     * @throws LogicException If the user or role is not persisted.
     * @throws InvalidArgumentException If the role slug is empty.
     * @throws ModelNotFoundException
     */
    public function assignRole(Role|string $role): void
    {
        $this->ensurePersistedForRoleAssignment();

        $resolvedRole = $this->resolveRoleForAssignment($role);

        $this->roles()->syncWithoutDetaching([
            $resolvedRole->getKey(),
        ]);
    }

    /**
     * Remove only the specified role assignment.
     *
     * The role definition and all other user-role assignments remain intact.
     *
     * @throws LogicException If the user or role is not persisted.
     * @throws InvalidArgumentException If the role slug is empty.
     * @throws ModelNotFoundException
     */
    public function removeRole(Role|string $role): void
    {
        $this->ensurePersistedForRoleAssignment();

        $resolvedRole = $this->resolveRoleForAssignment($role);

        $this->roles()->detach($resolvedRole->getKey());
    }

    /**
     * Determine whether this user has the specified role.
     *
     * An unpersisted role or user returns false.
     */
    public function hasRole(Role|string $role): bool
    {
        if (! $this->exists || $this->getKey() === null) {
            return false;
        }

        if ($role instanceof Role) {
            if (! $role->exists || $role->getKey() === null) {
                return false;
            }

            return $this->roles()
                ->where('roles.id', $role->getKey())
                ->exists();
        }

        $roleSlug = trim($role);

        if ($roleSlug === '') {
            return false;
        }

        return $this->roles()
            ->where('roles.slug', $roleSlug)
            ->exists();
    }

    /**
     * Determine whether any assigned role grants this permission.
     *
     * This checks role-permission assignments only. Authentication and
     * account activation must be enforced separately by middleware.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        if (! $this->exists || $this->getKey() === null) {
            return false;
        }

        $permissionSlug = trim($permissionSlug);

        if ($permissionSlug === '') {
            return false;
        }

        return $this->roles()
            ->whereHas(
                'permissions',
                static function (Builder $query) use ($permissionSlug): void {
                    $query->where('permissions.slug', $permissionSlug);
                },
            )
            ->exists();
    }

    /**
     * Get the union of permissions granted by every assigned role.
     *
     * Each permission appears only once, even if multiple roles grant it.
     *
     * @return EloquentCollection<int, Permission>
     */
    public function getAllPermissions(): EloquentCollection
    {
        if (! $this->exists || $this->getKey() === null) {
            return new EloquentCollection;
        }

        $userId = $this->getKey();

        return Permission::query()
            ->whereHas(
                'roles.users',
                static function (Builder $query) use ($userId): void {
                    $query->whereKey($userId);
                },
            )
            ->orderBy('permissions.slug')
            ->get();
    }

    /**
     * Ensure role assignments are only made for a persisted user.
     */
    private function ensurePersistedForRoleAssignment(): void
    {
        if (! $this->exists || $this->getKey() === null) {
            throw new LogicException(
                'Roles can only be assigned to or removed from a persisted user.',
            );
        }
    }

    /**
     * Resolve a role model or slug and verify the role still exists.
     *
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ModelNotFoundException
     */
    private function resolveRoleForAssignment(Role|string $role): Role
    {
        if ($role instanceof Role) {
            if (! $role->exists || $role->getKey() === null) {
                throw new LogicException(
                    'The role must be persisted before it can be assigned.',
                );
            }

            return Role::query()
                ->whereKey($role->getKey())
                ->firstOrFail();
        }

        $roleSlug = trim($role);

        if ($roleSlug === '') {
            throw new InvalidArgumentException(
                'The role slug must not be empty.',
            );
        }

        return Role::query()
            ->where('slug', $roleSlug)
            ->firstOrFail();
    }
}
