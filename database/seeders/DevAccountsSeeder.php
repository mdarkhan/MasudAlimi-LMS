<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\Points\PointService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local development accounts only. Never runs in production.
 *
 * Exactly three, all with the password "password":
 *   superadmin  — super_admin (everything, incl. staff & roles)
 *   admin       — admin (day-to-day management)
 *   roll 101    — a demo student (for trying the student side)
 *
 * Real students are never created here: they come from the legacy import.
 */
class DevAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DevAccountsSeeder skipped in production.');

            return;
        }

        $points = app(PointService::class);

        $superAdmin = User::query()->updateOrCreate(
            ['email' => 'superadmin'],
            ['name' => 'সুপার অ্যাডমিন', 'password' => Hash::make('password'), 'status' => User::STATUS_ACTIVE],
        );
        $superAdmin->assignRole(Role::SUPER_ADMIN);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin'],
            ['name' => 'অ্যাডমিন', 'password' => Hash::make('password'), 'status' => User::STATUS_ACTIVE],
        );
        $admin->assignRole(Role::ADMIN);

        $student = User::query()->updateOrCreate(
            ['roll' => User::normaliseRoll('১০১')],
            ['name' => 'ডেমো শিক্ষার্থী', 'guardian_name' => 'অভিভাবক', 'password' => Hash::make('password'), 'status' => User::STATUS_ACTIVE],
        );
        $student->assignRole(Role::STUDENT);

        if ($points->ledgerBalance($student) === 0) {
            $points->credit($student, 5, reason: 'প্রাথমিক পয়েন্ট', performedBy: $superAdmin);
        }

        $this->command?->info('Dev accounts (password "password"): superadmin, admin, student roll 101.');
    }
}
