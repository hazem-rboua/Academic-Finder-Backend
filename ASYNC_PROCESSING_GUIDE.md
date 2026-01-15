# Asynchronous Exam Processing Guide

## Overview

The exam processing system uses asynchronous job processing to handle long-running AI requests (approximately 35 seconds). This provides a better user experience by allowing the frontend to show progress updates while processing happens in the background.

## Architecture

```
Frontend → POST /api/exam-results/process
              ↓
         Returns job_id (202 Accepted)
              ↓
    Frontend polls GET /api/exam-results/status/{jobId}
              ↓
         Queue Worker processes job
              ↓
         Updates progress in database
              ↓
    Frontend receives completion/results
```

## How It Works

### 1. Starting Exam Processing

**Endpoint**: `POST /api/exam-results/process`

**Request**:
```json
{
  "exam_code": "EXAM123456"
}
```

**Response** (202 Accepted):
```json
{
  "success": true,
  "message": "Exam processing started",
  "data": {
    "job_id": "550e8400-e29b-41d4-a716-446655440000",
    "status_url": "/api/exam-results/status/550e8400-e29b-41d4-a716-446655440000",
    "estimated_time_seconds": 40
  }
}
```

### 2. Polling for Status

**Endpoint**: `GET /api/exam-results/status/{jobId}`

**Poll every 2 seconds** until status is `completed` or `failed`.

**Response - Processing**:
```json
{
  "success": true,
  "data": {
    "status": "processing",
    "progress": 65,
    "current_step": "Getting AI recommendations...",
    "started_at": "2026-01-15T10:30:00Z"
  }
}
```

**Response - Completed**:
```json
{
  "success": true,
  "data": {
    "status": "completed",
    "progress": 100,
    "result": {
      "job_title": "Airport Operations Director",
      "industry": "Aviation",
      "seniority": "Senior Management",
      "selected_branches": [...],
      "environment_status": [...]
    },
    "completed_at": "2026-01-15T10:30:38Z"
  }
}
```

**Response - Failed**:
```json
{
  "success": false,
  "data": {
    "status": "failed",
    "error_message": "AI API error (HTTP 500): Internal server error",
    "completed_at": "2026-01-15T10:30:05Z"
  }
}
```

**Common Error Messages**:
- `"Exam not found"` - Invalid exam code
- `"AI API error (HTTP 500): Internal server error"` - AI service error
- `"AI API error (HTTP 503): Service temporarily unavailable"` - AI service down
- Connection timeout or network errors from AI service

## Frontend Integration

### JavaScript Example

```javascript
async function processExam(examCode) {
  try {
    // Step 1: Start processing
    const startResponse = await fetch('/api/exam-results/process', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Accept-Language': 'en' // or 'ar' for Arabic
      },
      body: JSON.stringify({ exam_code: examCode })
    });

    if (!startResponse.ok) {
      throw new Error('Failed to start processing');
    }

    const { data } = await startResponse.json();
    const jobId = data.job_id;

    // Step 2: Poll for status
    return await pollJobStatus(jobId);
  } catch (error) {
    console.error('Error processing exam:', error);
    throw error;
  }
}

async function pollJobStatus(jobId) {
  return new Promise((resolve, reject) => {
    const pollInterval = setInterval(async () => {
      try {
        const response = await fetch(`/api/exam-results/status/${jobId}`, {
          headers: {
            'Accept': 'application/json',
          }
        });

        if (!response.ok) {
          clearInterval(pollInterval);
          reject(new Error('Failed to get job status'));
          return;
        }

        const { success, data } = await response.json();

        // Update UI with progress
        updateProgressBar(data.progress);
        updateStatusText(data.current_step || 'Processing...');

        // Check if complete
        if (data.status === 'completed') {
          clearInterval(pollInterval);
          resolve(data.result);
        } else if (data.status === 'failed') {
          clearInterval(pollInterval);
          reject(new Error(data.error_message));
        }
      } catch (error) {
        clearInterval(pollInterval);
        reject(error);
      }
    }, 2000); // Poll every 2 seconds
  });
}

function updateProgressBar(progress) {
  const progressBar = document.getElementById('progress-bar');
  progressBar.style.width = `${progress}%`;
  progressBar.textContent = `${progress}%`;
}

function updateStatusText(text) {
  const statusText = document.getElementById('status-text');
  statusText.textContent = text;
}

// Usage
processExam('EXAM123456')
  .then(results => {
    console.log('Exam results:', results);
    displayResults(results);
  })
  .catch(error => {
    console.error('Error:', error);
    showError(error.message);
  });
```

### React Example

```jsx
import { useState, useEffect } from 'react';

function ExamProcessor({ examCode }) {
  const [jobId, setJobId] = useState(null);
  const [status, setStatus] = useState('idle');
  const [progress, setProgress] = useState(0);
  const [currentStep, setCurrentStep] = useState('');
  const [results, setResults] = useState(null);
  const [error, setError] = useState(null);

  // Start processing
  const startProcessing = async () => {
    try {
      const response = await fetch('/api/exam-results/process', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ exam_code: examCode })
      });

      const { data } = await response.json();
      setJobId(data.job_id);
      setStatus('polling');
    } catch (err) {
      setError(err.message);
      setStatus('error');
    }
  };

  // Poll for status
  useEffect(() => {
    if (!jobId || status !== 'polling') return;

    const interval = setInterval(async () => {
      try {
        const response = await fetch(`/api/exam-results/status/${jobId}`);
        const { data } = await response.json();

        setProgress(data.progress);
        setCurrentStep(data.current_step);

        if (data.status === 'completed') {
          setResults(data.result);
          setStatus('completed');
          clearInterval(interval);
        } else if (data.status === 'failed') {
          setError(data.error_message);
          setStatus('error');
          clearInterval(interval);
        }
      } catch (err) {
        setError(err.message);
        setStatus('error');
        clearInterval(interval);
      }
    }, 2000);

    return () => clearInterval(interval);
  }, [jobId, status]);

  return (
    <div>
      {status === 'idle' && (
        <button onClick={startProcessing}>Process Exam</button>
      )}
      
      {status === 'polling' && (
        <div>
          <div className="progress-bar">
            <div style={{ width: `${progress}%` }}>{progress}%</div>
          </div>
          <p>{currentStep}</p>
        </div>
      )}
      
      {status === 'completed' && results && (
        <div>
          <h3>Results</h3>
          <pre>{JSON.stringify(results, null, 2)}</pre>
        </div>
      )}
      
      {status === 'error' && (
        <div className="error">Error: {error}</div>
      )}
    </div>
  );
}
```

## Backend Setup

### 1. Run Migration

```bash
php artisan migrate
```

This creates the `exam_processing_jobs` table to track job status.

### 2. Configure Environment

Add to your `.env` file:

```env
# AI API timeout (increased to 60 seconds)
AI_API_TIMEOUT=60

# Queue configuration
QUEUE_CONNECTION=database

# Optional: Use Redis for better performance
# QUEUE_CONNECTION=redis
```

### 3. Start Queue Worker

The queue worker processes jobs in the background.

**Development**:
```bash
php artisan queue:work
```

**Production** (use Supervisor):

Create `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/academic-finder-backend/artisan queue:work --sleep=3 --tries=1 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/academic-finder-backend/storage/logs/worker.log
stopwaitsecs=3600
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### 4. Clear Config Cache

```bash
php artisan config:clear
```

## Progress Stages

The job goes through these stages with progress percentages:

| Progress | Stage                         | Duration    |
|----------|-------------------------------|-------------|
| 0%       | Starting                      | Instant     |
| 5%       | Validating exam               | < 1s        |
| 10%      | Parsing exam answers          | < 1s        |
| 15%      | Loading calculation data      | < 1s        |
| 20%      | Calculating job compatibility | < 1s        |
| 25-90%   | Getting AI recommendations    | ~35s        |
| 95%      | Finalizing results            | < 1s        |
| 100%     | Completed                     | Done        |

**Note**: The 25-90% range is simulated since we can't track the Gemini AI's internal progress.

## Monitoring

### Check Job Status in Database

```sql
SELECT * FROM exam_processing_jobs ORDER BY created_at DESC LIMIT 10;
```

### View Queue Worker Logs

```bash
tail -f storage/logs/laravel.log | grep "Exam processing"
```

### Monitor Failed Jobs

```bash
php artisan queue:failed
```

Retry a failed job:
```bash
php artisan queue:retry {job-id}
```

## Troubleshooting

### Queue Worker Not Processing Jobs

**Problem**: Jobs stuck in `pending` status.

**Solution**: Ensure queue worker is running:
```bash
php artisan queue:work
```

### Jobs Timing Out

**Problem**: Jobs fail with timeout errors.

**Solution**: Check AI API timeout is set to 60 seconds:
```bash
php artisan config:cache
```

### Database Connection Issues

**Problem**: Can't find exam in external database.

**Solution**: Verify database connection in `config/database.php` for `external_api`.

### AI API Not Responding

**Problem**: AI recommendations always null.

**Solution**:
1. Check `AI_API_BASE_URL` in `.env`
2. Verify AI API is accessible
3. Check firewall/network settings
4. Review logs for detailed error messages

## API Documentation

Full API documentation is available at:
- **Development**: `http://localhost/api/documentation`
- **Production**: `https://your-domain.com/api/documentation`

Regenerate Swagger docs after changes:
```bash
php artisan l5-swagger:generate
```

## Performance Considerations

### Polling Frequency

- **Recommended**: 2 seconds
- **Too fast** (< 1s): Increased server load
- **Too slow** (> 5s): Poor user experience

### Queue Configuration

For high traffic, use Redis instead of database:

```env
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
```

### Scaling

For multiple servers, ensure:
1. Shared database for job tracking
2. Queue workers on each server
3. Load balancer for API requests

## Testing

Run the test suite:

```bash
php artisan test --filter AsyncExamProcessingTest
```

Test individual scenarios:
```bash
php artisan test --filter test_process_endpoint_dispatches_job_and_returns_job_id
```

## Best Practices

1. **Always poll with exponential backoff** for production
2. **Set a maximum poll duration** (e.g., 90 seconds) to avoid infinite polling
3. **Handle network errors** gracefully in polling logic
4. **Show meaningful progress text** to users, not just percentages
5. **Cache job results** if users might request the same exam multiple times
6. **Clean up old jobs** periodically to prevent database bloat

## Cleanup

Delete old completed jobs (older than 7 days):

```bash
php artisan tinker
>>> App\Models\ExamProcessingJob::where('completed_at', '<', now()->subDays(7))->delete();
```

Or create a scheduled job in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        ExamProcessingJob::where('completed_at', '<', now()->subDays(7))->delete();
    })->daily();
}
```

## Support

For issues related to:
- **AI API**: Contact AI service team
- **Queue processing**: Check Laravel logs
- **Integration**: Review this guide and API documentation
