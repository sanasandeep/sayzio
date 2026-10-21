@extends('user.layouts.app')
@section('title', 'Submissions · ' . $form->title)

@section('content')
@php
    $__user = auth()->user();
    $__ws = app()->bound('current_workspace') ? app('current_workspace') : null;
    $__can = fn($p) => $__user && $__ws ? $__user->canInWorkspace($__ws, $p) : false;
@endphp
<div class="forms-workspace">
    @include('user.partials.page-hero', [
        'title' => 'Submissions',
        'subtitle' => $form->title,
        'icon' => 'fa-inbox',
        'back' => route('user.forms.show', $form),
        'chips' => [
            ['icon' => 'fa-database text-pink-400', 'text' => number_format($form->total_submissions) . ' total'],
        ],
        'actions' => [
            ['label' => 'Export CSV', 'url' => route('user.forms.submissions.export', array_merge(['form'=>$form->id], request()->query())), 'icon' => 'fa-file-csv', 'class' => 'btn-ghost'],
        ],
    ])

    @include('user.forms._tabs')
    @if($errors->any())<p class="card-premium p-4 mb-4 text-red-500" role="alert">{{ $errors->first() }}</p>@endif

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
        <i class="fas fa-exclamation-circle mr-1.5"></i> {{ session('error') }}
    </div>
    @endif

    @if($__can('inbox.delete'))
    {{-- Erase a single submitter's history (GDPR-style takedown). Matches by
         email or any other identifier captured by your form fields, across
         every form this creator owns. --}}
    <div class="card-premium p-5 mb-6">
        <div class="flex items-start gap-3 mb-3">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background: rgba(239,68,68,0.15); color: #ef4444;">
                <i class="fas fa-user-slash"></i>
            </div>
            <div>
                <div class="text-sm font-semibold" style="color: var(--text-primary);">Erase a submitter's history</div>
                <div class="text-xs mt-0.5" style="color: var(--text-muted);">
                    Removes every submission tied to a single submitter across all of your forms. Search by email, user id, or any other value captured in your form fields.
                </div>
            </div>
        </div>
        <form method="POST" action="{{ route('user.forms.submissions.erase-submitter', $form) }}"
              class="flex flex-col sm:flex-row gap-2"
              onsubmit="return window.themedConfirmSubmit(this, {title: 'Erase every submission from this submitter?', message: 'Every submission matching this submitter, across all your forms, will be permanently deleted. This cannot be undone.', confirmText: 'Erase submissions', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
            @csrf
            <input type="text" name="identifier" required maxlength="255"
                   placeholder="email@example.com, user id, or fingerprint"
                   class="flex-1 px-3 py-2 rounded-lg text-sm"
                   style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold inline-flex items-center justify-center gap-1.5"
                    style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.25); color: #ef4444;">
                <i class="fas fa-eraser"></i> Erase submitter
            </button>
        </form>
    </div>
    @endif

    <form method="GET" class="card-premium p-5 mb-5 flex flex-wrap gap-3 items-end">
        <label class="text-sm">Search answers<input class="theme-input block mt-2" name="q" value="{{ request('q') }}" placeholder="Name, email or answer"></label>
        <label class="text-sm">From<input class="theme-input block mt-2" type="date" name="from" value="{{ request('from') }}"></label><label class="text-sm">To<input class="theme-input block mt-2" type="date" name="to" value="{{ request('to') }}"></label>
        <details><summary class="form-chip">+ Answer filter</summary><div class="p-3 space-y-2"><select name="answer_field" class="theme-input"><option value="">Any question</option>@foreach($form->fields??[] as $f)@if(!in_array($f['type'],['heading','paragraph','divider','page_break','section']))<option value="{{ $f['id'] }}" @selected(request('answer_field')===$f['id'])>{{ $f['label']??$f['id'] }}</option>@endif @endforeach</select><select name="answer_state" class="theme-input">@foreach(['filled'=>'Has answer','empty'=>'Empty / missing','equals'=>'Equals'] as $value=>$label)<option value="{{ $value }}" @selected(request('answer_state')===$value)>{{ $label }}</option>@endforeach</select><input name="answer_value" class="theme-input" value="{{ request('answer_value') }}" placeholder="Exact answer for Equals"></div></details>
        <select name="filter" class="theme-input"><option value="">Completed · non-spam</option>@foreach(['unread'=>'Unread','starred'=>'Starred','spam'=>'Spam'] as $v=>$label)<option value="{{ $v }}" @selected(request('filter')===$v)>{{ $label }}</option>@endforeach</select>
        <select name="payment" class="theme-input"><option value="">All payment states</option>@foreach(['paid','unpaid','refunded'] as $v)<option @selected(request('payment')===$v) value="{{ $v }}">{{ ucfirst($v) }}</option>@endforeach</select>
        <details><summary class="form-chip">+ Export columns</summary><div class="p-3" style="max-height:240px;overflow:auto">@foreach($form->fields??[] as $f)@if(!in_array($f['type'],['heading','paragraph','divider','page_break']))<label class="block text-sm py-1"><input type="checkbox" name="columns[]" value="{{ $f['id'] }}" @checked(in_array($f['id'],request('columns',[])))> {{ $f['label']??$f['id'] }}</label>@endif @endforeach</div></details>
        <select name="format" class="theme-input">@foreach(['csv'=>'CSV · wide table','tsv'=>'TSV · spreadsheet','long_csv'=>'CSV · one answer per row','json'=>'JSON · structured','ndjson'=>'NDJSON · stream'] as $v=>$label)<option value="{{ $v }}" @selected(request('format')===$v)>{{ $label }}</option>@endforeach</select>
        <button class="btn-primary">Apply filters</button><button class="btn-ghost" formaction="{{ route('user.forms.submissions.export',$form) }}">Export matching</button><a class="form-chip" href="{{ route('user.forms.submissions',$form) }}">Reset</a>
        <p class="text-xs w-full">Exports use these same filters across all matching pages. No columns selected means all columns. Empty matches missing values, blank text and empty arrays/objects.</p>
    </form>
    {{-- Filter pills --}}
    <div class="flex items-center gap-2 mb-5 flex-wrap">
        @foreach(['' => 'All', 'unread' => 'Unread', 'starred' => 'Starred', 'spam' => 'Spam'] as $val => $label)
            @php $active = (request('filter') ?? '') === $val; @endphp
            <a href="{{ route('user.forms.submissions',array_merge(['form'=>$form->id],request()->except('page'),['filter'=>$val])) }}" class="text-xs px-3 py-1.5 rounded-full font-semibold" style="{{ $active ? 'background: linear-gradient(135deg,#5c83ff,#2342c7); color:white;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if($submissions->isEmpty())
        <div class="card-premium p-12 text-center">
            <i class="fas fa-inbox text-4xl mb-3" style="color: var(--text-faint);"></i>
            <p class="text-sm" style="color: var(--text-muted);">No submissions match this filter yet.</p>
        </div>
    @else
        <div class="card-premium overflow-hidden">
            <div class="divide-y" style="border-color: var(--border-glass);">
                @foreach($submissions as $s)
                    @php
                        $name = $s->data['name'] ?? $s->data['email'] ?? '#' . $s->id;
                        // Repeatable-group fields are stored as {_repeatable_group:true, copies:[...]}.
                        // Surface them as a compact "N copies" badge that expands the numbered
                        // copy sub-cards inline (same layout as submission-show.blade.php).
                        $repGroups = collect($s->data ?? [])->filter(fn($v) => is_array($v) && !empty($v['_repeatable_group']));
                        $repCopyTotal = (int) $repGroups->sum(fn($v) => is_array($v['copies'] ?? null) ? count($v['copies']) : 0);
                        $hasRepGroups = $repGroups->isNotEmpty();
                    @endphp
                    <div @if($hasRepGroups) x-data="{ expanded: false }" @endif>
                    <div class="flex items-center gap-3 p-4 hover:bg-blue-500/5 transition-colors {{ !$s->is_read ? 'bg-blue-500/5' : '' }}">
                        @if($__can('inbox.edit'))
                        <form method="POST" action="{{ route('user.forms.submissions.star', [$form, $s]) }}">@csrf
                            <button class="text-base {{ $s->is_starred ? 'text-amber-400' : '' }}" style="color: {{ $s->is_starred ? '' : 'var(--text-faint)' }};" title="Star">
                                <i class="fa{{ $s->is_starred ? 's' : 'r' }} fa-star"></i>
                            </button>
                        </form>
                        @else
                        <span class="text-base cursor-not-allowed opacity-50" style="color: var(--text-faint);" title="Your role doesn't allow starring submissions, ask a workspace admin">
                            <i class="fa{{ $s->is_starred ? 's' : 'r' }} fa-star"></i>
                        </span>
                        @endif
                        <a href="{{ route('user.forms.submissions.show', [$form, $s]) }}" class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0" style="background: linear-gradient(135deg, #5c83ff, #ec4899); color: white;">
                                {{ strtoupper(substr($name, 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-{{ $s->is_read ? 'medium' : 'bold' }} truncate" style="color: var(--text-primary);">{{ $name }}</span>
                                    @unless($s->is_read)<span class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>@endunless
                                </div>
                                <div class="text-[11px] truncate" style="color: var(--text-faint);">
                                    @php $preview = collect($s->data)->reject(fn($v,$k) => in_array($k, ['name','email']) || is_array($v))->take(3)->map(fn($v,$k) => "$k: $v")->implode(' · '); @endphp
                                    {{ $preview ?: $s->data['email'] ?? 'No content' }}
                                </div>
                            </div>
                        </a>
                        @if($hasRepGroups)
                        <button type="button" @click="expanded = !expanded"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold flex-shrink-0"
                                style="background: rgba(92,131,255,0.12); border: 1px solid rgba(92,131,255,0.25); color: #93b0ff;"
                                :aria-expanded="expanded ? 'true' : 'false'"
                                :title="expanded ? 'Hide copies' : 'Show copies'">
                            <i class="fas fa-layer-group"></i>
                            <span>{{ $repCopyTotal }} {{ \Illuminate\Support\Str::plural('copy', $repCopyTotal) }}</span>
                            <i class="fas fa-chevron-down text-[9px] transition-transform" :class="expanded ? 'rotate-180' : ''"></i>
                        </button>
                        @endif
                        <div class="text-[10px] text-right flex-shrink-0" style="color: var(--text-faint);">
                            {{ $s->created_at->diffForHumans() }}<br>
                            <span class="font-mono">{{ $s->ip ?? '' }}</span>
                            @if($s->isPaid())
                                <span class="inline-block mt-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold" style="background: rgba(16,185,129,0.15); color: #34d399;">
                                    Paid {{ $s->amount_cents !== null ? strtoupper($s->currency ?? 'USD') . ' ' . number_format($s->amount_cents / 100, 2) : '' }}
                                </span>
                            @elseif($s->isRefunded())
                                <span class="inline-block mt-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold" style="background: rgba(148,163,184,0.18); color: #94a3b8;">
                                    Refunded
                                </span>
                            @endif
                        </div>
                        @if($s->isRefundable() && $__can('inbox.delete'))
                        <form method="POST" action="{{ route('user.forms.submissions.refund', [$form, $s]) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Refund this payment?', message: 'This refunds {{ $s->amount_cents !== null ? strtoupper($s->currency ?? 'USD') . ' ' . number_format($s->amount_cents / 100, 2) : 'the charge' }} to the customer. This cannot be undone.', confirmText: 'Refund', confirmIcon: 'fa-rotate-left', iconClass: 'fa-rotate-left'})">
                            @csrf
                            <button type="submit" class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px]" style="background: rgba(245,158,11,0.12); color: #fbbf24;" title="Refund this payment"><i class="fas fa-rotate-left"></i></button>
                        </form>
                        @endif
                        @if($__can('inbox.delete'))
                        <form method="POST" action="{{ route('user.forms.submissions.destroy', [$form, $s]) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this submission?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                            @csrf @method('DELETE')
                            <button class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px]" style="background: rgba(239,68,68,0.1); color: #f87171;"><i class="fas fa-trash"></i></button>
                        </form>
                        @else
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px] cursor-not-allowed opacity-60" style="background: var(--bg-glass-input); color: var(--text-faint);" title="Your role doesn't allow deleting submissions, ask a workspace admin"><i class="fas fa-lock"></i></span>
                        @endif
                    </div>
                    @if($hasRepGroups)
                    <div x-show="expanded" x-cloak n class="px-4 pb-4 pt-1 space-y-4" style="border-top: 1px solid var(--border-subtle);">
                        @foreach($repGroups as $gk => $gv)
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-wider mb-2" style="color: var(--text-secondary);">
                                    {{ str_replace('_', ' ', $gk) }}
                                    <span class="font-medium normal-case" style="color: var(--text-faint);">· {{ count($gv['copies'] ?? []) }} {{ \Illuminate\Support\Str::plural('copy', count($gv['copies'] ?? [])) }}</span>
                                </div>
                                @foreach(array_values($gv['copies'] ?? []) as $repIdx => $repCopy)
                                    <div class="mb-2 p-3 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                                        <div class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color: var(--text-faint);">Copy {{ $repIdx + 1 }}</div>
                                        @foreach((array) $repCopy as $ck => $cv)
                                            <div class="flex gap-2 text-xs mb-1 flex-wrap">
                                                <span class="font-medium shrink-0" style="color: var(--text-muted);">{{ str_replace('_', ' ', $ck) }}:</span>
                                                @if(is_array($cv))
                                                    <span style="color: var(--text-primary);">{{ implode(', ', array_map('strval', $cv)) }}</span>
                                                @elseif(is_bool($cv))
                                                    <span style="color: var(--text-primary);">{{ $cv ? 'Yes' : 'No' }}</span>
                                                @else
                                                    <span style="color: var(--text-primary);">{{ $cv }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    @endif
                    </div>
                @endforeach
            </div>
        </div>
        <div class="mt-6">{{ $submissions->links() }}</div>
    @endif
</div>
@endsection
