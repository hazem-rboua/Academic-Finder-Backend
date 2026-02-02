<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcessExamRequest;
use App\Jobs\ProcessExamJob;
use App\Models\ExamProcessingJob;
use App\Services\ExamResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class ExamResultController extends Controller
{
    protected ExamResultService $examResultService;

    /**
     * Compute a UI-friendly progress value when AI step has no real progress.
     * This is only an estimate and should not be treated as ground truth.
     */
    private function computeDisplayProgress(ExamProcessingJob $job): array
    {
        $progress = (int) $job->progress;
        $display = $progress;
        $isEstimated = false;

        // While waiting on AI we sit at 25%. We can only estimate based on elapsed time.
        if ($job->status === 'processing' && $progress === 25) {
            // Use a stable timestamp; updated_at may not be reliable for elapsed calculations.
            // We approximate AI-wait start as job start time.
            $start = $job->started_at ?? $job->created_at;
            $elapsedSeconds = $start ? now()->diffInSeconds($start) : 0;

            $min = 25;
            $max = 90;
            $duration = 35; // seconds (AI typically 30–40s)

            $ratio = $duration > 0 ? min(1, $elapsedSeconds / $duration) : 1;
            $display = (int) floor($min + ($max - $min) * $ratio);
            $display = max($min, min($max, $display));
            // Mark estimated whenever we're in the AI-wait window, even if display==25 at t=0.
            $isEstimated = true;
        }

        return [$display, $isEstimated];
    }

    public function __construct(ExamResultService $examResultService)
    {
        $this->examResultService = $examResultService;
    }

    #[OA\Post(
        path: "/api/exam-results/process",
        summary: "Start asynchronous exam processing",
        description: "Dispatches an exam processing job to the queue and returns a job ID for status polling. The job processes exam answers using the Academic Finder algorithm and gets AI recommendations.",
        tags: ["Exam Results"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["exam_code"],
                properties: [
                    new OA\Property(
                        property: "exam_code",
                        type: "string",
                        description: "The unique exam code to process",
                        example: "EXAM123456"
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: "Exam processing started successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Exam processing started"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "job_id", type: "string", format: "uuid", example: "550e8400-e29b-41d4-a716-446655440000"),
                                new OA\Property(property: "status_url", type: "string", example: "/api/exam-results/status/550e8400-e29b-41d4-a716-446655440000"),
                                new OA\Property(property: "estimated_time_seconds", type: "integer", example: 40)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validation error",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "The exam code field is required."),
                        new OA\Property(
                            property: "errors",
                            type: "object",
                            additionalProperties: new OA\AdditionalProperties(
                                type: "array",
                                items: new OA\Items(type: "string")
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: "Server error",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "An error occurred while starting exam processing")
                    ]
                )
            )
        ]
    )]
    public function process(ProcessExamRequest $request): JsonResponse
    {
        try {
            $examCode = $request->input('exam_code');
            $locale = App::getLocale();
            
            // Generate unique job ID
            $jobId = Str::uuid()->toString();
            
            // Create job record
            $job = ExamProcessingJob::create([
                'job_id' => $jobId,
                'exam_code' => $examCode,
                'status' => 'pending',
                'progress' => 0,
            ]);

            // Dispatch job to queue
            ProcessExamJob::dispatch($jobId, $examCode, $locale);

            Log::info('Exam processing job dispatched', [
                'job_id' => $jobId,
                'exam_code' => $examCode,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.exam_processing_started'),
                'data' => [
                    'job_id' => $jobId,
                    'status_url' => url("/api/exam-results/status/{$jobId}"),
                    'estimated_time_seconds' => 40,
                ],
            ], 202);

        } catch (\Exception $e) {
            Log::error('Error dispatching exam processing job: ' . $e->getMessage(), [
                'exam_code' => $request->input('exam_code'),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('messages.exam_processing_failed'),
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/exam-results/status/{jobId}",
        summary: "Get exam processing job status",
        description: "Poll this endpoint every 2 seconds to check the status and progress of an exam processing job. Response varies based on job status: pending (waiting), processing (in progress), completed (finished with results), or failed (error occurred).",
        tags: ["Exam Results"],
        parameters: [
            new OA\Parameter(
                name: "jobId",
                in: "path",
                required: true,
                description: "The unique job ID returned from the process endpoint",
                schema: new OA\Schema(type: "string", format: "uuid", example: "550e8400-e29b-41d4-a716-446655440000")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Job status retrieved successfully. Response structure varies by status.",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", description: "True for pending/processing/completed, false for failed"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "status", type: "string", enum: ["pending", "processing", "completed", "failed"], description: "Current job status"),
                                new OA\Property(property: "progress", type: "integer", description: "Progress percentage (0-100)", example: 65),
                                new OA\Property(property: "display_progress", type: "integer", description: "UI-friendly progress. During AI processing, this may be time-based (estimated) while progress stays at 25%. Range 0-100.", example: 72),
                                new OA\Property(property: "display_progress_is_estimated", type: "boolean", description: "True when display_progress is estimated (time-based) rather than a real milestone.", example: true),
                                new OA\Property(property: "current_step", type: "string", nullable: true, description: "Human-readable current step", example: "Getting AI recommendations..."),
                                new OA\Property(property: "started_at", type: "string", format: "date-time", nullable: true, description: "When job started processing", example: "2026-01-15T10:30:00+00:00"),
                                new OA\Property(
                                    property: "result",
                                    type: "object",
                                    description: "Only present when status=completed. Contains AI recommendations and exam results.",
                                    nullable: true,
                                    properties: [
                                        new OA\Property(property: "job_title", type: "string", example: "Airport Operations Director"),
                                        new OA\Property(property: "industry", type: "string", example: "Aviation"),
                                        new OA\Property(property: "seniority", type: "string", example: "Senior Management"),
                                        new OA\Property(property: "selected_branches", type: "array", items: new OA\Items(type: "object")),
                                        new OA\Property(property: "environment_status", type: "array", items: new OA\Items(type: "object"))
                                    ]
                                ),
                                new OA\Property(property: "error_message", type: "string", nullable: true, description: "Only present when status=failed. Contains error details.", example: "AI API error (HTTP 500): Internal server error"),
                                new OA\Property(property: "completed_at", type: "string", format: "date-time", nullable: true, description: "When job completed (success or failure)", example: "2026-01-15T10:30:38+00:00")
                            ]
                        )
                    ],
                    example: [
                        "success" => true,
                        "data" => [
                            "status" => "processing",
                            "progress" => 65,
                            "display_progress" => 72,
                            "display_progress_is_estimated" => true,
                            "current_step" => "Getting AI recommendations...",
                            "started_at" => "2026-01-15T10:30:00+00:00"
                        ]
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Job not found - Invalid job ID",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Job not found")
                    ]
                )
            )
        ]
    )]
    public function status(string $jobId): JsonResponse
    {
        $job = ExamProcessingJob::where('job_id', $jobId)->first();

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => __('messages.job_not_found'),
            ], 404);
        }

        [$displayProgress, $displayProgressIsEstimated] = $this->computeDisplayProgress($job);

        $responseData = [
            'status' => $job->status,
            'progress' => $job->progress,
            'display_progress' => $displayProgress,
            'display_progress_is_estimated' => $displayProgressIsEstimated,
            'current_step' => $job->current_step,
            'started_at' => $job->started_at?->toIso8601String(),
        ];

        if ($job->status === 'completed') {
            $responseData['result'] = $job->result;
            $responseData['completed_at'] = $job->completed_at?->toIso8601String();
        } elseif ($job->status === 'failed') {
            $responseData['error_message'] = $job->error_message;
            $responseData['completed_at'] = $job->completed_at?->toIso8601String();
        }

        return response()->json([
            'success' => $job->status !== 'failed',
            'data' => $responseData,
        ], 200)
            // Prevent browsers/CDNs from caching status responses
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}

