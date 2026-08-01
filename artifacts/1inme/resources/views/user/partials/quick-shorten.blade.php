{{-- Header clipboard quick-shorten popover (Task #6285).
     Reads the clipboard on open (with a manual-paste fallback), detects the
     content kind (web URL / email / phone / bare domain), lets the user pick
     an optional custom alias with live availability feedback (reusing the
     existing check-alias endpoint), then creates a short url-type link via
     AJAX only. On success the short URL is copied back to the clipboard and
     a confirmation toast offers open / edit. Included in BOTH the desktop
     header and the mobile drawer (drawer entry dispatches
     `open-quick-shorten` at this component). --}}
<div x-data="quickShorten()"
     @open-quick-shorten.window="openFromEvent()"
     @keydown.escape.window="open = false"
     class="relative">
    <button type="button"
            @click="toggle()"
            class="header-icon-btn hidden sm:flex"
            :class="open ? 'active' : ''"
            title="Quick shorten from clipboard"
            aria-label="Quick shorten from clipboard">
        <i class="fas fa-bolt"></i>
    </button>

    <div x-show="open" @click.away="open = false" x-cloak x-transition
         class="fixed sm:absolute inset-x-3 sm:inset-x-auto sm:right-0 top-[72px] sm:top-auto sm:mt-2 w-auto sm:w-96 rounded-xl p-4 z-50"
         style="background: var(--bg-sidebar); border: 1px solid var(--border-subtle); box-shadow: var(--card-shadow);">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold flex items-center gap-2" style="color: var(--text-primary);">
                <i class="fas fa-bolt text-blue-400"></i> Quick shorten
            </span>
            <button type="button" @click="open = false" class="p-1 rounded" style="color: var(--text-muted);" aria-label="Close">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        {{-- Clipboard unavailable / empty → ask for a paste --}}
        <template x-if="needPaste">
            <div class="mb-3">
                <p class="text-xs mb-2" style="color: var(--text-muted);" x-text="pasteHint"></p>
            </div>
        </template>

        <label class="block text-[10px] font-semibold uppercase tracking-wider mb-1" style="color: var(--text-muted);">Content</label>
        <textarea x-model="content" @input="detect()" rows="2" placeholder="Paste a URL, email, phone number or any text…"
                  class="w-full rounded-lg px-3 py-2 text-xs mb-1 resize-none"
                  style="background: var(--bg-input, rgba(255,255,255,0.05)); border: 1px solid var(--border-subtle); color: var(--text-primary);"></textarea>

        <div class="flex items-center gap-2 mb-3 min-h-[18px]">
            <template x-if="kind">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-medium bg-blue-500/15 text-blue-400">
                    <i class="fas" :class="kindIcon()"></i>
                    <span x-text="kindLabel()"></span>
                </span>
            </template>
            <span class="text-[10px] truncate" style="color: var(--text-muted);" x-text="preview"></span>
        </div>

        <template x-if="kind === 'text'">
            <p class="text-xs mb-3" style="color: var(--text-muted);">
                We'll turn this into a shareable text page, visitors see the full text with a copy button.
            </p>
        </template>

        <div x-show="kind">
            {{-- Domain picker — only when the user actually has choices
                 (own verified custom domains and/or admin global domains). --}}
            <template x-if="domains.length > 0">
                <div class="mb-3">
                    <label class="block text-[10px] font-semibold uppercase tracking-wider mb-1" style="color: var(--text-muted);">Domain</label>
                    <select x-model="domainId" @change="checkAlias()"
                            class="w-full rounded-lg px-3 py-2 text-xs"
                            style="background: var(--bg-input, rgba(255,255,255,0.05)); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                        <option value="" x-text="defaultHost || 'Default domain'"></option>
                        <template x-for="d in domains" :key="d.id">
                            <option :value="String(d.id)" x-text="d.domain" :selected="String(d.id) === domainId"></option>
                        </template>
                    </select>
                </div>
            </template>

            <label class="block text-[10px] font-semibold uppercase tracking-wider mb-1" style="color: var(--text-muted);">Custom alias <span class="normal-case font-normal">(optional)</span></label>
            <input type="text" x-model="alias" @input.debounce.400ms="checkAlias()" placeholder="Leave blank to auto-generate"
                   class="w-full rounded-lg px-3 py-2 text-xs"
                   style="background: var(--bg-input, rgba(255,255,255,0.05)); border: 1px solid var(--border-subtle); color: var(--text-primary);">
            <p class="text-[10px] mt-1 min-h-[14px]"
               :class="aliasStatus === 'available' ? 'text-emerald-400' : (aliasStatus && aliasStatus !== 'empty' ? 'text-rose-400' : '')"
               style="color: var(--text-muted);"
               x-text="aliasMessage"></p>

            <p class="text-xs text-rose-400 mt-1 min-h-[14px]" x-text="error"></p>

            <button type="button" @click="create()" :disabled="busy || !content.trim()"
                    class="btn-primary btn-primary-gradient w-full inline-flex items-center justify-center gap-1.5 text-xs px-3.5 py-2 mt-2 disabled:opacity-50">
                <i class="fas" :class="busy ? 'fa-spinner fa-spin' : 'fa-link'"></i>
                <span x-text="busy ? 'Creating…' : (kind === 'text' ? 'Create text page link' : 'Create short link')"></span>
            </button>
        </div>
    </div>

    {{-- Success toast --}}
    <template x-teleport="body">
        <div x-data x-show="$data.toast" x-cloak x-transition
             class="fixed bottom-5 right-5 z-[90] rounded-xl px-4 py-3 flex items-center gap-3 max-w-sm"
             style="background: var(--bg-sidebar); border: 1px solid var(--border-subtle); box-shadow: var(--card-shadow);">
            <i class="fas fa-check-circle text-emerald-400"></i>
            <div class="min-w-0">
                <p class="text-xs font-semibold" style="color: var(--text-primary);">
                    <span x-text="$data.copied ? 'Short link copied to clipboard' : 'Short link created'"></span>
                </p>
                <p class="text-[11px] truncate text-blue-400" x-text="$data.toastUrl"></p>
                <div class="flex items-center gap-3 mt-1">
                    <a :href="$data.toastUrl" target="_blank" rel="noopener" class="text-[11px] text-blue-400 hover:underline">Open</a>
                    <a :href="$data.toastEdit" class="text-[11px] text-blue-400 hover:underline">Edit</a>
                    <button type="button" @click="$data.toast = false" class="text-[11px]" style="color: var(--text-muted);">Dismiss</button>
                </div>
            </div>
        </div>
    </template>
</div>

@once
@push('scripts')
<script>
function quickShorten() {
    return {
        open: false, needPaste: false, pasteHint: '',
        content: '', kind: null, preview: '',
        alias: '', aliasStatus: null, aliasMessage: '',
        domains: [], domainId: '', defaultHost: '', domainsLoaded: false,
        busy: false, error: '',
        toast: false, toastUrl: '', toastEdit: '', copied: false,

        toggle() {
            this.open = !this.open;
            if (this.open) { this.readClipboard(); this.loadDomains(); }
        },
        openFromEvent() {
            this.open = true;
            this.readClipboard();
            this.loadDomains();
        },
        // Lazily fetch the user's attachable domains (own verified custom
        // domains + admin global domains) the first time the popover opens.
        // No domains → the picker stays hidden and behaviour is unchanged.
        async loadDomains() {
            if (this.domainsLoaded) return;
            try {
                const r = await fetch(`{{ route('user.links.quick-shorten.domains') }}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!r.ok) return;
                const d = await r.json();
                this.domains = d.items || [];
                this.defaultHost = d.default_host || '';
                // Pre-select the platform primary domain when the admin set one.
                if (d.primary_domain_id && this.domains.some(x => x.id === d.primary_domain_id)) {
                    this.domainId = String(d.primary_domain_id);
                }
                this.domainsLoaded = true;
            } catch (e) { /* picker is optional — default domain still works */ }
        },
        async readClipboard() {
            this.error = ''; this.needPaste = false;
            if (!navigator.clipboard || !navigator.clipboard.readText) {
                this.needPaste = true;
                this.pasteHint = 'Your browser blocks clipboard reading here, paste the content below instead.';
                return;
            }
            try {
                const text = (await navigator.clipboard.readText() || '').trim();
                if (!text) {
                    this.needPaste = true;
                    this.pasteHint = 'Your clipboard is empty, paste or type the content below.';
                    return;
                }
                this.content = text.slice(0, 20000);
                this.detect();
            } catch (e) {
                this.needPaste = true;
                this.pasteHint = 'Clipboard permission was denied, paste the content below instead.';
            }
        },
        // Mirrors LinkController::normalizeQuickDestination (server re-validates).
        detect() {
            const raw = this.content.trim();
            this.error = '';
            if (!raw) { this.kind = null; this.preview = ''; return; }
            if (/^https?:\/\//i.test(raw)) { this.kind = 'url'; }
            else if (/^mailto:/i.test(raw) || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(raw)) { this.kind = 'email'; }
            else if (/^tel:/i.test(raw) || /^\+?[0-9][0-9\s\-().]{4,24}$/.test(raw)) { this.kind = 'phone'; }
            else if (!/\s/.test(raw) && /^[A-Za-z0-9][A-Za-z0-9.-]*\.[A-Za-z]{2,}([\/?#][^\s]*)?$/.test(raw)) { this.kind = 'url'; }
            else { this.kind = 'text'; }
            this.preview = raw.length > 60 ? raw.slice(0, 57) + '…' : raw;
        },
        kindLabel() {
            return { url: 'Web URL', email: 'Email address', phone: 'Phone number', text: 'Text' }[this.kind] || '';
        },
        kindIcon() {
            return { url: 'fa-globe', email: 'fa-envelope', phone: 'fa-phone', text: 'fa-align-left' }[this.kind] || 'fa-question';
        },
        async checkAlias() {
            const a = this.alias.trim();
            if (!a) { this.aliasStatus = null; this.aliasMessage = ''; return; }
            try {
                const r = await fetch(`{{ route('user.links.check-alias') }}?alias=${encodeURIComponent(a)}${this.domainId ? `&domain_id=${encodeURIComponent(this.domainId)}` : ''}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const d = await r.json();
                this.aliasStatus = d.status; this.aliasMessage = d.message || '';
            } catch (e) { /* transient — submit still validates */ }
        },
        async create() {
            if (this.busy) return;
            this.busy = true; this.error = '';
            try {
                const r = await fetch(`{{ route('user.links.quick-shorten') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ destination: this.content.trim(), alias: this.alias.trim() || null, domain_id: this.domainId || null }),
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok) {
                    this.error = d.error || (d.errors ? Object.values(d.errors).flat()[0] : '') || d.message || 'Something went wrong, try again.';
                    return;
                }
                // Copy the new short URL back to the clipboard.
                this.copied = false;
                try {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        await navigator.clipboard.writeText(d.short_url);
                        this.copied = true;
                    }
                } catch (e) { /* copy is best-effort */ }
                this.toastUrl = d.short_url;
                this.toastEdit = d.edit_url;
                this.toast = true;
                setTimeout(() => { this.toast = false; }, 8000);
                this.open = false;
                this.content = ''; this.kind = null; this.preview = '';
                this.alias = ''; this.aliasStatus = null; this.aliasMessage = '';
            } catch (e) {
                this.error = 'Network error, try again.';
            } finally {
                this.busy = false;
            }
        },
    };
}
</script>
@endpush
@endonce
