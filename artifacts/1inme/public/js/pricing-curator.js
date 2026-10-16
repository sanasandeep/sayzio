/* Tab-session-only matcher. No model requests, coins, accounts or server writes. */
window.sayzioPricingCurator = function (catalogue, initiallyExpanded) {
    const storageKey = 'sayzio.pricing.matcher.v1';
    return {
        expanded: initiallyExpanded, matcherOpen: false, matching: false, matches: [],
        curating: false, curationPhase: '',
        answers: { purpose: 'bio', pages: '1', seats: '1', domain: false, payments: false, api: false },
        restoreCuration() {
            try {
                const saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
                if (!saved) return;
                for (const key of ['purpose', 'pages', 'seats']) {
                    if (typeof saved.answers?.[key] === 'string') this.answers[key] = saved.answers[key];
                }
                for (const key of ['domain', 'payments', 'api']) this.answers[key] = saved.answers?.[key] === true;
                this.expanded = saved.expanded === true || initiallyExpanded;
                if (saved.curated) this.matches = this.matchCatalogue();
            } catch (_) { /* Storage restrictions leave the matcher usable in memory. */ }
        },
        saveCuration(curated) {
            try { sessionStorage.setItem(storageKey, JSON.stringify({ answers: this.answers, expanded: this.expanded, curated })); } catch (_) {}
        },
        matchCatalogue() {
            const pages = Math.max(1, Number(this.answers.pages) || 1);
            const seats = Math.max(this.answers.purpose === 'team' ? 3 : 1, Number(this.answers.seats) || 1);
            const api = this.answers.api || this.answers.purpose === 'developer';
            const enough = (value, required) => Number(value) === -1 || Number(value) >= required;
            const ranked = catalogue.map(plan => {
                const f = plan.features || {};
                const missing = [];
                if (!enough(f.max_biolinks, pages)) missing.push('Link in Bio page allowance');
                if (!enough(f.max_seats_per_workspace, seats)) missing.push('team seats');
                if (this.answers.domain && !f.custom_domains) missing.push('custom domains');
                if (this.answers.payments && !f.paid_forms) missing.push('paid forms');
                if (api && !f.api_access) missing.push('API access');
                const price = plan.prices?.[this.currency] || plan.prices?.USD || {};
                const selected = this.cycle === 'annual' && price.annual ? price.annual : price.monthly;
                const cost = Number(selected?.amount_minor ?? Infinity);
                return { ...plan, missing, cost, exact: missing.length === 0 };
            }).filter(plan => Number.isFinite(plan.cost)).sort((a, b) => a.missing.length - b.missing.length || a.cost - b.cost);
            const exact = ranked.filter(plan => plan.exact);
            return (exact.length ? exact : ranked).slice(0, 3).map(plan => ({
                id: plan.id, slug: plan.slug, name: plan.name, exact: plan.exact,
                reason: plan.exact ? 'Meets your selected page, seat and feature requirements.' : 'Does not meet: ' + plan.missing.join(', ') + '.',
            }));
        },
        async curatePlans() {
            if (this.matching) return;
            this.matching = true;
            try {
                // Brief presentation transition keeps the change in comparison understandable.
                await new Promise(resolve => setTimeout(resolve, 450));
                this.matches = this.matchCatalogue();
                this.expanded = true;
                this.matcherOpen = false;
                this.saveCuration(true);
                this.$nextTick(() => this.$refs.curationResults?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' }));
            } finally { this.matching = false; }
        },
        /**
         * Press Curate, watch Zio for a beat, land on a different set.
         *
         * The phases are deliberately about what is happening on the page and
         * nothing else. Writing "checking your history" here would be a claim
         * about reading data that this page does not touch, and a pricing
         * page that lies about something small is not trusted about the
         * numbers either.
         *
         * The real work is a server round trip, so the pause is the whole
         * animation: it finishes, the browser navigates, and the new set is
         * rendered by the same code path as any other visit.
         */
        async startCuration(nextUrl) {
            if (this.curating) return;
            this.curating = true;

            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const phases = ['Reading the room', 'Lining up your options', 'Picking your set'];
            const beat = reduced ? 120 : 620;

            for (const phase of phases) {
                this.curationPhase = phase;
                await new Promise(resolve => setTimeout(resolve, beat));
            }

            window.location.assign(nextUrl);
        },
        showAllPlans() { this.expanded = true; this.saveCuration(this.matches.length > 0); },
        resetCuration() {
            this.expanded = false; this.matcherOpen = false; this.matches = [];
            this.answers = { purpose: 'bio', pages: '1', seats: '1', domain: false, payments: false, api: false };
            try { sessionStorage.removeItem(storageKey); } catch (_) {}
        },
        openMatchedPlan(id) {
            const element = document.getElementById('full-plan-' + id);
            element?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center', inline: 'center' });
        },
    };
};
