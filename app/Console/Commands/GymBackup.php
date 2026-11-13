<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('gym:backup {--if-missing-today : Only back up if there is no backup from today yet}')]
#[Description('Back up the gym database (and logo) to the backup folder from Settings')]
class GymBackup extends Command
{
    public function handle(BackupService $backups): int
    {
        if ($this->option('if-missing-today') && $backups->hasBackupToday()) {
            $this->info('A backup from today already exists.');

            return self::SUCCESS;
        }

        try {
            $backup = $backups->run();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup saved: {$backups->directory()}".DIRECTORY_SEPARATOR."{$backup['file']}");

        return self::SUCCESS;
    }
}
