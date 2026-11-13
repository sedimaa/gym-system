<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function valid(array $overrides = []): array
    {
        return array_merge([
            'gym_name' => 'Barangay Fitness Hub',
            'gym_address' => 'Purok 2, Brgy. Malinis',
            'gym_phone' => '0917 555 0101',
            'receipt_footer' => 'Salamat po!',
            'gym_rules' => "Wipe equipment after use\nNo slippers",
            'grace_days' => 2,
            'expiring_soon_days' => 5,
            'renewal_start_mode' => 'from_old_expiry',
            'walkin_price_editable' => '1',
            'backup_path' => '',
        ], $overrides);
    }

    protected function tearDown(): void
    {
        File::cleanDirectory(Branding::directory());
        File::put(Branding::directory().'/.gitignore', "*\n!.gitignore\n");
        parent::tearDown();
    }

    public function test_admin_updates_settings_and_the_change_is_logged(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('settings.edit'))->assertOk();
        $this->put(route('settings.update'), $this->valid())->assertRedirect(route('settings.edit'));

        $this->assertSame('Barangay Fitness Hub', setting('gym_name'));
        $this->assertSame(2, setting('grace_days'));
        $this->assertSame('from_old_expiry', setting('renewal_start_mode'));
        $this->assertTrue(setting('walkin_price_editable'));

        $log = Activity::where('log_name', 'settings')->firstOrFail();
        $this->assertSame(0, $log->properties['old']['grace_days']);
        $this->assertSame(2, (int) $log->properties['attributes']['grace_days']);

        $this->get(route('dashboard'))->assertSee('Barangay Fitness Hub');
    }

    public function test_settings_are_validated(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('settings.update'), $this->valid(['gym_name' => '', 'grace_days' => -1, 'renewal_start_mode' => 'whenever']))
            ->assertSessionHasErrors(['gym_name', 'grace_days', 'renewal_start_mode']);
    }

    public function test_logo_upload_is_served_and_can_be_removed(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('settings.update'), $this->valid() + ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])
            ->assertSessionHasNoErrors();

        $this->assertNotNull(Branding::logoPath());
        $this->get(route('branding.logo'))->assertOk();
        $this->assertStringStartsWith('data:image/png;base64,', Branding::logoDataUri());

        $this->put(route('settings.update'), $this->valid(['remove_logo' => '1']));
        $this->assertNull(Branding::logoPath());
        $this->get(route('branding.logo'))->assertNotFound();
    }

    public function test_staff_cannot_open_or_change_settings_backups_or_activity_log(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $this->get(route('settings.edit'))->assertForbidden();
        $this->put(route('settings.update'), $this->valid())->assertForbidden();
        $this->get(route('backups.index'))->assertForbidden();
        $this->post(route('backups.store'))->assertForbidden();
        $this->post(route('backups.restore'), ['file' => 'x.zip', 'confirmation' => 'RESTORE'])->assertForbidden();
        $this->get(route('backups.download', 'x.zip'))->assertForbidden();
        $this->delete(route('backups.destroy', 'x.zip'))->assertForbidden();
        $this->get(route('activity-log.index'))->assertForbidden();

        $this->assertSame('Gym Management System', setting('gym_name'));
    }

    public function test_activity_log_page_lists_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put(route('settings.update'), $this->valid());

        $this->get(route('activity-log.index'))->assertOk()->assertSee('Settings changed');
        $this->get(route('activity-log.index', ['log' => 'settings', 'user' => $admin->id]))->assertOk()->assertSee('grace days');
    }
}
