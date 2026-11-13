<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class StaffAccountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_create_a_staff_account_and_it_is_logged(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('staff-accounts.store'), [
            'name' => 'Maricel Bautista',
            'email' => 'maricel@gym.test',
            'role' => 'staff',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('staff-accounts.index'));

        $account = User::where('email', 'maricel@gym.test')->firstOrFail();
        $this->assertSame(Role::Staff, $account->role);
        $this->assertTrue($account->is_active);
        $this->assertTrue(Hash::check('password123', $account->password));

        $log = Activity::where('subject_type', User::class)->where('subject_id', $account->id)->latest('id')->first();
        $this->assertSame('created', $log->event);
        $this->assertTrue($log->causer->is($admin));
        $this->assertArrayNotHasKey('password', $log->properties['attributes']);
    }

    public function test_new_account_needs_a_unique_email_and_a_valid_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('staff-accounts.store'), [
            'name' => 'Duplicate',
            'email' => $admin->email,
            'role' => 'owner',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['email', 'role']);
    }

    public function test_admin_can_edit_an_account_and_blank_password_keeps_the_old_one(): void
    {
        $staff = User::factory()->staff()->create(['password' => 'old-password']);

        $this->actingAs($this->admin())->put(route('staff-accounts.update', $staff), [
            'name' => 'New Name',
            'email' => $staff->email,
            'role' => 'staff',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('staff-accounts.index'));

        $staff->refresh();
        $this->assertSame('New Name', $staff->name);
        $this->assertTrue(Hash::check('old-password', $staff->password));
    }

    public function test_admin_can_reset_a_forgotten_password(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($this->admin())->put(route('staff-accounts.update', $staff), [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => 'staff',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass', $staff->fresh()->password));
    }

    public function test_admin_cannot_remove_their_own_admin_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('staff-accounts.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'staff',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_admin_cannot_disable_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('staff-accounts.toggle-active', $admin))
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_disabled_account_cannot_log_in(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($this->admin())
            ->patch(route('staff-accounts.toggle-active', $staff))
            ->assertRedirect(route('staff-accounts.index'));

        $this->assertFalse($staff->fresh()->is_active);
        $this->assertDatabaseHas('activity_logs', ['subject_id' => $staff->id, 'event' => 'updated']);

        auth()->logout();

        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_disabled_while_logged_in_is_signed_out(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('dashboard'))->assertOk();

        $staff->update(['is_active' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_re_enabled_account_can_log_in_again(): void
    {
        $staff = User::factory()->staff()->disabled()->create();

        $this->actingAs($this->admin())->patch(route('staff-accounts.toggle-active', $staff));
        auth()->logout();

        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($staff->fresh());
    }
}
