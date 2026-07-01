@php
    $frNumber = $fr['number'] ?? '';
    $frProfile = route('user.dialer.profile', ['number' => $frNumber, 'contact' => $fr['contact_id'] ?? null]);
@endphp
<div class="flex flex-col items-center flex-shrink-0 w-20 text-center">
    <a href="{{ $frProfile }}" class="flex flex-col items-center w-full">
        <div class="w-12 h-12 rounded-full flex items-center justify-center text-xs font-bold text-white mb-1" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">{{ $fr['initials'] }}</div>
        <div class="text-[11px] font-semibold truncate w-full" style="color:var(--text-primary);">{{ $fr['name'] }}</div>
        <div class="text-[10px]" style="color:var(--text-faint);">{{ $fr['calls'] }} calls @if($fr['is_spam'] ?? false)· <span style="color:#ef4444;">spam</span>@endif</div>
    </a>
    @if($frNumber)
    <div class="mt-1 w-full">@include('user.dialer._channel_actions', ['number' => $frNumber, 'size' => 'sm'])</div>
    @endif
</div>
