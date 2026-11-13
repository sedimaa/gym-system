<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Services\BackupService;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * Real backup and restore against a file-based SQLite database
 * (the in-memory test database can't be dumped).
 */
class BackupTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        if (! (new ExecutableFinder)->find('sqlite3')) {
            $this->markTestSkipped('sqlite3 command-line tool not installed.');
        }

        $this->dir = sys_get_temp_dir().'/gym-backup-test-'.uniqid();
        File::ensureDirectoryExists($this->dir);
        touch($this->dir.'/db.sqlite');

        config(['database.connections.sqlite.database' => $this->dir.'/db.sqlite']);
        DB::purge('sqlite');
        Artisan::call('migrate:fresh', ['--force' => true]);

        app(Settings::class)->set(['backup_path' => $this->dir.'/backups']);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_backup_then_restore_brings_back_the_old_data_after_a_safety_backup(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'owner@gym.test']);
        Member::factory()->create(['last_name' => 'Bago']);

        Carbon::setTestNow('2026-11-14 08:00:00');
        $this->actingAs($admin)->post(route('backups.store'))->assertSessionHas('success');
        $backups = app(BackupService::class)->list();
        $this->assertCount(1, $backups);
        $file = $backups->first()['file'];

        // Data changes after the backup...
        Member::factory()->create(['last_name' => 'Pagkatapos']);
        $this->assertSame(2, Member::count());

        // Wrong confirmation: nothing happens.
        $this->post(route('backups.restore'), ['file' => $file, 'confirmation' => 'restore'])->assertSessionHasErrors('confirmation');
        $this->assertSame(2, Member::count());

        // Restore.
        Carbon::setTestNow('2026-11-14 09:00:00');
        $this->post(route('backups.restore'), ['file' => $file, 'confirmation' => 'RESTORE'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertSame(['Bago'], Member::pluck('last_name')->all(), 'data is back to the backup');
        $this->assertSame(1, User::where('email', 'owner@gym.test')->count());
        $this->assertCount(2, app(BackupService::class)->list(), 'a safety backup was made first');
        $this->assertTrue(Activity::where('log_name', 'backups')->where('event', 'restored')->exists());
        $this->assertGuest();
    }

    public function test_download_and_delete_only_accept_files_in_the_backup_folder(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('backups.store'));
        $file = app(BackupService::class)->list()->first()['file'];

        $this->get(route('backups.index'))->assertOk()->assertSee($file);
        $this->get(route('backups.download', $file))->assertOk();
        $this->get(route('backups.download', '..%2F..%2F.env'))->assertNotFound();
        $this->get(route('backups.download', 'missing.zip'))->assertNotFound();

        $this->delete(route('backups.destroy', $file))->assertSessionHas('success');
        $this->assertCount(0, app(BackupService::class)->list());
    }

    public function test_scheduled_command_makes_one_backup_per_day(): void
    {
        $this->artisan('gym:backup --if-missing-today')->assertSuccessful();
        Carbon::setTestNow(now()->addMinutes(5));
        $this->artisan('gym:backup --if-missing-today')->expectsOutput('A backup from today already exists.')->assertSuccessful();

        $this->assertCount(1, app(BackupService::class)->list());
    }
}
