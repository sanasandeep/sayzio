@extends('user.layouts.app')

@section('title', $contact->nameForDisplay())

@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('user.contacts.index') }}" class="inline-flex items-center gap-1 text-xs mb-4" style="color:var(--text-muted);">
        <i class="fas fa-arrow-left"></i> Back to contacts
    </a>

    @if(session('success'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
        <i class="fas fa-circle-exclamation mr-1.5"></i> {{ session('error') }}
    </div>
    @endif
    @if(session('duplicate_notice'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium flex items-center justify-between gap-3 flex-wrap" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.25); color: #f59e0b;">
        <span><i class="fas fa-clone mr-1.5"></i> {{ session('duplicate_notice') }}</span>
        <a href="{{ route('user.contacts.duplicates') }}" class="font-semibold underline whitespace-nowrap" style="color: #f59e0b;">Review &amp; merge</a>
    </div>
    @endif

    {{-- Recent merges into this contact that can still be undone (30-day window) --}}
    @foreach(($undoableMerges ?? collect()) as $audit)
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium flex items-center justify-between gap-3 flex-wrap" style="background: rgba(61,107,255,0.08); border: 1px solid rgba(61,107,255,0.25); color: var(--text-primary);">
        <span style="color:var(--text-muted);">
            <i class="fas fa-rotate-left mr-1.5" style="color:#3d6bff;"></i>
            <span class="font-semibold" style="color:var(--text-primary);">{{ $audit->sourceName() }}</span>
            was merged into this contact {{ $audit->created_at?->diffForHumans() }}.
        </span>
        <form method="POST" action="{{ route('user.contacts.merges.undo', $audit->id) }}">
            @csrf
            <button type="submit"
                    onclick="return window.themedConfirmSubmit && window.themedConfirmSubmit(this.form, {title:'Undo this merge?',message:'“{{ str_replace("'", '', $audit->sourceName()) }}” will be restored as its own contact, and its activity will move back to it.',confirmText:'Undo merge',confirmIcon:'fa-rotate-left',iconClass:'fa-rotate-left'}) || confirm('Undo this merge? The merged contact will be restored.')"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap"
                    style="background:rgba(61,107,255,.12);color:#90acff;border:1px solid rgba(61,107,255,.30);">
                <i class="fas fa-rotate-left mr-1"></i> Undo merge
            </button>
        </form>
    </div>
    @endforeach

    <div class="card-premium p-6">
        <div class="flex items-start gap-4 mb-5">
            <div class="w-16 h-16 rounded-full flex items-center justify-center text-xl font-bold text-white flex-shrink-0" style="background: linear-gradient(135deg,#3d6bff,#ec4899);">
                @if($contact->photoUrl())
                    <img src="{{ $contact->photoUrl() }}" class="w-full h-full rounded-full object-cover">
                @else
                    {{ $contact->initials() }}
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl font-bold" style="color:var(--text-primary);">{{ $contact->nameForDisplay() }}</h1>
                @if($contact->organization)
                    <p class="text-sm" style="color:var(--text-muted);">{{ $contact->job_title ? $contact->job_title . ' · ' : '' }}{{ $contact->organization }}</p>
                @endif
                @if($contact->google_resource_name)
                    <span class="inline-block mt-2 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider" style="background:rgba(236,72,153,.15);color:#f472b6">
                        <i class="fab fa-google mr-1"></i> Synced
                    </span>
                @endif
            </div>
            <div class="flex gap-2 flex-shrink-0">
                @if($shareContext['is_owner'] || ($shareContext['is_shared_contact'] && $shareContext['current_workspace'] && request()->user()->canInWorkspace($shareContext['current_workspace'], 'settings.edit')))
                <a href="{{ route('user.contacts.edit', $contact) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                    <i class="fas fa-pen mr-1"></i> Edit
                </a>
                @endif
                @if($shareContext['is_owner'])
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-merge-into'))" class="px-3 py-1.5 rounded-lg text-xs font-semibold" style="background:rgba(245,158,11,.10);color:#f59e0b;border:1px solid rgba(245,158,11,.25)">
                    <i class="fas fa-code-merge mr-1"></i> Merge into&hellip;
                </button>
                @endif
            </div>
        </div>

        @if($biolinkPreview)
        <div class="mb-5 p-4 rounded-xl" style="background:linear-gradient(135deg,rgba(236,72,153,.08),rgba(61,107,255,.08));border:1px solid rgba(236,72,153,.20);">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background:linear-gradient(135deg,#ec4899,#3d6bff);">
                        {{ mb_strtoupper(mb_substr($biolinkPreview['user']->name ?? '?', 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-bold uppercase tracking-wider" style="color:#f472b6;">Sayzio Link in Bio</div>
                        <div class="text-sm font-semibold truncate" style="color:var(--text-primary);">{{ $biolinkPreview['user']->name }}</div>
                        @if($biolinkPreview['url'])
                            <a href="{{ $biolinkPreview['url'] }}" target="_blank" class="text-xs truncate" style="color:#90acff;">{{ $biolinkPreview['url'] }}</a>
                        @endif
                    </div>
                </div>
                <form method="POST" action="{{ route('user.contacts.biolink.detach', $contact) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Detach this biolink?', message: 'It will not auto-attach again on future syncs unless you re-attach.', confirmText: 'Detach', confirmIcon: 'fa-link-slash', iconClass: 'fa-link-slash'})">
                    @csrf
                    <button class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)">
                        Detach
                    </button>
                </form>
            </div>
            @php($_smsPhone = $contact->phones->first(fn($p) => !empty($p->value_e164)) ?? $contact->phones->first())
            @if($biolinkPreview['url'] && $_smsPhone)
                @php($_smsTo = $_smsPhone->value_e164 ?: $_smsPhone->value)
                @php($_smsBody = ($contact->nameForDisplay() ? 'Hey ' . $contact->nameForDisplay() . ', ' : 'Hey, ') . "here's my Sayzio page: " . $biolinkPreview['url'])
                <div class="mt-3 pt-3 flex flex-wrap items-center gap-2" style="border-top:1px dashed rgba(236,72,153,.20);">
                    <a href="sms:{{ $_smsTo }}?body={{ rawurlencode($_smsBody) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-semibold"
                       style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.20)">
                        <i class="fas fa-comment-sms mr-1"></i> Text Link in Bio to {{ $_smsTo }}
                    </a>
                    <form method="POST" action="{{ route('user.contacts.biolink.sms', $contact) }}" class="inline">
                        @csrf
                        <input type="hidden" name="to" value="{{ $_smsTo }}">
                        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold"
                                style="background:rgba(61,107,255,.12);color:#90acff;border:1px solid rgba(61,107,255,.20)"
                                title="Send via your configured SMS gateway (desktop fallback)">
                            <i class="fas fa-paper-plane mr-1"></i> Send via gateway
                        </button>
                    </form>
                </div>
            @endif
        </div>
        @else
            <form method="POST" action="{{ route('user.contacts.biolink.attach', $contact) }}" class="mb-5">
                @csrf
                <button class="text-xs font-medium" style="color:#90acff;">
                    <i class="fas fa-link mr-1"></i> Re-check for a Sayzio Link in Bio
                </button>
            </form>
        @endif

        @if($contact->phones->isNotEmpty())
        <div class="mb-4">
            <h3 class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color:var(--text-faint);">Phone</h3>
            @foreach($contact->phones as $p)
                <div class="flex items-center justify-between py-2" style="border-top: 1px solid rgba(255,255,255,.06);">
                    <div>
                        <div class="text-sm" style="color:var(--text-primary);">{{ $p->value }}</div>
                        <div class="text-[11px]" style="color:var(--text-faint);">{{ $p->label ?: 'Phone' }}</div>
                    </div>
                    <div class="flex gap-1">
                        <a href="tel:{{ $p->value_e164 ?: $p->value }}" class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.20)">
                            <i class="fas fa-phone mr-1"></i> Call
                        </a>
                        <a href="{{ route('user.dialer.profile', ['number' => $p->value_e164 ?: $p->value, 'contact' => $contact->id]) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                            <i class="fas fa-id-card mr-1"></i> Profile
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
        @endif

        @if($contact->emails->isNotEmpty())
        <div class="mb-4">
            <h3 class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color:var(--text-faint);">Email</h3>
            @foreach($contact->emails as $e)
                <div class="flex items-center justify-between py-2" style="border-top: 1px solid rgba(255,255,255,.06);">
                    <div>
                        <div class="text-sm" style="color:var(--text-primary);">{{ $e->value }}</div>
                        <div class="text-[11px]" style="color:var(--text-faint);">{{ $e->label ?: 'Email' }}</div>
                    </div>
                    <a href="mailto:{{ $e->value }}" class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(59,130,246,.12);color:#3b82f6;border:1px solid rgba(59,130,246,.20)">
                        <i class="fas fa-envelope mr-1"></i> Email
                    </a>
                </div>
            @endforeach
        </div>
        @endif

        {{-- Tags --}}
        <div class="mt-5 pt-4" style="border-top: 1px solid rgba(255,255,255,.06);"
             x-data="contactTagsEditor({
                 id: {{ $contact->id }},
                 initial: @js($contact->tags ?? []),
                 tagsUrl: '{{ route('user.contacts.tags') }}',
                 patchUrl: '{{ route('user.contacts.tags.update', $contact) }}',
                 csrf: '{{ csrf_token() }}',
             })">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-[10px] font-bold uppercase tracking-wider" style="color:var(--text-faint);">Tags</h3>
                <button type="button" @click="editing = !editing" class="text-[11px] font-medium" style="color:#90acff;">
                    <span x-text="editing ? 'Done' : 'Edit'"></span>
                </button>
            </div>
            <div class="flex flex-wrap gap-1.5 mb-1">
                <template x-for="(tag, i) in tags" :key="i">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                          style="background:rgba(61,107,255,.15);color:#90acff;border:1px solid rgba(61,107,255,.25);">
                        <span x-text="tag"></span>
                        <button x-show="editing" type="button" @click="removeTag(i)" class="opacity-60 hover:opacity-100 leading-none ml-0.5">&times;</button>
                    </span>
                </template>
                <span x-show="tags.length === 0 && !editing" class="text-xs" style="color:var(--text-faint);">No tags yet</span>
            </div>
            <div x-show="editing" class="relative mt-2">
                <input type="text" x-model="input" @keydown.enter.prevent="addFromInput()"
                       @keydown.comma.prevent="addFromInput()" @keydown.backspace="onBackspace()"
                       @input="filterSuggestions()" @focus="filterSuggestions()" @blur.window="showDropdown = false"
                       placeholder="Add tag…"
                       class="w-full px-3 py-2 rounded-lg text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                <div x-show="showDropdown && filtered.length" x-cloak
                     class="absolute left-0 z-20 mt-1 w-full rounded-xl shadow-xl overflow-hidden"
                     style="background:var(--surface-2,#1a1d2e);border:1px solid rgba(255,255,255,.12);">
                    <template x-for="s in filtered" :key="s">
                        <button type="button" @mousedown.prevent="addTag(s)"
                                class="w-full text-left px-3 py-2 text-xs hover:brightness-125 transition"
                                style="color:var(--text-primary);background:rgba(255,255,255,.03);" x-text="s"></button>
                    </template>
                </div>
                <p class="text-[11px] mt-1" style="color:var(--text-faint);">Enter or comma to add · Backspace to remove last</p>
            </div>
            <p x-show="saveError" x-cloak class="text-[11px] mt-1" style="color:#ef4444;" x-text="saveError"></p>
        </div>

        {{-- Notes — inline quick-edit --}}
        <div class="mt-5 pt-4" style="border-top: 1px solid rgba(255,255,255,.06);"
             x-data="contactNotesEditor({
                 id: {{ $contact->id }},
                 initial: @js($contact->notes ?? ''),
                 patchUrl: '{{ route('user.contacts.notes.update', $contact) }}',
                 csrf: '{{ csrf_token() }}',
             })">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-[10px] font-bold uppercase tracking-wider" style="color:var(--text-faint);">Notes</h3>
                <button type="button" @click="toggleEdit()" class="text-[11px] font-medium" style="color:#90acff;">
                    <span x-text="editing ? 'Done' : (notes ? 'Edit' : 'Add note')"></span>
                </button>
            </div>
            <p x-show="!editing && notes" class="text-sm whitespace-pre-line" style="color:var(--text-muted);" x-text="notes"></p>
            <p x-show="!editing && !notes" class="text-xs" style="color:var(--text-faint);">No notes yet</p>
            <div x-show="editing" class="mt-1">
                <textarea x-model="draft" rows="4" maxlength="5000"
                          placeholder="Add notes about this contact…"
                          class="w-full px-3 py-2 rounded-lg text-sm" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);"></textarea>
                <div class="flex items-center gap-2 mt-2">
                    <button type="button" @click="save()" :disabled="saving"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
                            style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
                        <span x-text="saving ? 'Saving…' : 'Save'"></span>
                    </button>
                    <button type="button" @click="editing = false; draft = notes"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(255,255,255,.06);color:var(--text-muted);border:1px solid rgba(255,255,255,.10)">
                        Cancel
                    </button>
                </div>
                <p x-show="saveError" x-cloak class="text-[11px] mt-1" style="color:#ef4444;" x-text="saveError"></p>
            </div>
        </div>

        <div class="mt-5 pt-4" style="border-top: 1px solid rgba(255,255,255,.06);">
            <h3 class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color:var(--text-faint);">Follow-up reminder</h3>
            @if($errors->has('follow_up_at'))
                <div class="mb-3 px-4 py-3 rounded-xl text-xs" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)">
                    {{ $errors->first('follow_up_at') }}
                </div>
            @endif
            @if($contact->follow_up_at)
                <div class="flex items-start justify-between gap-3 p-3 rounded-xl" style="background:rgba(61,107,255,.08);border:1px solid rgba(61,107,255,.18);">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 text-sm font-semibold" style="color:var(--text-primary);">
                            @php($followUpTz = $contact->follow_up_tz ?? $contact->user->timezone ?? config('app.timezone'))
                            <i class="fas fa-bell" style="color:#90acff;"></i>
                            Follow up {{ $contact->follow_up_at->timezone($followUpTz)->format('M j, Y g:i A') }} ({{ $followUpTz }})
                        </div>
                        @if($contact->follow_up_note)
                            <p class="text-xs mt-1 whitespace-pre-line" style="color:var(--text-muted);">{{ $contact->follow_up_note }}</p>
                        @endif
                    </div>
                    <div class="flex gap-1.5 flex-shrink-0">
                        <button type="button" onclick="document.getElementById('follow-up-form').classList.remove('hidden'); document.getElementById('follow-up-toggle')?.classList.add('hidden');" class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                            Edit
                        </button>
                        <form method="POST" action="{{ route('user.contacts.follow-up.clear', $contact) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)">
                                Clear
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <button type="button" id="follow-up-toggle" onclick="document.getElementById('follow-up-form').classList.remove('hidden'); this.classList.add('hidden');" class="px-3 py-1.5 rounded-lg text-xs font-semibold" style="background:rgba(61,107,255,.12);color:#90acff;border:1px solid rgba(61,107,255,.20)">
                    <i class="fas fa-bell mr-1"></i> Set a follow-up reminder
                </button>
            @endif

            <form method="POST" action="{{ route('user.contacts.follow-up.set', $contact) }}" id="follow-up-form" class="{{ $errors->has('follow_up_at') ? '' : 'hidden' }} mt-3 space-y-3">
                @csrf
                @php($accountTz = $contact->user->timezone ?? config('app.timezone'))
                @php($selectedTz = old('follow_up_tz', $contact->follow_up_tz ?? $accountTz))
                <div>
                    <label class="block text-[11px] font-semibold mb-1" style="color:var(--text-faint);">Date &amp; time</label>
                    <input type="datetime-local" name="follow_up_at" required
                           value="{{ old('follow_up_at', $contact->follow_up_at ? $contact->follow_up_at->timezone($contact->follow_up_tz ?? $accountTz)->format('Y-m-d\TH:i') : '') }}"
                           class="w-full px-3 py-2 rounded-lg text-sm" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold mb-1" style="color:var(--text-faint);">Time zone</label>
                    <select name="follow_up_tz"
                            class="w-full px-3 py-2 rounded-lg text-sm" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                        @foreach(timezone_identifiers_list() as $tzId)
                            <option value="{{ $tzId }}" {{ $selectedTz === $tzId ? 'selected' : '' }}>{{ $tzId }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] mt-1" style="color:var(--text-faint);">Defaults to your account time zone ({{ $accountTz }}). Pick another to schedule in that zone.</p>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold mb-1" style="color:var(--text-faint);">Note (optional)</label>
                    <textarea name="follow_up_note" rows="2" maxlength="2000" placeholder="What to follow up about…"
                              class="w-full px-3 py-2 rounded-lg text-sm" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">{{ old('follow_up_note', $contact->follow_up_note) }}</textarea>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold" style="background:linear-gradient(135deg,#3d6bff,#ec4899);color:#fff;">
                        Save reminder
                    </button>
                    @if($contact->follow_up_at)
                        <button type="button" onclick="document.getElementById('follow-up-form').classList.add('hidden');" class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                            Cancel
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- ── Activity across Sayzio (Task #6501) ──────────────────────────── --}}
    <div class="mt-4 pt-4" style="border-top:1px solid rgba(255,255,255,.06);">
        <div class="flex items-center gap-2 mb-3 flex-wrap">
            <h3 class="text-[10px] font-bold uppercase tracking-wider" style="color:var(--text-faint);">Activity across Sayzio</h3>
            @if($contact->is_auto_captured)
                <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold" title="This contact was created automatically from a customer capture" style="background:rgba(34,211,238,.12);color:#22d3ee;border:1px solid rgba(34,211,238,.20)">Auto-captured</span>
            @endif
            @if(!empty($followerBridge['is_follower']))
                <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold" title="This contact's Sayzio account follows you" style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.20)"><i class="fas fa-user-check mr-0.5"></i> Follows you</span>
            @endif
        </div>
        @if(empty($activityGroups))
            <p class="text-xs" style="color:var(--text-muted);">No linked activity yet. Subscriptions, orders, bookings, RSVPs, reviews and conversations from this person will show up here automatically.</p>
        @else
            <div class="space-y-3">
                @foreach($activityGroups as $group)
                    <div class="rounded-xl p-3" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold" style="color:var(--text-primary);">{{ $group['label'] }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold" style="background:rgba(61,107,255,.12);color:#90acff;">{{ $group['count'] }}</span>
                        </div>
                        <div class="space-y-1.5">
                            @foreach($group['items'] as $item)
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <div class="min-w-0 flex-1">
                                        @if(!empty($item['url']))
                                            <a href="{{ $item['url'] }}" class="truncate block font-medium" style="color:#90acff;">{{ $item['title'] }}</a>
                                        @else
                                            <span class="truncate block" style="color:var(--text-primary);">{{ $item['title'] }}</span>
                                        @endif
                                        @if(!empty($item['subtitle']))
                                            <span class="text-[10px]" style="color:var(--text-muted);">{{ $item['subtitle'] }}</span>
                                        @endif
                                    </div>
                                    @if(!empty($item['date']))
                                        <span class="text-[10px] flex-shrink-0" style="color:var(--text-faint);">{{ \Illuminate\Support\Carbon::parse($item['date'])->diffForHumans() }}</span>
                                    @endif
                                </div>
                            @endforeach
                            @if($group['count'] > count($group['items']))
                                <p class="text-[10px]" style="color:var(--text-faint);">+ {{ $group['count'] - count($group['items']) }} more</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ── Workspace sharing panel ──────────────────────────────────────── --}}
    @if($shareContext['is_shared_contact'])
    <div class="mt-4 p-4 rounded-xl" style="background:linear-gradient(135deg,rgba(61,107,255,.07),rgba(34,211,238,.07));border:1px solid rgba(61,107,255,.18);">
        <div class="flex items-center gap-2 mb-1">
            <i class="fas fa-share-nodes text-xs" style="color:#90acff;"></i>
            <span class="text-xs font-semibold" style="color:#90acff;">Shared contact</span>
        </div>
        <p class="text-xs" style="color:var(--text-muted);">
            Shared by <strong>{{ $shareContext['shared_by']?->name ?? 'a team member' }}</strong> with <strong>{{ $shareContext['current_workspace']?->name }}</strong>.
        </p>
    </div>
    @endif

    @if($shareContext['is_owner'] && $shareContext['shareable_workspaces']->isNotEmpty())
    <div class="mt-4 pt-4" style="border-top:1px solid rgba(255,255,255,.06);">
        <h3 class="text-[10px] font-bold uppercase tracking-wider mb-3" style="color:var(--text-faint);">Share with workspaces</h3>
        <div class="space-y-2">
            @foreach($shareContext['shareable_workspaces'] as $ws)
                @php($alreadyShared = $shareContext['shares']->firstWhere('workspace_id', $ws->id))
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <span class="text-sm font-medium truncate block" style="color:var(--text-primary);">{{ $ws->name }}</span>
                        @if($alreadyShared)
                            <span class="text-[10px]" style="color:#22c55e;">
                                <i class="fas fa-check-circle mr-0.5"></i> Shared
                                @if($alreadyShared->sharedBy && $alreadyShared->sharedBy->id !== auth()->id())
                                    by {{ $alreadyShared->sharedBy->name }}
                                @endif
                            </span>
                        @endif
                    </div>
                    @if($alreadyShared)
                        <form method="POST" action="{{ route('user.contacts.unshare', $contact) }}">
                            @csrf @method('DELETE')
                            <input type="hidden" name="workspace_id" value="{{ $ws->id }}">
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-[11px] font-medium flex-shrink-0" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20);">
                                <i class="fas fa-times mr-1"></i> Unshare
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('user.contacts.share', $contact) }}">
                            @csrf
                            <input type="hidden" name="workspace_id" value="{{ $ws->id }}">
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-[11px] font-medium flex-shrink-0" style="background:rgba(61,107,255,.12);color:#90acff;border:1px solid rgba(61,107,255,.20);">
                                <i class="fas fa-share-nodes mr-1"></i> Share
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif
    </div>
</div>

@if($shareContext['is_owner'])
{{-- "Merge into…" picker: absorb this contact into another one. --}}
<div x-data="mergeIntoPicker({
        candidatesUrl: '{{ route('user.contacts.merge-candidates', $contact) }}',
        mergeUrl: '{{ route('user.contacts.merge-into', $contact) }}',
        csrf: '{{ csrf_token() }}',
        contactName: @js($contact->nameForDisplay()),
     })"
     x-on:open-merge-into.window="open()"
     x-show="show" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.60);backdrop-filter:blur(4px);">
    <div class="w-full max-w-md rounded-2xl p-5 card-premium" style="max-height:80vh;display:flex;flex-direction:column;" @click.outside="close()">
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-sm font-bold" style="color:var(--text-primary);"><i class="fas fa-code-merge mr-1.5" style="color:#f59e0b;"></i> Merge into another contact</h3>
            <button type="button" @click="close()" class="w-7 h-7 rounded-lg" style="color:var(--text-muted);background:rgba(255,255,255,.05);"><i class="fas fa-times text-xs"></i></button>
        </div>
        <p class="text-xs mb-3" style="color:var(--text-muted);">
            Pick the contact that should survive. All emails, phones and captured activity (subscribers, form entries, orders, bookings, RSVPs, tickets, reviews, conversations) from <span class="font-semibold" x-text="cfg.contactName"></span> move to it, then this duplicate is deleted.
        </p>
        <input type="text" x-model="query" x-ref="search" @input.debounce.300ms="search()"
               placeholder="Search contacts by name, email or phone…"
               class="w-full px-3 py-2 rounded-lg text-sm mb-3"
               style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);color:var(--text-primary);">
        <div class="overflow-y-auto flex-1 -mx-1 px-1" style="min-height:120px;">
            <template x-if="loading"><div class="py-6 text-center text-xs" style="color:var(--text-muted);">Searching…</div></template>
            <template x-if="!loading && candidates.length === 0"><div class="py-6 text-center text-xs" style="color:var(--text-muted);">No other contacts found.</div></template>
            <template x-for="c in candidates" :key="c.id">
                <button type="button" @click="selectedId = c.id"
                        class="w-full flex items-center gap-3 px-2 py-2 rounded-lg text-left mb-1"
                        :style="selectedId === c.id ? 'background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.35)' : 'background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06)'">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0 overflow-hidden" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
                        <template x-if="c.photo_url"><img :src="c.photo_url" class="w-full h-full object-cover"></template>
                        <template x-if="!c.photo_url"><span x-text="(c.display_name || '?').slice(0,2).toUpperCase()"></span></template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold truncate" style="color:var(--text-primary);">
                            <span x-text="c.display_name || 'Unnamed contact'"></span>
                            <span x-show="c.is_auto_captured" class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase" style="background:rgba(61,107,255,.15);color:#90acff;">Auto</span>
                        </div>
                        <div class="text-[11px] truncate" style="color:var(--text-faint);" x-text="[c.email, c.phone, c.organization].filter(Boolean).join(' · ')"></div>
                    </div>
                    <i class="fas fa-check text-xs" :style="selectedId === c.id ? 'color:#f59e0b' : 'color:transparent'"></i>
                </button>
            </template>
        </div>
        <form method="POST" :action="cfg.mergeUrl" class="mt-3 flex gap-2" @submit="submitting = true">
            <input type="hidden" name="_token" :value="cfg.csrf">
            <input type="hidden" name="target_id" :value="selectedId ?? ''">
            <button type="button" @click="close()" class="flex-1 px-3 py-2 rounded-lg text-xs font-semibold" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">Cancel</button>
            <button type="submit" :disabled="!selectedId || submitting"
                    class="flex-1 px-3 py-2 rounded-lg text-xs font-bold"
                    :style="(!selectedId || submitting) ? 'background:rgba(245,158,11,.15);color:rgba(245,158,11,.5);cursor:not-allowed' : 'background:#f59e0b;color:#1a1408'">
                <span x-show="!submitting">Merge &amp; delete duplicate</span>
                <span x-show="submitting">Merging…</span>
            </button>
        </form>
    </div>
</div>
@endif
@push('scripts')
<script>
function contactTagsEditor(cfg) {
    return {
        tags: [...(cfg.initial || [])],
        suggestions: [],
        input: '',
        filtered: [],
        showDropdown: false,
        editing: false,
        saving: false,
        saveError: '',

        init() {
            this.$watch('editing', v => { if (v) this.loadSuggestions(); });
        },

        loadSuggestions() {
            fetch(cfg.tagsUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json()).then(d => { this.suggestions = d.data || []; }).catch(() => {});
        },

        addFromInput() {
            const v = this.input.replace(/,/g, '').trim();
            if (v) this.addTag(v);
        },

        addTag(tag) {
            tag = tag.trim();
            if (!tag || this.tags.includes(tag)) { this.input = ''; this.showDropdown = false; return; }
            this.tags.push(tag);
            this.input = '';
            this.showDropdown = false;
            this.persist();
        },

        removeTag(i) {
            this.tags.splice(i, 1);
            this.persist();
        },

        onBackspace() {
            if (this.input === '' && this.tags.length) { this.tags.pop(); this.persist(); }
        },

        filterSuggestions() {
            const q = this.input.trim().toLowerCase();
            this.filtered = this.suggestions.filter(s =>
                (!q || s.toLowerCase().includes(q)) && !this.tags.includes(s)
            ).slice(0, 8);
            this.showDropdown = this.filtered.length > 0;
        },

        async persist() {
            this.saveError = '';
            try {
                const fd = new FormData();
                fd.append('_method', 'PATCH');
                fd.append('_token', cfg.csrf);
                this.tags.forEach((t, i) => fd.append('tags[' + i + ']', t));
                const r = await fetch(cfg.patchUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                if (!r.ok) throw new Error('save failed');
            } catch (e) {
                this.saveError = 'Could not save tags. Please try again.';
            }
        },
    };
}

function mergeIntoPicker(cfg) {
    return {
        cfg: cfg,
        show: false,
        query: '',
        candidates: [],
        selectedId: null,
        loading: false,
        submitting: false,

        open() {
            this.show = true;
            this.selectedId = null;
            this.query = '';
            this.search();
            this.$nextTick(() => this.$refs.search && this.$refs.search.focus());
        },

        close() {
            if (this.submitting) return;
            this.show = false;
        },

        async search() {
            this.loading = true;
            try {
                const url = cfg.candidatesUrl + (this.query.trim() ? ('?q=' + encodeURIComponent(this.query.trim())) : '');
                const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) throw new Error('search failed');
                const d = await r.json();
                this.candidates = (d.data && d.data.candidates) || [];
                if (this.selectedId && !this.candidates.some(c => c.id === this.selectedId)) {
                    this.selectedId = null;
                }
            } catch (e) {
                this.candidates = [];
            } finally {
                this.loading = false;
            }
        },
    };
}

function contactNotesEditor(cfg) {
    return {
        notes: cfg.initial || '',
        draft: cfg.initial || '',
        editing: false,
        saving: false,
        saveError: '',

        toggleEdit() {
            this.editing = !this.editing;
            if (this.editing) this.draft = this.notes;
        },

        async save() {
            this.saving = true;
            this.saveError = '';
            try {
                const fd = new FormData();
                fd.append('_method', 'PATCH');
                fd.append('_token', cfg.csrf);
                fd.append('notes', this.draft);
                const r = await fetch(cfg.patchUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                if (!r.ok) throw new Error('save failed');
                const d = await r.json();
                this.notes = d.data?.notes ?? this.draft;
                this.editing = false;
            } catch (e) {
                this.saveError = 'Could not save notes. Please try again.';
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
@endsection
