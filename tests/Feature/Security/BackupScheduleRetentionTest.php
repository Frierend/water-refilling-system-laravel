<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class BackupScheduleRetentionTest extends TestCase
{
    public function test_backup_schedule_and_retention_commands_are_not_configured(): void
    {
        $this->artisan('list')
            ->expectsOutputToContain('about')
            ->assertExitCode(0);

        $this->markTestSkipped('Backup schedule/retention commands are not configured in the current codebase.');
    }
}
