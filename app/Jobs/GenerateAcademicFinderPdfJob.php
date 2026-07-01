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

    public function __construct(public string $examCode, public ?string $lang = null, public ?string $env = null) {}

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

        $lang = in_array($this->lang, ['ar', 'en']) ? $this->lang : (in_array($job->lang, ['ar', 'en']) ? $job->lang : 'en');
        $isTest = $this->env === 'testing';
        $pdfPath = storage_path('app/reports/' . $this->examCode . '-' . $lang . ($isTest ? '.test' : '') . '.pdf');

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
            $examResultService->externalConnection = ($this->env === 'testing') ? 'external_api_test' : 'external_api';
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

            // Render the cover and the content as SEPARATE one-purpose PDFs, then merge them
            // with Ghostscript. WHY: Chrome's printToPDF leaves a ~74px unprintable bottom
            // strip on normal flow content, so a single-render full-bleed cover bleeds onto
            // page 2. Rendering the cover on its own (a position:fixed background fills the
            // whole physical page, and there is no page 2 to bleed onto) and the white content
            // separately, then concatenating, yields a fully-blue cover + clean white content.
            @mkdir(dirname($pdfPath), 0775, true);
            $base       = dirname($pdfPath) . '/' . $this->examCode . '-' . $lang . ($isTest ? '.test' : '');
            $coverPdf   = $base . '.cover.pdf';
            $contentPdf = $base . '.content.pdf';

            $viewData = [
                'code'        => $this->examCode,
                'userName'    => $job->user_name ?: '—',
                'lang'        => $lang,
                'jobs'        => $jobs,
                'logoDataUri' => $this->logoDataUri(),
                'reportTitle' => $job->reportName(),
            ];

            $this->renderHtmlToPdf(view('pdf.academic-finder-report', $viewData + ['part' => 'cover'])->render(),   $coverPdf);
            $this->renderHtmlToPdf(view('pdf.academic-finder-report', $viewData + ['part' => 'content'])->render(), $contentPdf);

            // Merge with Ghostscript (present on the host; no PHP PDF library required).
            // Use Symfony Process (proc_open) — some shared hosts disable exec().
            $gs = config('services.ghostscript.bin', 'gs');
            $process = new \Symfony\Component\Process\Process([
                $gs, '-dBATCH', '-dNOPAUSE', '-q', '-sDEVICE=pdfwrite', '-dAutoRotatePages=/None',
                '-sOutputFile=' . $pdfPath, $coverPdf, $contentPdf,
            ]);
            $process->setTimeout(60);
            $process->run();

            if (!$process->isSuccessful() || !file_exists($pdfPath)) {
                throw new \RuntimeException('Ghostscript merge failed: ' . $process->getErrorOutput() . ' ' . $process->getOutput());
            }

            @unlink($coverPdf);
            @unlink($contentPdf);

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

    /**
     * Render a single HTML string to a PDF file via Browsershot/Chrome
     * (same hardened config used for the shared-hosting Chrome).
     */
    private function renderHtmlToPdf(string $html, string $path): void
    {
        $bs = Browsershot::html($html);

        if ($node   = config('services.browsershot.node_binary'))  { $bs->setNodeBinary($node); }
        if ($npm    = config('services.browsershot.npm_binary'))    { $bs->setNpmBinary($npm); }
        if ($chrome = config('services.browsershot.chrome_path'))   { $bs->setChromePath($chrome); }

        // Force Symfony Process to use fork/exec instead of posix_spawn (blocked on cPanel/CloudLinux)
        putenv('SYMFONY_PROCESS_POSIX_SPAWN=0');

        $bs->noSandbox()
           ->showBackground()
           ->waitUntilNetworkIdle()
           ->timeout(60)
           ->format('A4')
           ->margins(0, 0, 0, 0)
           ->addChromiumArguments([
               // Browsershot prepends "--" itself; keep these WITHOUT it (see commit 53a7b48).
               'disable-dev-shm-usage',
               'disable-gpu',
               'no-zygote',
               'single-process',
               'disable-setuid-sandbox',
           ])
           ->savePdf($path);
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
