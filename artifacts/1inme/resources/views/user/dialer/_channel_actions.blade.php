{{-- Shared direct-action cluster for a phone number. Config-independent: every
     button is a plain device/deep-link handoff (tel/sms/wa.me/t.me) wired to the
     chan* JS helpers in the dialer page. Used by keypad, favourites, frequent and
     recents so every surface offers the same one-tap channels (no Google needed).
     Params: $number (E.164/raw), $size ('sm'|'md'). --}}
@php
    $chNum = trim((string) ($number ?? ''));
    $sz    = ($size ?? 'md') === 'sm' ? 'sm' : 'md';
    $btn   = $sz === 'sm' ? 'w-7 h-7' : 'w-8 h-8';
    $ico   = $sz === 'sm' ? 'text-[10px]' : 'text-xs';
@endphp
@if($chNum !== '')
<div class="flex items-center justify-center flex-wrap gap-1">
    <button type="button" onclick="chanCall('{{ $chNum }}')" title="Call" class="{{ $btn }} rounded-full flex items-center justify-center" style="background:rgba(34,197,94,.14);color:#22c55e;"><i class="fas fa-phone {{ $ico }}"></i></button>
    <button type="button" onclick="chanSms('{{ $chNum }}')" title="Text message" class="{{ $btn }} rounded-full flex items-center justify-center" style="background:rgba(56,189,248,.14);color:#38bdf8;"><i class="fas fa-comment-sms {{ $ico }}"></i></button>
    <button type="button" onclick="chanWa('{{ $chNum }}')" title="Message on WhatsApp" class="{{ $btn }} rounded-full flex items-center justify-center" style="background:rgba(37,211,102,.14);color:#25d366;"><i class="fab fa-whatsapp {{ $ico }}"></i></button>
    <button type="button" onclick="chanWaCall('{{ $chNum }}')" title="WhatsApp call" class="{{ $btn }} rounded-full flex items-center justify-center" style="background:rgba(37,211,102,.14);color:#25d366;"><i class="fas fa-phone-volume {{ $ico }}"></i></button>
    <button type="button" onclick="chanTelegram('{{ $chNum }}')" title="Open in Telegram" class="{{ $btn }} rounded-full flex items-center justify-center" style="background:rgba(56,139,225,.16);color:#3390ec;"><i class="fab fa-telegram {{ $ico }}"></i></button>
</div>
@endif
