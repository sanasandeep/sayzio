    @php
        $tipMeta = match($block->type) {
            'buy_me_coffee' => ['base' => 'https://www.buymeacoffee.com/', 'icon' => 'fa-coffee',           'bg' => '#FFDD00', 'fg' => '#0D0C22', 'unit' => '☕', 'label' => 'Buy me a coffee'],
            'patreon'       => ['base' => 'https://www.patreon.com/',      'icon' => 'fa-hand-holding-usd', 'bg' => '#F96854', 'fg' => '#fff',    'unit' => '★', 'label' => 'Become a patron'],
            'ko_fi'         => ['base' => 'https://ko-fi.com/',            'icon' => 'fa-mug-hot',          'bg' => '#FF5E5B', 'fg' => '#fff',    'unit' => '☕', 'label' => 'Support on Ko-fi'],
        };
        $username = ltrim((string)($s['username'] ?? ''), '@/');
        $tipUrl = $username !== '' ? $tipMeta['base'] . $username : '#';
        // Inline widget preview: numeric "tip jar" amounts for buy_me_coffee
        // and ko_fi (each becomes a deep-link); a single tier name preview
        // chip for patreon. Defaults give a useful experience even when the
        // creator hasn't customised them yet.
        $amounts = is_array($s['amounts'] ?? null) ? array_values(array_filter(array_map('intval', $s['amounts']), fn($n) => $n > 0)) : [];
        if (empty($amounts) && in_array($block->type, ['buy_me_coffee', 'ko_fi'], true)) {
            $amounts = [1, 3, 5];
        }
        $tierName = trim((string)($s['tier_name'] ?? ''));
    @endphp
    <div class="mb-3 rounded-2xl overflow-hidden" style="background: {{ $tipMeta['bg'] }}; color: {{ $tipMeta['fg'] }};">
        <a href="{{ $tipUrl }}" target="_blank" rel="noopener" class="block px-5 py-4 flex items-center gap-3">
            <i class="fas {{ $tipMeta['icon'] }} text-2xl"></i>
            <div class="flex-1 min-w-0">
                <div class="font-semibold truncate">{{ $s['text'] ?? $tipMeta['label'] }}</div>
                @if($username !== '')
                    <div class="text-xs opacity-70 truncate">@{{ $username }}</div>
                @endif
            </div>
            <i class="fas fa-arrow-right opacity-60"></i>
        </a>
        @if(!empty($s['description']))
            <div class="px-5 pb-3 text-xs opacity-80">{{ $s['description'] }}</div>
        @endif
        @if($block->type === 'patreon' && $tierName !== '')
            <div class="px-5 pb-4">
                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold" style="background: rgba(0,0,0,.2);">
                    <i class="fas fa-crown"></i> {{ $tierName }} tier
                </span>
            </div>
        @elseif(in_array($block->type, ['buy_me_coffee', 'ko_fi'], true) && $username !== '' && !empty($amounts))
            <div class="px-5 pb-4 flex flex-wrap gap-2">
                @foreach(array_slice($amounts, 0, 4) as $amt)
                    <a href="{{ $tipUrl }}/{{ $block->type === 'ko_fi' ? '?amount=' . $amt : (int)$amt }}"
                       target="_blank" rel="noopener"
                       class="px-3 py-1.5 rounded-full text-xs font-semibold transition hover:-translate-y-0.5"
                       style="background: rgba(0,0,0,.18); color: inherit;">
                        {{ $tipMeta['unit'] }} ${{ (int)$amt }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
