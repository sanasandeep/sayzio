@php($when = $contact->follow_up_at->timezone($tz))
<a href="{{ route('user.contacts.show', $contact) }}"
   class="flex items-start gap-3 p-4 rounded-xl transition hover:brightness-110"
   style="background: {{ $overdue ? 'rgba(239,68,68,.06)' : 'var(--surface-1, rgba(255,255,255,.03))' }}; border:1px solid {{ $overdue ? 'rgba(239,68,68,.22)' : 'rgba(255,255,255,.08)' }};">
    <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold"
         style="background: {{ $overdue ? 'rgba(239,68,68,.14)' : 'rgba(61,107,255,.12)' }}; color: {{ $overdue ? '#ef4444' : '#90acff' }};">
        {{ $contact->initials() }}
    </div>
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-sm font-semibold truncate" style="color:var(--text-primary);">{{ $contact->nameForDisplay() }}</span>
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
    </div>
    <i class="fas fa-chevron-right text-[11px] mt-1 flex-shrink-0" style="color:var(--text-faint);"></i>
</a>
