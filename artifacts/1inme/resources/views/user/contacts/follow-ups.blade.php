@extends('user.layouts.app')

@section('title', 'Follow-ups')

@section('content')
<div class="max-w-4xl mx-auto" x-data="followUpsList()" data-reload-url="{{ route('user.contacts.follow-ups') }}">
    @include('user.partials.page-hero', [
        'title' => 'Follow-ups',
        'subtitle' => 'Everything you need to follow up on, soonest first — clear or snooze right from here.',
        'icon' => 'fa-bell',
        'chips' => [
            ['icon' => 'fa-exclamation-circle text-red-400', 'text' => $overdue->count() . ' overdue'],
            ['icon' => 'fa-clock text-cyan-400',            'text' => $upcoming->count() . ' upcoming'],
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
</div>

@push('scripts')
<script>
function followUpsList() {
    return {
        loading: false,
        _csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        },
        // Clear a follow-up straight from the list ("Done").
        async done(id) {
            await this._act(`{{ url('user/contacts') }}/${id}/follow-up`, 'DELETE');
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
            if (this.loading) return;
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
            } catch (e) {
                // Leave the list untouched on a transient failure.
            } finally {
                this.loading = false;
            }
        },
        // Pull a fresh list body so overdue/upcoming buckets re-sort in place.
        async reload() {
            try {
                const r = await fetch(this.$el.dataset.reloadUrl, {
                    headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (r.ok) this.$refs.body.innerHTML = await r.text();
            } catch (e) {}
        },
    };
}
</script>
@endpush
@endsection
