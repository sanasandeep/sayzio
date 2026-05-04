@extends('user.layouts.app')
@section('title', 'Quarantined · ' . $file->original_name)
@section('content')
@php
    $highRisk = $file->isHighRiskExtension();
    $confirmUrl = $file->url_path . '?confirm=1';
@endphp
<div class="max-w-2xl mx-auto">
    <div class="card-premium p-8" style="border: 1px solid rgba(239,68,68,0.35);">
        <div class="flex items-start gap-4 mb-5">
            <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0"
                 style="background: rgba(239,68,68,0.15);">
                <i class="fas fa-shield-exclamation text-2xl" style="color: #f87171;"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-[10px] uppercase font-bold tracking-wider mb-1" style="color: #f87171;">
                    Attachment quarantined
                </div>
                <h2 class="text-lg font-bold mb-1" style="color: var(--text-primary);">{{ $file->original_name }}</h2>
                <div class="text-xs" style="color: var(--text-muted);">
                    {{ $file->scanReasonLabel() }}
                </div>
            </div>
        </div>

        <p class="text-sm mb-4" style="color: var(--text-secondary);">
            Our scanner flagged this file as potentially harmful. Opening it could
            put your device at risk.
        </p>

        @if($highRisk)
            <div class="mb-4 px-4 py-3 rounded-lg text-xs"
                 style="background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.35); color: #fca5a5;">
                <i class="fas fa-triangle-exclamation mr-1"></i>
                <strong>High-risk file type.</strong> This kind of file can run code on your
                computer when opened. Only continue if you trust the sender and recognise the file.
            </div>
        @endif

        @if(!$isOwner)
            <div class="mb-4 px-4 py-3 rounded-lg text-xs"
                 style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);">
                Only the workspace owner can override the quarantine on a flagged file.
            </div>
        @endif

        <div class="flex flex-wrap gap-2 mt-6">
            <a href="{{ url()->previous() }}" class="px-4 py-2 rounded-lg text-xs font-semibold"
               style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                <i class="fas fa-arrow-left mr-1"></i>Back to safety
            </a>
            @if($isOwner)
                <a href="{{ $confirmUrl }}"
                   onclick="return confirm({{ $highRisk ? "'This file type can run code on your computer. Are you SURE you want to download it?'" : "'Download this quarantined file anyway?'" }});"
                   class="px-4 py-2 rounded-lg text-xs font-semibold text-white"
                   style="background: linear-gradient(135deg,#dc2626,#991b1b);">
                    <i class="fas fa-download mr-1"></i>Download anyway (I accept the risk)
                </a>
            @endif
        </div>
    </div>
</div>
@endsection
