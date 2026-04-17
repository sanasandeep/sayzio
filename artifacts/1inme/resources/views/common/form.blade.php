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
    <script src="//unpkg.com/alpinejs" defer></script>
    <style>
        :root {
            --form-accent: {{ $accent }};
            --form-bg: {{ $background }};
            --form-text: {{ $text }};
            --form-radius: {{ $radius }}px;
            --form-radius-sm: {{ max(4, $radius / 2) }}px;
        }
        * { box-sizing: border-box; }
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

        @php
            // Sanitize custom CSS — strip any tag-like sequences that could break out of <style>
            $rawCss = (string) ($design['custom_css'] ?? '');
            $safeCss = preg_replace(['#</style#i', '#<script#i', '#javascript:#i', '#expression\s*\(#i', '#@import#i'], '/*blocked*/', $rawCss);
        @endphp
        {!! $safeCss !!}
    </style>
</head>
<body class="theme-{{ $theme }} {{ ($embed ?? false) ? 'embed-mode' : '' }}">
    <div class="form-page">
        <div style="width: 100%; max-width: 640px;">
            @if($cover)
                <img src="{{ $cover }}" alt="" class="form-cover">
            @endif

            @if(session('form_success'))
                <div class="form-card {{ $cover ? 'has-cover' : '' }}">
                    <div class="form-success">
                        <div class="form-success-icon"><i class="fas fa-check-circle"></i></div>
                        <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0 0 0.5rem;">All done!</h2>
                        <p style="opacity: 0.75; margin: 0;">{{ session('form_success') }}</p>
                        @if(($settings['allow_multiple'] ?? true))
                            <button type="button" onclick="window.location.reload()" class="form-button" style="margin-top: 1.5rem;">
                                <i class="fas fa-redo text-xs"></i> Submit another response
                            </button>
                        @endif
                    </div>
                </div>
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
