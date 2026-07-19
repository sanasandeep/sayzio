@extends('user.layouts.app')

@section('title', 'Duplicate Contacts')

@section('content')
<div class="max-w-5xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Duplicate Contacts',
        'subtitle' => 'Review likely duplicates and merge them to keep your address book clean.',
        'icon'     => 'fa-copy',
        'chips'    => [
            ['icon' => 'fa-layer-group text-amber-400', 'text' => $groupCount . ' group' . ($groupCount === 1 ? '' : 's') . ' found'],
        ],
    ])

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background:rgba(16,185,129,.10);border:1px solid rgba(16,185,129,.20);color:#10b981;">
        <i class="fas fa-check-circle mr-1.5"></i>{{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background:rgba(239,68,68,.10);border:1px solid rgba(239,68,68,.20);color:#ef4444;">
        <i class="fas fa-exclamation-circle mr-1.5"></i>{{ session('error') }}
    </div>
    @endif

    @if($groupCount === 0)
    <div class="card-premium p-10 text-center">
        <div class="text-5xl mb-4" style="color:var(--text-faint);">✓</div>
        <p class="text-base font-semibold mb-1" style="color:var(--text-primary);">No duplicates found</p>
        <p class="text-sm" style="color:var(--text-muted);">Your address book looks clean, every contact is unique.</p>
        <a href="{{ route('user.contacts.index') }}" class="inline-flex items-center gap-2 mt-5 px-4 py-2 rounded-lg text-sm font-medium" style="background:rgba(61,107,255,.15);color:#90acff;border:1px solid rgba(61,107,255,.30);">
            <i class="fas fa-arrow-left text-xs"></i> Back to Contacts
        </a>
    </div>
    @else

    {{-- Bulk action: merge every group in one tap --}}
    <div class="mb-5 flex items-center justify-between gap-3 flex-wrap card-premium p-4">
        <p class="text-xs" style="color:var(--text-muted);">
            <i class="fas fa-bolt mr-1.5 text-amber-400"></i>
            In a hurry? Merge all {{ $groupCount }} group{{ $groupCount === 1 ? '' : 's' }} at once, the first contact in each group becomes the primary.
        </p>
        <form method="POST" action="{{ route('user.contacts.duplicates.merge-all') }}">
            @csrf
            <button type="submit"
                    onclick="return window.themedConfirmSubmit && window.themedConfirmSubmit(this.form, {title:'Merge all duplicates?',message:'This will merge all {{ $groupCount }} group{{ $groupCount === 1 ? '' : 's' }} at once. The first contact in each group keeps all data; the others are deleted. This cannot be undone.',confirmText:'Merge all',confirmIcon:'fa-code-merge',iconClass:'fa-code-merge'}) || confirm('Merge all {{ $groupCount }} duplicate group{{ $groupCount === 1 ? '' : 's' }}? The first contact in each group keeps all data; the others are deleted.')"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white transition"
                    style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
                <i class="fas fa-code-merge text-xs"></i> Merge all
            </button>
        </form>
    </div>

    <div class="space-y-5">
    @foreach($groups as $gi => $group)
    @php
        $contacts = $group['contacts'];
        $reason   = $group['reason'];
        $ids      = collect($contacts)->pluck('id')->sort()->values();
    @endphp
    <div class="card-premium p-5" x-data="{ primary: {{ $contacts[0]['id'] }}, open: true }" id="group-{{ $gi }}">

        {{-- Group header --}}
        <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
            <div>
                <span class="text-sm font-bold" style="color:var(--text-primary);">
                    {{ count($contacts) }} possible duplicates
                </span>
                <span class="ml-2 px-2 py-0.5 rounded text-[11px] font-medium" style="background:rgba(245,158,11,.15);color:#f59e0b;">
                    {{ $reason }}
                </span>
            </div>
            {{-- Dismiss all pairs in this group --}}
            <form method="POST" action="{{ route('user.contacts.duplicates.dismiss') }}">
                @csrf
                @foreach($ids as $idA)
                    @foreach($ids as $idB)
                        @if($idA < $idB)
                            <input type="hidden" name="pairs[]" value="{{ $idA }}:{{ $idB }}">
                        @endif
                    @endforeach
                @endforeach
                <button type="submit" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition"
                    style="background:rgba(255,255,255,.05);color:var(--text-muted);border:1px solid rgba(255,255,255,.10);">
                    <i class="fas fa-ban text-[10px]"></i> Not duplicates
                </button>
            </form>
        </div>

        {{-- Side-by-side comparison --}}
        <div class="overflow-x-auto mb-4">
            <div class="grid gap-3" style="grid-template-columns: repeat({{ min(count($contacts), 3) }}, minmax(0,1fr))">
            @foreach($contacts as $c)
            @php
                $isSelected = true; // will be driven by x-bind
            @endphp
            <div class="rounded-xl p-4 border transition-all"
                 :class="primary === {{ $c['id'] }} ? 'ring-2 ring-indigo-500' : ''"
                 style="background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.10);">

                {{-- Avatar + name --}}
                <div class="flex items-center gap-3 mb-3">
                    @if($c['photo_url'])
                        <img src="{{ $c['photo_url'] }}" class="w-10 h-10 rounded-full object-cover" alt="">
                    @else
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white"
                             style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
                            {{ mb_strtoupper(mb_substr($c['display_name'] ?: '?', 0, 1)) }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <p class="text-sm font-semibold truncate" style="color:var(--text-primary);">{{ $c['display_name'] ?: '(no name)' }}</p>
                        @if($c['organization'])
                        <p class="text-xs truncate" style="color:var(--text-muted);">{{ $c['organization'] }}</p>
                        @endif
                    </div>
                </div>

                @if(!empty($c['phones']))
                <div class="space-y-1 mb-2">
                    @foreach($c['phones'] as $ph)
                    <div class="flex items-center gap-1.5 text-xs" style="color:var(--text-muted);">
                        <i class="fas fa-phone text-[9px] text-cyan-400"></i>
                        {{ $ph['value'] }}
                        @if($ph['label'])<span class="opacity-50">({{ $ph['label'] }})</span>@endif
                    </div>
                    @endforeach
                </div>
                @endif

                @if(!empty($c['emails']))
                <div class="space-y-1 mb-2">
                    @foreach($c['emails'] as $em)
                    <div class="flex items-center gap-1.5 text-xs" style="color:var(--text-muted);">
                        <i class="fas fa-envelope text-[9px] text-pink-400"></i>
                        <span class="truncate">{{ $em['value'] }}</span>
                        @if($em['label'])<span class="opacity-50 shrink-0">({{ $em['label'] }})</span>@endif
                    </div>
                    @endforeach
                </div>
                @endif

                @if($c['notes'])
                <p class="text-[11px] line-clamp-2 mb-2" style="color:var(--text-faint);">{{ $c['notes'] }}</p>
                @endif

                <div class="flex items-center gap-2 mt-3">
                    <button type="button" @click="primary = {{ $c['id'] }}"
                            class="flex-1 py-1.5 rounded-lg text-xs font-medium transition"
                            :class="primary === {{ $c['id'] }}
                                ? 'text-white'
                                : ''"
                            :style="primary === {{ $c['id'] }}
                                ? 'background:linear-gradient(135deg,#3d6bff,#ec4899);'
                                : 'background:rgba(255,255,255,.06);color:var(--text-muted);border:1px solid rgba(255,255,255,.10);'">
                        <span x-text="primary === {{ $c['id'] }} ? '✓ Primary' : 'Set as primary'"></span>
                    </button>
                    <a href="{{ route('user.contacts.show', $c['id']) }}" target="_blank"
                       class="px-2 py-1.5 rounded-lg text-xs transition"
                       style="background:rgba(255,255,255,.04);color:var(--text-faint);border:1px solid rgba(255,255,255,.08);"
                       title="Open contact">
                        <i class="fas fa-arrow-up-right-from-square text-[9px]"></i>
                    </a>
                </div>
            </div>
            @endforeach
            </div>
        </div>

        {{-- Merge confirmation --}}
        <form method="POST" :action="'/user/contacts/' + primary + '/merge-duplicate'">
            @csrf
            {{-- All contact IDs in this group —primary excluded server-side --}}
            @foreach($contacts as $c)
            <input type="hidden" name="loser_ids[]" value="{{ $c['id'] }}">
            @endforeach
            <div class="flex items-center gap-3 flex-wrap">
                <button type="submit"
                        onclick="return window.themedConfirmSubmit && window.themedConfirmSubmit(this.form, {title:'Merge contacts?',message:'The selected primary will keep all data from both contacts. The others will be deleted.',confirmText:'Merge',confirmIcon:'fa-merge',iconClass:'fa-merge'}) || confirm('Merge these contacts? The primary keeps all data; the others are deleted.')"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white transition"
                        style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
                    <i class="fas fa-code-merge text-xs"></i> Merge into primary
                </button>
                <p class="text-xs" style="color:var(--text-faint);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Phones, emails, tags, and notes are combined, nothing is lost.
                </p>
            </div>
        </form>
    </div>
    @endforeach
    </div>

    @endif
</div>
@endsection
