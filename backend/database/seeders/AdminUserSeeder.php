<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Never create a development administrator outside local.
        if (! app()->environment('local')) {
            $this->command?->info(
                'Skipped development admin seeder outside the local environment.'
            );

            return;
        }

        $name = trim((string) config('rbac.admin.name', ''));
        $email = strtolower(trim((string) config('rbac.admin.email', '')));
        $password = (string) config('rbac.admin.password', '');

        $credentials = [$name, $email, $password];

        $hasAnyConfiguration = count(
            array_filter(
                $credentials,
                static fn (string $value): bool => $value !== '',
            ),
        ) > 0;

        // Empty credentials mean that local admin creation is disabled.
        if (! $hasAnyConfiguration) {
            $this->command?->info(
                'Skipped development admin seeder: credentials are not configured.'
            );

            return;
        }

        // Partial configuration must never create an incomplete account.
        if ($name === '' || $email === '' || $password === '') {
            throw new RuntimeException(
                'Configure RBAC_ADMIN_NAME, RBAC_ADMIN_EMAIL, and '
                    .'RBAC_ADMIN_PASSWORD together.'
            );
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException(
                'RBAC_ADMIN_EMAIL must contain a valid email address.'
            );
        }

        $passwordCharacterCount = preg_match_all('/./us', $password);

        if ($passwordCharacterCount === false || $passwordCharacterCount < 15) {
            throw new RuntimeException(
                'RBAC_ADMIN_PASSWORD must contain at least 15 characters.'
            );
        }

        // Keep the bcrypt byte limit consistent with the existing Auth policy.
        if (strlen($password) > 72) {
            throw new RuntimeException(
                'RBAC_ADMIN_PASSWORD must not exceed 72 bytes.'
            );
        }

        $adminRole = Role::query()
            ->where('slug', 'admin')
            ->first();

        if ($adminRole === null) {
            throw new RuntimeException(
                'The admin role does not exist. '
                    .'Run RolePermissionSeeder first.'
            );
        }

        /*
         * Find an existing account without overwriting its credentials.
         * Only a newly created development account gets is_active = true.
         */
        $user = User::query()
            ->where('email', $email)
            ->first();

        if ($user === null) {
            $user = new User;
            $user->name = $name;
            $user->email = $email;
            $user->password = $password;

            // is_active is intentionally not mass assignable in Auth.
            // Force-fill it only for this explicitly configured new account.
            $user->forceFill([
                'is_active' => true,
            ]);

            $user->save();
        }

        // Never silently reactivate an existing inactive account.
        if (! $user->is_active) {
            throw new RuntimeException(
                'The configured development admin account is inactive. '
                    .'Activate it through an explicitly authorized workflow.'
            );
        }

        // Preserve existing roles and add admin only when absent.
        $user->roles()->syncWithoutDetaching([
            $adminRole->getKey(),
        ]);

        $this->command?->info(
            "Development admin account is ready: {$email}"
        );
    }
}
