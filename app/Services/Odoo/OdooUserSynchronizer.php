<?php

namespace App\Services\Odoo;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class OdooUserSynchronizer
{
    private const ODOO_MANAGED_ROLES = ['User', 'Manager'];

    public function sync(array $profile): User
    {
        $resolvedOdooUserId = isset($profile['odoo_user_id']) && is_numeric($profile['odoo_user_id'])
            ? (int) $profile['odoo_user_id']
            : null;
        $odooEmployeeId = isset($profile['odoo_employee_id']) && is_numeric($profile['odoo_employee_id'])
            ? (int) $profile['odoo_employee_id']
            : null;
        $email = Str::lower((string) ($profile['email'] ?? ''));

        if ($odooEmployeeId === null || $odooEmployeeId <= 0 || $email === '') {
            throw new OdooException('The Odoo user profile is incomplete and could not be synced.');
        }

        $existingByOdooId = $resolvedOdooUserId
            ? User::where('odoo_user_id', $resolvedOdooUserId)->first()
            : null;
        $existingByEmployeeId = User::where('odoo_employee_id', $odooEmployeeId)->first();
        $existingByEmail = User::whereRaw('LOWER(email) = ?', [$email])->first();

        $matches = collect([$existingByOdooId, $existingByEmployeeId, $existingByEmail])
            ->filter()
            ->unique(fn (User $user) => $user->getKey())
            ->values();

        if ($matches->count() > 1) {
            throw new OdooException('Conflicting local user records were found for this Odoo account.');
        }

        $user = $matches->first() ?? new User();

        if ($user->exists) {
            $this->guardProtectedLocalAccount($user, $resolvedOdooUserId, $odooEmployeeId);
            $this->guardEmailCollision($user, $email);
        }

        $odooUserIdForStorage = $resolvedOdooUserId ?: $user->odoo_user_id;

        $user->fill([
            'name' => (string) ($profile['name'] ?? $user->name ?? $email),
            'email' => $email,
            'role' => $this->normalizeOdooRole($profile['role'] ?? null),
            'auth_source' => 'odoo',
            'password' => Hash::make(Str::random(40)),
            'odoo_user_id' => $odooUserIdForStorage,
            'odoo_employee_id' => $odooEmployeeId,
            'odoo_resource_id' => isset($profile['odoo_resource_id']) ? (int) $profile['odoo_resource_id'] : null,
            'odoo_last_synced_at' => now(),
            'email_verified_at' => now(),
        ]);

        $user->save();
        $this->syncOdooAccessRole($user);

        return $user;
    }

    private function guardProtectedLocalAccount(User $user, ?int $odooUserId, int $odooEmployeeId): void
    {
        if ($odooUserId !== null && $user->odoo_user_id !== null && $user->odoo_user_id !== $odooUserId) {
            throw new OdooException('This local user is already linked to a different Odoo account.');
        }

        if ($user->odoo_employee_id !== null && (int) $user->odoo_employee_id !== $odooEmployeeId) {
            throw new OdooException('This local user is already linked to a different Odoo employee record.');
        }

        if (
            $user->auth_source === 'local'
            && (
                $user->role === 'admin'
                || $user->hasRole('Admin')
                || $user->hasRole('Super Admin')
            )
        ) {
            throw new OdooException(
                'A protected local admin account already uses this email. Please contact an administrator to link it safely.'
            );
        }
    }

    private function guardEmailCollision(User $user, string $email): void
    {
        $emailOwner = User::whereRaw('LOWER(email) = ?', [$email])
            ->whereKeyNot($user->getKey())
            ->exists();

        if ($emailOwner) {
            throw new OdooException('Another local account already uses this email address.');
        }
    }

    private function normalizeOdooRole(mixed $role): string
    {
        return $role === 'manager' ? 'manager' : 'user';
    }

    private function syncOdooAccessRole(User $user): void
    {
        $targetRole = $user->isOdooManager() ? 'Manager' : 'User';

        Role::findOrCreate($targetRole);

        $preservedRoles = $user->roles()
            ->whereNotIn('name', self::ODOO_MANAGED_ROLES)
            ->pluck('name')
            ->all();

        $user->syncRoles(array_values(array_unique([...$preservedRoles, $targetRole])));
    }
}
