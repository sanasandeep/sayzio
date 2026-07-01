@php
    $rNumber = $r['number'] ?? '';
    $rProfile = route('user.dialer.profile', ['number' => $rNumber, 'contact' => $r['contact_id'] ?? null]);
@endphp
<div class="flex items-center gap-2 px-3 py-2 rounded-xl" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);">
    <a href="{{ $rProfile }}" class="min-w-0 flex-1">
        <div class="text-sm font-semibold truncate flex items-center gap-1.5" style="color:var(--text-primary);">
            {{ $r['name'] }}
            @if(($r['calls'] ?? 0) > 1)<span class="text-[10px] px-1.5 rounded-full" style="background:rgba(61,107,255,.15);color:#90acff;">×{{ $r['calls'] }}</span>@endif
            @if($r['biolink'] ?? false)<span class="px-1 rounded text-[8px] font-bold" style="background:rgba(236,72,153,.15);color:#f472b6;">Sayzio</span>@endif
            @if($r['is_spam'] ?? false)<span class="px-1 rounded text-[8px] font-bold" style="background:rgba(239,68,68,.15);color:#ef4444;">SPAM</span>@endif
            @if($r['is_blocked'] ?? false)<span class="px-1 rounded text-[8px] font-bold" style="background:rgba(107,114,128,.2);color:#9ca3af;">BLOCKED</span>@endif
        </div>
        <div class="text-[11px] flex items-center gap-2" style="color:var(--text-faint);">
            <span>{{ $r['last_human'] ?? '' }}</span>
            @if($r['tag'] ?? false)<span class="px-1.5 rounded-full" style="background:rgba(255,255,255,.06);">{{ $r['tag'] }}</span>@endif
            @if($r['outcome'] ?? false)<span>· {{ str_replace('_',' ',$r['outcome']) }}</span>@endif
        </div>
    </a>
    @if($rNumber)
    <div class="flex-shrink-0">@include('user.dialer._channel_actions', ['number' => $rNumber, 'size' => 'md'])</div>
    @endif
</div>
