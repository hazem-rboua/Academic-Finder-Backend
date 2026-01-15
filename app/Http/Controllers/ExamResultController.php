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
        description: "Poll this endpoint to check the status and progress of an exam processing job",
        tags: ["Exam Results"],
        parameters: [
            new OA\Parameter(
                name: "jobId",
                in: "path",
                required: true,
                description: "The unique job ID returned from the process endpoint",
                schema: new OA\Schema(type: "string", format: "uuid")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Job status retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "status", type: "string", enum: ["pending", "processing", "completed", "failed"], example: "processing"),
                                new OA\Property(property: "progress", type: "integer", example: 65),
                                new OA\Property(property: "current_step", type: "string", example: "Getting AI recommendations..."),
                                new OA\Property(property: "started_at", type: "string", format: "date-time", example: "2026-01-15T10:30:00Z"),
                                new OA\Property(
                                    property: "result",
                                    type: "object",
                                    description: "Only present when status is completed",
                                    nullable: true
                                ),
                                new OA\Property(property: "completed_at", type: "string", format: "date-time", nullable: true),
                                new OA\Property(property: "error_message", type: "string", nullable: true)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Job not found",
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

        $responseData = [
            'status' => $job->status,
            'progress' => $job->progress,
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
        ], 200);
    }
}

