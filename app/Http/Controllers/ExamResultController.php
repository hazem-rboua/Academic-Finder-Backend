<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcessExamRequest;
use App\Services\ExamResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
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
        summary: "Process exam results and calculate job compatibility",
        description: "Takes an exam code, reads answers from external database, processes them using the Academic Finder algorithm, and returns job compatibility results",
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
                response: 200,
                description: "Exam results processed successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Exam results processed successfully"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            description: "AI API response if available, otherwise exam results with selected_branches and environment_status",
                            properties: [
                                new OA\Property(property: "job_title", type: "string", example: "Airport Operations Director", nullable: true),
                                new OA\Property(property: "industry", type: "string", example: "Aviation", nullable: true),
                                new OA\Property(property: "seniority", type: "string", example: "Senior Management", nullable: true),
                                new OA\Property(
                                    property: "selected_branches",
                                    type: "array",
                                    items: new OA\Items(
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "job_type", type: "string", example: "Open Thinking Jobs"),
                                            new OA\Property(
                                                property: "chosen_competencies",
                                                type: "array",
                                                items: new OA\Items(type: "integer", example: 0),
                                                example: [0, 0, 0, 0, 0]
                                            )
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: "environment_status",
                                    type: "array",
                                    items: new OA\Items(
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "question", type: "integer", example: 1),
                                            new OA\Property(property: "selected_option", type: "integer", example: 1)
                                        ]
                                    )
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Exam not found",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Exam not found")
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
                        new OA\Property(property: "message", type: "string", example: "An error occurred while processing exam results")
                    ]
                )
            )
        ]
    )]
    public function process(ProcessExamRequest $request): JsonResponse
    {
        try {
            $examCode = $request->input('exam_code');
            
            $results = $this->examResultService->processExamResults($examCode);

            return response()->json([
                'success' => true,
                'message' => __('messages.exam_processed_successfully'),
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error processing exam results: ' . $e->getMessage(), [
                'exam_code' => $request->input('exam_code'),
                'trace' => $e->getTraceAsString(),
            ]);

            // Ensure we have a valid HTTP status code
            $statusCode = is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600 
                ? $e->getCode() 
                : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}

