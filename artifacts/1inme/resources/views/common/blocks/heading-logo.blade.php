    <div class="mb-3 text-{{ $s['align'] ?? 'center' }} flex items-center justify-{{ $s['align'] ?? 'center' }} gap-3">
        @if(!empty($s['logo_url']))<img src="{{ $s['logo_url'] }}" alt="" class="h-8 w-8 object-contain">@endif
        @php $hs = match($s['size'] ?? 'h2') { 'h1' => 'text-2xl md:text-3xl', 'h2' => 'text-xl md:text-2xl', 'h3' => 'text-lg md:text-xl', default => 'text-xl md:text-2xl' }; @endphp
        <h2 class="{{ $hs }} font-bold">{{ $s['text'] ?? '' }}</h2>
    </div>
