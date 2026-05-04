    <div class="mb-4 space-y-2" x-data="{ open: null }">
        @foreach(($s['items'] ?? []) as $i => $item)
        <div class="glass-block rounded-xl overflow-hidden {{ $block->type === 'faq_v2' ? 'border border-white/10' : '' }}">
            <button @click="open = open === {{ $i }} ? null : {{ $i }}" class="w-full px-4 py-3 flex items-center justify-between text-left">
                <span class="text-sm font-medium flex items-center gap-2">@if(!empty($item['icon']))<i class="{{ fa_icon_class($item['icon']) }}"></i>@endif{{ $item['question'] ?? '' }}</span>
                <i class="fas fa-chevron-down text-xs transition-transform" :class="open === {{ $i }} ? 'rotate-180' : ''"></i>
            </button>
            <div x-show="open === {{ $i }}" x-cloak class="px-4 pb-3"><p class="text-sm" style="color:{{ $fontColor }}99">{{ $item['answer'] ?? '' }}</p></div>
        </div>
        @endforeach
    </div>
