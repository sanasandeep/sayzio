{{--
    Shared "Custom URL available / taken" live-availability behaviour.

    Registers the `aliasChecker` Alpine component once per page. Apply it on any
    Create Link form by putting `x-data="aliasChecker('{{ route('user.links.check-alias') }}')" x-init="init()"`
    on a wrapper that contains a single `input[name=alias]`, and wiring the input
    with `@input.debounce.400ms="check($event.target.value)"`.

    Debounced GET against user.links.check-alias, which mirrors the exact
    server-side alias rules (alpha_dash, plan length limits, unique, banned).
--}}
@once
@push('scripts')
<script>
document.addEventListener('alpine:init', function () {
    if (window.__aliasCheckerRegistered) { return; }
    window.__aliasCheckerRegistered = true;

    window.Alpine.data('aliasChecker', function (endpoint) {
        return {
            endpoint: endpoint,
            state: '',        // '' | 'empty' | 'checking' | 'available' | <error status>
            message: '',
            reqToken: 0,
            controller: null,

            get isError() {
                return this.state && this.state !== 'empty'
                    && this.state !== 'checking' && this.state !== 'available';
            },

            init: function () {
                var el = this.$el.querySelector('input[name=alias]');
                if (el && el.value.trim()) { this.check(el.value); }
            },

            check: function (raw) {
                var value = (raw || '').trim();
                this.reqToken++;
                var token = this.reqToken;

                // Abort any in-flight request so a stale response can't win.
                if (this.controller) { try { this.controller.abort(); } catch (e) {} }

                if (value === '') {
                    this.state = 'empty';
                    this.message = '';
                    return;
                }

                this.state = 'checking';
                this.message = 'Checking availability…';

                this.controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
                var self = this;

                fetch(this.endpoint + '?alias=' + encodeURIComponent(value), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: this.controller ? this.controller.signal : undefined
                })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
                .then(function (data) {
                    if (token !== self.reqToken) { return; }   // a newer check superseded this one
                    self.state = data.status || '';
                    self.message = data.message || '';
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') { return; }
                    if (token !== self.reqToken) { return; }
                    // Network/server hiccup — fail quietly; submit-time validation still guards.
                    self.state = '';
                    self.message = '';
                });
            },
        };
    });
});
</script>
@endpush
@endonce
