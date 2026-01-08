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
                            properties: [
                                new OA\Property(property: "exam_code", type: "string", example: "EXAM123456"),
                                new OA\Property(property: "total_questions", type: "integer", example: 45),
                                new OA\Property(
                                    property: "reference_values",
                                    type: "array",
                                    items: new OA\Items(
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "reference", type: "string", example: "R17"),
                                            new OA\Property(property: "total_value", type: "integer", example: 3),
                                            new OA\Property(
                                                property: "titles",
                                                type: "object",
                                                additionalProperties: new OA\AdditionalProperties(
                                                    type: "object",
                                                    properties: [
                                                        new OA\Property(property: "title", type: "string"),
                                                        new OA\Property(property: "value", type: "integer"),
                                                        new OA\Property(property: "ones_count", type: "integer"),
                                                        new OA\Property(property: "total_questions", type: "integer")
                                                    ]
                                                )
                                            )
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: "job_compatibility",
                                    type: "array",
                                    items: new OA\Items(
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "reference", type: "string"),
                                            new OA\Property(property: "total_value", type: "integer"),
                                            new OA\Property(property: "titles", type: "object"),
                                            new OA\Property(property: "job_descriptions", type: "array", items: new OA\Items(type: "object"))
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

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }
}

