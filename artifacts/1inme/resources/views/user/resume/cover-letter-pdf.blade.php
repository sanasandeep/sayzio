<!DOCTYPE html>
{{-- Print-only HTML for the cover-letter PDF export. Kept deliberately
     simple (no template selector, no color theme) so dompdf renders
     it predictably and the letter stays the focus. --}}
<html lang="{{ $letter->language ?: 'en' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $letter->title }}</title>
    <style>
        @page { margin: 22mm 20mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 11pt; line-height: 1.55; }
        .head { margin-bottom: 28px; }
        .head .name { font-size: 18pt; font-weight: 700; margin: 0 0 2px 0; }
        .head .meta { font-size: 9pt; color: #555; }
        .head .meta span + span::before { content: " · "; color: #888; }
        .greeting { margin: 0 0 14px 0; }
        .body p { margin: 0 0 12px 0; }
        .sign-off { white-space: pre-line; margin-top: 14px; }
        .footer { margin-top: 28px; font-size: 8pt; color: #888; border-top: 1px solid #ddd; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="head">
        @if(!empty($header['name']))
            <h1 class="name">{{ $header['name'] }}</h1>
        @endif
        <div class="meta">
            @if(!empty($header['headline'])) <span>{{ $header['headline'] }}</span> @endif
            @if(!empty($header['location'])) <span>{{ $header['location'] }}</span> @endif
            @if(!empty($header['email']))    <span>{{ $header['email'] }}</span> @endif
            @if(!empty($header['phone']))    <span>{{ $header['phone'] }}</span> @endif
            @if(!empty($header['website']))  <span>{{ $header['website'] }}</span> @endif
        </div>
    </div>

    @php
        $content  = is_array($letter->content) ? $letter->content : [];
        $greeting = trim((string)($content['greeting'] ?? ''));
        $signOff  = trim((string)($content['sign_off'] ?? ''));
        $body     = array_values(array_filter(array_map(
            fn($p) => trim((string) $p),
            (array)($content['body'] ?? []),
        ), fn($p) => $p !== ''));
    @endphp

    @if($greeting !== '')
        <p class="greeting">{{ $greeting }}</p>
    @endif

    <div class="body">
        @foreach($body as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach
    </div>

    @if($signOff !== '')
        <div class="sign-off">{{ $signOff }}</div>
    @endif

    <div class="footer">
        Generated {{ optional($letter->created_at)->toFormattedDateString() }} · Tone: {{ ucfirst($letter->tone) }}
    </div>
</body>
</html>
