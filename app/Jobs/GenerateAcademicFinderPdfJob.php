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
use Spatie\Browsershot\Browsershot;

class GenerateAcademicFinderPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180;
    public $tries   = 1;

    public function __construct(public string $examCode) {}

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

        $pdfPath = storage_path('app/reports/' . $this->examCode . '.pdf');

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
            $aiResult = $examResultService->processExamResults($this->examCode);

            Log::info('AF AI result shape', [
                'top_keys'   => array_keys($aiResult ?? []),
                'jobs_count' => count($aiResult['recommended_jobs'] ?? $aiResult['recommendedJobs'] ?? []),
            ]);

            $job->markAsCompleted($aiResult);

            // Defensive mapping — handles both snake_case and camelCase AI response shapes
            $rawJobs = $aiResult['recommended_jobs'] ?? $aiResult['recommendedJobs'] ?? [];
            $jobs = array_map(function (array $item) use ($job): array {
                $lang  = $job->lang ?? 'en';
                $title = $item['jobTitle'] ?? $item['faculty'] ?? $item['job_title'] ?? '';
                if (!empty($item['justification'])) {
                    $body = $item['justification'];
                } else {
                    $majors    = array_filter([
                        $item['major_1'] ?? null,
                        $item['major_2'] ?? null,
                        $item['major_3'] ?? null,
                    ]);
                    $majorsStr = implode(' · ', $majors);
                    $prefix    = $majorsStr
                        ? ($lang === 'ar' ? "التخصصات: {$majorsStr}\n\n" : "Majors: {$majorsStr}\n\n")
                        : '';
                    $body = $prefix . ($item['reasoning'] ?? '');
                }
                return ['jobTitle' => $title, 'justification' => $body];
            }, $rawJobs);

            // Render Blade → HTML → PDF
            $html = view('pdf.academic-finder-report', [
                'code'        => $this->examCode,
                'userName'    => $job->user_name ?: '—',
                'lang'        => in_array($job->lang, ['ar', 'en']) ? $job->lang : 'en',
                'jobs'        => $jobs,
                'logoDataUri' => $this->logoDataUri(),
            ])->render();

            @mkdir(dirname($pdfPath), 0775, true);

            $bs = Browsershot::html($html);

            if ($node   = config('services.browsershot.node_binary'))  { $bs->setNodeBinary($node); }
            if ($npm    = config('services.browsershot.npm_binary'))    { $bs->setNpmBinary($npm); }
            if ($chrome = config('services.browsershot.chrome_path'))   { $bs->setChromePath($chrome); }

            $bs->noSandbox()
               ->showBackground()
               ->waitUntilNetworkIdle()
               ->timeout(60)
               ->format('A4')
               ->margins(0, 0, 0, 0)
               ->savePdf($pdfPath);

            $job->update([
                'pdf_ready' => true,
                'pdf_path'  => $pdfPath,
            ]);

            Log::info('AF PDF generated', ['exam_code' => $this->examCode, 'path' => $pdfPath]);

        } catch (\Exception $e) {
            $job->markAsFailed($e->getMessage());
            Log::error('AF PDF generation failed', [
                'exam_code' => $this->examCode,
                'error'     => $e->getMessage(),
            ]);
            // No rethrow — queue stays clean, status shows failed via DB
        }
    }

    private function logoDataUri(): string
    {
        $path = public_path('report-logo.png');
        if (!file_exists($path)) {
            return '';
        }
        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }
}
