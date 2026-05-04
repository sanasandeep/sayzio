    <div class="mb-4 glass-block rounded-xl p-5 text-center">
        <p class="text-sm font-medium mb-1">{{ $s['title'] ?? '' }}</p>
        <div class="flex items-baseline justify-center gap-1"><span class="text-3xl font-bold">{{ $s['amount'] ?? '' }}</span><span class="text-sm text-white/40">{{ $s['period'] ?? '' }}</span></div>
        @if(!empty($s['features']))<ul class="mt-3 space-y-1.5 text-sm text-left">@foreach(($s['features'] ?? []) as $f)<li class="flex items-center gap-2"><i class="fas fa-check text-green-400 text-xs"></i><span style="color:{{ $fontColor }}cc">{{ $f }}</span></li>@endforeach</ul>@endif
        @if(!empty($s['url']))<a href="{{ $s['url'] }}" target="_blank" class="bio-btn block w-full mt-4 py-2.5 text-sm font-medium">Get Started</a>@endif
    </div>
