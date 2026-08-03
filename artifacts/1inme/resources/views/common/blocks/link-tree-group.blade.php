@php
    $items   = is_array($s['items'] ?? null) ? $s['items'] : [];
    $style   = is_array($s['_style'] ?? null) ? $s['_style'] : [];
    // Layout can come from a curated variant (stamped into the opaque
    // `_style._ltg_layout` hook) or from the block's own content setting.
    $layout  = $style['_ltg_layout'] ?? ($s['layout'] ?? ($s['_registry']['layout'] ?? 'list'));
    if (!in_array($layout, ['list', 'grid', 'text_divider'], true)) $layout = 'list';
    $align   = $style['_ltg_align'] ?? ($s['align'] ?? 'left');
    if (!in_array($align, ['left', 'center', 'right'], true)) $align = 'left';
    $title   = trim($s['title'] ?? '');
    $accent  = $s['accent_color'] ?? '#3d6bff';

    // Per-item click tracking (Task #6576): route every http(s) item URL
    // through the shared block-redirect pipeline so taps are recorded in
    // analytics exactly like single link blocks. Non-web schemes (mailto:,
    // tel:) stay raw — the redirect endpoint only forwards http/https.
    $alias = $alias ?? ($link->primary_alias ?? $link->alias ?? null);
    $ltgHref = function (array $it) use ($alias, $block) {
        $raw = trim((string) ($it['url'] ?? ''));
        if ($raw === '' ) return '#';
        if (!$alias || !preg_match('/^https?:\/\//i', $raw)) return $raw;
        $q = '?to=' . urlencode($raw);
        if (!empty($it['id'])) $q .= '&item=' . urlencode((string) $it['id']);
        return route('redirect.block', ['alias' => $alias, 'blockId' => $block->id]) . $q;
    };
    $alignClass = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'][$align];
@endphp

<div class="mb-4">
    @if($title !== '')
        <p class="text-sm font-semibold mb-2 px-1 {{ $layout === 'text_divider' ? $alignClass : '' }}" style="color: {{ $fontColor }};">{{ $title }}</p>
    @endif

    @if($layout === 'grid')
        <div class="grid grid-cols-2 gap-2">
            @foreach($items as $it)
                <a href="{{ $ltgHref($it) }}" target="_blank" rel="noopener"
                   class="rounded-xl px-3 py-3 text-center text-sm font-medium transition border hover:-translate-y-0.5"
                   style="background: {{ $fontColor }}08; border-color: {{ $fontColor }}1a; color: {{ $fontColor }};">
                    @if(!empty($it['icon']))<i class="{{ fa_icon_class($it['icon']) }} mr-1.5" style="color: {{ $accent }};"></i>@endif
                    <span class="truncate">{{ $it['text'] ?? 'Link' }}</span>
                </a>
            @endforeach
        </div>
    @elseif($layout === 'text_divider')
        {{-- Minimal "text + hairline divider" list (Task #6576): plain text
             labels, no button chrome, each row carries its own bottom
             hairline derived from currentColor so it stays legible on any
             page theme (same treatment as the single link block's
             text_divider layout). --}}
        <div class="w-full" data-ltg-layout="text_divider">
            @foreach($items as $it)
                <a href="{{ $ltgHref($it) }}" target="_blank" rel="noopener"
                   class="block w-full py-3.5 {{ $alignClass }} transition-opacity duration-200 hover:opacity-70"
                   style="color: {{ $fontColor }}; border-bottom: 1px solid color-mix(in srgb, currentColor 25%, transparent); font-weight: 500; font-size: 15px;">
                    @if(!empty($it['icon']))<i class="{{ fa_icon_class($it['icon']) }} mr-2 text-[0.85em] opacity-80"></i>@endif{{ $it['text'] ?? 'Link' }}
                </a>
            @endforeach
        </div>
    @else
        <div class="space-y-2">
            @foreach($items as $it)
                <a href="{{ $ltgHref($it) }}" target="_blank" rel="noopener"
                   class="block rounded-xl px-4 py-3 transition border hover:-translate-y-0.5"
                   style="background: {{ $fontColor }}08; border-color: {{ $fontColor }}1a; color: {{ $fontColor }};">
                    <div class="flex items-center gap-3">
                        @if(!empty($it['icon']))<i class="{{ fa_icon_class($it['icon']) }} w-5 text-center" style="color: {{ $accent }};"></i>@endif
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate">{{ $it['text'] ?? 'Link' }}</div>
                            @if(!empty($it['description']))<div class="text-xs opacity-60 truncate">{{ $it['description'] }}</div>@endif
                        </div>
                        <i class="fas fa-arrow-right text-xs opacity-40"></i>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
