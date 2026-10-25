<?php

namespace App\Jobs;

use App\Models\ExamProcessingJob;
use App\Services\ExamResultService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerateAcademicFinderPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180;
    // Worker-side backstop only. The real retry cap is self::MAX_ATTEMPTS, enforced
    // inside handle() below so the FINAL attempt can persist status=failed + the real
    // error text on the row before giving up. $tries must stay strictly greater than
    // MAX_ATTEMPTS, otherwise the worker's pre-handle max-attempts check would kill the
    // last attempt before handle() runs and the diagnostic would be lost. This property
    // also overrides the CLI `--tries=1` in process-queue.php (job property wins).
    public $tries   = 5;

    /** Total generation attempts (initial + transient retries). */
    private const MAX_ATTEMPTS = 4;

    /**
     * Backoff in seconds between transient retries: ~5m after attempt 1, ~30m
     * after attempt 2, ~60m after attempt 3 (~95min total window). Sized for the
     * real failure mode — upstream "No API keys available"/quota exhaustion, which
     * persists for hours, not seconds. A shorter window died inside the outage.
     */
    private const RETRY_BACKOFF = [300, 1800, 3600];

    public function __construct(public string $examCode, public ?string $lang = null, public ?string $env = null) {}

    public function handle(ExamResultService $examResultService): void
    {
        $job = ExamProcessingJob::where('exam_code', $this->examCode)->latest()->first();

        if (!$job) {
            $job = ExamProcessingJob::create([
                'job_id'    => Str::uuid()->toString(),
                'exam_code' => $this->examCode,
                'status'    => 'pending',
                'progress'  => 0,
            ]);
        }

        $lang = in_array($this->lang, ['ar', 'en']) ? $this->lang : (in_array($job->lang, ['ar', 'en']) ? $job->lang : 'en');
        $isTest = $this->env === 'testing';
        $pdfPath = storage_path('app/reports/' . $this->examCode . '-' . $lang . ($isTest ? '.test' : '') . '.pdf');

        // Idempotency: already done and file exists
        if ($job->pdf_ready && file_exists($pdfPath)) {
            Log::info('AF PDF already exists, skipping', ['exam_code' => $this->examCode]);
            return;
        }

        // Regenerate: remove stale file
        if (file_exists($pdfPath)) {
            @unlink($pdfPath);
        }

        try {
            $job->markAsProcessing();

            // Run pipeline: validate + algorithm + AI
            $examResultService->externalConnection = ($this->env === 'testing') ? 'external_api_test' : 'external_api';
            $aiResult = $examResultService->processExamResults($this->examCode);

            Log::info('AF AI result shape', [
                'top_keys'   => array_keys($aiResult ?? []),
                'jobs_count' => count($aiResult['recommended_jobs'] ?? $aiResult['recommendedJobs'] ?? []),
            ]);

            $job->markAsCompleted($aiResult);

            // The same report as the Twindix API draws (pdf.academic-report): every faculty
            // the AI sends with major_1..3 and the full reasoning. One render, no merge needed.
            $rawJobs = $aiResult['recommended_jobs'] ?? $aiResult['recommendedJobs'] ?? [];
            app(\App\Services\AcademicReportPdf::class)->render(
                $lang,
                $rawJobs,
                (string) ($job->user_name ?: '—'),
                $this->examCode,
                now(),
                $pdfPath
            );

            $job->update([
                'pdf_ready' => true,
                'pdf_path'  => $pdfPath,
            ]);

            Log::info('AF PDF generated', ['exam_code' => $this->examCode, 'path' => $pdfPath]);

        } catch (\Throwable $e) {
            // Retry ONLY transient upstream failures (AI 5xx / connection / timeout),
            // with backoff, capped at MAX_ATTEMPTS total. Permanent errors (exam not
            // found, invalid data, missing CSV, 4xx) fail fast — retrying them would
            // just hammer the provider for something that can never succeed.
            if ($this->isTransient($e) && $this->attempts() < self::MAX_ATTEMPTS) {
                $backoff = self::RETRY_BACKOFF;
                $delay   = $backoff[$this->attempts() - 1] ?? end($backoff);
                Log::warning('AF PDF transient failure — releasing for retry', [
                    'exam_code' => $this->examCode,
                    'attempt'   => $this->attempts(),
                    'retry_in'  => $delay,
                    'error'     => $e->getMessage(),
                ]);
                // Put the job back on the queue with a delay; attempts() increments on
                // the next reservation. Generation is idempotent (deterministic filename,
                // row keyed by exam code) so a later success simply overwrites.
                $this->release($delay);
                return;
            }

            // Permanent error, or transient retries exhausted: record the terminal
            // failed state WITH the real error text preserved for diagnosis.
            $job->markAsFailed($e->getMessage());
            Log::error('AF PDF generation failed', [
                'exam_code' => $this->examCode,
                'attempt'   => $this->attempts(),
                'transient' => $this->isTransient($e),
                'error'     => $e->getMessage(),
            ]);
            // No rethrow — terminal state is on the row; the worker deletes the job.
        }
    }

    /**
     * Transient = a temporary upstream/infra hiccup worth retrying.
     * Permanent = will fail identically every time and must fail fast.
     */
    private function isTransient(\Throwable $e): bool
    {
        // Deterministic domain errors: exam not found (404), invalid data (422),
        // missing/unreadable CSV (local 500). Retrying changes nothing.
        if ($e instanceof \App\Exceptions\ExamProcessingException) {
            return false;
        }

        // Connection failure / timeout reaching the AI API.
        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return true;
        }

        // Typed Laravel HTTP client error (if a ->throw() path is reintroduced).
        if ($e instanceof \Illuminate\Http\Client\RequestException) {
            return ($e->response?->status() ?? 0) >= 500;
        }

        // AiRecommendationService wraps upstream HTTP errors as
        // "AI API error (HTTP {status}): ...". Historical rows also carry Laravel's
        // "HTTP request returned status code {status}". Retry only on 5xx.
        if (preg_match('/\(HTTP (\d{3})\)|status code (\d{3})/', $e->getMessage(), $m)) {
            $status = (int) ((($m[1] ?? '') !== '') ? $m[1] : ($m[2] ?? 0));
            return $status >= 500;
        }

        // Unknown cause → treat as permanent (fail fast, keep the diagnostic).
        return false;
    }
}
