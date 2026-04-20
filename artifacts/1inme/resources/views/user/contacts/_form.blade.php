@php
    $contact     = $contact ?? null;
    $phoneLabels = $phoneLabels ?? ['Mobile','Work','Home','Other'];
    $emailLabels = $emailLabels ?? ['Personal','Work','Other'];
    $phones      = $contact ? $contact->phones->all() : [];
    $emails      = $contact ? $contact->emails->all() : [];
    if (empty($phones)) $phones = [(object)['label'=>'Mobile','value'=>$prefillPhone ?? '']];
    if (empty($emails)) $emails = [(object)['label'=>'Personal','value'=>'']];
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-muted);">First name</label>
        <input name="given_name" value="{{ old('given_name', $contact?->given_name) }}"
               class="w-full px-3 py-2 rounded-lg text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
    </div>
    <div>
        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-muted);">Last name</label>
        <input name="family_name" value="{{ old('family_name', $contact?->family_name) }}"
               class="w-full px-3 py-2 rounded-lg text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
    </div>
    <div class="md:col-span-2">
        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-muted);">Display name <span style="color:var(--text-faint);">(optional)</span></label>
        <input name="display_name" value="{{ old('display_name', $contact?->display_name) }}"
               class="w-full px-3 py-2 rounded-lg text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
    </div>
    <div>
        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-muted);">Organization</label>
        <input name="organization" value="{{ old('organization', $contact?->organization) }}"
               class="w-full px-3 py-2 rounded-lg text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
    </div>
    <div>
        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-muted);">Job title</label>
        <input name="job_title" value="{{ old('job_title', $contact?->job_title) }}"
               class="w-full px-3 py-2 rounded-lg text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
    </div>
</div>

<div class="mt-5">
    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-muted);">Phones</label>
    <div id="phones-list" class="space-y-2">
        @foreach($phones as $i => $p)
            <div class="flex gap-2 phone-row">
                <select name="phones[{{ $i }}][label]" class="px-2 py-2 rounded-lg text-xs w-28" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                    @foreach($phoneLabels as $l)
                        <option value="{{ $l }}" @selected($p->label === $l)>{{ $l }}</option>
                    @endforeach
                </select>
                <input type="tel" name="phones[{{ $i }}][value]" value="{{ $p->value }}" placeholder="+1 555 0100"
                       class="flex-1 px-3 py-2 rounded-lg text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                <button type="button" onclick="this.closest('.phone-row').remove()" class="px-3 rounded-lg text-xs" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)"><i class="fas fa-times"></i></button>
            </div>
        @endforeach
    </div>
    <button type="button" data-add-row="phones-list" data-name="phones" class="mt-2 px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-muted);">
        <i class="fas fa-plus mr-1"></i> Add phone
    </button>
</div>

<div class="mt-5">
    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-muted);">Emails</label>
    <div id="emails-list" class="space-y-2">
        @foreach($emails as $i => $e)
            <div class="flex gap-2 email-row">
                <select name="emails[{{ $i }}][label]" class="px-2 py-2 rounded-lg text-xs w-28" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                    @foreach($emailLabels as $l)
                        <option value="{{ $l }}" @selected($e->label === $l)>{{ $l }}</option>
                    @endforeach
                </select>
                <input type="email" name="emails[{{ $i }}][value]" value="{{ $e->value }}" placeholder="name@example.com"
                       class="flex-1 px-3 py-2 rounded-lg text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                <button type="button" onclick="this.closest('.email-row').remove()" class="px-3 rounded-lg text-xs" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)"><i class="fas fa-times"></i></button>
            </div>
        @endforeach
    </div>
    <button type="button" data-add-row="emails-list" data-name="emails" class="mt-2 px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);color:var(--text-muted);">
        <i class="fas fa-plus mr-1"></i> Add email
    </button>
</div>

<div class="mt-5">
    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-muted);">Notes</label>
    <textarea name="notes" rows="3" class="w-full px-3 py-2 rounded-lg text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">{{ old('notes', $contact?->notes) }}</textarea>
</div>

<div class="mt-5">
    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-muted);">Photo</label>
    @if($contact && $contact->photoUrl())
        <div class="flex items-center gap-3 mb-2">
            <img src="{{ $contact->photoUrl() }}" class="w-12 h-12 rounded-full object-cover">
            <label class="text-xs flex items-center gap-1 cursor-pointer" style="color:var(--text-muted);">
                <input type="checkbox" name="remove_photo" value="1"> Remove current photo
            </label>
        </div>
    @endif
    <input type="file" name="photo" accept="image/*" class="text-xs" style="color:var(--text-muted);">
</div>

<script>
(function () {
    // Per-list monotonically increasing counter so newly-added rows always
    // get a unique array index (otherwise PHP overwrites duplicates).
    const counters = {};
    function nextIdx(listId) {
        if (counters[listId] === undefined) {
            const list = document.getElementById(listId);
            let max = -1;
            list.querySelectorAll('[name]').forEach(el => {
                const m = el.name.match(/\[(\d+)\]/);
                if (m) max = Math.max(max, parseInt(m[1], 10));
            });
            counters[listId] = max + 1;
        }
        return counters[listId]++;
    }
    document.querySelectorAll('[data-add-row]').forEach(btn => {
        btn.addEventListener('click', () => {
            const listId = btn.dataset.addRow;
            const list   = document.getElementById(listId);
            const tpl    = list.querySelector('div').cloneNode(true);
            const idx    = nextIdx(listId);
            tpl.querySelectorAll('input,select').forEach(el => {
                el.name = el.name.replace(/\[\d+\]/, '['+idx+']');
                if (el.tagName === 'INPUT') el.value = '';
            });
            list.appendChild(tpl);
        });
    });
})();
</script>
