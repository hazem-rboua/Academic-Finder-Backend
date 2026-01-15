<?php

namespace App\Jobs;

use App\Models\ExamProcessingJob;
use App\Services\AiRecommendationService;
use App\Services\ExamResultService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessExamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 90;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $jobId,
        public string $examCode,
        public string $locale
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ExamResultService $examResultService, AiRecommendationService $aiRecommendationService): void
    {
        $job = ExamProcessingJob::where('job_id', $this->jobId)->first();
        
        if (!$job) {
            Log::error('Job not found', ['job_id' => $this->jobId]);
            return;
        }

        try {
            // Step 1: Mark as processing (0%)
            $job->markAsProcessing();
            $job->updateProgress(0, 'Starting exam processing...');
            
            Log::info('Processing exam job', [
                'job_id' => $this->jobId,
                'exam_code' => $this->examCode,
                'locale' => $this->locale,
            ]);

            // Step 2: Validate exam exists (5%)
            $job->updateProgress(5, 'Validating exam...');
            $examEnrollment = $examResultService->validateAndGetExam($this->examCode);

            // Step 3: Parse exam answers (10%)
            $job->updateProgress(10, 'Parsing exam answers...');
            $answers = $examResultService->parseExamAnswers($examEnrollment);

            // Step 4: Load CSV mapping and calculate (15%)
            $job->updateProgress(15, 'Loading calculation data...');
            $csvData = $examResultService->loadCsvMapping();
            
            // Step 5: Calculate branches and environment (20%)
            $job->updateProgress(20, 'Calculating job compatibility...');
            $examResults = $examResultService->processExamData($answers, $examEnrollment, $csvData);

            // Step 6: Call AI API (25% -> 90%)
            $job->updateProgress(25, 'Getting AI recommendations...');
            
            $startTime = microtime(true);
            $aiRecommendations = $aiRecommendationService->getRecommendations($examResults, $this->locale);
            $duration = microtime(true) - $startTime;
            
            // Simulate progress during AI call
            // The AI call is synchronous, so we can't update in real-time
            // But we set it to 90% after completion
            $job->updateProgress(90, 'Processing AI response...');

            Log::info('AI recommendations completed', [
                'job_id' => $this->jobId,
                'duration' => round($duration, 2),
                'has_recommendations' => $aiRecommendations !== null,
            ]);

            // Step 7: Prepare final result (95%)
            $job->updateProgress(95, 'Finalizing results...');
            
            $finalResult = $aiRecommendations ?? $examResults;

            // Step 8: Mark as completed (100%)
            $job->markAsCompleted($finalResult);
            
            Log::info('Exam processing job completed', [
                'job_id' => $this->jobId,
                'exam_code' => $this->examCode,
            ]);

        } catch (Exception $e) {
            Log::error('Exam processing job failed', [
                'job_id' => $this->jobId,
                'exam_code' => $this->examCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $job->markAsFailed($e->getMessage());
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        $job = ExamProcessingJob::where('job_id', $this->jobId)->first();
        
        if ($job) {
            $job->markAsFailed($exception->getMessage());
        }

        Log::error('Exam processing job permanently failed', [
            'job_id' => $this->jobId,
            'exam_code' => $this->examCode,
            'error' => $exception->getMessage(),
        ]);
    }
}
