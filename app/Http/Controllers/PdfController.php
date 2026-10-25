<?php

namespace App\Http\Controllers;

use App\Services\AcademicReportPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * POST /api/generate-pdf — the Academic Finder PDF for the Twindix API.
 *
 * Same request as Career-finder-bk's generate-pdf (which now draws Career reports only):
 *   {type: academic, lang, exam_code, user_name, issued_at, faculties: [...]}
 * Returns {success, path, url}; the Twindix API downloads the file from url.
 */
class PdfController extends Controller
{
    public function generate(Request $request, AcademicReportPdf $pdf): JsonResponse
    {
        $request->validate([
            'type'      => 'nullable|string|in:academic',
            'lang'      => 'required|string|in:en,ar',
            'exam_code' => 'required|string',
            'user_name' => 'required|string',
            'issued_at' => 'required|string',
            'faculties' => 'required|array',
        ]);

        $lang     = $request->input('lang');
        $examCode = (string) $request->input('exam_code');
        $filename = 'academic-' . preg_replace('/[^A-Za-z0-9_-]/', '', $examCode) . '-' . $lang . '-' . Str::random(8) . '.pdf';
        // Private storage, not public/: only GET /api/reports/{file} (trusted) hands it out.
        $path     = storage_path('app/generated/' . $filename);

        try {
            $pdf->render(
                $lang,
                $request->input('faculties', []),
                (string) $request->input('user_name'),
                $examCode,
                \Carbon\Carbon::parse($request->input('issued_at')),
                $path
            );
        } catch (\Throwable $e) {
            Log::error('[PdfController] Academic PDF failed', ['exam_code' => $examCode, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'PDF generation failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'path'    => $path,
            'url'     => url('api/reports/' . $filename),
        ]);
    }

    /** GET /api/reports/{file} — one generated PDF, deleted after it is sent. */
    public function download(string $file)
    {
        $path = storage_path('app/generated/' . basename($file));
        if (! is_file($path)) {
            return response()->json(['success' => false, 'message' => 'Report not found.'], 404);
        }

        return response()->download($path, $file, ['Content-Type' => 'application/pdf'])->deleteFileAfterSend(true);
    }
}
