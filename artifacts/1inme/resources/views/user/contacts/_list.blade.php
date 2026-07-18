{{-- Contacts list body: rendered both on full page load and via the live
     as-you-type AJAX search (ContactController@index returns just this partial
     when the request is XHR). Keeps the tab-specific rendering, empty-state and
     pagination in one place so the two code paths can never drift. --}}
@php($hasShared = isset($sharedContacts) && $sharedContacts->isNotEmpty())
@if($contacts->isEmpty() && !$hasShared)
    <div class="text-center py-16">
        <div class="w-16 h-16 mx-auto rounded-2xl flex items-center justify-center mb-4" style="background: linear-gradient(135deg, rgba(34,211,238,0.18), rgba(61,107,255,0.18));">
            <i class="fas fa-address-book text-2xl text-cyan-400"></i>
        </div>
        @if(trim((string) $search) !== '')
            <p class="text-sm font-semibold mb-1" style="color: var(--text-primary);">No matches for "{{ $search }}"</p>
            <p class="text-xs mb-4" style="color: var(--text-muted);">Try a different name, phone number, or email.</p>
        @else
            <p class="text-sm font-semibold mb-1" style="color: var(--text-primary);">No contacts yet</p>
            <p class="text-xs mb-4" style="color: var(--text-muted);">Add one manually or connect your Google account.</p>
            <a href="{{ route('user.contacts.create') }}" class="inline-block px-4 py-2 rounded-lg text-xs font-semibold" style="background:linear-gradient(135deg,#3d6bff,#ec4899);color:white">
                <i class="fas fa-user-plus mr-1"></i> New contact
            </a>
        @endif
    </div>
@else
    @if($contacts->isNotEmpty())
    @if($tab === 'biolink')
        {{-- Richer card view: includes the matched user's biolink URL inline. --}}
        <div class="space-y-3">
            @foreach($contacts as $c)
                @php
                    $bioLink = null;
                    if ($c->biolinkUser) {
                        $bioLink = \App\Modules\User\Models\Link::where('user_id', $c->biolink_user_id)
                            ->where('type', 'biolink')->where('is_active', true)
                            ->orderByDesc('id')->first();
                    }
                @endphp
                <div class="px-4 py-4 rounded-xl" style="background:linear-gradient(135deg,rgba(236,72,153,.06),rgba(61,107,255,.06));border:1px solid rgba(236,72,153,.20);">
                    <div class="flex items-start gap-3">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold text-white" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
                            @if($c->photoUrl())
                                <img src="{{ $c->photoUrl() }}" class="w-full h-full rounded-full object-cover">
                            @else
                                {{ $c->initials() }}
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('user.contacts.show', $c) }}" class="text-sm font-semibold truncate block" style="color:var(--text-primary);">
                                {{ $c->nameForDisplay() }}
                                <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase" style="background:rgba(236,72,153,.15);color:#f472b6">Sayzio</span>
                            </a>
                            <div class="text-xs truncate" style="color:var(--text-muted);">
                                {{ $c->phones->first()?->value ?? $c->emails->first()?->value ?? '—' }}
                            </div>
                            <div class="mt-2 text-[11px] flex items-center gap-2 flex-wrap">
                                <span class="px-2 py-0.5 rounded" style="background:rgba(255,255,255,.05);color:var(--text-muted);">{{ '@' . $c->biolinkUser?->publicHandle() }}</span>
                                @if($bioLink)
                                    <a href="{{ url('/' . $bioLink->alias) }}" target="_blank" class="font-medium" style="color:#90acff;">
                                        {{ url('/' . $bioLink->alias) }} <i class="fas fa-external-link-alt ml-1 text-[9px]"></i>
                                    </a>
                                @else
                                    <span style="color:var(--text-faint);">no published Link in Bio</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            @if($c->phones->first())
                                <a href="tel:{{ $c->phones->first()->value_e164 ?: $c->phones->first()->value }}" class="px-2.5 py-1 rounded-lg text-[10px] font-medium text-center" style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.20)"><i class="fas fa-phone"></i></a>
                            @endif
                            @if($bioLink)
                                <a href="{{ url('/' . $bioLink->alias) }}" target="_blank" class="px-2.5 py-1 rounded-lg text-[10px] font-bold text-white text-center" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">Open</a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($contacts as $c)
                <a href="{{ route('user.contacts.show', $c) }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);" onmouseover="this.style.background='rgba(61,107,255,.08)'" onmouseout="this.style.background='rgba(255,255,255,.04)'">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold text-white" style="background: linear-gradient(135deg,#3d6bff,#ec4899);">
                        @if($c->photoUrl())
                            <img src="{{ $c->photoUrl() }}" class="w-full h-full rounded-full object-cover">
                        @else
                            {{ $c->initials() }}
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold truncate" style="color:var(--text-primary);">
                            {{ $c->nameForDisplay() }}
                            @if($c->biolink_user_id)
                                <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase" style="background:rgba(236,72,153,.15);color:#f472b6">Sayzio</span>
                            @endif
                        </div>
                        <div class="text-xs truncate" style="color:var(--text-muted);">
                            {{ $c->phones->first()?->value ?? $c->emails->first()?->value ?? '—' }}
                        </div>
                        @if(!empty($c->tags))
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach(array_slice((array)$c->tags, 0, 3) as $tag)
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-medium"
                                          style="background:rgba(61,107,255,.12);color:#90acff;border:1px solid rgba(61,107,255,.18);">{{ $tag }}</span>
                                @endforeach
                                @if(count((array)$c->tags) > 3)
                                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-medium" style="color:var(--text-faint);">+{{ count((array)$c->tags) - 3 }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                    <i class="fas fa-chevron-right text-[10px] opacity-40"></i>
                </a>
            @endforeach
        </div>
    @endif
    <div class="mt-4">{{ $contacts->links() }}</div>
    @endif

    {{-- Contacts shared with the current team workspace by other members. --}}
    @if($hasShared)
    <div class="mt-6">
        <div class="flex items-center gap-2 mb-3">
            <i class="fas fa-users text-[11px]" style="color:#90acff;"></i>
            <span class="text-[10px] font-bold uppercase tracking-wider" style="color:var(--text-faint);">
                Shared with {{ $currentWorkspace?->name ?? 'your workspace' }}
            </span>
            <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold" style="background:rgba(61,107,255,.15);color:#90acff;">{{ $sharedContacts->count() }}</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($sharedContacts as $c)
                @php($share = $c->workspaceShares->first())
                <a href="{{ route('user.contacts.show', $c) }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition relative" style="background:rgba(61,107,255,.04);border:1px solid rgba(61,107,255,.14);" onmouseover="this.style.background='rgba(61,107,255,.10)'" onmouseout="this.style.background='rgba(61,107,255,.04)'">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold text-white" style="background: linear-gradient(135deg,#3d6bff,#ec4899);">
                        @if($c->photoUrl())
                            <img src="{{ $c->photoUrl() }}" class="w-full h-full rounded-full object-cover">
                        @else
                            {{ $c->initials() }}
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold truncate" style="color:var(--text-primary);">
                            {{ $c->nameForDisplay() }}
                            @if($c->biolink_user_id)
                                <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase" style="background:rgba(236,72,153,.15);color:#f472b6">Sayzio</span>
                            @endif
                        </div>
                        <div class="text-xs truncate" style="color:var(--text-muted);">
                            {{ $c->phones->first()?->value ?? $c->emails->first()?->value ?? '—' }}
                        </div>
                        @if($share?->sharedBy)
                        <div class="text-[10px] mt-0.5" style="color:var(--text-faint);">
                            <i class="fas fa-share-nodes mr-0.5"></i> Shared by {{ $share->sharedBy->name }}
                        </div>
                        @endif
                    </div>
                    <i class="fas fa-chevron-right text-[10px] opacity-40"></i>
                </a>
            @endforeach
        </div>
    </div>
    @endif
@endif
