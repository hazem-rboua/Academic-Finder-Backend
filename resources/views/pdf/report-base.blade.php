{{--
    Shared PDF layout for Academic Finder & Career Finder reports.
    Required vars: $lang, $fontFaces, $coverTitle, $labelReport, $labelSpec,
                   $labelCandidate, $labelCode, $labelIssueDate, $disclaimer,
                   $userName, $examCode, $formattedDate, $ar, $dir, $fontFamily,
                   $items  — array of ['name'=>'', 'sub_items'=>'A · B', 'reasoning'=>'']
--}}
<!DOCTYPE html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $dir }}">
<head>
<meta charset="UTF-8">
<title>{{ $coverTitle }}</title>
<style>
    {!! $fontFaces !!}

    @page { size: A4; margin: 0; }
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: {!! $fontFamily !!};
        font-size: 12px;
        line-height: 1.7;
        color: #1e2a40;
        direction: {{ $dir }};
    }

    /* ── COVER ───────────────────────────────────────────────── */
    .cover {
        width: 100%; height: 297mm; background: #1B2EB8;
        display: flex; flex-direction: column;
        page-break-after: always;
    }
    .cover-top {
        display: flex; justify-content: space-between; align-items: flex-start;
        padding: 32px 40px 0;
    }
    .cover-label { color: rgba(255,255,255,0.85); font-size: 11px; letter-spacing: 0.5px; }
    .logo-text { text-align: {{ $ar ? 'left' : 'right' }}; }
    .logo-text .logo-main { font-size: 20px; font-weight: 700; color: #fff; letter-spacing: 2px; }
    .logo-text .logo-sub { font-size: 9px; color: rgba(255,255,255,0.75); letter-spacing: 3px; display: block; margin-top: 1px; }

    .cover-body { flex: 1; display: flex; flex-direction: column; justify-content: flex-start; padding: 40px 60px 60px; }
    .cover-company { font-size: 11px; color: rgba(255,255,255,0.70); letter-spacing: 6px; margin-bottom: 18px; display: flex; align-items: center; gap: 12px; }
    .cover-company::after { content: ''; display: inline-block; width: 50px; height: 1px; background: rgba(255,255,255,0.50); }
    .cover-title { font-size: 34px; font-weight: 800; color: #fff; line-height: 1.25; margin-bottom: 20px; }
    .cover-gold-line { width: 60px; height: 4px; background: #C9A22A; margin-bottom: 40px; {{ $ar ? 'margin-right:auto;' : '' }} }

    .cover-disclaimer { padding: 0 60px 24px; font-size: 10px; color: rgba(255,255,255,0.80); text-align: {{ $ar ? 'right' : 'left' }}; line-height: 1.6; }

    .cover-footer { border-top: 1px solid rgba(255,255,255,0.20); display: flex; flex-direction: {{ $ar ? 'row-reverse' : 'row' }}; justify-content: space-between; padding: 18px 60px; }
    .cover-footer-item { text-align: center; }
    .cover-footer-item .footer-label { font-size: 9px; color: rgba(255,255,255,0.55); letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
    .cover-footer-item .footer-value { font-size: 13px; font-weight: 700; color: #fff; }

    /* ── CONTENT ─────────────────────────────────────────────── */
    .content-page { padding: 0 0 40px; min-height: 297mm; page-break-after: always; }
    .content-page:last-child { page-break-after: auto; }

    .page-header { display: flex; flex-direction: {{ $ar ? 'row-reverse' : 'row' }}; justify-content: space-between; align-items: center; padding: 20px 40px 16px; border-bottom: 1px solid #e5e7eb; margin-bottom: 32px; }
    .page-header-meta { display: flex; gap: 28px; font-size: 10px; color: #6b7c8a; flex-direction: {{ $ar ? 'row-reverse' : 'row' }}; }
    .page-header-meta span strong { color: #1e2a40; }
    .page-logo .logo-main { font-size: 16px; font-weight: 700; color: #1B2EB8; letter-spacing: 2px; }
    .page-logo .logo-sub { font-size: 7px; color: #6b7c8a; letter-spacing: 2px; display: block; text-align: {{ $ar ? 'left' : 'right' }}; }

    /* ── CARD ────────────────────────────────────────────────── */
    .item-card { margin: 0 36px 24px; border-radius: 10px; border: 1px solid #e8eaf0; border-{{ $ar ? 'right' : 'left' }}: 4px solid #1B2EB8; background: #fff; padding: 24px 28px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
    .item-header { display: flex; flex-direction: {{ $ar ? 'row-reverse' : 'row' }}; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
    .item-name { font-size: 17px; font-weight: 700; color: #1e2a40; }
    .item-badge { background: #1B2EB8; color: #fff; font-size: 14px; font-weight: 800; width: 36px; height: 36px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; {{ $ar ? 'margin-right:14px;' : 'margin-left:14px;' }} }
    .item-gold-line { width: 40px; height: 3px; background: #C9A22A; margin-bottom: 12px; {{ $ar ? 'margin-right:auto;' : '' }} }
    .item-sub { font-size: 11px; color: #4a5568; margin-bottom: 14px; text-align: {{ $ar ? 'right' : 'left' }}; }
    .item-sub .sub-label { font-weight: 600; color: #1e2a40; }
    .item-reasoning { font-size: 11.5px; color: #374151; line-height: 1.75; text-align: justify; }
</style>
</head>
<body>

{{-- COVER --}}
<div class="cover">
    <div class="cover-top">
        @if($ar)
            <div class="logo-text"><div class="logo-main">TWINDIX</div><span class="logo-sub">GLOBAL INC.</span></div>
            <div class="cover-label">{{ $labelReport }}</div>
        @else
            <div class="cover-label">{{ $labelReport }}</div>
            <div class="logo-text"><div class="logo-main">TWINDIX</div><span class="logo-sub">GLOBAL INC.</span></div>
        @endif
    </div>
    <div class="cover-body">
        <div class="cover-company">. T W I N D I X &nbsp; G L O B A L &nbsp; I N C</div>
        <h1 class="cover-title">{{ $coverTitle }}</h1>
        <div class="cover-gold-line"></div>
    </div>
    <div class="cover-disclaimer">{{ $disclaimer }}</div>
    <div class="cover-footer">
        <div class="cover-footer-item"><span class="footer-label">{{ $labelCandidate }}</span><div class="footer-value">{{ $userName }}</div></div>
        <div class="cover-footer-item"><span class="footer-label">{{ $labelCode }}</span><div class="footer-value">{{ $examCode }}</div></div>
        <div class="cover-footer-item"><span class="footer-label">{{ $labelIssueDate }}</span><div class="footer-value">{{ $formattedDate }}</div></div>
    </div>
</div>

{{-- CONTENT PAGES (3 cards per page) --}}
@foreach(array_chunk($items, 3) as $chunk)
<div class="content-page">
    <div class="page-header">
        @if($ar)
            <div class="page-logo"><div class="logo-main">TWINDIX</div><span class="logo-sub">GLOBAL INC.</span></div>
            <div class="page-header-meta">
                <span>{{ $labelIssueDate }}&nbsp;<strong>{{ $formattedDate }}</strong></span>
                <span>{{ $labelCode }}&nbsp;<strong>{{ $examCode }}</strong></span>
            </div>
        @else
            <div class="page-header-meta">
                <span>{{ $labelCode }}&nbsp;<strong>{{ $examCode }}</strong></span>
                <span>{{ $labelIssueDate }}&nbsp;<strong>{{ $formattedDate }}</strong></span>
            </div>
            <div class="page-logo"><div class="logo-main">TWINDIX</div><span class="logo-sub">GLOBAL INC.</span></div>
        @endif
    </div>

    @foreach($chunk as $item)
    @php $idx = ($loop->parent->index * 3) + $loop->index + 1; @endphp
    <div class="item-card">
        <div class="item-header">
            @if($ar)
                <div class="item-badge">{{ str_pad($idx, 2, '0', STR_PAD_LEFT) }}</div>
                <div class="item-name">{{ $item['name'] }}</div>
            @else
                <div class="item-name">{{ $item['name'] }}</div>
                <div class="item-badge">{{ str_pad($idx, 2, '0', STR_PAD_LEFT) }}</div>
            @endif
        </div>
        <div class="item-gold-line"></div>
        @if(trim((string) $item['sub_items']) !== '')
            <div class="item-sub"><span class="sub-label">{{ $labelSpec }}:&nbsp;</span>{{ $item['sub_items'] }}</div>
        @endif
        <p class="item-reasoning">{!! nl2br(e($item['reasoning'])) !!}</p>
    </div>
    @endforeach
</div>
@endforeach

</body>
</html>
