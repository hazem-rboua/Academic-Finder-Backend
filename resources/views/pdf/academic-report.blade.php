@php
    $ar  = ($lang ?? 'en') === 'ar';
    $dir = $ar ? 'rtl' : 'ltr';
    $fontFamily = $ar
        ? "'TwindixAR','TwindixLat','Noto Sans Arabic',Arial,sans-serif"
        : "'TwindixLat','TwindixAR',Arial,sans-serif";

    $t = fn(string $en, string $arText) => $ar ? $arText : $en;

    $labelReport    = $t('Recommendations Report', 'تقرير التوصيات');
    $labelCandidate = $t('Candidate', 'المرشح');
    $labelCode      = $t('Code', 'الرمز');
    $labelIssueDate = $t('Issue Date', 'تاريخ الإصدار');
    $labelSpec      = $t('Specializations', 'التخصصات');
    $disclaimer     = $t(
        'Note: This report is AI-generated based on your answers, with an average AI accuracy of 95%.',
        'تنويه: هذا التقرير مُولَّد بناءً على إجاباتك وبواسطة الذكاء الاصطناعي، وتعتمد دقّته على مدى صدق إجاباتك. متوسط دقة الذكاء الاصطناعي 95%.'
    );

    $formattedDate = $ar
        ? $issuedAt->locale('ar')->isoFormat('D MMMM YYYY')
        : $issuedAt->format('Y-m-d');

    $coverTitle = 'Academic Finder - ' . $userName . ' - ' . $examCode . ' - ' . $issuedAt->format('Y-m-d');

    // Every faculty the AI sends, with all its majors: major_1, major_2, major_3 (today's
    // answer) or a "majors" list (older answers).
    $items = collect($faculties)->map(function ($f) {
        $majors = collect($f)
            ->filter(fn ($value, $key) => preg_match('/^major_\d+$/', (string) $key) && is_string($value) && trim($value) !== '')
            ->sortKeys(SORT_NATURAL)
            ->values();
        if ($majors->isEmpty()) {
            $majors = collect($f['majors'] ?? [])->map(fn ($m) => is_array($m) ? ($m['major_name'] ?? '') : (string) $m)->filter();
        }
        $majors    = $majors->implode(' · ');
        $reasoning = $f['reasoning'] ?? '';
        if (empty($reasoning) && ! empty($f['majors'])) {
            $reasoning = collect($f['majors'])->pluck('reasoning')->filter()->implode(' ');
        }
        return ['name' => $f['faculty'] ?? '', 'sub_items' => $majors, 'reasoning' => $reasoning];
    })->all();
@endphp
@include('pdf.report-base')
