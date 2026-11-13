<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RoleRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_only_gates_allow_admin_and_block_staff(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();

        foreach (AppServiceProvider::ADMIN_ABILITIES as $ability) {
            $this->assertTrue(Gate::forUser($admin)->allows($ability), "Admin should be allowed: {$ability}");
            $this->assertTrue(Gate::forUser($staff)->denies($ability), "Staff should be blocked: {$ability}");
        }
    }

    public function test_staff_is_blocked_from_every_staff_account_action(): void
    {
        $staff = User::factory()->staff()->create();
        $other = User::factory()->staff()->create();

        $this->actingAs($staff);

        $this->get(route('staff-accounts.index'))->assertForbidden();
        $this->get(route('staff-accounts.create'))->assertForbidden();
        $this->post(route('staff-accounts.store'), [
            'name' => 'Sneaky Admin',
            'email' => 'sneaky@gym.test',
            'role' => 'admin',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();
        $this->get(route('staff-accounts.edit', $other))->assertForbidden();
        $this->put(route('staff-accounts.update', $staff), [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => 'admin',
        ])->assertForbidden();
        $this->patch(route('staff-accounts.toggle-active', $other))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@gym.test']);
        $this->assertTrue($staff->fresh()->isStaff());
        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_admin_can_open_staff_accounts(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('staff-accounts.index'))
            ->assertOk()
            ->assertSee($admin->email);
    }

    public function test_sidebar_shows_admin_menu_only_to_admin(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Staff accounts')
            ->assertDontSee('Plans &amp; prices', false);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Staff accounts')
            ->assertSee('Plans &amp; prices', false);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('staff-accounts.index'))->assertRedirect(route('login'));
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }
}
