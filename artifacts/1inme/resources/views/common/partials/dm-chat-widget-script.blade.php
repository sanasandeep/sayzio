{{-- Registers the global window.dmBlock() Alpine component used by both
     the biolink Direct Message block and the Creators directory chat
     overlay. Idempotent — safe to include from multiple partials. --}}
@once
    <script>
        if (typeof window.dmBlock !== 'function') {
            window.dmBlock = function (cfg) {
                return {
                    linkId:   cfg.linkId,
                    limit:    cfg.limit,
                    loggedIn: !!cfg.loggedIn,
                    csrf:     cfg.csrf || '',
                    body:     '',
                    loading:  false,
                    error:    '',
                    messages: [],
                    state:    { sent: 0, owner_replied: false, blocked: false, throttled: false, unavailable: false },
                    async init() {
                        if (!this.loggedIn) return;
                        await this.refresh();
                    },
                    async refresh() {
                        try {
                            const r = await fetch(`/viewer/dm/${this.linkId}/thread`, { headers: { Accept: 'application/json' } });
                            if (r.status === 401) { this.loggedIn = false; return; }
                            // 404 = link gone; 403 dm_disabled = creator turned DM off mid-session.
                            if (r.status === 404) { this.state = { ...this.state, unavailable: true }; return; }
                            if (r.status === 403) {
                                let reason = '';
                                try { reason = (await r.clone().json()).reason || ''; } catch (e) {}
                                if (reason === 'dm_disabled') { this.state = { ...this.state, unavailable: true }; return; }
                            }
                            const j = await r.json();
                            if (j.ok) {
                                this.messages = j.messages || [];
                                this.state    = { ...this.state, ...(j.state || {}), unavailable: false };
                            }
                        } catch (e) { /* swallow */ }
                    },
                    async send() {
                        const trimmed = (this.body || '').trim();
                        if (this.loading || !trimmed) return;
                        this.loading = true;
                        this.error   = '';
                        try {
                            const meta  = document.querySelector('meta[name="csrf-token"]');
                            const token = (meta && meta.getAttribute('content')) || this.csrf || '';
                            const fd = new FormData();
                            fd.append('body', trimmed);
                            fd.append('_token', token);
                            const r = await fetch(`/viewer/dm/${this.linkId}/send`, {
                                method:  'POST',
                                body:    fd,
                                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
                            });
                            const j = await r.json();
                            if (!j.ok) {
                                const map = {
                                    login_required: 'Please log in to send a message.',
                                    blocked:        'The creator has blocked this conversation.',
                                    throttled:      `You've used your ${this.limit} intro messages. Wait for a reply.`,
                                    self:           "You can't message your own Link in Bio.",
                                    not_found:      'This creator can no longer be messaged.',
                                    dm_disabled:    'This creator has turned off direct messages.',
                                    empty:          'Message cannot be empty.',
                                };
                                this.error = map[j.reason] || 'Could not send message.';
                                if (j.reason === 'login_required') { this.loggedIn = false; }
                                if (j.reason === 'not_found' || j.reason === 'dm_disabled') {
                                    this.state = { ...this.state, unavailable: true };
                                }
                            } else {
                                this.body = '';
                                await this.refresh();
                            }
                        } catch (e) {
                            this.error = 'Network error. Try again.';
                        } finally {
                            this.loading = false;
                        }
                    },
                };
            };
        }
    </script>
@endonce
