<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAcademicFinderPdfJob;
use App\Models\ExamProcessingJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AcademicFinderPdfController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $request->validate(['exam_code' => 'required|string']);

        $examCode = $request->input('exam_code');
        $userName = $request->input('user_name');
        $lang     = in_array($request->input('lang'), ['ar', 'en'])
            ? $request->input('lang')
            : 'en';

        // Already done
        $done = ExamProcessingJob::where('exam_code', $examCode)
            ->where('pdf_ready', true)
            ->latest()
            ->first();

        if ($done && file_exists(storage_path('app/reports/' . $examCode . '.pdf'))) {
            return response()->json([
                'success' => true,
                'data'    => ['exam_code' => $examCode, 'status' => 'completed'],
            ]);
        }

        // Find active tracking record or create one
        $job = ExamProcessingJob::where('exam_code', $examCode)
            ->whereIn('status', ['pending', 'processing'])
            ->latest()
            ->first();

        if (!$job) {
            $job = ExamProcessingJob::create([
                'job_id'    => Str::uuid()->toString(),
                'exam_code' => $examCode,
                'status'    => 'pending',
                'progress'  => 0,
                'user_name' => $userName,
                'lang'      => $lang,
            ]);
        } else {
            // Update meta if provided
            $job->update(array_filter([
                'user_name' => $userName,
                'lang'      => $lang,
            ], fn ($v) => $v !== null));
        }

        GenerateAcademicFinderPdfJob::dispatch($examCode);

        return response()->json([
            'success' => true,
            'data'    => ['exam_code' => $examCode, 'status' => 'processing'],
        ], 202);
    }

    public function reportStatus(Request $request): JsonResponse
    {
        $examCode = $request->query('exam_code');

        if (!$examCode) {
            return response()->json(['success' => false, 'message' => 'exam_code is required'], 422);
        }

        $job = ExamProcessingJob::where('exam_code', $examCode)->latest()->first();

        if (!$job) {
            return response()->json([
                'success' => true,
                'data'    => ['exam_code' => $examCode, 'ready' => false, 'status' => 'not_started'],
            ]);
        }

        if ($job->pdf_ready) {
            $status = 'completed';
        } elseif ($job->status === 'failed') {
            $status = 'failed';
        } else {
            $status = 'processing';
        }

        return response()->json([
            'success' => true,
            'data'    => ['exam_code' => $examCode, 'ready' => (bool) $job->pdf_ready, 'status' => $status],
        ]);
    }

    public function reportPdf(Request $request): mixed
    {
        $examCode = $request->query('exam_code');

        if (!$examCode) {
            return response()->json(['success' => false, 'message' => 'exam_code is required'], 422);
        }

        $job = ExamProcessingJob::where('exam_code', $examCode)
            ->where('pdf_ready', true)
            ->latest()
            ->first();

        if (!$job || !$job->pdf_path) {
            return response()->json(['success' => false, 'message' => 'PDF not ready yet'], 404);
        }

        $fullPath = $job->pdf_path;   // stored as absolute path

        if (!file_exists($fullPath)) {
            return response()->json(['success' => false, 'message' => 'PDF file not found on disk'], 404);
        }

        $filename = 'academic-finder-report-' . $examCode . '.pdf';

        return response()->download($fullPath, $filename, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
