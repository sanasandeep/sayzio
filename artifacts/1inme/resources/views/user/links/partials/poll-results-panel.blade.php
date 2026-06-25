{{--
    Inline poll results panel rendered under each poll block in the
    biolink editor so the creator sees the same per-option counts and
    percentages that voters see, without leaving the editor. The
    aggregate is computed once per page render in
    BiolinkBlockController::editor and passed in as $tally
    (['counts' => [index => count], 'total' => int]) so this partial
    stays stateless and cheap. Counts refresh on the next page reload —
    the task spec explicitly does not require live websockets.

    Inputs:
      - $block:   the poll BiolinkBlock model (top-level or child)
      - $tally:   ['counts' => [int => int], 'total' => int] | null
      - $compact: bool — when true, render a tighter variant suitable for
                  use inside a card-container's child block list
--}}
@php
    $compact = $compact ?? false;
    $settings = $block->settings ?? [];
    $rawOptions = $settings['options'] ?? $settings['choices'] ?? $settings['items'] ?? [];
    $labels = [];
    foreach ((array) $rawOptions as $opt) {
        if (is_string($opt)) {
            $labels[] = $opt;
        } elseif (is_array($opt)) {
            $labels[] = (string) ($opt['label'] ?? $opt['text'] ?? $opt['title'] ?? $opt['name'] ?? '');
        }
    }
    // Only count votes whose option_index still maps to a current
    // option, mirroring the viewer-facing pollResults API. Otherwise a
    // creator who shrank or reordered the option list would see
    // percentages that don't add up to 100, with no row to explain
    // the missing share — and the creator's totals would diverge from
    // the totals shown to voters on the public page.
    $rawCounts = $tally['counts'] ?? [];
    $counts = [];
    $total = 0;
    foreach ($labels as $i => $_label) {
        $c = (int) ($rawCounts[$i] ?? 0);
        $counts[$i] = $c;
        $total += $c;
    }
@endphp
<div class="poll-results-panel {{ $compact ? 'px-2 pb-1.5' : 'px-3 pb-3' }}">
    <div class="rounded-lg" style="border: 1px solid var(--border-glass); background: rgba(61,107,255,0.04);">
        <div class="flex items-center justify-between px-2.5 {{ $compact ? 'py-1' : 'py-1.5' }}" style="border-bottom: 1px solid var(--border-glass);">
            <span class="font-semibold {{ $compact ? 'text-[9px]' : 'text-[10px]' }}" style="color: var(--text-faint);">
                <i class="fas fa-chart-bar mr-1"></i>Poll results
            </span>
            <span class="font-semibold {{ $compact ? 'text-[9px]' : 'text-[10px]' }}" style="color: var(--text-muted);">
                {{ $total }} {{ $total === 1 ? 'vote' : 'votes' }}
            </span>
        </div>
        <div class="{{ $compact ? 'p-1.5 space-y-1' : 'p-2 space-y-1.5' }}">
            @if(empty($labels))
                <div class="text-center {{ $compact ? 'text-[9px] py-1' : 'text-[10px] py-2' }}" style="color: var(--text-dimmed);">
                    Add options to this poll to start collecting votes.
                </div>
            @else
                @foreach($labels as $i => $label)
                    @php
                        $c = (int) ($counts[$i] ?? 0);
                        $pct = $total > 0 ? (int) round(($c / $total) * 100) : 0;
                        $width = $total > 0 ? max(2, $pct) : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-0.5">
                            <span class="truncate {{ $compact ? 'text-[10px]' : 'text-[11px]' }}" style="color: var(--text-primary);">{{ $label !== '' ? $label : 'Option ' . ($i + 1) }}</span>
                            <span class="flex-shrink-0 font-semibold {{ $compact ? 'text-[9px]' : 'text-[10px]' }}" style="color: var(--text-muted);">
                                {{ $c }} · {{ $pct }}%
                            </span>
                        </div>
                        <div class="rounded-full overflow-hidden" style="height: {{ $compact ? '4px' : '6px' }}; background: rgba(255,255,255,0.04);">
                            <div class="h-full rounded-full transition-all" style="width: {{ $width }}%; background: linear-gradient(90deg, #5c83ff, #3d6bff);"></div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
