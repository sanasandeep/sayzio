    @php
        $imgSt = $s['_image_style'] ?? [];
        $imgInline = \App\Modules\User\Models\BiolinkBlock::buildImageInlineStyle($imgSt);
        $imgLk = $s['_link'] ?? [];
        $imgLinkUrl = $imgLk['url'] ?? $s['link'] ?? '';
        $imgTrackUrl = $imgLinkUrl ? route('redirect.block', ['alias' => $link->alias, 'blockId' => $block->id]) : '';
        $imgTarget = $imgLk['target'] ?? '_blank';
        $imgRel = $imgLk['rel'] ?? 'noopener';
        $imgTitle = $imgLk['title'] ?? '';

        // ── Hero-photo decorations (Task #5922) ─────────────────────────
        // Keys live in _style so curated variants can carry them.
        $phSt = is_array($s['_style'] ?? null) ? $s['_style'] : [];
        $phHasImgMask = !empty($imgSt['mask_shape']) && ($imgSt['mask_shape'] ?? 'none') !== 'none';
        // Frame implies its own arch shape; the standalone `_photo_mask`
        // only kicks in when Image Styling hasn't already set a mask.
        $phFrame = ($phSt['_photo_frame'] ?? '') === 'concentric_arch';
        $phMask = $phHasImgMask ? '' : (string) ($phSt['_photo_mask'] ?? '');
        $phBanner = trim((string) ($phSt['_photo_banner_text'] ?? ''));
        $phAccents = array_filter(explode(',', (string) ($phSt['_photo_accents'] ?? '')));

        // ── Custom sticker overlays (Task #5939) ────────────────────────
        // Persisted entries were already ownership-checked by the
        // sanitizer, but we re-verify at render time against the link
        // owner and fail closed: any sticker whose file no longer exists,
        // changed hands, or got flagged simply doesn't render.
        $phStickers = [];
        $phStickersRaw = is_array($phSt['_photo_stickers'] ?? null) ? $phSt['_photo_stickers'] : [];
        if (!empty($phStickersRaw)) {
            $phStickerIds = array_values(array_unique(array_filter(array_map(
                fn ($e) => is_array($e) ? (int) ($e['file_id'] ?? 0) : 0,
                $phStickersRaw
            ))));
            $phStickerFiles = $phStickerIds === [] ? collect() :
                \App\Modules\User\Models\UserFile::whereIn('id', $phStickerIds)
                    ->where('user_id', $link->user_id)
                    ->where('type', 'image')
                    ->where('scan_status', '!=', 'flagged')
                    ->get()->keyBy('id');
            foreach ($phStickersRaw as $phE) {
                if (!is_array($phE)) continue;
                $phF = $phStickerFiles->get((int) ($phE['file_id'] ?? 0));
                if (!$phF) continue;
                $phPos = (string) ($phE['pos'] ?? 'top_right');
                if (!in_array($phPos, \App\Modules\User\Models\BiolinkBlock::PHOTO_STICKER_POSITIONS, true)) $phPos = 'top_right';
                $phStickers[] = [
                    'url'    => $phF->url_path,
                    'pos'    => $phPos,
                    'size'   => max(24, min(160, (int) ($phE['size'] ?? 64))),
                    'rotate' => max(-180, min(180, (int) ($phE['rotate'] ?? 0))),
                    'dx'     => max(-80, min(80, (int) ($phE['dx'] ?? 0))),
                    'dy'     => max(-80, min(80, (int) ($phE['dy'] ?? 0))),
                ];
                if (count($phStickers) >= \App\Modules\User\Models\BiolinkBlock::PHOTO_STICKER_MAX) break;
            }
        }

        // ── Text overlays (Task #5954) ──────────────────────────────────
        // Sanitized {text,font,color,pos,size,rotate,dx,dy} entries in
        // _style. Re-clamped at render time; text goes through {{ }} so
        // it is escaped on output.
        $phTexts = [];
        $phTextsRaw = is_array($phSt['_photo_text_stickers'] ?? null) ? $phSt['_photo_text_stickers'] : [];
        foreach ($phTextsRaw as $phTE) {
            if (!is_array($phTE)) continue;
            $phTTxt = trim((string) ($phTE['text'] ?? ''));
            if ($phTTxt === '') continue;
            $phTPos = (string) ($phTE['pos'] ?? 'top_right');
            if (!in_array($phTPos, \App\Modules\User\Models\BiolinkBlock::PHOTO_STICKER_POSITIONS, true)) $phTPos = 'top_right';
            $phTColor = (string) ($phTE['color'] ?? '');
            if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $phTColor)) $phTColor = '#ffffff';
            $phTFont = (string) preg_replace('/[^a-zA-Z0-9 :_\-]/', '', (string) ($phTE['font'] ?? ''));
            if (str_starts_with($phTFont, 'custom:')) $phTFont = substr($phTFont, 7);
            $phTexts[] = [
                'text'   => mb_substr($phTTxt, 0, 80),
                'font'   => $phTFont,
                'color'  => $phTColor,
                'pos'    => $phTPos,
                'size'   => max(10, min(64, (int) ($phTE['size'] ?? 20))),
                'rotate' => max(-180, min(180, (int) ($phTE['rotate'] ?? 0))),
                'dx'     => max(-80, min(80, (int) ($phTE['dx'] ?? 0))),
                'dy'     => max(-80, min(80, (int) ($phTE['dy'] ?? 0))),
            ];
            if (count($phTexts) >= \App\Modules\User\Models\BiolinkBlock::PHOTO_TEXT_STICKER_MAX) break;
        }

        // Anchor preset → CSS placement; dx/dy offsets + rotation ride on
        // the transform so the anchor rule stays static per preset.
        $phStickerAnchors = [
            'top_left'     => 'left:-10px;top:-10px',
            'top_right'    => 'right:-10px;top:-10px',
            'bottom_left'  => 'left:-10px;bottom:-10px',
            'bottom_right' => 'right:-10px;bottom:-10px',
            'center_left'  => 'left:-12px;top:50%',
            'center_right' => 'right:-12px;top:50%',
        ];

        $phDecorated = $phFrame || $phMask !== '' || $phBanner !== '' || !empty($phAccents) || !empty($phStickers) || !empty($phTexts);

        if ($phDecorated) {
            $phFrameColor = (string) ($phSt['_photo_frame_color'] ?? '') ?: '#57534e';
            $phStrokes = (int) ($phSt['_photo_frame_strokes'] ?? 0);
            $phStrokes = max(2, min(5, $phStrokes ?: 3));
            $phGap = 9;                                      // px between strokes
            $phPad = $phFrame ? ($phStrokes * $phGap + 6) : 0;
            $phBannerBg = (string) ($phSt['_photo_banner_bg'] ?? '') ?: '#2a201c';
            $phBannerColor = (string) ($phSt['_photo_banner_text_color'] ?? '') ?: '#ffffff';
            $phAccentColor = (string) ($phSt['_photo_accent_color'] ?? '') ?: '#3f4e63';

            // Clip applied to the photo box. The concentric-arch frame uses
            // the smooth border-radius arch (outline strokes hug it); the
            // standalone masks reuse the Image Styling clip paths.
            $phClip = '';
            if ($phFrame || $phMask === 'arch') {
                $phClip = 'border-radius:999px 999px 0 0;overflow:hidden';
            } elseif ($phMask === 'torn') {
                $phClip = 'clip-path:' . \App\Modules\User\Models\BiolinkBlock::MASK_CLIP_PATHS['torn'];
            }
        }
    @endphp
    @if($phDecorated)
        <div class="mb-4 relative" data-photo-hero
             style="padding:{{ $phPad }}px;{{ $phBanner !== '' ? 'margin-bottom:2.6rem;' : '' }}">
            @if($phFrame)
                {{-- Concentric arch outline strokes (open at the bottom). --}}
                @for($i = 0; $i < $phStrokes; $i++)
                    <div class="absolute pointer-events-none" aria-hidden="true" data-photo-frame-stroke
                         style="inset:{{ $i * $phGap }}px;bottom:0;border:1.5px solid {{ e($phFrameColor) }};border-bottom:none;border-radius:999px 999px 0 0;opacity:{{ 1 - $i * 0.12 }}"></div>
                @endfor
            @endif
            <div class="relative" @if($phClip !== '') style="{{ $phClip }}" @endif>
                @if($imgTrackUrl)<a href="{{ $imgTrackUrl }}" target="{{ $imgTarget }}" rel="{{ $imgRel }}"{{ $imgTitle ? ' title="'.e($imgTitle).'"' : '' }}>@endif
                <img src="{{ $s['url'] ?? '' }}" alt="{{ $s['alt'] ?? '' }}" class="w-full block" style="{{ $imgInline }}">
                @if($imgTrackUrl)</a>@endif
            </div>
            @if($phBanner !== '')
                {{-- Half-overlapping title banner straddling the bottom edge. --}}
                <div class="absolute z-10 text-center font-bold uppercase" data-photo-banner
                     style="left:50%;bottom:0;transform:translate(-50%,50%);background:{{ e($phBannerBg) }};color:{{ e($phBannerColor) }};padding:0.7rem 1.9rem;letter-spacing:0.14em;font-size:0.95rem;line-height:1.25;max-width:92%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    {{ $phBanner }}
                </div>
            @endif
            @php
                // Per-shape collage positions (unchanged look); the SVGs
                // themselves come from the shared AccentShapeCatalog.
                $phAccentPos = [
                    'starburst' => 'left:-8px;top:42%;transform:translateY(-50%)',
                    'dots'      => 'right:-6px;top:-10px',
                    'squiggle'  => 'left:-4px;bottom:-8px',
                    'ring'      => 'left:-10px;top:-8px',
                    'blob'      => 'right:-10px;bottom:-6px',
                ];
            @endphp
            @foreach($phAccents as $phAcc)
                @include('common.partials.accent-shape', [
                    'shape'    => $phAcc,
                    'color'    => $phAccentColor,
                    'posStyle' => $phAccentPos[$phAcc] ?? '',
                ])
            @endforeach
            @foreach($phStickers as $phStk)
                @php
                    $phStkT = 'translate(' . $phStk['dx'] . 'px,' . $phStk['dy'] . 'px)';
                    if (in_array($phStk['pos'], ['center_left', 'center_right'], true)) {
                        $phStkT = 'translateY(-50%) ' . $phStkT;
                    }
                    if ($phStk['rotate'] !== 0) {
                        $phStkT .= ' rotate(' . $phStk['rotate'] . 'deg)';
                    }
                @endphp
                <img src="{{ $phStk['url'] }}" alt="" aria-hidden="true" loading="lazy"
                     class="absolute pointer-events-none z-10" data-photo-sticker
                     style="{{ $phStickerAnchors[$phStk['pos']] }};width:{{ $phStk['size'] }}px;height:{{ $phStk['size'] }}px;object-fit:contain;transform:{{ $phStkT }}">
            @endforeach
            @foreach($phTexts as $phTx)
                @php
                    $phTxT = 'translate(' . $phTx['dx'] . 'px,' . $phTx['dy'] . 'px)';
                    if (in_array($phTx['pos'], ['center_left', 'center_right'], true)) {
                        $phTxT = 'translateY(-50%) ' . $phTxT;
                    }
                    if ($phTx['rotate'] !== 0) {
                        $phTxT .= ' rotate(' . $phTx['rotate'] . 'deg)';
                    }
                    $phTxFont = $phTx['font'] !== '' ? "font-family:'" . str_replace("'", '', $phTx['font']) . "';" : '';
                @endphp
                <span class="absolute pointer-events-none z-10 font-bold" data-photo-text-sticker
                      style="{{ $phStickerAnchors[$phTx['pos']] }};{{ $phTxFont }}color:{{ $phTx['color'] }};font-size:{{ $phTx['size'] }}px;line-height:1.15;white-space:nowrap;text-shadow:0 1px 6px rgba(0,0,0,0.35);transform:{{ $phTxT }}">{{ $phTx['text'] }}</span>
            @endforeach
        </div>
    @else
    <div class="mb-4 overflow-hidden{{ empty($imgSt['mask_shape']) || ($imgSt['mask_shape'] ?? 'none') === 'none' ? ' rounded-xl' : '' }}">
        @if($imgTrackUrl)<a href="{{ $imgTrackUrl }}" target="{{ $imgTarget }}" rel="{{ $imgRel }}"{{ $imgTitle ? ' title="'.e($imgTitle).'"' : '' }}>@endif
        <img src="{{ $s['url'] ?? '' }}" alt="{{ $s['alt'] ?? '' }}" class="w-full{{ empty($imgInline) ? ' rounded-xl' : '' }}" style="{{ $imgInline }}">
        @if($imgTrackUrl)</a>@endif
    </div>
    @endif
