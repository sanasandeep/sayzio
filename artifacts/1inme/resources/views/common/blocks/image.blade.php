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
            @foreach($phAccents as $phAcc)
                @if($phAcc === 'starburst')
                    <svg class="absolute pointer-events-none z-10" aria-hidden="true" viewBox="0 0 100 100" width="54" height="54" style="left:-8px;top:42%;transform:translateY(-50%)" fill="{{ e($phAccentColor) }}">
                        <path d="M50 0 L56 33 L75 7 L63 38 L96 22 L67 44 L100 50 L67 56 L96 78 L63 62 L75 93 L56 67 L50 100 L44 67 L25 93 L37 62 L4 78 L33 56 L0 50 L33 44 L4 22 L37 38 L25 7 L44 33 Z"/>
                    </svg>
                @elseif($phAcc === 'dots')
                    <svg class="absolute pointer-events-none z-10" aria-hidden="true" viewBox="0 0 90 90" width="76" height="76" style="right:-6px;top:-10px" fill="{{ e($phAccentColor) }}">
                        <circle cx="78" cy="10" r="6"/><circle cx="58" cy="18" r="4.5"/><circle cx="76" cy="30" r="4"/><circle cx="44" cy="10" r="3.5"/><circle cx="62" cy="38" r="3.2"/><circle cx="82" cy="46" r="3"/><circle cx="48" cy="28" r="2.6"/><circle cx="70" cy="54" r="2.4"/><circle cx="34" cy="20" r="2.2"/><circle cx="56" cy="50" r="2"/><circle cx="84" cy="62" r="2"/><circle cx="42" cy="42" r="1.8"/><circle cx="66" cy="68" r="1.6"/><circle cx="78" cy="76" r="1.4"/>
                    </svg>
                @elseif($phAcc === 'squiggle')
                    <svg class="absolute pointer-events-none z-10" aria-hidden="true" viewBox="0 0 120 40" width="84" height="28" style="left:-4px;bottom:-8px" fill="none" stroke="{{ e($phAccentColor) }}" stroke-width="5" stroke-linecap="round">
                        <path d="M5 30 Q20 5 35 25 T65 22 T95 24 T115 15"/>
                    </svg>
                @elseif($phAcc === 'ring')
                    <svg class="absolute pointer-events-none z-10" aria-hidden="true" viewBox="0 0 60 60" width="46" height="46" style="left:-10px;top:-8px" fill="none" stroke="{{ e($phAccentColor) }}" stroke-width="6">
                        <circle cx="30" cy="30" r="24"/>
                    </svg>
                @elseif($phAcc === 'blob')
                    <svg class="absolute pointer-events-none z-10" aria-hidden="true" viewBox="0 0 100 100" width="58" height="58" style="right:-10px;bottom:-6px" fill="{{ e($phAccentColor) }}">
                        <path d="M83 45 C90 62 78 84 58 88 C38 92 16 82 12 62 C8 42 22 20 44 14 C66 8 76 28 83 45 Z"/>
                    </svg>
                @endif
            @endforeach
        </div>
    @else
    <div class="mb-4 overflow-hidden{{ empty($imgSt['mask_shape']) || ($imgSt['mask_shape'] ?? 'none') === 'none' ? ' rounded-xl' : '' }}">
        @if($imgTrackUrl)<a href="{{ $imgTrackUrl }}" target="{{ $imgTarget }}" rel="{{ $imgRel }}"{{ $imgTitle ? ' title="'.e($imgTitle).'"' : '' }}>@endif
        <img src="{{ $s['url'] ?? '' }}" alt="{{ $s['alt'] ?? '' }}" class="w-full{{ empty($imgInline) ? ' rounded-xl' : '' }}" style="{{ $imgInline }}">
        @if($imgTrackUrl)</a>@endif
    </div>
    @endif
