{{-- Shared direct-action cluster for a phone number. Config-independent: every
     button is a plain device/deep-link handoff (tel/sms/wa.me/t.me/signal.me/
     viber) wired to the chanOpen() JS helper in the dialer page. Used by the
     keypad, favourites, frequent and recents so every surface offers the same
     one-tap channels (no Google needed). Only the channels the user picked
     (App\Modules\User\Support\DialerChannels) are rendered.
     Params: $number (E.164/raw), $size ('sm'|'md'). --}}
@php
    $chNum = trim((string) ($number ?? ''));
    $sz    = ($size ?? 'md') === 'sm' ? 'sm' : 'md';
    $btn   = $sz === 'sm' ? 'w-7 h-7' : 'w-8 h-8';
    $ico   = $sz === 'sm' ? 'text-[10px]' : 'text-xs';
    $chList = \App\Modules\User\Support\DialerChannels::enabledFor(auth()->user());
@endphp
@if($chNum !== '' && !empty($chList))
<div class="flex items-center justify-center flex-wrap gap-1">
    @foreach($chList as $ch)
    <button type="button" onclick="chanOpen('{{ $ch['js'] }}','{{ $chNum }}')" title="{{ $ch['label'] }}" class="{{ $btn }} rounded-full flex items-center justify-center" style="background:{{ $ch['color'] }}24;color:{{ $ch['color'] }};"><i class="{{ $ch['fa'] }} {{ $ico }}"></i></button>
    @endforeach
</div>
@endif
