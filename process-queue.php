#!/usr/bin/env php
<?php

/**
 * Queue Processor for Shared Hosting
 * 
 * This script processes pending queue jobs.
 * Run via cron: * * * * * php /path/to/process-queue.php
 */

// Change to project directory
chdir(__DIR__);

// Check if artisan exists
if (!file_exists(__DIR__ . '/artisan')) {
    echo "Error: artisan file not found. Make sure this script is in your project root.\n";
    exit(1);
}

// Get the PHP binary path
$phpBinary = defined('PHP_BINARY') ? PHP_BINARY : 'php';

// Build the full command with absolute paths
$artisanPath = __DIR__ . '/artisan';
$command = sprintf(
    '%s %s queue:work database --stop-when-empty --max-time=50 --tries=1 2>&1',
    escapeshellarg($phpBinary),
    escapeshellarg($artisanPath)
);

echo "[" . date('Y-m-d H:i:s') . "] Starting queue worker...\n";
echo "Command: $command\n";

// Execute the command
passthru($command, $exitCode);

echo "[" . date('Y-m-d H:i:s') . "] Queue worker finished with exit code: $exitCode\n";

exit($exitCode);
