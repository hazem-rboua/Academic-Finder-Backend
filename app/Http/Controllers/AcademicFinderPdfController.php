<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAcademicFinderPdfJob;
use App\Models\ExamProcessingJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AcademicFinderPdfController extends Controller
{
    // ADDITIVE: resolve environment. Explicit ?env= wins; else infer from
    // Origin/Referer (front/back.twindix.com => testing). Default production.
    private function resolveEnv(Request $request): string
    {
        $e = $request->input('env') ?? $request->query('env');
        if (in_array($e, ['testing', 'production'], true)) {
            return $e;
        }
        $origin = (string) ($request->header('origin') . ' ' . $request->header('referer'));
        if (str_contains($origin, 'front.twindix.com') || str_contains($origin, 'back.twindix.com')) {
            return 'testing';
        }
        return 'production';
    }

    private function pdfPath(string $examCode, string $lang, string $env): string
    {
        $suffix = $env === 'testing' ? '.test' : '';
        return storage_path('app/reports/' . $examCode . '-' . $lang . $suffix . '.pdf');
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate(['exam_code' => 'required|string']);

        $examCode = $request->input('exam_code');
        $userName = $request->input('user_name');
        $lang     = in_array($request->input('lang'), ['ar', 'en'])
            ? $request->input('lang')
            : 'en';
        $env = $this->resolveEnv($request);

        $done = ExamProcessingJob::where('exam_code', $examCode)
            ->where('pdf_ready', true)
            ->latest()
            ->first();

        if ($done && file_exists($this->pdfPath($examCode, $lang, $env))) {
            return response()->json([
                'success' => true,
                'data'    => ['exam_code' => $examCode, 'env' => $env, 'status' => 'completed'],
            ]);
        }

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
            $job->update(array_filter([
                'user_name' => $userName,
                'lang'      => $lang,
            ], fn ($v) => $v !== null));
        }

        GenerateAcademicFinderPdfJob::dispatch($examCode, $lang, $env);

        return response()->json([
            'success' => true,
            'data'    => ['exam_code' => $examCode, 'env' => $env, 'status' => 'processing'],
        ], 202);
    }

    public function reportStatus(Request $request): JsonResponse
    {
        $examCode = $request->query('exam_code');

        if (!$examCode) {
            return response()->json(['success' => false, 'message' => 'exam_code is required'], 422);
        }

        $lang = in_array($request->query('lang'), ['ar', 'en']) ? $request->query('lang') : 'en';
        $env  = $this->resolveEnv($request);
        $fileReady = file_exists($this->pdfPath($examCode, $lang, $env));

        $job = ExamProcessingJob::where('exam_code', $examCode)->latest()->first();

        // ADDITIVE: trust the job's actually-saved file (pdf_ready/pdf_path) even when the
        // request's lang/env differ from what generation used (e.g. the frontend polls
        // report-status without the lang it passed to generate). Prevents a stuck "processing".
        if (!$fileReady && $job && $job->pdf_ready && $job->pdf_path && file_exists($job->pdf_path)) {
            $fileReady = true;
        }

        if (!$fileReady && !$job) {
            return response()->json([
                'success' => true,
                'data'    => ['exam_code' => $examCode, 'lang' => $lang, 'env' => $env, 'ready' => false, 'status' => 'not_started'],
            ]);
        }

        if ($fileReady) {
            $status = 'completed';
        } elseif ($job && $job->status === 'failed') {
            $status = 'failed';
        } else {
            $status = 'processing';
        }

        return response()->json([
            'success' => true,
            'data'    => ['exam_code' => $examCode, 'lang' => $lang, 'env' => $env, 'ready' => $fileReady, 'status' => $status],
        ]);
    }

    public function reportPdf(Request $request): mixed
    {
        $examCode = $request->query('exam_code');

        if (!$examCode) {
            return response()->json(['success' => false, 'message' => 'exam_code is required'], 422);
        }

        $lang = in_array($request->query('lang'), ['ar', 'en']) ? $request->query('lang') : 'en';
        $env  = $this->resolveEnv($request);
        $job  = ExamProcessingJob::where('exam_code', $examCode)->latest()->first();

        $fullPath = $this->pdfPath($examCode, $lang, $env);

        // ADDITIVE: fall back to the job's actually-saved file if the path computed from the
        // request's lang/env doesn't exist (frontend may omit/mismatch lang on download).
        if (!file_exists($fullPath) && $job && $job->pdf_ready && $job->pdf_path && file_exists($job->pdf_path)) {
            $fullPath = $job->pdf_path;
        }

        if (!file_exists($fullPath)) {
            return response()->json(['success' => false, 'message' => 'PDF not ready yet'], 404);
        }

        // Use the report name ("Academic Finder - First Second - code - date.pdf"),
        // falling back to the legacy name if no tracking record exists.
        $filename = $job
            ? $job->reportFileName()
            : 'academic-finder-report-' . $examCode . '-' . $lang . '.pdf';

        return response()->download($fullPath, $filename, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
