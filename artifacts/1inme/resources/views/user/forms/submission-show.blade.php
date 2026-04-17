@extends('user.layouts.app')
@section('title', 'Submission #' . $submission->id)

@section('content')
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Submission #' . $submission->id,
        'subtitle' => $form->title,
        'icon' => 'fa-envelope-open-text',
        'back' => route('user.forms.submissions', $form),
        'chips' => [
            ['icon' => 'fa-clock', 'text' => $submission->created_at->format('M d, Y H:i')],
            ['icon' => 'fa-network-wired', 'text' => $submission->ip ?? 'unknown ip'],
        ],
        'actions' => [
            ['label' => 'Back to inbox', 'url' => route('user.forms.submissions', $form), 'icon' => 'fa-arrow-left', 'class' => 'btn-ghost'],
        ],
    ])

    <div class="card-premium p-6 mb-6">
        <h3 class="text-sm font-bold mb-5" style="color: var(--text-primary);">Submitted Data</h3>
        <dl class="space-y-3">
            @foreach($submission->data ?? [] as $k => $v)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 py-3 border-b" style="border-color: var(--border-subtle);">
                    <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--text-faint);">{{ str_replace('_', ' ', $k) }}</dt>
                    <dd class="sm:col-span-2 text-sm" style="color: var(--text-primary);">
                        @if(is_array($v))
                            <ul class="list-disc pl-4">@foreach($v as $vv)<li>{{ $vv }}</li>@endforeach</ul>
                        @elseif(is_bool($v))
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $v ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400' }}">{{ $v ? 'Yes' : 'No' }}</span>
                        @elseif(filter_var($v, FILTER_VALIDATE_EMAIL))
                            <a href="mailto:{{ $v }}" class="text-violet-400 hover:underline">{{ $v }}</a>
                        @else
                            <span class="whitespace-pre-line">{{ $v }}</span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>

        @if(!empty($submission->files))
            <h4 class="text-xs font-bold uppercase tracking-wider mt-6 mb-3" style="color: var(--text-faint);">Attachments</h4>
            <div class="space-y-2">
                @foreach($submission->files as $field => $url)
                    <a href="{{ $url }}" target="_blank" class="flex items-center gap-3 p-3 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                        <i class="fas fa-paperclip text-violet-400"></i>
                        <span class="text-sm flex-1" style="color: var(--text-primary);">{{ $submission->data[$field] ?? $field }}</span>
                        <i class="fas fa-download text-xs" style="color: var(--text-faint);"></i>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card-premium p-5">
        <h4 class="text-xs font-bold uppercase tracking-wider mb-3" style="color: var(--text-faint);">Metadata</h4>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div><dt style="color: var(--text-faint);">IP Address</dt><dd class="font-mono" style="color: var(--text-secondary);">{{ $submission->ip ?? '—' }}</dd></div>
            <div><dt style="color: var(--text-faint);">Country</dt><dd style="color: var(--text-secondary);">{{ $submission->country ?? '—' }}</dd></div>
            <div class="sm:col-span-2"><dt style="color: var(--text-faint);">User Agent</dt><dd class="break-all" style="color: var(--text-secondary);">{{ $submission->user_agent ?? '—' }}</dd></div>
            <div class="sm:col-span-2"><dt style="color: var(--text-faint);">Referrer</dt><dd class="break-all" style="color: var(--text-secondary);">{{ $submission->referrer ?? '—' }}</dd></div>
        </dl>
    </div>
</div>
@endsection
