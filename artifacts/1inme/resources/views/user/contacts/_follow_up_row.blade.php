@php($when = $contact->follow_up_at->timezone($tz))
<div class="flex items-start gap-3 p-4 rounded-xl transition"
     x-data="{ menu: false }"
     style="background: {{ $overdue ? 'rgba(239,68,68,.06)' : 'var(--surface-1, rgba(255,255,255,.03))' }}; border:1px solid {{ $overdue ? 'rgba(239,68,68,.22)' : 'rgba(255,255,255,.08)' }};">
    <a href="{{ route('user.contacts.show', $contact) }}" class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold"
       style="background: {{ $overdue ? 'rgba(239,68,68,.14)' : 'rgba(61,107,255,.12)' }}; color: {{ $overdue ? '#ef4444' : '#90acff' }};">
        {{ $contact->initials() }}
    </a>
    <a href="{{ route('user.contacts.show', $contact) }}" class="min-w-0 flex-1 group">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-sm font-semibold truncate group-hover:underline" style="color:var(--text-primary);">{{ $contact->nameForDisplay() }}</span>
            @if($overdue)
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide" style="background:rgba(239,68,68,.14);color:#ef4444;">Overdue</span>
            @endif
        </div>
        <div class="flex items-center gap-1.5 text-xs mt-0.5" style="color: {{ $overdue ? '#ef4444' : 'var(--text-muted)' }};">
            <i class="fas {{ $overdue ? 'fa-exclamation-circle' : 'fa-bell' }} text-[10px]"></i>
            <span>{{ $when->format('M j, Y g:i A') }}</span>
            <span style="color:var(--text-faint);">· {{ $when->diffForHumans() }}</span>
        </div>
        @if($contact->follow_up_note)
            <p class="text-xs mt-1.5 whitespace-pre-line line-clamp-2" style="color:var(--text-muted);">{{ $contact->follow_up_note }}</p>
        @endif
    </a>

    {{-- Inline quick actions: clear ("Done") or snooze to a preset, no need to open the contact. --}}
    <div class="flex items-center gap-1.5 flex-shrink-0">
        <button type="button" x-on:click="done({{ $contact->id }}, @js($contact->follow_up_at->toISOString()), @js($contact->follow_up_note), @js($contact->follow_up_tz))"
                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold transition hover:brightness-110"
                style="background:rgba(34,197,94,.12);color:#4ade80;border:1px solid rgba(34,197,94,.22);"
                title="Mark done — clears this reminder">
            <i class="fas fa-check text-[10px]"></i> Done
        </button>
        <div class="relative">
            <button type="button" x-on:click="menu = !menu" x-on:click.outside="menu = false"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold transition hover:brightness-110"
                    style="background:rgba(61,107,255,.12);color:#90acff;border:1px solid rgba(61,107,255,.20);"
                    title="Snooze this reminder">
                <i class="fas fa-clock text-[10px]"></i> Snooze
                <i class="fas fa-chevron-down text-[8px]"></i>
            </button>
            <div x-show="menu" x-cloak
                 class="absolute right-0 mt-1.5 z-20 w-40 rounded-xl p-1.5 shadow-xl"
                 style="background:var(--surface-2, #1a1d2e);border:1px solid rgba(255,255,255,.12);">
                <button type="button" @click="menu = false; snooze({{ $contact->id }}, 1, @js($contact->follow_up_note))"
                        class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition hover:brightness-125"
                        style="color:var(--text-primary);background:rgba(255,255,255,.03);">
                    <i class="fas fa-sun text-[10px] mr-1.5" style="color:#fbbf24;"></i> Tomorrow
                </button>
                <button type="button" @click="menu = false; snooze({{ $contact->id }}, 7, @js($contact->follow_up_note))"
                        class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition hover:brightness-125 mt-1"
                        style="color:var(--text-primary);background:rgba(255,255,255,.03);">
                    <i class="fas fa-calendar-week text-[10px] mr-1.5" style="color:#90acff;"></i> Next week
                </button>
            </div>
        </div>
    </div>
</div>
