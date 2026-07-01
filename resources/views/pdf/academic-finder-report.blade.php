@php
    $isArabic = ($lang ?? 'en') === 'ar';
    $startEdge = $isArabic ? 'right' : 'left';
    $endEdge = $isArabic ? 'left' : 'right';
    $accentDirection = $isArabic ? '270deg' : '90deg';
    $W = 794; $H = 1123;
    $companyName = 'Twindix Global Inc.';
    $title = $isArabic ? 'التوصيات الأكاديمية' : 'Academic Recommendations';
    $docLabel = $isArabic ? 'تقرير التوصيات' : 'Recommendations Report';
    $recipientLabel = $isArabic ? 'المرشح' : 'Prepared for';
    $codeLabel = $isArabic ? 'الرمز' : 'Code';
    $issuedLabel = $isArabic ? 'تاريخ الإصدار' : 'Issued';
    $issued = \Illuminate\Support\Carbon::now()
        ->locale($isArabic ? 'ar' : 'en')->isoFormat('D MMMM YYYY');
    // Report title — same string as the downloaded file name (set from the job).
    $reportTitle = $reportTitle ?? $title;
    $disclaimer = $isArabic
        ? 'هذا التقرير مُولّد بناءً على إجاباتك وبواسطة الذكاء الاصطناعي، وتعتمد دقّته على مدى صدق إجاباتك. متوسط دقة الذكاء الاصطناعي 95%.'
        : 'This report is generated based on your answers and AI generation; its accuracy depends on the honesty of your answers. The average AI accuracy is 95%.';
@endphp
<!DOCTYPE html>
<html lang="{{ $isArabic ? 'ar' : 'en' }}" dir="{{ $isArabic ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<title>{{ $reportTitle }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    @page { size: A4; margin: 0; }
    .pdf-root, .pdf-root * { box-sizing: border-box; margin: 0; padding: 0;
        -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .pdf-root {
        font-family: "Inter","Noto Sans Arabic","Segoe UI",system-ui,-apple-system,sans-serif;
        color: #0E1B3A; background: #FFFFFF; font-size: 14px; line-height: 1.5; font-weight: 400;
        text-align: {{ $isArabic ? 'right' : 'left' }}; direction: {{ $isArabic ? 'rtl' : 'ltr' }}; width: {{ $W }}px; }
    .pdf-root .page { width: {{ $W }}px; position: relative; overflow: hidden; background: #FFFFFF; }
    .pdf-root .cover { height: 1120px; break-after: page; page-break-after: always;
        background: radial-gradient(circle at 80% 0%, rgba(212,175,106,.22) 0%, rgba(212,175,106,0) 55%),
            radial-gradient(circle at 0% 100%, rgba(43,111,224,.5) 0%, rgba(43,111,224,0) 55%),
            linear-gradient(135deg,#001A82 0%,#0025BA 45%,#1356BC 100%);
        color:#FFF; display:flex; flex-direction:column; padding:80px 68px; }
    .pdf-root .cover-orb { position:absolute; {{ $endEdge }}:-440px; top:-180px; width:980px; height:980px;
        border-radius:50%; background:radial-gradient(circle, rgba(255,255,255,.07) 0%, rgba(255,255,255,0) 60%); }
    .pdf-root .cover-top { display:flex; align-items:center; justify-content:space-between; position:relative; z-index:2; }
    .pdf-root .cover-logo { height:44px; width:auto; filter:brightness(0) invert(1); opacity:.95; }
    .pdf-root .cover-doc { font-size:11px; letter-spacing:.32em; text-transform:uppercase; color:rgba(255,255,255,.78); font-weight:500; }
    .pdf-root .cover-mid { flex:1; display:flex; flex-direction:column; justify-content:center; position:relative; z-index:2; padding:60px 0; }
    .pdf-root .cover-eyebrow { font-size:11px; letter-spacing:.4em; text-transform:uppercase; color:rgba(255,255,255,.7); margin-bottom:18px; display:flex; align-items:center; gap:14px; }
    .pdf-root .cover-eyebrow-line { display:inline-block; width:36px; height:1px; background:#D4AF6A; }
    .pdf-root .cover-title { font-size:64px; line-height:1; font-weight:800; letter-spacing:-.025em; color:#FFF; max-width:90%; }
    .pdf-root .cover-title.is-name { font-size:40px; line-height:1.12; max-width:100%; word-break:break-word; }
    .pdf-root .cover-rule { margin-top:28px; height:3px; width:96px; background:linear-gradient(90deg,#D4AF6A 0%,#EBD9A8 100%); border-radius:2px; }
    .pdf-root .cover-foot { position:absolute; bottom:40px; left:68px; right:68px; z-index:2; }
    .pdf-root .cover-disclaimer { font-size:11px; line-height:1.7; color:rgba(255,255,255,.82); margin-bottom:22px; }
    .pdf-root .cover-disclaimer strong { color:#FFF; font-weight:600; }
    .pdf-root .cover-bottom { display:flex; gap:32px; z-index:2; padding-top:68px; border-top:1px solid rgba(255,255,255,.2); }
    .pdf-root .meta { flex:1; display:flex; flex-direction:column; gap:6px; }
    .pdf-root .meta-label { font-size:10px; letter-spacing:.28em; text-transform:uppercase; color:rgba(255,255,255,.65); font-weight:500; }
    .pdf-root .meta-value { font-size:16px; font-weight:600; color:#FFF; letter-spacing:-.01em; }
    .pdf-root .meta-value.code { font-family:"Inter","Noto Sans Arabic","Segoe UI",system-ui,sans-serif; font-size:14px; letter-spacing:.08em; }
    .pdf-root .content { padding:68px 60px 90px; background:#FFF; }
    .pdf-root .content-header { display:flex; align-items:center; justify-content:space-between; padding-bottom:14px; margin-bottom:22px; border-bottom:1px solid #E2E8F2; }
    .pdf-root .content-header img { height:28px; width:auto; }
    .pdf-root .content-header-meta { display:flex; gap:18px; font-size:10px; letter-spacing:.18em; text-transform:uppercase; color:#7A879D; font-weight:500; }
    .pdf-root .content-header-meta strong { color:#0E1B3A; font-weight:600; letter-spacing:.06em; margin-{{ $startEdge }}:6px; }
    .pdf-root .card { position:relative; break-inside:avoid; page-break-inside:avoid; background:linear-gradient(180deg,#FFF 0%,#FBFCFE 100%); border:1px solid #E2E8F2; border-radius:18px; padding:22px 26px 26px; margin-bottom:16px; overflow:hidden; box-shadow:0 1px 0 rgba(14,27,58,.02),0 12px 28px -18px rgba(14,27,58,.18); }
    .pdf-root .card-stripe { position:absolute; top:0; {{ $startEdge }}:0; bottom:0; width:4px; background:linear-gradient(180deg,#001A82 0%,#1356BC 50%,#D4AF6A 100%); }
    .pdf-root .card-glow { position:absolute; top:-40px; {{ $endEdge }}:-40px; width:180px; height:180px; border-radius:50%; background:radial-gradient(circle, rgba(19,86,188,.07) 0%, rgba(19,86,188,0) 70%); }
    .pdf-root .card-head { break-inside:avoid; display:flex; align-items:center; gap:18px; margin-bottom:14px; position:relative; z-index:1; }
    .pdf-root .card-num { flex-shrink:0; width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg,#1356BC 0%,#001A82 100%); color:#FFF; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:700; letter-spacing:.04em; font-family:"Inter","Noto Sans Arabic","Segoe UI",system-ui,sans-serif; box-shadow:0 8px 18px -8px rgba(19,86,188,.55); }
    .pdf-root .card-title { font-size:22px; line-height:1.2; font-weight:700; color:#0E1B3A; letter-spacing:-.015em; flex:1; }
    .pdf-root .card-body-wrap { position:relative; padding-{{ $startEdge }}:70px; }
    .pdf-root .card-body-accent { position:absolute; top:9px; {{ $startEdge }}:0; width:52px; height:1px; background:linear-gradient({{ $accentDirection }},#D4AF6A 0%, rgba(212,175,106,0) 100%); }
    .pdf-root .card-body { font-size:13px; line-height:1.75; color:#2D3D5C; text-align:justify; white-space:pre-line; }
    .pdf-root .disclaimer { margin-top:18px; padding:14px 18px; border:1px solid #E2E8F2; border-radius:12px;
        background:#F7F9FC; font-size:11px; line-height:1.7; color:#5A6B86; }
    .pdf-root .disclaimer strong { color:#0E1B3A; font-weight:600; }
</style>
</head>
<body>
<div class="pdf-root">
    <section class="page cover">
        <span class="cover-orb"></span>
        <div class="cover-top">
            @if($logoDataUri)<img src="{{ $logoDataUri }}" alt="{{ $companyName }}" class="cover-logo" />@endif
            <span class="cover-doc">{{ $docLabel }}</span>
        </div>
        <div class="cover-mid">
            <div class="cover-eyebrow"><span class="cover-eyebrow-line"></span>{{ $companyName }}</div>
            <h1 class="cover-title is-name">{{ $reportTitle }}</h1>
            <div class="cover-rule"></div>
        </div>
        <div class="cover-foot">
            <p class="cover-disclaimer"><strong>{{ $isArabic ? 'تنويه:' : 'Disclaimer:' }}</strong> {{ $disclaimer }}</p>
            <div class="cover-bottom">
                <div class="meta"><span class="meta-label">{{ $recipientLabel }}</span><span class="meta-value">{{ $userName }}</span></div>
                <div class="meta"><span class="meta-label">{{ $codeLabel }}</span><span class="meta-value code">{{ $code }}</span></div>
                <div class="meta"><span class="meta-label">{{ $issuedLabel }}</span><span class="meta-value">{{ $issued }}</span></div>
            </div>
        </div>
    </section>
    <section class="page content">
        <div class="content-header">
            @if($logoDataUri)<img src="{{ $logoDataUri }}" alt="{{ $companyName }}" />@endif
            <div class="content-header-meta">
                <span>{{ $codeLabel }}<strong>{{ $code }}</strong></span>
                <span>{{ $issuedLabel }}<strong>{{ $issued }}</strong></span>
            </div>
        </div>
        @foreach($jobs as $i => $job)
            <article class="card">
                <span class="card-stripe"></span><span class="card-glow"></span>
                <header class="card-head">
                    <span class="card-num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <h2 class="card-title">{{ $job['jobTitle'] ?? '' }}</h2>
                </header>
                <div class="card-body-wrap">
                    <span class="card-body-accent"></span>
                    <p class="card-body">{{ $job['justification'] ?? '' }}</p>
                </div>
            </article>
        @endforeach
    </section>
</div>
</body>
</html>
