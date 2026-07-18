@php
    use App\Support\CountryDialCodes;
    $contact     = $contact ?? null;
    $phoneLabels = $phoneLabels ?? ['Mobile','Work','Home','Other'];
    $emailLabels = $emailLabels ?? ['Personal','Work','Other'];
    $phones      = $contact ? $contact->phones->all() : [];
    $emails      = $contact ? $contact->emails->all() : [];
    if (empty($phones)) $phones = [(object)['label'=>'Mobile','value'=>$prefillPhone ?? '']];
    if (empty($emails)) $emails = [(object)['label'=>'Personal','value'=>'']];
    $dialCountries = CountryDialCodes::all();
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
            @php [$_ccDial, $_ccNum] = CountryDialCodes::parse($p->value ?? ''); @endphp
            <div class="flex gap-2 phone-row">
                <select name="phones[{{ $i }}][label]" class="px-2 py-2 rounded-lg text-xs w-28" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                    @foreach($phoneLabels as $l)
                        <option value="{{ $l }}" @selected($p->label === $l)>{{ $l }}</option>
                    @endforeach
                </select>
                {{-- Country code + number split --}}
                <div class="flex flex-1 rounded-lg overflow-hidden" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);">
                    <select class="phone-cc-select px-1.5 py-2 text-xs border-r border-white/10 shrink-0 bg-transparent focus:outline-none cursor-pointer" style="color:var(--text-primary);">
                        @foreach($dialCountries as $c)
                            <option value="{{ $c['dial'] }}" @selected($_ccDial === $c['dial'])>{{ $c['flag'] }} {{ $c['dial'] }}</option>
                        @endforeach
                    </select>
                    <input type="tel" class="phone-num-input flex-1 px-2 py-2 text-sm bg-transparent focus:outline-none" style="color:var(--text-primary);" value="{{ $_ccNum }}" placeholder="555 0100">
                </div>
                <input type="hidden" name="phones[{{ $i }}][value]" value="{{ $p->value }}" class="phone-value-hidden">
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

<div class="mt-5"
     x-data="tagChipInput({ existing: @js(old('tags', $contact?->tags ?? [])), suggestions: [] })"
     x-init="loadSuggestions()">
    <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-muted);">Tags</label>
    <div class="flex flex-wrap gap-1.5 mb-2">
        <template x-for="(tag, i) in tags" :key="i">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                  style="background:rgba(61,107,255,.15);color:#90acff;border:1px solid rgba(61,107,255,.25);">
                <span x-text="tag"></span>
                <button type="button" @click="removeTag(i)" class="opacity-60 hover:opacity-100 leading-none">&times;</button>
                <input type="hidden" :name="'tags[' + i + ']'" :value="tag">
            </span>
        </template>
    </div>
    <div class="relative">
        <input type="text" x-model="input" @keydown.enter.prevent="addFromInput()"
               @keydown.comma.prevent="addFromInput()"
               @keydown.backspace="onBackspace()"
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
    </div>
    <p class="text-[11px] mt-1" style="color:var(--text-faint);">Press Enter or comma to add. Click &times; to remove.</p>
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
function tagChipInput(cfg) {
    return {
        tags: (cfg.existing || []).filter(Boolean),
        suggestions: cfg.suggestions || [],
        input: '',
        filtered: [],
        showDropdown: false,

        loadSuggestions() {
            fetch('{{ route("user.contacts.tags") }}', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            }).then(r => r.json()).then(d => {
                this.suggestions = d.data || [];
            }).catch(() => {});
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
        },

        removeTag(i) {
            this.tags.splice(i, 1);
        },

        onBackspace() {
            if (this.input === '' && this.tags.length) this.tags.pop();
        },

        filterSuggestions() {
            const q = this.input.trim().toLowerCase();
            this.filtered = this.suggestions.filter(s =>
                (!q || s.toLowerCase().includes(q)) && !this.tags.includes(s)
            ).slice(0, 8);
            this.showDropdown = this.filtered.length > 0;
        },
    };
}
</script>

<style>
html.light-mode #phones-list .phone-cc-select,
html.light-mode #phones-list .phone-num-input { color: #1e1e2e !important; }
html.light-mode #phones-list .flex { background: rgba(0,0,0,.04) !important; border-color: rgba(0,0,0,.12) !important; }
html.light-mode #phones-list .border-white\/10 { border-color: rgba(0,0,0,.12) !important; }
</style>

<script>
(function () {
    var DIAL_CODES = @json($dialCountries);

    function parseDial(val) {
        val = (val || '').trim();
        if (!val) return ['+1', ''];
        if (val[0] !== '+') return ['+1', val];
        var sorted = DIAL_CODES.map(function (c) { return c.dial; })
            .filter(function (d, i, arr) { return arr.indexOf(d) === i; })
            .sort(function (a, b) { return b.length - a.length; });
        for (var i = 0; i < sorted.length; i++) {
            if (val.startsWith(sorted[i])) {
                return [sorted[i], val.slice(sorted[i].length).trimStart()];
            }
        }
        return ['+1', val];
    }

    function combinePhone(row) {
        var cc  = row.querySelector('.phone-cc-select');
        var num = row.querySelector('.phone-num-input');
        var hid = row.querySelector('.phone-value-hidden');
        if (!cc || !num || !hid) return;
        var n = num.value.trim();
        hid.value = n ? (cc.value + ' ' + n) : '';
    }

    document.getElementById('phones-list').addEventListener('change', function (e) {
        var row = e.target.closest('.phone-row');
        if (row) combinePhone(row);
    });
    document.getElementById('phones-list').addEventListener('input', function (e) {
        var row = e.target.closest('.phone-row');
        if (row && e.target.classList.contains('phone-num-input')) combinePhone(row);
    });

    // Per-list monotonically increasing counter so newly-added rows always
    // get a unique array index (otherwise PHP overwrites duplicates).
    var counters = {};
    function nextIdx(listId) {
        if (counters[listId] === undefined) {
            var list = document.getElementById(listId);
            var max = -1;
            list.querySelectorAll('[name]').forEach(function (el) {
                var m = el.name.match(/\[(\d+)\]/);
                if (m) max = Math.max(max, parseInt(m[1], 10));
            });
            counters[listId] = max + 1;
        }
        return counters[listId]++;
    }

    document.querySelectorAll('[data-add-row]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var listId = btn.dataset.addRow;
            var list   = document.getElementById(listId);
            var tpl    = list.querySelector('div').cloneNode(true);
            var idx    = nextIdx(listId);
            tpl.querySelectorAll('input,select').forEach(function (el) {
                if (el.name) el.name = el.name.replace(/\[\d+\]/, '['+idx+']');
                if (el.tagName === 'INPUT' && el.type !== 'hidden') el.value = '';
                if (el.type === 'hidden') el.value = '';
            });
            list.appendChild(tpl);
        });
    });
})();
</script>
