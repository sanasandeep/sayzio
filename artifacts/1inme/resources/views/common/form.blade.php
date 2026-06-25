<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $design = array_merge(\App\Modules\User\Models\Form::defaultDesign(), $form->design ?? []);
        $settings = array_merge(\App\Modules\User\Models\Form::defaultSettings(), $form->settings ?? []);
        $theme = $design['theme'];
        $accent = $design['accent'];
        $background = $design['background'];
        $text = $design['text'];
        $radius = (int) ($design['border_radius'] ?? 12);
        $btnStyle = $design['button_style'];
        $btnLabel = $design['button_label'];
        $font = $design['font'] ?? 'Plus Jakarta Sans';
        $logo = $design['logo'] ?? null;
        $cover = $design['cover'] ?? null;
        $cardColor = $design['card_color'] ?? ($theme === 'light' ? '#ffffff' : ($theme === 'dark' ? '#1e293b' : 'rgba(255,255,255,0.04)'));
        $cardImage = $design['card_image'] ?? null;
        $cardImageMode = $design['card_image_mode'] ?? 'cover';
        $cardImageOpacity = max(0, min(100, (int) ($design['card_image_opacity'] ?? 100)));
        $cardBgSize = $cardImageMode === 'contain' ? 'contain' : ($cardImageMode === 'tile' ? 'auto' : 'cover');
        $cardBgRepeat = $cardImageMode === 'tile' ? 'repeat' : 'no-repeat';

        // Group fields into pages by page_break
        $pages = [[]];
        foreach (($form->fields ?? []) as $f) {
            if (($f['type'] ?? null) === 'page_break') $pages[] = [];
            else $pages[count($pages) - 1][] = $f;
        }
        $pageCount = count($pages);
        $isMulti = $pageCount > 1;
    @endphp
    <title>{{ $form->title }}</title>
    @if($form->description)<meta name="description" content="{{ $form->description }}">@endif
    <meta property="og:title" content="{{ $form->title }}">
    @if($form->description)<meta property="og:description" content="{{ $form->description }}">@endif
    @if($logo)<link rel="icon" href="{{ $logo }}">@endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family={{ urlencode($font) }}:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script>
    <script src="{{ asset('js/vendor/alpine.min.js') }}" defer></script>
    <style>
        :root {
            --form-accent: {{ $accent }};
            --form-bg: {{ $background }};
            --form-text: {{ $text }};
            --form-radius: {{ $radius }}px;
            --form-radius-sm: {{ max(4, $radius / 2) }}px;
        }
        * { box-sizing: border-box; }
        [x-cloak] { display: none !important; }
        html, body { margin: 0; padding: 0; min-height: 100vh; font-family: '{{ $font }}', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }

        body.theme-light { background: var(--form-bg); color: var(--form-text); }
        body.theme-dark  { background: #0f172a; color: #f1f5f9; }
        body.theme-glass {
            background:
              radial-gradient(ellipse at 20% 0%, rgba(139,92,246,0.18), transparent 55%),
              radial-gradient(ellipse at 80% 100%, rgba(236,72,153,0.16), transparent 55%),
              linear-gradient(180deg, #0a0b10, #14111f 60%, #1a1230);
            color: #f5f6fa;
        }

        .form-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        body.embed-mode { background: transparent; }
        body.embed-mode .form-page { min-height: auto; padding: 1rem; }

        .form-cover {
            width: 100%; max-width: 640px; height: 160px; object-fit: cover;
            border-radius: var(--form-radius) var(--form-radius) 0 0;
        }

        .form-card {
            position: relative;
            width: 100%; max-width: 640px;
            background-color: {{ $cardColor }};
            @if($cardImage)
            background-image: url('{{ $cardImage }}');
            background-size: {{ $cardBgSize }};
            background-position: center;
            background-repeat: {{ $cardBgRepeat }};
            @endif
            border-radius: var(--form-radius);
            padding: 2.5rem 2rem;
            {{ $theme === 'glass' ? 'border: 1px solid rgba(255,255,255,0.10); backdrop-filter: blur(18px) saturate(1.1); -webkit-backdrop-filter: blur(18px) saturate(1.1);' : '' }}
            {{ $theme === 'light' ? 'box-shadow: 0 30px 80px -20px rgba(0,0,0,0.10), 0 4px 12px -2px rgba(0,0,0,0.05);' : 'box-shadow: 0 30px 80px -20px rgba(0,0,0,0.6);' }}
        }
        @if($cardImage && $cardImageOpacity < 100)
        /* Color scrim over the image so text stays legible. Opacity slider is
           the *image* opacity, so scrim alpha = (100 - opacity) / 100. */
        .form-card::before {
            content: '';
            position: absolute; inset: 0;
            background: {{ $cardColor }};
            opacity: {{ (100 - $cardImageOpacity) / 100 }};
            border-radius: inherit;
            pointer-events: none;
            z-index: 0;
        }
        .form-card > * { position: relative; z-index: 1; }
        @endif
        .form-section-card {
            background: {{ $theme === 'light' ? 'rgba(15,23,42,0.025)' : 'rgba(255,255,255,0.04)' }};
            border: 1px solid {{ $theme === 'light' ? '#e2e8f0' : 'rgba(255,255,255,0.08)' }};
            border-radius: var(--form-radius-sm);
            padding: 1.25rem 1.25rem 0.25rem;
            margin-bottom: 1.25rem;
        }
        .form-section-card .form-grid { row-gap: 0; }
        .form-card.has-cover { border-top-left-radius: 0; border-top-right-radius: 0; padding-top: 1.5rem; }
        .form-logo { width: 60px; height: 60px; border-radius: 14px; margin-bottom: 1rem; object-fit: contain; padding: 6px; background: rgba(0,0,0,0.04); }
        .form-title { font-size: 1.6rem; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 0.5rem; }
        .form-desc { font-size: 0.92rem; opacity: 0.7; margin: 0 0 2rem; line-height: 1.5; }

        .form-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); column-gap: 1rem; row-gap: 0; align-items: start; }
        .form-grid-cell { min-width: 0; }
        .form-field { margin-bottom: 1.25rem; min-width: 0; }
        /* Collapse all width-spans on small screens — apply to the wrapper that actually owns the grid-column */
        @media (max-width: 640px) { .form-grid-cell { grid-column: span 12 !important; } }
        .form-label { display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 0.5rem; }
        .form-required { color: #ef4444; margin-left: 0.25rem; }
        .form-help { font-size: 0.74rem; opacity: 0.6; margin-top: 0.4rem; }
        .form-error { font-size: 0.74rem; color: #ef4444; margin-top: 0.4rem; font-weight: 500; }

        .form-input, .form-textarea, .form-select {
            width: 100%; padding: 0.7rem 0.9rem; font-size: 0.9rem; font-family: inherit;
            background: {{ $theme === 'light' ? '#f8fafc' : 'rgba(255,255,255,0.04)' }};
            color: inherit;
            border: 1px solid {{ $theme === 'light' ? '#e2e8f0' : 'rgba(255,255,255,0.10)' }};
            border-radius: var(--form-radius-sm);
            outline: none; transition: all 0.15s;
        }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            border-color: var(--form-accent);
            box-shadow: 0 0 0 3px {{ $accent }}33;
            background: {{ $theme === 'light' ? 'white' : 'rgba(255,255,255,0.08)' }};
        }
        .form-textarea { resize: vertical; min-height: 100px; }
        .form-row-inline .form-field { display: grid; grid-template-columns: 1fr 2fr; align-items: center; gap: 1rem; }
        @media (max-width: 640px) { .form-row-inline .form-field { grid-template-columns: 1fr; } }

        .form-radio-group, .form-check-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .form-radio-group label, .form-check-group label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem 0.75rem; border-radius: var(--form-radius-sm); border: 1px solid transparent; transition: all 0.15s; }
        .form-radio-group label:hover, .form-check-group label:hover { background: {{ $theme === 'light' ? '#f1f5f9' : 'rgba(255,255,255,0.04)' }}; }
        .form-radio-group input, .form-check-group input { accent-color: var(--form-accent); }

        .rating-stars { display: flex; gap: 0.4rem; font-size: 1.6rem; }
        .rating-stars label { cursor: pointer; color: {{ $theme === 'light' ? '#e2e8f0' : 'rgba(255,255,255,0.15)' }}; transition: color 0.15s; }
        .rating-stars input { display: none; }
        .rating-stars label.active, .rating-stars label.hover { color: #f59e0b; }

        .scale-row { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .scale-row label {
            cursor: pointer;
            min-width: 36px; height: 36px; padding: 0 0.4rem;
            display: inline-flex; align-items: center; justify-content: center;
            border: 1px solid {{ $theme === 'light' ? '#e2e8f0' : 'rgba(255,255,255,0.10)' }};
            border-radius: var(--form-radius-sm);
            font-size: 0.8rem; font-weight: 600;
            background: {{ $theme === 'light' ? '#f8fafc' : 'rgba(255,255,255,0.04)' }};
            transition: all 0.15s;
        }
        .scale-row label:hover { border-color: var(--form-accent); }
        .scale-row input { display: none; }
        .scale-row input:checked + label, .scale-row label.checked { background: var(--form-accent); border-color: var(--form-accent); color: white; }

        .form-divider { border-top: 1px solid {{ $theme === 'light' ? '#e2e8f0' : 'rgba(255,255,255,0.10)' }}; margin: 1.5rem 0; }
        .form-heading { font-size: 1.05rem; font-weight: 700; margin: 1rem 0 0.5rem; }
        .form-paragraph { font-size: 0.9rem; opacity: 0.75; line-height: 1.6; margin: 0.5rem 0 1rem; }

        .form-button {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            padding: 0.85rem 2rem; font-size: 0.9rem; font-weight: 700; font-family: inherit;
            border-radius: var(--form-radius-sm); cursor: pointer; border: 0; transition: all 0.2s;
            {!!
                $btnStyle === 'gradient' ? "background: linear-gradient(135deg, {$accent}, {$accent}cc); color: white; box-shadow: 0 10px 30px -8px {$accent}88;"
              : ($btnStyle === 'outline' ? "background: transparent; color: {$accent}; border: 2px solid {$accent};"
              : "background: {$accent}; color: white;")
            !!}
        }
        .form-button:hover { transform: translateY(-2px); }
        .form-button:active { transform: translateY(0); }
        .form-button:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .form-button-secondary {
            background: transparent; color: inherit; opacity: 0.6;
            border: 1px solid {{ $theme === 'light' ? '#e2e8f0' : 'rgba(255,255,255,0.10)' }};
        }

        .form-success {
            text-align: center; padding: 3rem 2rem;
            background: {{ $theme === 'light' ? 'rgba(16,185,129,0.05)' : 'rgba(16,185,129,0.10)' }};
            border-radius: var(--form-radius);
            border: 1px solid rgba(16,185,129,0.25);
        }
        .form-success-icon { font-size: 3rem; color: #10b981; margin-bottom: 1rem; }

        .progress-track { height: 4px; background: {{ $theme === 'light' ? '#e2e8f0' : 'rgba(255,255,255,0.08)' }}; border-radius: 99px; margin-bottom: 1.5rem; overflow: hidden; }
        .progress-bar { height: 100%; background: linear-gradient(90deg, var(--form-accent), {{ $accent }}aa); transition: width 0.3s; }

        .branding { font-size: 0.7rem; opacity: 0.5; text-align: center; margin-top: 1.5rem; letter-spacing: 0.05em; }
        .branding a { color: inherit; text-decoration: none; }

        .honeypot { position: absolute; left: -9999px; opacity: 0; pointer-events: none; }

        /* One-question-at-a-time (Typeform-style) layout */
        body.layout-oneq .form-page { min-height: 100vh; padding: 0; align-items: stretch; justify-content: stretch; }
        body.layout-oneq.embed-mode .form-page { min-height: auto; padding: 0; }
        body.layout-oneq .form-page > div { max-width: none !important; width: 100%; display: flex; flex-direction: column; }
        body.layout-oneq .form-cover { max-width: none; width: 100%; height: 200px; border-radius: 0; }
        .form-card.form-oneq {
            max-width: none; width: 100%; flex: 1;
            min-height: 100vh;
            display: flex; flex-direction: column;
            border-radius: 0; padding: 0;
            box-shadow: none;
        }
        body.embed-mode .form-card.form-oneq { min-height: 480px; }
        .form-card.form-oneq.has-cover { min-height: calc(100vh - 200px); padding-top: 0; }
        body.embed-mode .form-card.form-oneq.has-cover { min-height: 360px; }
        .oneq-progress {
            position: sticky; top: 0; z-index: 5;
            height: 6px;
            background: {{ $theme === 'light' ? '#e2e8f0' : 'rgba(255,255,255,0.08)' }};
        }
        .oneq-progress > div {
            height: 100%;
            background: linear-gradient(90deg, var(--form-accent), {{ $accent }}aa);
            transition: width 0.35s ease;
        }
        .oneq-stage {
            flex: 1;
            display: flex; align-items: center; justify-content: center;
            padding: 3rem 1.5rem;
        }
        .oneq-slide {
            width: 100%; max-width: 640px;
            transition: opacity 0.25s ease;
        }
        .oneq-slide-counter { font-size: 0.78rem; opacity: 0.55; margin-bottom: 1rem; letter-spacing: 0.04em; }
        .oneq-slide .form-label,
        .oneq-slide-title { font-size: 1.6rem; font-weight: 700; line-height: 1.3; margin-bottom: 0.6rem; letter-spacing: -0.01em; }
        .oneq-slide .form-help,
        .oneq-slide-help { font-size: 0.95rem; opacity: 0.7; margin-top: 0; margin-bottom: 1.5rem; line-height: 1.5; }
        .oneq-slide .form-input,
        .oneq-slide .form-textarea,
        .oneq-slide .form-select { font-size: 1.05rem; padding: 0.95rem 1.1rem; }
        .oneq-slide .form-textarea { min-height: 140px; }
        .oneq-slide .form-radio-group label,
        .oneq-slide .form-check-group label { padding: 0.85rem 1rem; font-size: 1rem; }
        .oneq-slide .form-field { margin-bottom: 0; }
        .oneq-intro .form-title { font-size: 2.2rem; }
        .oneq-intro .form-desc { font-size: 1.05rem; margin-bottom: 0; }
        .oneq-intro .form-logo { width: 72px; height: 72px; margin-bottom: 1.5rem; }
        .oneq-controls {
            display: flex; gap: 0.75rem; align-items: center;
            padding: 1rem 1.5rem 1.5rem;
            border-top: 1px solid {{ $theme === 'light' ? '#e2e8f0' : 'rgba(255,255,255,0.08)' }};
            flex-wrap: wrap;
            background: inherit;
        }
        .oneq-controls .oneq-hint { font-size: 0.7rem; opacity: 0.5; margin-left: auto; }
        @media (max-width: 640px) {
            .oneq-stage { padding: 2rem 1.25rem; }
            .oneq-slide .form-label, .oneq-slide-title { font-size: 1.25rem; }
            .oneq-intro .form-title { font-size: 1.65rem; }
        }

        @php
            // Sanitize custom CSS — strip any tag-like sequences that could break out of <style>
            $rawCss = (string) ($design['custom_css'] ?? '');
            $safeCss = preg_replace(['#</style#i', '#<script#i', '#javascript:#i', '#expression\s*\(#i', '#@import#i'], '/*blocked*/', $rawCss);
        @endphp
        {!! $safeCss !!}
    </style>
</head>
<body class="theme-{{ $theme }} {{ ($embed ?? false) ? 'embed-mode' : '' }} {{ ($design['layout'] ?? '') === 'oneq' ? 'layout-oneq' : '' }}">
    <div class="form-page">
        <div style="width: 100%; max-width: 640px;">
            @if($cover)
                <img src="{{ $cover }}" alt="" class="form-cover">
            @endif

            @if(session('form_success') || request('paid'))
                <div class="form-card {{ $cover ? 'has-cover' : '' }}">
                    <div class="form-success">
                        <div class="form-success-icon"><i class="fas fa-check-circle"></i></div>
                        <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0 0 0.5rem;">All done!</h2>
                        <p style="opacity: 0.75; margin: 0;">{{ session('form_success') ?: (request('paid') ? (session('success') ?: 'Payment received — your response has been recorded. Thank you!') : '') }}</p>
                        @if(($settings['allow_multiple'] ?? true))
                            <button type="button" onclick="window.location.reload()" class="form-button" style="margin-top: 1.5rem;">
                                <i class="fas fa-redo text-xs"></i> Submit another response
                            </button>
                        @endif
                    </div>
                </div>
            @else
                @if(($design['layout'] ?? '') === 'oneq')
                    @php
                        // Build a flat ordered list of slides for the one-question-at-a-time runner.
                        $allFields = [];
                        foreach ($pages as $pf) { foreach ($pf as $f) { $allFields[] = $f; } }

                        $sectionIds = [];
                        foreach ($allFields as $f) {
                            if (($f['type'] ?? null) === 'section') $sectionIds[$f['id']] = true;
                        }
                        $childrenBySection = [];
                        foreach ($allFields as $f) {
                            $parent = $f['parent'] ?? null;
                            if ($parent && isset($sectionIds[$parent]) && ($f['type'] ?? null) !== 'section') {
                                $childrenBySection[$parent][] = $f;
                            }
                        }

                        $slides = [];
                        $slides[] = ['type' => 'intro', 'ids' => []];
                        $hiddenFields = [];

                        $pushSlide = function (array $field) use (&$slides) {
                            $t = $field['type'] ?? 'text';
                            if (in_array($t, ['heading', 'paragraph'])) {
                                $slides[] = ['type' => 'message', 'field' => $field, 'ids' => []];
                            } else {
                                $slides[] = ['type' => 'field', 'field' => $field, 'ids' => [$field['id'] ?? '']];
                            }
                        };

                        foreach ($allFields as $f) {
                            $t = $f['type'] ?? 'text';
                            $parent = $f['parent'] ?? null;
                            if ($parent && isset($sectionIds[$parent])) continue;
                            if ($t === 'hidden') { $hiddenFields[] = $f; continue; }
                            if (in_array($t, ['divider', 'page_break'])) continue;
                            if ($t === 'section') {
                                if (!empty($f['label']) || !empty($f['help'])) {
                                    $slides[] = ['type' => 'section_intro', 'field' => $f, 'ids' => []];
                                }
                                foreach ($childrenBySection[$f['id']] ?? [] as $child) {
                                    $ct = $child['type'] ?? 'text';
                                    if (in_array($ct, ['divider', 'page_break'])) continue;
                                    if ($ct === 'hidden') { $hiddenFields[] = $child; continue; }
                                    $pushSlide($child);
                                }
                                continue;
                            }
                            $pushSlide($f);
                        }

                        $slideCount = count($slides);
                        // Map of fieldId => slideIndex for error jumps & required-validation
                        $fieldSlideIndex = [];
                        foreach ($slides as $idx => $s) {
                            foreach ($s['ids'] as $fid) {
                                if ($fid !== '') $fieldSlideIndex[$fid] = $idx;
                            }
                        }
                        $startSlide = 0;
                        if ($errors->any()) {
                            foreach ($slides as $idx => $s) {
                                $hit = false;
                                foreach ($s['ids'] as $fid) {
                                    if ($fid !== '' && $errors->has($fid)) { $hit = true; break; }
                                }
                                if ($hit) { $startSlide = $idx; break; }
                            }
                        }
                        // Build required-field map: slideIndex => [{id, type}]
                        $requiredBySlide = [];
                        foreach ($slides as $idx => $s) {
                            if (($s['type'] ?? '') !== 'field') continue;
                            $field = $s['field'];
                            if (!empty($field['required'])) {
                                $requiredBySlide[$idx][] = [
                                    'id' => $field['id'] ?? '',
                                    'type' => $field['type'] ?? 'text',
                                ];
                            }
                        }
                    @endphp

                    <form method="POST" action="{{ route('forms.public.submit', $form->slug) }}" enctype="multipart/form-data"
                          class="form-card form-oneq {{ $cover ? 'has-cover' : '' }}"
                          x-data="formOneq({{ $slideCount }}, {{ $startSlide }}, @js($requiredBySlide))"
                          @keydown.enter="onEnter($event)">
                        @csrf

                        {{-- Honeypot --}}
                        <div class="honeypot" aria-hidden="true">
                            <label>Leave this empty: <input type="text" name="_hp" tabindex="-1" autocomplete="off"></label>
                        </div>

                        {{-- Hidden fields are always present in the form --}}
                        @foreach($hiddenFields as $hf)
                            <input type="hidden" name="{{ $hf['id'] }}" value="{{ $hf['value'] ?? '' }}">
                        @endforeach

                        <div class="oneq-progress"><div :style="`width: ${((slide+1)/{{ $slideCount }})*100}%`"></div></div>

                        <div class="oneq-stage">
                            @foreach($slides as $idx => $s)
                                @if($s['type'] === 'intro')
                                    <div class="oneq-slide oneq-intro" x-show="slide === {{ $idx }}" x-cloak>
                                        @if($logo)<img src="{{ $logo }}" alt="logo" class="form-logo">@endif
                                        <h1 class="form-title">{{ $form->title }}</h1>
                                        @if($form->description)<p class="form-desc">{{ $form->description }}</p>@endif
                                        @if($errors->any())
                                            <div style="margin-top: 1rem; background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.25); color: #b91c1c; padding: 0.75rem 1rem; border-radius: var(--form-radius-sm); font-size: 0.85rem;">
                                                <i class="fas fa-exclamation-triangle"></i> Please fix the errors below — we jumped to the first one.
                                            </div>
                                        @endif
                                    </div>
                                @elseif($s['type'] === 'message')
                                    @php $f = $s['field']; @endphp
                                    <div class="oneq-slide" x-show="slide === {{ $idx }}" x-cloak>
                                        <div class="oneq-slide-counter">{{ $idx }} / {{ $slideCount - 1 }}</div>
                                        @if(($f['type'] ?? '') === 'heading')
                                            <h2 class="oneq-slide-title">{{ $f['label'] ?? '' }}</h2>
                                        @else
                                            <p class="oneq-slide-help" style="font-size: 1.05rem; opacity: 0.85;">{{ $f['label'] ?? '' }}</p>
                                        @endif
                                    </div>
                                @elseif($s['type'] === 'section_intro')
                                    @php $f = $s['field']; @endphp
                                    <div class="oneq-slide" x-show="slide === {{ $idx }}" x-cloak>
                                        <div class="oneq-slide-counter">{{ $idx }} / {{ $slideCount - 1 }}</div>
                                        @if(!empty($f['label']))<h2 class="oneq-slide-title">{{ $f['label'] }}</h2>@endif
                                        @if(!empty($f['help']))<p class="oneq-slide-help">{{ $f['help'] }}</p>@endif
                                    </div>
                                @else
                                    @php $f = $s['field']; @endphp
                                    <div class="oneq-slide" x-show="slide === {{ $idx }}" x-cloak>
                                        <div class="oneq-slide-counter">{{ $idx }} / {{ $slideCount - 1 }}</div>
                                        @include('common.form-field', ['field' => $f, 'errors' => $errors, 'fieldOwner' => $form->user ?? null])
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <div class="oneq-controls">
                            <button type="button" x-show="slide > 0" @click="prev()" class="form-button form-button-secondary">
                                <i class="fas fa-arrow-left text-xs"></i> Back
                            </button>
                            <button type="button" x-show="slide < {{ $slideCount - 1 }}" @click="next()" class="form-button">
                                <span x-text="slide === 0 ? 'Start' : 'Next'"></span> <i class="fas fa-arrow-right text-xs"></i>
                            </button>
                            <button type="submit" x-show="slide === {{ $slideCount - 1 }}" class="form-button">
                                {{ $btnLabel }} <i class="fas fa-arrow-right text-xs"></i>
                            </button>
                            <span class="oneq-hint" x-show="slide < {{ $slideCount - 1 }}">press <strong>Enter</strong> ↵</span>
                            @if($design['show_branding'] ?? true)
                                <span x-show="slide === {{ $slideCount - 1 }}" class="oneq-hint">Powered by <a href="{{ url('/') }}" target="_blank" style="color: inherit;">1INME</a></span>
                            @endif
                        </div>
                    </form>
                @else
                <form method="POST" action="{{ route('forms.public.submit', $form->slug) }}" enctype="multipart/form-data"
                      class="form-card {{ $cover ? 'has-cover' : '' }} {{ $design['layout'] === 'inline' ? 'form-row-inline' : '' }}"
                      x-data="formRunner({{ $pageCount }})">
                    @csrf

                    @if($logo)<img src="{{ $logo }}" alt="logo" class="form-logo">@endif
                    <h1 class="form-title">{{ $form->title }}</h1>
                    @if($form->description)<p class="form-desc">{{ $form->description }}</p>@endif

                    {{-- Honeypot --}}
                    <div class="honeypot" aria-hidden="true">
                        <label>Leave this empty: <input type="text" name="_hp" tabindex="-1" autocomplete="off"></label>
                    </div>

                    @if($errors->any())
                        <div style="background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.25); color: #b91c1c; padding: 0.75rem 1rem; border-radius: var(--form-radius-sm); margin-bottom: 1rem; font-size: 0.85rem;">
                            <i class="fas fa-exclamation-triangle"></i> Please fix the errors below.
                        </div>
                    @endif

                    @if($isMulti)
                        <div class="progress-track">
                            <div class="progress-bar" :style="`width: ${((page+1)/{{ $pageCount }})*100}%`"></div>
                        </div>
                        <div style="font-size: 0.72rem; opacity: 0.55; margin-bottom: 1rem;">Step <span x-text="page+1"></span> of {{ $pageCount }}</div>
                    @endif

                    @foreach($pages as $pageIdx => $pageFields)
                        @php
                            // Build section map for this page so we can group children
                            // under their parent section card (single grouped surface).
                            $sectionIds = [];
                            foreach ($pageFields as $pf) {
                                if (($pf['type'] ?? null) === 'section') $sectionIds[$pf['id']] = true;
                            }
                            $childrenBySection = [];
                            foreach ($pageFields as $pf) {
                                $parent = $pf['parent'] ?? null;
                                if ($parent && isset($sectionIds[$parent]) && ($pf['type'] ?? null) !== 'section') {
                                    $childrenBySection[$parent][] = $pf;
                                }
                            }
                        @endphp
                        <div x-show="page === {{ $pageIdx }}" class="form-grid">
                            @foreach($pageFields as $field)
                                @php
                                    $parent = $field['parent'] ?? null;
                                    // Skip child fields here — they're rendered inside their section card below.
                                    if ($parent && isset($sectionIds[$parent]) && ($field['type'] ?? null) !== 'section') continue;
                                @endphp
                                @if(($field['type'] ?? null) === 'section')
                                    <div class="form-grid-cell form-section-card" style="grid-column: span 12;">
                                        @if(!empty($field['label']))
                                            <h3 class="form-heading" style="margin: 0 0 0.4rem;">{{ $field['label'] }}</h3>
                                        @endif
                                        @if(!empty($field['help']))
                                            <p class="form-help" style="margin: 0 0 0.85rem;">{{ $field['help'] }}</p>
                                        @endif
                                        <div class="form-grid">
                                            @foreach(($childrenBySection[$field['id']] ?? []) as $child)
                                                @php $cw = (int) ($child['width'] ?? 12); if (!in_array($cw, [4,6,8,12], true)) $cw = 12; @endphp
                                                <div class="form-grid-cell" style="grid-column: span {{ $cw }};">
                                                    @include('common.form-field', ['field' => $child, 'errors' => $errors, 'fieldOwner' => $form->user ?? null])
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    @php $w = (int) ($field['width'] ?? 12); if (!in_array($w, [4,6,8,12], true)) $w = 12; @endphp
                                    <div class="form-grid-cell" style="grid-column: span {{ $w }};">
                                        @include('common.form-field', ['field' => $field, 'errors' => $errors, 'fieldOwner' => $form->user ?? null])
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach

                    <div style="display: flex; gap: 0.75rem; align-items: center; margin-top: 1.5rem; flex-wrap: wrap;">
                        @if($isMulti)
                            <button type="button" x-show="page > 0" @click="page--" class="form-button form-button-secondary">
                                <i class="fas fa-arrow-left text-xs"></i> Back
                            </button>
                            <button type="button" x-show="page < {{ $pageCount - 1 }}" @click="page++" class="form-button">
                                Next <i class="fas fa-arrow-right text-xs"></i>
                            </button>
                            <button type="submit" x-show="page === {{ $pageCount - 1 }}" class="form-button">
                                {{ $btnLabel }} <i class="fas fa-arrow-right text-xs"></i>
                            </button>
                        @else
                            <button type="submit" class="form-button">
                                {{ $btnLabel }} <i class="fas fa-arrow-right text-xs"></i>
                            </button>
                        @endif
                    </div>

                    @if($design['show_branding'] ?? true)
                        <div class="branding">Powered by <a href="{{ url('/') }}" target="_blank">1INME</a></div>
                    @endif
                </form>
                @endif
            @endif
        </div>
    </div>

    <script>
        function formRunner(pageCount) {
            return {
                page: 0,
                init() {
                    if ({{ $errors->any() ? 'true' : 'false' }}) {
                        // Stay on first page with errors — for simplicity
                        this.page = 0;
                    }
                    this.postHeight();
                    new ResizeObserver(() => this.postHeight()).observe(document.body);
                },
                postHeight() {
                    if (window.parent !== window) {
                        window.parent.postMessage({ type: '1inme-form-resize', height: document.body.scrollHeight + 20 }, '*');
                    }
                },
            };
        }

        function formOneq(slideCount, startSlide, requiredBySlide) {
            return {
                slide: startSlide || 0,
                slideCount: slideCount,
                requiredBySlide: requiredBySlide || {},
                slideError: '',
                init() {
                    this.postHeight();
                    new ResizeObserver(() => this.postHeight()).observe(document.body);
                    this.$nextTick(() => this.focusCurrent());
                },
                postHeight() {
                    if (window.parent !== window) {
                        // In embed: report viewport-ish height so the iframe stays large enough
                        // for the full-screen runner without massive overflow.
                        const h = Math.max(480, Math.min(window.innerHeight, document.body.scrollHeight));
                        window.parent.postMessage({ type: '1inme-form-resize', height: h }, '*');
                    }
                },
                currentSlideEl() {
                    const root = this.$root || this.$el || document.querySelector('form.form-oneq');
                    if (!root) return null;
                    const slides = root.querySelectorAll('.oneq-slide');
                    return slides[this.slide] || null;
                },
                focusCurrent() {
                    const el = this.currentSlideEl();
                    if (!el) return;
                    const focusable = el.querySelector('input:not([type=hidden]):not([type=radio]):not([type=checkbox]), textarea, select');
                    if (focusable) { try { focusable.focus({ preventScroll: true }); } catch (e) { focusable.focus(); } }
                },
                validateCurrent() {
                    const el = this.currentSlideEl();
                    if (!el) return true;
                    const required = el.querySelectorAll('input[required], textarea[required], select[required]');
                    const seenGroups = new Set();
                    for (const inp of required) {
                        const t = (inp.type || '').toLowerCase();
                        if (t === 'radio' || t === 'checkbox') {
                            if (seenGroups.has(inp.name)) continue;
                            seenGroups.add(inp.name);
                            const group = el.querySelectorAll(`input[name="${inp.name.replace(/"/g, '\\"')}"]`);
                            const anyChecked = Array.from(group).some(i => i.checked);
                            if (!anyChecked) { this.slideError = 'Please choose an option to continue.'; return false; }
                        } else if (t === 'file') {
                            if (!inp.files || inp.files.length === 0) { this.slideError = 'Please upload a file to continue.'; return false; }
                        } else {
                            if (!String(inp.value || '').trim()) { this.slideError = 'This field is required.'; return false; }
                        }
                    }
                    this.slideError = '';
                    return true;
                },
                next() {
                    if (!this.validateCurrent()) {
                        // Surface inline error on the current slide
                        const el = this.currentSlideEl();
                        if (el) {
                            let bar = el.querySelector('.oneq-runtime-error');
                            if (!bar) {
                                bar = document.createElement('div');
                                bar.className = 'form-error oneq-runtime-error';
                                bar.style.marginTop = '0.6rem';
                                el.appendChild(bar);
                            }
                            bar.textContent = this.slideError;
                        }
                        return;
                    }
                    if (this.slide < this.slideCount - 1) {
                        this.slide++;
                        this.$nextTick(() => { this.focusCurrent(); this.postHeight(); window.scrollTo({ top: 0, behavior: 'smooth' }); });
                    }
                },
                prev() {
                    if (this.slide > 0) {
                        this.slide--;
                        this.$nextTick(() => { this.focusCurrent(); this.postHeight(); window.scrollTo({ top: 0, behavior: 'smooth' }); });
                    }
                },
                onEnter(e) {
                    const tag = (e.target && e.target.tagName) || '';
                    // Allow newlines in textareas (Shift+Enter or plain Enter inside textarea)
                    if (tag === 'TEXTAREA') return;
                    // Don't hijack the submit button on the final slide
                    if (this.slide >= this.slideCount - 1) return;
                    e.preventDefault();
                    this.next();
                },
            };
        }

        function signaturePad(id, required) {
            return {
                ctx: null, drawing: false, hasInk: false, dataUrl: '',
                init() {
                    const c = this.$refs.pad;
                    // High-DPI sharp canvas
                    const r = window.devicePixelRatio || 1;
                    const w = c.offsetWidth, h = c.offsetHeight;
                    c.width = Math.round(w * r); c.height = Math.round(h * r);
                    this.ctx = c.getContext('2d');
                    this.ctx.scale(r, r);
                    this.ctx.lineCap = 'round'; this.ctx.lineJoin = 'round';
                    this.ctx.strokeStyle = '#0f172a'; this.ctx.lineWidth = 2;
                },
                _xy(e) {
                    const c = this.$refs.pad, b = c.getBoundingClientRect();
                    const t = e.touches && e.touches[0];
                    return { x: ((t ? t.clientX : e.clientX) - b.left), y: ((t ? t.clientY : e.clientY) - b.top) };
                },
                startStroke(e) {
                    this.drawing = true;
                    const p = this._xy(e);
                    this.ctx.beginPath(); this.ctx.moveTo(p.x, p.y);
                },
                moveStroke(e) {
                    if (!this.drawing) return;
                    const p = this._xy(e);
                    this.ctx.lineTo(p.x, p.y); this.ctx.stroke();
                    this.hasInk = true;
                },
                endStroke() {
                    if (!this.drawing) return;
                    this.drawing = false;
                    if (this.hasInk) this.dataUrl = this.$refs.pad.toDataURL('image/png');
                },
                clearPad() {
                    const c = this.$refs.pad;
                    this.ctx.clearRect(0, 0, c.width, c.height);
                    this.hasInk = false; this.dataUrl = '';
                },
            };
        }
    </script>
</body>
</html>
