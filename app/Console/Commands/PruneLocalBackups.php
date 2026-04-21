<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class PruneLocalBackups extends Command
{
    protected $signature = 'backup:prune-local {--days= : Delete archives older than the provided day threshold}';

    protected $description = 'Delete local backup archives and checksums older than the configured retention window.';

    public function handle(): int
    {
        $filesystem = new Filesystem();
        $backupRoot = storage_path(config('backup.local.path', 'app/backups'));
        $days = max(1, (int) ($this->option('days') ?: config('backup.local.retention_days', 7)));
        $cutoff = now()->subDays($days)->getTimestamp();

        if (! is_dir($backupRoot)) {
            $this->info('Backup directory does not exist. Nothing to prune.');

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($filesystem->files($backupRoot) as $file) {
            $path = $file->getRealPath();
            if (! $path) {
                continue;
            }

            $name = $file->getFilename();
            $isBackupArtifact = str_ends_with($name, '.zip') || str_ends_with($name, '.zip.sha256');
            if (! $isBackupArtifact) {
                continue;
            }

            if (filemtime($path) <= $cutoff) {
                $filesystem->delete($path);
                $deleted++;
            }
        }

        $tmpDirectory = $backupRoot.'/tmp';
        if (is_dir($tmpDirectory)) {
            foreach ($filesystem->directories($tmpDirectory) as $directory) {
                if (filemtime($directory) <= $cutoff) {
                    $filesystem->deleteDirectory($directory);
                }
            }
        }

        $this->info("Prune completed. Deleted {$deleted} backup artifact(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
