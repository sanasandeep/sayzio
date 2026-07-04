@extends('user.layouts.app')

@section('title', 'Follow-ups')

@section('content')
<div class="max-w-4xl mx-auto" x-data="followUpsList()" data-reload-url="{{ route('user.contacts.follow-ups') }}">
    @include('user.partials.page-hero', [
        'title' => 'Follow-ups',
        'subtitle' => 'Everything you need to follow up on, soonest first — clear or snooze right from here.',
        'icon' => 'fa-bell',
        'chips' => [
            ['icon' => 'fa-exclamation-circle text-red-400', 'text' => $overdue->count() . ' overdue',  'textId' => 'foChipOverdue'],
            ['icon' => 'fa-clock text-cyan-400',            'text' => $upcoming->count() . ' upcoming', 'textId' => 'foChipUpcoming'],
        ],
    ])

    <div class="mb-6">
        <a href="{{ route('user.contacts.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium" style="color:var(--text-muted);">
            <i class="fas fa-arrow-left text-[10px]"></i> Back to contacts
        </a>
    </div>

    <div x-ref="body" :class="loading ? 'opacity-50 pointer-events-none transition' : ''">
        @include('user.contacts._follow_ups_body', ['overdue' => $overdue, 'upcoming' => $upcoming])
    </div>

    {{-- Undo snackbar: a brief window to restore a follow-up cleared by accident. --}}
    <div x-cloak x-show="toast.visible"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-3"
         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-2xl"
         style="background:var(--surface-2, #1a1d2e);border:1px solid rgba(255,255,255,.14);">
        <span class="text-sm font-medium whitespace-nowrap" style="color:var(--text-primary);">
            <i class="fas fa-check-circle text-[12px] mr-1.5" style="color:#4ade80;"></i>
            Follow-up cleared
        </span>
        <button type="button" x-on:click="undo()"
                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold transition hover:brightness-125"
                style="background:rgba(61,107,255,.16);color:#90acff;border:1px solid rgba(61,107,255,.28);">
            <i class="fas fa-rotate-left text-[10px]"></i> Undo
        </button>
    </div>
</div>

@push('scripts')
<script>
function followUpsList() {
    return {
        loading: false,
        toast: { visible: false },
        undoState: null,
        _toastT: null,
        _csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        },
        // Clear a follow-up straight from the list ("Done"). Keep the previous
        // reminder date + note around so an accidental click can be undone.
        async done(id, prevAt, prevNote, prevTz) {
            const ok = await this._act(`{{ url('user/contacts') }}/${id}/follow-up`, 'DELETE');
            if (ok && prevAt) this._showUndo(id, prevAt, prevNote, prevTz);
        },
        // Re-set the follow-up we just cleared via the existing set endpoint.
        async undo() {
            const u = this.undoState;
            if (!u) return;
            this._hideToast();
            const body = new URLSearchParams();
            body.set('follow_up_at', u.at);
            if (u.note) body.set('follow_up_note', u.note);
            if (u.tz) body.set('follow_up_tz', u.tz);
            // Allow restoring an overdue reminder whose time is already past.
            body.set('restore', '1');
            await this._act(`{{ url('user/contacts') }}/${u.id}/follow-up`, 'POST', body);
        },
        _showUndo(id, at, note, tz) {
            this.undoState = { id, at, note, tz };
            this.toast.visible = true;
            clearTimeout(this._toastT);
            this._toastT = setTimeout(() => this._hideToast(), 6000);
        },
        _hideToast() {
            this.toast.visible = false;
            clearTimeout(this._toastT);
        },
        // Snooze to a preset (+1 day / +7 days from now), preserving the note.
        async snooze(id, days, note) {
            const at = new Date(Date.now() + days * 86400000);
            const body = new URLSearchParams();
            body.set('follow_up_at', at.toISOString());
            if (note) body.set('follow_up_note', note);
            await this._act(`{{ url('user/contacts') }}/${id}/follow-up`, 'POST', body);
        },
        async _act(url, method, body) {
            if (this.loading) return false;
            this.loading = true;
            try {
                const headers = {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this._csrf(),
                };
                const opts = { method, headers };
                if (body) {
                    headers['Content-Type'] = 'application/x-www-form-urlencoded';
                    opts.body = body.toString();
                }
                const r = await fetch(url, opts);
                if (!r.ok) throw new Error('request failed');
                await this.reload();
                return true;
            } catch (e) {
                // Leave the list untouched on a transient failure.
                return false;
            } finally {
                this.loading = false;
            }
        },
        // Pull a fresh list body so overdue/upcoming buckets re-sort in place,
        // then sync the header chips to the refreshed counts.
        async reload() {
            try {
                const r = await fetch(this.$el.dataset.reloadUrl, {
                    headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (r.ok) {
                    this.$refs.body.innerHTML = await r.text();
                    this._syncChips();
                }
            } catch (e) {}
        },
        // Read the fresh counts embedded in the reloaded body and update the
        // page-hero chips so "N overdue / N upcoming" never lags the list.
        _syncChips() {
            const counts = this.$refs.body.querySelector('[data-fo-counts]');
            if (!counts) return;
            const overdue = counts.dataset.foOverdue;
            const upcoming = counts.dataset.foUpcoming;
            const overdueChip = document.getElementById('foChipOverdue');
            const upcomingChip = document.getElementById('foChipUpcoming');
            if (overdueChip && overdue != null) overdueChip.textContent = `${overdue} overdue`;
            if (upcomingChip && upcoming != null) upcomingChip.textContent = `${upcoming} upcoming`;
        },
    };
}
</script>
@endpush
@endsection
