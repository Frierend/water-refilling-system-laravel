<?php

namespace Tests\Feature\Security;

use App\Console\Commands\PruneLocalBackups;
use App\Console\Commands\RunLocalBackup;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupScheduleRetentionTest extends TestCase
{
    public function test_backup_command_signatures_are_registered(): void
    {
        $this->assertSame(
            'backup:run-local {--include-storage : Include configured storage artifacts}',
            (new RunLocalBackup())->getSignature()
        );

        $this->assertSame(
            'backup:prune-local {--days= : Delete archives older than the provided day threshold}',
            (new PruneLocalBackups())->getSignature()
        );
    }

    public function test_scheduler_registers_backup_run_every_fifteen_minutes_and_daily_prune(): void
    {
        $kernelSource = File::get(app_path('Console/Kernel.php'));

        $this->assertStringContainsString("$schedule->command('backup:run-local')", $kernelSource);
        $this->assertStringContainsString('->everyFifteenMinutes()', $kernelSource);

        $this->assertStringContainsString("$schedule->command('backup:prune-local --days=7')", $kernelSource);
        $this->assertStringContainsString("->dailyAt('01:00')", $kernelSource);
    }

    public function test_backup_retention_default_matches_configuration(): void
    {
        $this->assertSame(7, config('backup.local.retention_days'));
    }
}
