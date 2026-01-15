#!/usr/bin/env php
<?php

/**
 * Queue Status Checker
 * 
 * Quick script to check the status of queue jobs
 * Run: php check-queue-status.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ExamProcessingJob;

echo "=== Queue Status ===\n\n";

// Count by status
$pending = ExamProcessingJob::where('status', 'pending')->count();
$processing = ExamProcessingJob::where('status', 'processing')->count();
$completed = ExamProcessingJob::where('status', 'completed')
    ->where('created_at', '>', now()->subHours(24))
    ->count();
$failed = ExamProcessingJob::where('status', 'failed')
    ->where('created_at', '>', now()->subHours(24))
    ->count();

echo "Pending:          $pending\n";
echo "Processing:       $processing\n";
echo "Completed (24h):  $completed\n";
echo "Failed (24h):     $failed\n\n";

// Show recent jobs
echo "=== Recent Jobs (Last 10) ===\n\n";

$recentJobs = ExamProcessingJob::orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

foreach ($recentJobs as $job) {
    $duration = $job->completed_at 
        ? $job->completed_at->diffInSeconds($job->started_at ?? $job->created_at) . 's'
        : 'N/A';
    
    echo sprintf(
        "[%s] %s - %s - Progress: %d%% - Duration: %s\n",
        $job->created_at->format('Y-m-d H:i:s'),
        $job->job_id,
        strtoupper($job->status),
        $job->progress,
        $duration
    );
    
    if ($job->status === 'failed' && $job->error_message) {
        echo "  Error: " . substr($job->error_message, 0, 100) . "\n";
    }
}

echo "\n";

// Check for stuck jobs
$stuckJobs = ExamProcessingJob::where('status', 'processing')
    ->where('updated_at', '<', now()->subMinutes(10))
    ->count();

if ($stuckJobs > 0) {
    echo "⚠️  WARNING: $stuckJobs job(s) stuck in processing for >10 minutes\n";
    echo "   Run: php artisan queue:restart\n\n";
}

// Check for old pending jobs
$oldPending = ExamProcessingJob::where('status', 'pending')
    ->where('created_at', '<', now()->subMinutes(5))
    ->count();

if ($oldPending > 0) {
    echo "⚠️  WARNING: $oldPending pending job(s) older than 5 minutes\n";
    echo "   Queue worker may not be running!\n";
    echo "   Check your cron job configuration.\n\n";
}

if ($pending === 0 && $processing === 0 && $stuckJobs === 0 && $oldPending === 0) {
    echo "✅ Queue is healthy!\n\n";
}
