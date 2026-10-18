<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SettingsSeeder::class,
            PostCategorySeeder::class,
            BookSeeder::class,
            AmolSeeder::class,
            LegacyCourseSeeder::class,
            DevAccountsSeeder::class,       // skips itself in production
            ProductionAdminSeeder::class,   // no-op unless INITIAL_SUPERADMIN_PASSWORD is set
        ]);
    }
}
