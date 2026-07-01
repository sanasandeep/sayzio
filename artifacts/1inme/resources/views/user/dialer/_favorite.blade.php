@php
    $profileUrl = route('user.dialer.profile', ['number' => $f['number'], 'contact' => $f['contact_id']]);
@endphp
<div id="fav-{{ $f['id'] }}" data-fav-id="{{ $f['id'] }}" draggable="true"
     ondragstart="favDragStart(event, {{ $f['id'] }})" ondragover="favDragOver(event)" ondrop="favDrop(event, {{ $f['id'] }})"
     class="relative group flex flex-col items-center text-center cursor-move">
    <a href="{{ $profileUrl }}" class="flex flex-col items-center w-full">
        <div class="w-14 h-14 rounded-full flex items-center justify-center text-sm font-bold text-white mb-1" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">{{ $f['initials'] }}</div>
        <div class="text-[11px] font-semibold truncate w-full" style="color:var(--text-primary);">{{ $f['label'] }}</div>
        @if($f['biolink'])<span class="text-[8px] font-bold" style="color:#f472b6;">Sayzio</span>@endif
    </a>
    @if(!empty($f['number']))
        <div class="mt-1 w-full">@include('user.dialer._channel_actions', ['number' => $f['number'], 'size' => 'sm'])</div>
    @endif
    <button type="button" onclick="removeFavorite(event, {{ $f['id'] }})" title="Remove favorite"
            class="absolute -top-1 -right-1 w-5 h-5 rounded-full text-[10px] opacity-0 group-hover:opacity-100 transition"
            style="background:rgba(239,68,68,.9);color:#fff;">
        <i class="fas fa-times"></i>
    </button>
</div>
