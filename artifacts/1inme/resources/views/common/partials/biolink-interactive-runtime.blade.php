    <script>
    // Live poll component: posts the picked option to the JSON poll-vote
    // endpoint and then fetches the aggregated tallies so viewers see
    // counts/bars instead of a generic "Thanks!" message.
    function biolinkPoll(opts) {
        return {
            alias: opts.alias,
            blockId: opts.blockId,
            options: Array.isArray(opts.options) ? opts.options : [],
            voted: null,
            submitting: null,
            results: null,
            error: '',
            // Reveal-at deadline: when set + still in the future, the
            // /poll-results endpoint refuses tallies (even for voters),
            // so we surface a "Results visible after <date>" line.
            revealAt: opts.revealAt || null,
            resultsLocked: false,
            revealAtDisplay: '',
            init() {
                if (this.revealAt) {
                    const d = new Date(this.revealAt);
                    if (!Number.isNaN(d.getTime())) {
                        this.revealAtDisplay = d.toLocaleString();
                        if (d.getTime() > Date.now()) this.resultsLocked = true;
                    }
                }
            },
            async vote(i, label) {
                if (this.submitting !== null) return;
                this.submitting = i;
                this.error = '';
                try {
                    const r = await fetch(`/api/v1/biolinks/${encodeURIComponent(this.alias)}/blocks/${this.blockId}/poll-vote`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ option_index: i, option_label: typeof label === 'string' ? label : null }),
                    });
                    if (!r.ok) throw new Error('vote failed');
                    this.voted = i;
                    await this.loadResults();
                } catch (e) {
                    this.error = 'Could not save your vote. Please try again.';
                } finally {
                    this.submitting = null;
                }
            },
            async loadResults() {
                try {
                    const r = await fetch(`/api/v1/biolinks/${encodeURIComponent(this.alias)}/blocks/${this.blockId}/poll-results`, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                    });
                    if (r.status === 403) {
                        // Either the creator has hidden tallies until the
                        // viewer votes, or the reveal-at deadline hasn't
                        // passed yet. Inspect the envelope to tell them apart.
                        let body = null;
                        try { body = await r.json(); } catch (_) { /* noop */ }
                        const code = body && body.error && body.error.code;
                        const details = (body && body.error && body.error.details) || {};
                        if (code === 'results_locked' && details.reveal_at) {
                            this.revealAt = details.reveal_at;
                            const d = new Date(details.reveal_at);
                            if (!Number.isNaN(d.getTime())) this.revealAtDisplay = d.toLocaleString();
                            this.resultsLocked = true;
                            this.error = '';
                            return;
                        }
                        this.error = 'Vote to see results';
                        return;
                    }
                    if (!r.ok) return;
                    const json = await r.json();
                    if (json && json.data && Array.isArray(json.data.options)) {
                        this.results = json.data;
                    }
                } catch (_) {
                    /* swallow — vote is already recorded */
                }
            },
        };
    }

    function countdown(target) {
        return {
            days: 0, hours: 0, minutes: 0, seconds: 0, interval: null,
            start() {
                if (!target) return;
                const end = new Date(target).getTime();
                this.interval = setInterval(() => {
                    const now = Date.now();
                    const diff = Math.max(0, end - now);
                    this.days = Math.floor(diff / 86400000);
                    this.hours = Math.floor((diff % 86400000) / 3600000);
                    this.minutes = Math.floor((diff % 3600000) / 60000);
                    this.seconds = Math.floor((diff % 60000) / 1000);
                    if (diff <= 0) clearInterval(this.interval);
                }, 1000);
            }
        }
    }
    </script>
