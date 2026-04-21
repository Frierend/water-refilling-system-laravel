<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class RunLocalBackup extends Command
{
    protected $signature = 'backup:run-local {--include-storage : Include configured storage artifacts}';

    protected $description = 'Create a local backup archive with database, critical logs, and optional storage artifacts.';

    public function handle(): int
    {
        $filesystem = new Filesystem();
        $timestamp = now()->format('Ymd_His');
        $backupRoot = storage_path(config('backup.local.path', 'app/backups'));
        $tempDir = $backupRoot.'/tmp/'.$timestamp.'_'.Str::lower(Str::random(6));
        $archivePath = $backupRoot.'/backup_'.$timestamp.'.zip';
        $checksumPath = $archivePath.'.sha256';

        $filesystem->ensureDirectoryExists($tempDir);

        try {
            $databaseDumpPath = $tempDir.'/database.sql';
            $this->dumpDatabase($databaseDumpPath);

            $copiedLogs = $this->copyCriticalLogs($tempDir.'/logs');
            $copiedStorageFiles = $this->copyStorageArtifacts($tempDir.'/storage');

            $manifestPath = $tempDir.'/manifest.txt';
            $this->writeManifest($tempDir, $manifestPath);

            $this->createZipArchive($tempDir, $archivePath);
            $this->writeAndVerifyChecksum($archivePath, $checksumPath);

            $filesystem->deleteDirectory($tempDir);

            $this->info('Backup completed successfully.');
            $this->line('Archive: '.$archivePath);
            $this->line('Checksum: '.$checksumPath);
            $this->line('Logs included: '.$copiedLogs);
            $this->line('Storage artifacts included: '.$copiedStorageFiles);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $filesystem->deleteDirectory($tempDir);
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function dumpDatabase(string $targetPath): void
    {
        $connectionName = config('database.default');
        $config = config("database.connections.{$connectionName}", []);
        $driver = $config['driver'] ?? '';

        if ($driver === 'sqlite') {
            $databasePath = $config['database'] ?? null;
            if (! $databasePath || ! file_exists($databasePath)) {
                throw new RuntimeException('SQLite database file was not found for backup.');
            }

            copy($databasePath, $targetPath);

            return;
        }

        if ($driver === 'mysql') {
            $binary = (string) config('backup.local.mysql_dump_binary', env('BACKUP_MYSQLDUMP_BINARY', 'mysqldump'));
            $commandParts = [
                escapeshellarg($binary),
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--host='.escapeshellarg((string) ($config['host'] ?? '127.0.0.1')),
                '--port='.escapeshellarg((string) ($config['port'] ?? '3306')),
                '--user='.escapeshellarg((string) ($config['username'] ?? 'root')),
                '--result-file='.escapeshellarg($targetPath),
                escapeshellarg((string) ($config['database'] ?? '')),
            ];

            if (! empty($config['unix_socket'])) {
                $commandParts[] = '--socket='.escapeshellarg((string) $config['unix_socket']);
            }

            $this->runExternalDumpCommand(
                implode(' ', $commandParts),
                ['MYSQL_PWD' => (string) ($config['password'] ?? '')],
                'mysqldump'
            );

            return;
        }

        if ($driver === 'pgsql') {
            $binary = (string) config('backup.local.pg_dump_binary', env('BACKUP_PG_DUMP_BINARY', 'pg_dump'));
            $commandParts = [
                escapeshellarg($binary),
                '--host='.escapeshellarg((string) ($config['host'] ?? '127.0.0.1')),
                '--port='.escapeshellarg((string) ($config['port'] ?? '5432')),
                '--username='.escapeshellarg((string) ($config['username'] ?? 'postgres')),
                '--format=plain',
                '--file='.escapeshellarg($targetPath),
                escapeshellarg((string) ($config['database'] ?? '')),
            ];

            $this->runExternalDumpCommand(
                implode(' ', $commandParts),
                ['PGPASSWORD' => (string) ($config['password'] ?? '')],
                'pg_dump'
            );

            return;
        }

        throw new RuntimeException("Unsupported database driver [{$driver}] for local backup command.");
    }

    private function runExternalDumpCommand(string $command, array $environment, string $toolName): void
    {
        $process = Process::fromShellCommandline($command, base_path(), $environment, null, (float) config('backup.local.dump_timeout_seconds', 120));
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Unknown error';
            throw new RuntimeException("{$toolName} command failed: {$message}");
        }
    }

    private function copyCriticalLogs(string $targetDirectory): int
    {
        $filesystem = new Filesystem();
        $filesystem->ensureDirectoryExists($targetDirectory);

        $logRoot = storage_path('logs');
        $patterns = config('backup.local.log_patterns', ['security*.log', 'system*.log', 'laravel*.log']);
        $copied = 0;

        foreach ($patterns as $pattern) {
            $files = glob($logRoot.DIRECTORY_SEPARATOR.$pattern) ?: [];
            foreach ($files as $filePath) {
                if (! is_file($filePath)) {
                    continue;
                }

                $filesystem->copy($filePath, $targetDirectory.'/'.basename($filePath));
                $copied++;
            }
        }

        return $copied;
    }

    private function copyStorageArtifacts(string $targetDirectory): int
    {
        $includeStorage = (bool) $this->option('include-storage') || (bool) config('backup.local.include_storage', false);
        if (! $includeStorage) {
            return 0;
        }

        $filesystem = new Filesystem();
        $filesystem->ensureDirectoryExists($targetDirectory);

        $paths = config('backup.local.storage_paths', []);
        $copied = 0;

        foreach ($paths as $path) {
            $normalized = ltrim((string) $path, '/\\');
            $fullPath = storage_path('app/'.$normalized);

            if (! file_exists($fullPath)) {
                continue;
            }

            $destination = $targetDirectory.'/'.$normalized;

            if (is_dir($fullPath)) {
                $filesystem->copyDirectory($fullPath, $destination);
                $copied += count($filesystem->allFiles($fullPath));
            } else {
                $filesystem->ensureDirectoryExists(dirname($destination));
                $filesystem->copy($fullPath, $destination);
                $copied++;
            }
        }

        return $copied;
    }

    private function writeManifest(string $sourceDirectory, string $manifestPath): void
    {
        $filesystem = new Filesystem();
        $manifestLines = [
            'generated_at='.now()->toIso8601String(),
            'application='.config('app.name'),
            'database='.DB::connection()->getDatabaseName(),
            'source='.$sourceDirectory,
            '',
            '# sha256 files',
        ];

        foreach ($filesystem->allFiles($sourceDirectory) as $file) {
            $realPath = $file->getRealPath();
            if (! $realPath || $realPath === $manifestPath) {
                continue;
            }

            $relative = ltrim(Str::replaceFirst($sourceDirectory, '', $realPath), DIRECTORY_SEPARATOR);
            $manifestLines[] = hash_file('sha256', $realPath).'  '.$relative;
        }

        file_put_contents($manifestPath, implode(PHP_EOL, $manifestLines).PHP_EOL);
    }

    private function createZipArchive(string $sourceDirectory, string $archivePath): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive extension is required to build backup archives.');
        }

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create backup ZIP archive.');
        }

        $filesystem = new Filesystem();
        foreach ($filesystem->allFiles($sourceDirectory) as $file) {
            $realPath = $file->getRealPath();
            if (! $realPath) {
                continue;
            }

            $localName = ltrim(Str::replaceFirst($sourceDirectory, '', $realPath), DIRECTORY_SEPARATOR);
            $zip->addFile($realPath, $localName);
        }

        $zip->close();
    }

    private function writeAndVerifyChecksum(string $archivePath, string $checksumPath): void
    {
        $checksum = hash_file('sha256', $archivePath);
        file_put_contents($checksumPath, $checksum.'  '.basename($archivePath).PHP_EOL);

        $recomputed = hash_file('sha256', $archivePath);
        if (! hash_equals($checksum, $recomputed)) {
            throw new RuntimeException('Checksum verification failed after archive creation.');
        }
    }
}
