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
        $phDecorated = $phFrame || $phMask !== '' || $phBanner !== '' || !empty($phAccents);

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
                    <div class="absolute pointer-events-none" aria-hidden="true"
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
                <div class="absolute z-10 text-center font-bold uppercase"
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
        </div>
    @else
    <div class="mb-4 overflow-hidden{{ empty($imgSt['mask_shape']) || ($imgSt['mask_shape'] ?? 'none') === 'none' ? ' rounded-xl' : '' }}">
        @if($imgTrackUrl)<a href="{{ $imgTrackUrl }}" target="{{ $imgTarget }}" rel="{{ $imgRel }}"{{ $imgTitle ? ' title="'.e($imgTitle).'"' : '' }}>@endif
        <img src="{{ $s['url'] ?? '' }}" alt="{{ $s['alt'] ?? '' }}" class="w-full{{ empty($imgInline) ? ' rounded-xl' : '' }}" style="{{ $imgInline }}">
        @if($imgTrackUrl)</a>@endif
    </div>
    @endif
