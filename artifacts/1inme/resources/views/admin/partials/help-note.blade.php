{{--
    Shared inline help-note callout for admin settings pages.

    Usage:
        @include('admin.partials.help-note', ['body' => 'Plain text or <a href="…">HTML</a>.'])
        @include('admin.partials.help-note', ['type' => 'warn', 'body' => 'Warning message.'])
        @include('admin.partials.help-note', ['type' => 'tip',  'body' => 'Tip message.'])

    Props:
        type  – 'info' (default, blue) | 'warn' (amber) | 'tip' (emerald)
        icon  – optional FA class override, e.g. 'fas fa-key'
        body  – required; HTML allowed (content originates from trusted views only)
--}}
@php
    $noteType  = $type  ?? 'info';
    $noteIcon  = $icon  ?? match($noteType) {
        'warn'  => 'fas fa-triangle-exclamation',
        'tip'   => 'fas fa-lightbulb',
        default => 'fas fa-circle-info',
    };
    $noteWrap  = match($noteType) {
        'warn'  => 'bg-amber-500/10 border-amber-500/20',
        'tip'   => 'bg-emerald-500/10 border-emerald-500/20',
        default => 'bg-blue-500/10 border-blue-500/20',
    };
    $noteText  = match($noteType) {
        'warn'  => 'text-amber-200/85',
        'tip'   => 'text-emerald-200/85',
        default => 'text-blue-200/85',
    };
    $noteIconC = match($noteType) {
        'warn'  => 'text-amber-400',
        'tip'   => 'text-emerald-400',
        default => 'text-blue-400',
    };
@endphp
<div class="flex items-start gap-2.5 p-3 rounded-xl border {{ $noteWrap }} {{ $noteText }} text-xs leading-relaxed">
    <i class="{{ $noteIcon }} {{ $noteIconC }} mt-0.5 shrink-0 text-[13px]"></i>
    <div class="min-w-0 space-y-1">{!! $body !!}</div>
</div>
