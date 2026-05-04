    @php
        $headingStyle = $s['style'] ?? 'plain';
        $hs = match($s['size'] ?? 'h2') { 'h1' => 'text-2xl md:text-3xl', 'h2' => 'text-xl md:text-2xl', 'h3' => 'text-lg md:text-xl', default => 'text-xl md:text-2xl' };
    @endphp
    <div class="mb-3 text-{{ $s['align'] ?? 'center' }}">
        @if($headingStyle === 'gradient')
            <h2 class="{{ $hs }} font-bold bg-clip-text text-transparent" style="background-image: linear-gradient(to right, {{ $s['from_color'] ?? '#7c3aed' }}, {{ $s['to_color'] ?? '#ec4899' }});">{{ $s['text'] ?? '' }}</h2>
        @elseif($headingStyle === 'animated')
            <h2 class="{{ $hs }} font-bold morph-text">{{ $s['text'] ?? '' }}</h2>
        @else
            <h2 class="{{ $hs }} font-bold">{{ $s['text'] ?? '' }}</h2>
        @endif
    </div>
