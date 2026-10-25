<?php

namespace App\Services;

use Carbon\CarbonInterface;
use RuntimeException;
use Spatie\Browsershot\Browsershot;

/**
 * The Academic Finder PDF, exactly as the Twindix API draws it (pdf.academic-report on
 * pdf.report-base, same fonts and page settings): every faculty the AI sends, with
 * major_1..3 and the full reasoning.
 *
 * Used by POST /api/generate-pdf (the Twindix API) and GenerateAcademicFinderPdfJob
 * (the HR "AF Report" flow), so both give the same report.
 */
class AcademicReportPdf
{
    /** @return string the PDF path */
    public function render(string $lang, array $faculties, string $userName, string $examCode, CarbonInterface $issuedAt, string $path): string
    {
        $html = view('pdf.academic-report', [
            'lang' => $lang,
            'fontFaces' => $this->fontFaces(),
            'userName' => trim($userName) ?: '—',
            'examCode' => $examCode,
            'issuedAt' => $issuedAt,
            'faculties' => array_values($faculties),
        ])->render();

        @mkdir(dirname($path), 0775, true);
        if (file_exists($path)) {
            @unlink($path);
        }

        $this->renderHtmlToPdf($html, $path);

        if (! file_exists($path)) {
            throw new RuntimeException('Browsershot finished but the PDF file is missing.');
        }

        return $path;
    }

    protected function renderHtmlToPdf(string $html, string $path): void
    {
        $bs = Browsershot::html($html);

        if ($node = config('services.browsershot.node_binary')) {
            $bs->setNodeBinary($node);
        }
        if ($npm = config('services.browsershot.npm_binary')) {
            $bs->setNpmBinary($npm);
        }
        if ($chrome = config('services.browsershot.chrome_path')) {
            $bs->setChromePath($chrome);
        }

        // posix_spawn is blocked on cPanel/CloudLinux; harmless elsewhere.
        putenv('SYMFONY_PROCESS_POSIX_SPAWN=0');
        // Chrome needs a writable home for its profile (as in Career-finder-bk). On the VPS the
        // web user's HOME is /var/www, which it cannot write.
        if (! is_writable((string) getenv('HOME'))) {
            putenv('HOME=/tmp');
        }

        $bs->noSandbox()
            ->showBackground()
            ->timeout(90)
            ->format('A4')
            ->margins(0, 0, 0, 0)
            ->addChromiumArguments([
                'disable-dev-shm-usage',
                'disable-gpu',
                'disable-crash-reporter',
                'no-zygote',
                'single-process',
                'disable-setuid-sandbox',
            ])
            ->savePdf($path);
    }

    /** Embedded subsetted woff2 faces (no host fonts / fontconfig needed). */
    private function fontFaces(): string
    {
        $faces = '';

        foreach ([
            ['TwindixLat', 400, 'latin-Regular.woff2'],
            ['TwindixLat', 700, 'latin-Bold.woff2'],
            ['TwindixAR', 400, 'arabic-Regular.woff2'],
            ['TwindixAR', 700, 'arabic-Bold.woff2'],
        ] as [$family, $weight, $file]) {
            $file = resource_path('fonts/twindix-pdf/' . $file);

            if (! is_readable($file)) {
                continue;
            }

            $faces .= "@font-face{font-family:'{$family}';font-weight:{$weight};font-display:block;"
                . "src:url('data:font/woff2;base64," . base64_encode((string) file_get_contents($file)) . "') format('woff2');}\n";
        }

        return $faces;
    }
}
