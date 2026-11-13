<?php

namespace App\Services;

use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * Backups use spatie/laravel-backup: a zip with the database dump and the uploaded logo,
 * saved to the backup_path setting (default storage/app/backups/gym-system).
 *
 * Restore (admin only, typed confirmation in the UI) first makes a fresh safety backup,
 * then replaces the database with the dump from the chosen zip.
 */
class BackupService
{
    public function __construct(private readonly Settings $settings) {}

    /** Folder that holds the backup zips. */
    public function directory(): string
    {
        return rtrim($this->baseDirectory(), '/\\').DIRECTORY_SEPARATOR.config('backup.backup.name');
    }

    public function run(): array
    {
        $this->configure();

        $exit = Artisan::call('backup:run', ['--disable-notifications' => true]);
        $output = Artisan::output();

        if ($exit !== 0 || str_contains($output, 'Backup failed')) {
            throw new RuntimeException('Backup failed: '.$this->lastLines($output));
        }

        Artisan::call('backup:clean', ['--disable-notifications' => true]);

        return $this->list()->first() ?? throw new RuntimeException('Backup finished but no file was found in '.$this->directory());
    }

    public function hasBackupToday(): bool
    {
        return $this->list()->contains(fn ($b) => $b['date']->isToday());
    }

    /** @return Collection<int, array{file: string, size: int, date: CarbonImmutable}> newest first */
    public function list(): Collection
    {
        $this->configure();

        if (! File::isDirectory($this->directory())) {
            return collect();
        }

        return collect(File::files($this->directory()))
            ->filter(fn ($f) => strtolower($f->getExtension()) === 'zip')
            ->map(fn ($f) => [
                'file' => $f->getFilename(),
                'size' => $f->getSize(),
                'date' => CarbonImmutable::createFromTimestamp($f->getMTime(), config('app.timezone')),
            ])
            ->sortByDesc(fn ($b) => $b['date']->timestamp)
            ->values();
    }

    /** Absolute path of a backup in the backup folder; refuses anything else. */
    public function path(string $file): string
    {
        if (! preg_match('/^[\w\-. ]+\.zip$/', $file) || str_contains($file, '..')) {
            throw new InvalidArgumentException('Invalid backup file name.');
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$file;

        if (! File::isFile($path)) {
            throw new InvalidArgumentException('Backup file not found.');
        }

        return $path;
    }

    public function delete(string $file): void
    {
        File::delete($this->path($file));
    }

    /**
     * Replace the database (and logo) with the contents of a backup.
     *
     * @return array{safety_backup: string}
     */
    public function restore(string $file, User $by): array
    {
        $source = $this->path($file);

        // Read the dump out of the zip before anything changes.
        $workDir = storage_path('app/restore-'.uniqid());
        File::ensureDirectoryExists($workDir);

        try {
            [$sqlPath, $brandingFiles] = $this->extract($source, $workDir);

            // 1. Safety backup of the current data. If this fails, nothing is touched.
            $safety = $this->run();

            // 2. Replace the database.
            $connection = DB::connection();
            $this->withDumpBinariesOnPath(function () use ($connection, $sqlPath) {
                $connection->getSchemaBuilder()->dropAllTables();
                $connection->getSchemaState()->load($sqlPath);
            });

            // 3. Put back the logo from the backup.
            $brandingDir = storage_path('app/private/branding');
            File::ensureDirectoryExists($brandingDir);
            foreach ($brandingFiles as $name => $path) {
                File::copy($path, $brandingDir.DIRECTORY_SEPARATOR.$name);
            }
        } finally {
            File::deleteDirectory($workDir);
        }

        $this->settings->set([]); // forget cached settings

        // Logged after the import, so the entry survives in the restored activity log.
        activity('backups')->causedBy(User::find($by->id))->event('restored')
            ->withProperties(['file' => $file, 'safety_backup' => $safety['file']])
            ->log("Database restored from {$file}");

        return ['safety_backup' => $safety['file']];
    }

    /** @return array{0: string, 1: array<string, string>} sql dump path, branding files */
    private function extract(string $zipPath, string $workDir): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('The backup file could not be opened.');
        }

        $sqlPath = null;
        $branding = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', $zip->getNameIndex($i));
            $base = basename($name);

            if (str_starts_with($name, 'db-dumps/') && str_ends_with($name, '.sql')) {
                $sqlPath = $workDir.DIRECTORY_SEPARATOR.'database.sql';
                File::put($sqlPath, $zip->getFromIndex($i));
            } elseif (str_starts_with($name, 'branding/') && $base !== '' && $base !== '.gitignore' && ! str_ends_with($name, '/')) {
                $branding[$base] = $workDir.DIRECTORY_SEPARATOR.'branding-'.$base;
                File::put($branding[$base], $zip->getFromIndex($i));
            }
        }

        $zip->close();

        if (! $sqlPath || File::size($sqlPath) === 0) {
            throw new RuntimeException('This backup does not contain a database dump.');
        }

        return [$sqlPath, $branding];
    }

    private function baseDirectory(): string
    {
        $custom = trim((string) $this->settings->get('backup_path'));

        return $custom !== '' ? $custom : storage_path('app/backups');
    }

    /** Point the backups disk at the configured folder and make sure the folders exist. */
    private function configure(): void
    {
        File::ensureDirectoryExists($this->baseDirectory());
        File::ensureDirectoryExists(storage_path('app/private/branding'));

        config(['filesystems.disks.backups.root' => $this->baseDirectory()]);
        Storage::forgetDisk('backups');
    }

    /** Laragon's mysql/mysqldump may not be on the PATH of the web server; DB_DUMP_BINARY_PATH fixes that. */
    private function withDumpBinariesOnPath(callable $callback): void
    {
        $dir = (string) config('database.connections.'.config('database.default').'.dump.dump_binary_path');

        if ($dir === '') {
            $callback();

            return;
        }

        $original = getenv('PATH') ?: '';
        putenv('PATH='.$dir.PATH_SEPARATOR.$original);
        $_SERVER['PATH'] = $_ENV['PATH'] = $dir.PATH_SEPARATOR.$original;

        try {
            $callback();
        } finally {
            putenv('PATH='.$original);
            $_SERVER['PATH'] = $_ENV['PATH'] = $original;
        }
    }

    private function lastLines(string $output): string
    {
        return collect(preg_split('/\R/', trim($output)))->filter()->take(-3)->implode(' ');
    }
}
