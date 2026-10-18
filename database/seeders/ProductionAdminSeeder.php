<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Gives a fresh production site its first way in, without a password ever living in the repo.
 *
 * The password comes from the INITIAL_SUPERADMIN_PASSWORD environment variable (set on the
 * hosting dashboard, never in code or chat). It is applied ONLY to the `superadmin` account,
 * ONLY while that account has never signed in, and the account is forced to choose its own
 * password at first login. Once the owner has logged in this seeder does nothing — a leftover
 * variable can never reset a live admin password. Without the variable it does nothing at all.
 */
class ProductionAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) env('INITIAL_SUPERADMIN_PASSWORD', '');
        if ($password === '' || strlen($password) < 12) {
            if ($password !== '') {
                $this->command?->warn('INITIAL_SUPERADMIN_PASSWORD is shorter than 12 characters; ignored.');
            }

            return;
        }

        $admin = User::query()->firstOrNew(['email' => 'superadmin']);

        if ($admin->exists && $admin->last_login_at !== null) {
            return;   // already in use
        }

        $admin->fill(['name' => $admin->name ?: 'সুপার অ্যাডমিন', 'status' => User::STATUS_ACTIVE]);
        $admin->forceFill(['password' => Hash::make($password), 'force_password_change' => true])->save();
        $admin->assignRole(Role::SUPER_ADMIN);

        $this->command?->info('Initial superadmin password applied (must be changed at first login).');
    }
}
