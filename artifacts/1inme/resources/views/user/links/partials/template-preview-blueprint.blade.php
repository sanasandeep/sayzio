{{--
    Shared shape-aware mini-blueprint for template previews.

    Renders the auto-generated `preview_layout` of a page/card template's
    top-level blocks as a thumbnail-sized blueprint: each row is a flex row
    whose children flex-grow proportional to their grid_span, with shape-aware
    cell rendering (avatar circles, pill buttons, stacked input lines, social
    dot rows, etc.) so the tile shows the page's actual content at a glance.

    Shared by the template picker gallery and the guided wizard's
    starting-design step so every surface reads the same. The `.tpl-prev-*`
    typography + shimmer CSS is emitted once per response via @once.

    Expects:
      $previewRows — array of rows, each an array of cell descriptors
                     (shape/bg/h/span/icon/text/... as produced by
                     TemplatePreviewLayoutBuilder).
--}}
@once
    <style>
        /* Mini page-preview placeholder typography — mirrors the card-templates
           gallery (editor-special-panel) so both previews read the same. White on
           the dark theme; dark ink under html.light-mode where the pale thumbnail
           background would wash white text out. Pill/button labels stay white
           because they sit on a coloured fill. */
        .tpl-prev-heading { font-size: 8px; font-weight: 700; line-height: 1.1; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tpl-prev-name    { font-size: 7.5px; font-weight: 700; line-height: 1.1; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tpl-prev-sub     { font-size: 6px; line-height: 1.15; color: rgba(255,255,255,0.6); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tpl-prev-text    { font-size: 6px; line-height: 1.3; color: rgba(255,255,255,0.6); display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden; }
        .tpl-prev-list    { font-size: 6px; line-height: 1.1; color: rgba(255,255,255,0.65); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tpl-prev-pill    { font-size: 6px; font-weight: 700; line-height: 1; }
        html.light-mode .tpl-prev-heading,
        html.light-mode .tpl-prev-name { color: rgba(7,20,55,0.88); }
        html.light-mode .tpl-prev-sub,
        html.light-mode .tpl-prev-text,
        html.light-mode .tpl-prev-list { color: rgba(7,20,55,0.55); }
        /* Loading shimmer behind media/avatar image cells until the thumbnail
           loads (mirrors the mobile picker's ShimmerOverlay). Sits absolutely
           behind the <img>, so it causes no layout shift; removed on (e)load. */
        .tpl-prev-shimmer { position: absolute; inset: 0; border-radius: inherit; overflow: hidden; background: rgba(255,255,255,0.06); z-index: 0; }
        .tpl-prev-shimmer::after { content: ""; position: absolute; inset: 0; transform: translateX(-100%); background: linear-gradient(90deg, transparent, rgba(255,255,255,0.16), transparent); animation: tpl-prev-shimmer-sweep 1.2s ease-in-out infinite; }
        html.light-mode .tpl-prev-shimmer { background: rgba(15,12,30,0.07); }
        html.light-mode .tpl-prev-shimmer::after { background: linear-gradient(90deg, transparent, rgba(255,255,255,0.55), transparent); }
        @keyframes tpl-prev-shimmer-sweep { 100% { transform: translateX(100%); } }
        @media (prefers-reduced-motion: reduce) { .tpl-prev-shimmer::after { animation: none; } }
    </style>
@endonce
<div class="w-full h-full px-2 py-1.5 flex flex-col gap-1 justify-center">
    @foreach($previewRows as $row)
        <div class="flex gap-1 w-full items-center">
            @foreach($row as $cell)
                @php
                    $shape = $cell['shape'] ?? 'tile';
                    $bg    = $cell['bg']    ?? 'rgba(255,255,255,0.10)';
                    $h     = (int) ($cell['h'] ?? 12);
                    $icon  = $cell['icon']  ?? '';
                    $lines = (int) ($cell['lines'] ?? 2);
                    $dots  = (int) ($cell['dots']  ?? 5);
                    $sub   = !empty($cell['sub']);
                    $btnBg = $cell['btn_bg'] ?? 'rgba(92,131,255,0.85)';
                    $text  = $cell['text'] ?? '';
                    $subText = $cell['sub_text'] ?? '';
                    $imgUrl = $cell['img'] ?? '';
                    $items = is_array($cell['items'] ?? null) ? $cell['items'] : [];
                    $play  = !empty($cell['play']);
                @endphp
                <div class="flex items-center justify-center" style="flex: {{ $cell['span'] }} 0 0;">
                    @switch($shape)
                        @case('heading')
                            <div class="w-full flex flex-col gap-[1px] items-center text-center">
                                @if($text !== '')
                                    <div class="tpl-prev-heading w-full">{{ $text }}</div>
                                @else
                                    <div class="rounded-[2px] w-full" style="background: {{ $bg }}; height: {{ $h }}px;"></div>
                                @endif
                                @if($sub && $subText !== '')
                                    <div class="tpl-prev-sub w-full">{{ $subText }}</div>
                                @elseif($sub)
                                    <div class="rounded-[2px]" style="background: {{ $bg }}; height: {{ max($h - 6, 4) }}px; width: 55%;"></div>
                                @endif
                            </div>
                            @break
                        @case('text_lines')
                            <div class="w-full flex flex-col gap-[2px] justify-center" style="min-height: {{ $h }}px;">
                                @if($text !== '')
                                    <div class="tpl-prev-text" style="-webkit-line-clamp: {{ max($lines, 1) }};">{{ $text }}</div>
                                @else
                                    @for($i = 1; $i <= max($lines, 1); $i++)
                                        <div class="rounded-[2px]" style="background: {{ $bg }}; height: 3px; width: {{ $i === max($lines, 1) ? '60%' : '100%' }};"></div>
                                    @endfor
                                @endif
                            </div>
                            @break
                        @case('pill')
                            <div class="w-full rounded-full flex items-center justify-center gap-1 px-1.5 text-white/95 tpl-prev-pill"
                                 style="background: {{ $bg }}; min-height: {{ $h }}px;">
                                @if($text !== '')<span class="truncate">{{ $text }}</span>@endif
                                @if($icon)<i class="fas {{ $icon }}" style="font-size: 6px;"></i>@endif
                            </div>
                            @break
                        @case('avatar')
                            <div class="w-full flex items-center gap-1.5" style="min-height: {{ $h }}px;">
                                @if($imgUrl !== '')
                                    <div class="relative rounded-full overflow-hidden shrink-0" style="width: {{ max($h - 8, 14) }}px; height: {{ max($h - 8, 14) }}px;">
                                        <div class="tpl-prev-shimmer"></div>
                                        <img src="{{ $imgUrl }}" alt="" loading="lazy" class="relative w-full h-full object-cover"
                                             onload="this.previousElementSibling && this.previousElementSibling.remove()" onerror="this.previousElementSibling && this.previousElementSibling.remove()">
                                    </div>
                                @else
                                    <div class="rounded-full flex items-center justify-center text-white/90 shrink-0"
                                         style="background: {{ $bg }}; width: {{ max($h - 8, 14) }}px; height: {{ max($h - 8, 14) }}px;">
                                        @if($icon)<i class="fas {{ $icon }}" style="font-size: 7px;"></i>@endif
                                    </div>
                                @endif
                                <div class="flex-1 flex flex-col gap-[1px] min-w-0">
                                    @if($text !== '')
                                        <div class="tpl-prev-name">{{ $text }}</div>
                                    @else
                                        <div class="rounded-[2px]" style="background: rgba(255,255,255,0.55); height: 4px; width: 70%;"></div>
                                    @endif
                                    @if($subText !== '')
                                        <div class="tpl-prev-sub">{{ $subText }}</div>
                                    @else
                                        <div class="rounded-[2px]" style="background: rgba(255,255,255,0.30); height: 3px; width: 50%;"></div>
                                    @endif
                                </div>
                            </div>
                            @break
                        @case('media')
                            <div class="w-full rounded-[3px] relative overflow-hidden flex items-center justify-center text-white/85"
                                 style="background: {{ $bg }}; min-height: {{ $h }}px; height: {{ $h }}px;">
                                @if($imgUrl !== '')
                                    <div class="tpl-prev-shimmer"></div>
                                    <img src="{{ $imgUrl }}" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover"
                                         onload="this.previousElementSibling && this.previousElementSibling.remove()" onerror="this.previousElementSibling && this.previousElementSibling.remove()">
                                @endif
                                @if($play || $imgUrl === '')
                                    <i class="fas {{ $play ? 'fa-play' : $icon }} relative" style="font-size: 11px;{{ $imgUrl !== '' ? ' text-shadow: 0 1px 3px rgba(0,0,0,0.6);' : '' }}"></i>
                                @endif
                            </div>
                            @break
                        @case('dot_row')
                            <div class="w-full flex items-center justify-center gap-1" style="min-height: {{ $h }}px;">
                                @for($i = 1; $i <= max($dots, 1); $i++)
                                    <div class="rounded-full" style="background: {{ $bg }}; width: 5px; height: 5px;"></div>
                                @endfor
                            </div>
                            @break
                        @case('form')
                            <div class="w-full flex flex-col gap-1 justify-center" style="min-height: {{ $h }}px;">
                                @for($i = 1; $i <= max($lines, 1); $i++)
                                    <div class="rounded-[2px] w-full" style="background: {{ $bg }}; height: 5px;"></div>
                                @endfor
                                <div class="rounded-full mx-auto flex items-center justify-center text-white/95 tpl-prev-pill px-1.5" style="background: {{ $btnBg }}; min-height: 7px; width: 70%;">
                                    @if($text !== '')<span class="truncate">{{ $text }}</span>@endif
                                </div>
                            </div>
                            @break
                        @case('list_rows')
                            @php $listRows = !empty($items) ? array_slice($items, 0, max($lines, 1)) : array_fill(0, max($lines, 1), null); @endphp
                            <div class="w-full flex flex-col gap-1 justify-center" style="min-height: {{ $h }}px;">
                                @foreach($listRows as $item)
                                    <div class="flex items-center gap-1 w-full">
                                        <div class="rounded-full shrink-0" style="background: {{ $bg }}; width: 3px; height: 3px;"></div>
                                        @if($item)
                                            <div class="tpl-prev-list flex-1">{{ $item }}</div>
                                        @else
                                            <div class="rounded-[2px] flex-1" style="background: {{ $bg }}; height: 3px;"></div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @break
                        @case('hairline')
                            <div class="w-full rounded-[2px]" style="background: {{ $bg }}; height: {{ $h }}px;"></div>
                            @break
                        @case('spacer')
                            <div class="w-full" style="min-height: {{ $h }}px;"></div>
                            @break
                        @case('badge')
                            <div class="rounded-full mx-auto" style="background: {{ $bg }}; height: {{ $h }}px; width: 50%;"></div>
                            @break
                        @default
                            <div class="w-full rounded-[3px] flex items-center justify-center text-white/70"
                                 style="background: {{ $bg }}; min-height: {{ $h }}px;">
                                @if($icon)<i class="fas {{ $icon }}" style="font-size: 8px;"></i>@endif
                            </div>
                    @endswitch
                </div>
            @endforeach
        </div>
    @endforeach
</div>
