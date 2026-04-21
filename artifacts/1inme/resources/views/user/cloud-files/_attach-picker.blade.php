{{--
    Reusable Alpine component that opens a modal listing the workspace cloud
    library so the user can attach files to a post composer, an inbox reply,
    or a task card.

    Two modes are supported by the component itself (selected per call site):
      - form mode  : selected files become hidden inputs in the parent form
                     and are rendered as removable chips. The form's normal
                     submit handles persistence.
      - ajax mode  : on confirm we POST /user/cloud-files/attach with the
                     target_type / target_id; the caller receives the resulting
                     attachment rows and re-renders.

    The component is registered once via @once @push('scripts'). Each picker
    instance is a small inline Alpine block that calls cloudAttachPicker(...).
--}}
@once
@push('scripts')
<script>
window.cloudAttachPicker = function (opts) {
    return {
        // --- config -----------------------------------------------------
        mode: opts.mode || 'form',          // 'form' | 'ajax'
        targetType: opts.targetType || '',  // 'post' | 'task_card' | 'inbox_reply'
        targetId: opts.targetId || null,
        onAttached: opts.onAttached || null, // ajax mode: callback(att[])
        // --- state ------------------------------------------------------
        open: false,
        loading: false,
        saving: false,
        error: '',
        search: '',
        files: [],
        picked: [],   // form-mode: full file objects already attached
        async show() {
            this.open = true;
            this.error = '';
            await this.refresh();
        },
        async refresh() {
            this.loading = true;
            try {
                const url = '{{ route('user.cloud-files.library') }}'
                    + (this.search ? ('?q=' + encodeURIComponent(this.search)) : '');
                const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!r.ok) throw new Error('Failed to load library');
                const j = await r.json();
                this.files = j.files || [];
            } catch (e) { this.error = 'Could not load the cloud library.'; }
            finally { this.loading = false; }
        },
        isPicked(f) { return this.picked.some(p => p.id === f.id); },
        toggle(f) {
            if (this.isPicked(f)) {
                this.picked = this.picked.filter(p => p.id !== f.id);
            } else {
                this.picked.push(f);
            }
        },
        remove(id) { this.picked = this.picked.filter(p => p.id !== id); },
        async confirm() {
            if (this.mode === 'form') { this.open = false; return; }
            if (!this.picked.length) { this.open = false; return; }
            this.saving = true;
            try {
                const r = await fetch('{{ route('user.cloud-files.attach') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        target_type: this.targetType,
                        target_id: this.targetId,
                        cloud_file_ids: this.picked.map(p => p.id),
                    }),
                });
                if (!r.ok) { this.error = 'Could not attach files.'; return; }
                const j = await r.json();
                this.picked = [];
                this.open = false;
                if (typeof this.onAttached === 'function') this.onAttached(j.attachments || []);
            } finally { this.saving = false; }
        },
    };
};
</script>
@endpush
@endonce
