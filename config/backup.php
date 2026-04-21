<?php

return [
    'local' => [
        'path' => env('LOCAL_BACKUP_PATH', 'app/backups'),
        'retention_days' => env('LOCAL_BACKUP_RETENTION_DAYS', 7),
        'include_storage' => env('LOCAL_BACKUP_INCLUDE_STORAGE', false),
        'storage_paths' => array_values(array_filter(array_map(
            static fn (string $path): string => trim($path),
            explode(',', (string) env('LOCAL_BACKUP_STORAGE_PATHS', 'exports,imports'))
        ))),
        'log_patterns' => ['security*.log', 'system*.log', 'laravel*.log'],
        'dump_timeout_seconds' => env('LOCAL_BACKUP_DUMP_TIMEOUT_SECONDS', 180),
        'mysql_dump_binary' => env('BACKUP_MYSQLDUMP_BINARY', 'mysqldump'),
        'pg_dump_binary' => env('BACKUP_PG_DUMP_BINARY', 'pg_dump'),
    ],
];
