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
use Illuminate\Support\Facades\App;
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
            // Ensure translations match the locale used to start the job
            App::setLocale($this->locale);

            // Step 1: Mark as processing (0%)
            $job->markAsProcessing();
            $job->updateProgress(0, __('messages.exam_processing_step_starting'));
            
            Log::info('Processing exam job', [
                'job_id' => $this->jobId,
                'exam_code' => $this->examCode,
                'locale' => $this->locale,
            ]);

            // Step 2: Validate exam exists (5%)
            $job->updateProgress(5, __('messages.exam_processing_step_validating_exam'));
            $examEnrollment = $examResultService->validateAndGetExam($this->examCode);

            // Step 3: Parse exam answers (10%)
            $job->updateProgress(10, __('messages.exam_processing_step_parsing_answers'));
            $answers = $examResultService->parseExamAnswers($examEnrollment);

            // Step 4: Load CSV mapping and calculate (15%)
            $job->updateProgress(15, __('messages.exam_processing_step_loading_calculation_data'));
            $csvData = $examResultService->loadCsvMapping();
            
            // Step 5: Calculate branches and environment (20%)
            $job->updateProgress(20, __('messages.exam_processing_step_calculating_compatibility'));
            $examResults = $examResultService->processExamData($answers, $examEnrollment, $csvData);

            // Step 6: Call AI API (25% -> 90%)
            $job->updateProgress(25, __('messages.exam_processing_step_getting_ai_recommendations'));
            
            $startTime = microtime(true);
            $aiRecommendations = $aiRecommendationService->getRecommendations($examResults, $this->locale);
            $duration = microtime(true) - $startTime;
            
            // Simulate progress during AI call
            // The AI call is synchronous, so we can't update in real-time
            // But we set it to 90% after completion
            $job->updateProgress(90, __('messages.exam_processing_step_processing_ai_response'));

            Log::info('AI recommendations completed', [
                'job_id' => $this->jobId,
                'duration' => round($duration, 2),
            ]);

            // Step 7: Prepare final result (95%)
            $job->updateProgress(95, __('messages.exam_processing_step_finalizing_results'));

            // Step 8: Mark as completed (100%)
            $job->markAsCompleted($aiRecommendations);
            
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
