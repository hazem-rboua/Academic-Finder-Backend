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

            $job->markAsCompleted($aiResult);

            // Map AI response keys to template keys
            // AI returns: {faculty, major_1, major_2, major_3, reasoning}
            $rawJobs = $aiResult['recommended_jobs'] ?? [];
            $jobs = array_map(function (array $item) use ($job): array {
                $lang      = $job->lang ?? 'en';
                $majors    = array_filter([
                    $item['major_1'] ?? null,
                    $item['major_2'] ?? null,
                    $item['major_3'] ?? null,
                ]);
                $majorsStr = implode(' · ', $majors);
                $prefix    = $lang === 'ar'
                    ? ($majorsStr ? "التخصصات: {$majorsStr}\n\n" : '')
                    : ($majorsStr ? "Majors: {$majorsStr}\n\n" : '');

                return [
                    'jobTitle'      => $item['faculty'] ?? '',
                    'justification' => $prefix . ($item['reasoning'] ?? ''),
                ];
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

            Browsershot::html($html)
                ->setNodeBinary('/opt/alt/alt-nodejs20/root/usr/bin/node')
                ->setNpmBinary('/opt/alt/alt-nodejs20/root/usr/bin/npm')
                ->setChromePath('/home/twindix/.cache/puppeteer/chrome/linux-148.0.7778.97/chrome-linux64/chrome')
                ->noSandbox()
                ->showBackground()
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
