# Shared Hosting Queue Setup Guide

## Overview

Since shared hosting doesn't support Supervisor or systemd, we use a **cron job** to process the queue every minute. This provides near-real-time processing without requiring root access.

## How It Works

```
1. User submits exam → Job created in database (status: pending)
2. API returns job_id immediately
3. Cron job runs every minute → Processes pending jobs
4. Frontend polls status endpoint → Gets progress updates
5. Job completes → Frontend receives results
```

## Setup Steps

### Step 1: Verify Queue Configuration

Check your `.env` file has:

```env
QUEUE_CONNECTION=database
```

**Do NOT use** `sync` or `redis` - use `database` for shared hosting.

### Step 2: Create Cron Job in cPanel

1. **Log into cPanel**
2. **Find "Cron Jobs"** (usually under "Advanced" section)
3. **Add New Cron Job** with these settings:

**Common Settings:**
- **Minute**: `*` (every minute)
- **Hour**: `*` (every hour)
- **Day**: `*` (every day)
- **Month**: `*` (every month)
- **Weekday**: `*` (every weekday)

**Command:**
```bash
cd /home/username/public_html/academic-finder-backend && /usr/bin/php artisan queue:work database --stop-when-empty --max-time=50
```

**Important**: Replace `/home/username/public_html/academic-finder-backend` with your actual path!

### Step 3: Find Your Correct Paths

To find your paths, create a test file `info.php` in your project root:

```php
<?php
echo "PHP Binary: " . PHP_BINARY . "\n";
echo "Current Directory: " . getcwd() . "\n";
echo "Project Root: " . __DIR__ . "\n";
?>
```

Visit `https://yourdomain.com/info.php` in browser, then **delete the file** after noting the paths.

### Step 4: Alternative Cron Command Formats

If the above doesn't work, try these alternatives:

**Format 1** (Most common):
```bash
* * * * * cd /home/username/public_html/academic-finder-backend && php artisan queue:work database --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

**Format 2** (With full PHP path):
```bash
* * * * * /usr/local/bin/php /home/username/public_html/academic-finder-backend/artisan queue:work database --stop-when-empty --max-time=50
```

**Format 3** (With logging):
```bash
* * * * * cd /home/username/public_html/academic-finder-backend && php artisan queue:work database --stop-when-empty --max-time=50 >> storage/logs/cron.log 2>&1
```

### Step 5: Understanding the Command

- `queue:work database` - Process jobs from database queue
- `--stop-when-empty` - Exit when no jobs (important for cron!)
- `--max-time=50` - Stop after 50 seconds (before next cron run)
- `>> /dev/null 2>&1` - Suppress output (optional)

## Verification

### Check if Cron is Running

1. **Submit a test exam** via API
2. **Wait 1-2 minutes** (for cron to run)
3. **Check job status** - should change from `pending` to `processing` to `completed`

### View Cron Logs

If you used Format 3 with logging:

```bash
tail -f storage/logs/cron.log
```

### Check Laravel Logs

```bash
tail -f storage/logs/laravel.log | grep "Exam processing"
```

### Manual Test

SSH into your server (if available) and run:

```bash
cd /path/to/academic-finder-backend
php artisan queue:work database --stop-when-empty
```

This processes any pending jobs immediately.

## Troubleshooting

### Jobs Stay in "Pending" Status

**Problem**: Cron job not running or failing

**Solutions**:
1. Check cron job is active in cPanel
2. Verify paths are correct
3. Check PHP version: `php -v` (should be 8.1+)
4. Look for cron execution emails in your email
5. Check file permissions: `chmod -R 755 storage`

### "Command not found" Error

**Problem**: Wrong PHP path

**Solution**: Find correct PHP binary:
```bash
which php
# or
whereis php
# or
/usr/bin/php -v
/usr/local/bin/php -v
```

Use the working path in your cron command.

### Jobs Timeout

**Problem**: Jobs take longer than 50 seconds

**Solution**: Increase `--max-time`:
```bash
--max-time=110
```

But keep it under 2 minutes to avoid overlapping cron runs.

### Permission Denied

**Problem**: Can't write to storage/logs

**Solution**:
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

## Performance Considerations

### Cron Frequency

- **Every minute** (recommended): `* * * * *`
  - Jobs processed within 1-2 minutes
  - Good balance of responsiveness and server load

- **Every 2 minutes**: `*/2 * * * *`
  - Less server load
  - Slower processing (2-4 minutes delay)

- **Every 30 seconds** (if supported):
  ```bash
  * * * * * sleep 0 && cd /path && php artisan queue:work --stop-when-empty
  * * * * * sleep 30 && cd /path && php artisan queue:work --stop-when-empty
  ```

### Multiple Workers

For high traffic, run multiple workers:

```bash
# Worker 1
* * * * * cd /path && php artisan queue:work database --stop-when-empty --max-time=50

# Worker 2 (30 seconds offset)
* * * * * sleep 30 && cd /path && php artisan queue:work database --stop-when-empty --max-time=20
```

## Monitoring

### Check Queue Status

Create a simple monitoring script `check-queue.php`:

```php
<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$pending = \App\Models\ExamProcessingJob::where('status', 'pending')->count();
$processing = \App\Models\ExamProcessingJob::where('status', 'processing')->count();
$failed = \App\Models\ExamProcessingJob::where('status', 'failed')
    ->where('created_at', '>', now()->subHours(24))
    ->count();

echo "Pending: $pending\n";
echo "Processing: $processing\n";
echo "Failed (24h): $failed\n";
```

Run: `php check-queue.php`

### Email Notifications

Add to cron for email alerts on failures:

```bash
* * * * * cd /path && php artisan queue:work database --stop-when-empty 2>&1 | grep -i error && echo "Queue error detected" | mail -s "Queue Alert" your@email.com
```

## Cleanup Old Jobs

Add a daily cron to clean up old completed jobs:

```bash
0 2 * * * cd /path && php artisan tinker --execute="App\Models\ExamProcessingJob::where('completed_at', '<', now()->subDays(7))->delete();"
```

This runs at 2 AM daily and deletes jobs older than 7 days.

## Alternative: Laravel Scheduler

Instead of multiple cron jobs, use Laravel's scheduler (requires only ONE cron job).

### Step 1: Add to `app/Console/Kernel.php`

```php
protected function schedule(Schedule $schedule)
{
    // Process queue every minute
    $schedule->command('queue:work database --stop-when-empty --max-time=50')
        ->everyMinute()
        ->withoutOverlapping();
    
    // Clean up old jobs daily
    $schedule->call(function () {
        \App\Models\ExamProcessingJob::where('completed_at', '<', now()->subDays(7))->delete();
    })->daily();
}
```

### Step 2: Add ONE cron job

```bash
* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
```

This is cleaner and easier to manage!

## Summary

✅ **What you did**:
- Set up database queue connection
- Created cron job to process queue every minute
- Queue processes jobs automatically in background

✅ **What happens now**:
- User submits exam → Instant response with job_id
- Cron processes job within 1-2 minutes
- Frontend polls for progress
- User sees results when complete

✅ **No Supervisor needed** - Works perfectly on shared hosting!

## Need Help?

If you're stuck:
1. Check cPanel cron job logs
2. Check `storage/logs/laravel.log`
3. Test manually: `php artisan queue:work database --stop-when-empty`
4. Verify database connection works
5. Ensure file permissions are correct (755 for storage)
