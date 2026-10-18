<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ProductionAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function runSeeder(?string $password): void
    {
        putenv($password === null ? 'INITIAL_SUPERADMIN_PASSWORD' : 'INITIAL_SUPERADMIN_PASSWORD='.$password);
        $_ENV['INITIAL_SUPERADMIN_PASSWORD'] = $password ?? '';
        $_SERVER['INITIAL_SUPERADMIN_PASSWORD'] = $password ?? '';
        $this->seed(ProductionAdminSeeder::class);
    }

    protected function tearDown(): void
    {
        putenv('INITIAL_SUPERADMIN_PASSWORD');
        unset($_ENV['INITIAL_SUPERADMIN_PASSWORD'], $_SERVER['INITIAL_SUPERADMIN_PASSWORD']);
        parent::tearDown();
    }

    public function test_without_the_variable_nothing_is_created(): void
    {
        $this->runSeeder(null);

        $this->assertSame(0, User::where('email', 'superadmin')->count());
    }

    public function test_a_short_password_is_ignored(): void
    {
        $this->runSeeder('short');

        $this->assertSame(0, User::where('email', 'superadmin')->count());
    }

    public function test_it_creates_the_superadmin_with_a_forced_password_change(): void
    {
        $this->runSeeder('a-long-initial-secret');

        $u = User::where('email', 'superadmin')->firstOrFail();
        $this->assertTrue(Hash::check('a-long-initial-secret', $u->password));
        $this->assertTrue($u->force_password_change);
        $this->assertTrue($u->hasRole('super_admin'));
    }

    public function test_it_never_touches_an_account_that_has_signed_in(): void
    {
        $this->runSeeder('a-long-initial-secret');
        $u = User::where('email', 'superadmin')->firstOrFail();
        $u->forceFill(['password' => Hash::make('owners-own-password'), 'force_password_change' => false, 'last_login_at' => now()])->save();

        $this->runSeeder('a-different-long-secret');

        $u->refresh();
        $this->assertTrue(Hash::check('owners-own-password', $u->password), 'a leftover variable cannot reset a live admin');
        $this->assertFalse($u->force_password_change);
    }
}
