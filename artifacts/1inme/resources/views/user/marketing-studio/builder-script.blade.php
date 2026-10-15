<script>
function brandStudio() {
    return {
        brandKitId: @js((string)($campaign?->brief['brand_kit_id']??'')), brandName: '', brandColors: '', brandVoice: '', brandDescription: '',
        brief: @js($campaignBrief??''), mode: 'kit', bulkKind: 'short_link', bulkCount: 5,
        busy: false, error: '', estimateText: '',
        estQuote:null,estCredits: null, estBalance: {{ (int) $balance }}, estBusy: false,
        _estTimer: null, _estSeq: 0,
        composition: @js(in_array(request('output'),\App\Services\Brand\AiBrandStudioService::ASSET_KINDS)?[['kind'=>request('output'),'count'=>1,'purpose'=>'']]:[]),
        savedPresets: @js($savedPresets),
        savingPreset: false, presetName: '', presetBusy: false, presetError: '',
        renamingId: null, renameName: '', renameBusy: false, renameError: '',
        kitCaps: @js($kitCaps),
        kindLabels: { biolink: 'Link in Bio page', short_link: 'Short link', qr_code: 'QR code', form: 'Form', vcard: 'Digital card' },
        presets: [
            { label: 'Product + sales + card', rows: [
                { kind: 'biolink', count: 1, purpose: 'Product page' },
                { kind: 'biolink', count: 1, purpose: 'Sales offer page' },
                { kind: 'vcard', count: 1, purpose: 'Digital business card' },
            ] },
            { label: 'Launch pack', rows: [
                { kind: 'biolink', count: 1, purpose: 'Launch landing page' },
                { kind: 'short_link', count: 3, purpose: 'Campaign links' },
                { kind: 'qr_code', count: 2, purpose: 'Poster QR codes' },
            ] },
            { label: 'Lead-gen pack', rows: [
                { kind: 'biolink', count: 1, purpose: 'Lead capture page' },
                { kind: 'form', count: 1, purpose: 'Lead form' },
            ] },
            { label: 'Personal brand', rows: [
                { kind: 'biolink', count: 1, purpose: 'Personal bio page' },
                { kind: 'vcard', count: 1, purpose: 'Digital card' },
                { kind: 'qr_code', count: 1, purpose: 'Share-me QR code' },
            ] },
        ],
        init() {
            if(this.brief)this.scheduleEstimate();
            ['brief', 'mode', 'bulkKind', 'bulkCount', 'brandKitId', 'brandName', 'brandColors', 'brandVoice', 'brandDescription', 'composition']
                .forEach((k) => this.$watch(k, () => this.scheduleEstimate()));
        },
        applyPreset(p) {
            this.composition = p.rows.map((r) => ({ ...r }));
        },
        async savePreset() {
            const name = this.presetName.trim();
            if (!name || this.presetBusy || !this.composition.length) return;
            this.presetBusy = true; this.presetError = '';
            try {
                const res = await fetch(@js(route('user.brand-studio.presets.store')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        name,
                        composition: this.composition.map((r) => ({ kind: r.kind, count: Math.max(1, parseInt(r.count, 10) || 1), purpose: (r.purpose || '').trim() })),
                    }),
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(json.message || 'Could not save this combo. Please try again.');
                this.savedPresets = [json.preset, ...this.savedPresets.filter((p) => p.id !== json.preset.id)];
                this.savingPreset = false; this.presetName = '';
            } catch (e) {
                this.presetError = e.message;
            } finally {
                this.presetBusy = false;
            }
        },
        startRename(p) {
            this.renamingId = p.id;
            this.renameName = p.label;
            this.renameError = '';
        },
        cancelRename() {
            this.renamingId = null;
            this.renameName = '';
            this.renameError = '';
        },
        async renamePreset(p) {
            const name = this.renameName.trim();
            if (!name || this.renameBusy) return;
            if (name === p.label) { this.cancelRename(); return; }
            this.renameBusy = true; this.renameError = '';
            try {
                const res = await fetch(@js(route('user.brand-studio.presets.rename', ':id')).replace(':id', p.id), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ name }),
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(json.message || 'Could not rename this combo. Please try again.');
                this.savedPresets = this.savedPresets.map((x) => x.id === json.preset.id ? json.preset : x);
                this.cancelRename();
            } catch (e) {
                this.renameError = e.message;
            } finally {
                this.renameBusy = false;
            }
        },
        async deletePreset(p) {
            if (!confirm(`Delete the saved combo “${p.label}”?`)) return;
            try {
                const res = await fetch(@js(route('user.brand-studio.presets.destroy', ':id')).replace(':id', p.id), {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                });
                if (!res.ok) throw new Error('Could not delete this combo. Please try again.');
                this.savedPresets = this.savedPresets.filter((x) => x.id !== p.id);
            } catch (e) {
                this.presetError = e.message;
                this.savingPreset = true;
            }
        },
        addRow() {
            this.composition.push({ kind: 'biolink', count: 1, purpose: '' });
        },
        compositionError() {
            const sums = {};
            for (const r of this.composition) {
                sums[r.kind] = (sums[r.kind] || 0) + Math.max(1, parseInt(r.count, 10) || 1);
                const cap = this.kitCaps[r.kind] || 0;
                if (sums[r.kind] > cap) {
                    return `Too many ${this.kindLabels[r.kind] || r.kind}s: max ${cap} per kit.`;
                }
            }
            return '';
        },
        canGenerate() {
            if (this.mode === 'kit' && this.composition.length) return !this.compositionError();
            return !!this.brief.trim();
        },
        bulkVariants() {
            const cap = {{ (int) $bulkCap }};
            let n = Math.max(1, parseInt(this.bulkCount, 10) || 1);
            if (cap > 0) n = Math.min(n, cap);
            return n;
        },
        perVariantCredits() {
            if (this.estCredits === null) return 0;
            return Math.max(1, Math.round(this.estCredits / this.bulkVariants()));
        },
        lowBalance() {
            return this.estCredits !== null && this.estCredits > this.estBalance;
        },
        scheduleEstimate() {
            clearTimeout(this._estTimer);
            this.estQuote=null;this.estCredits = null; this.estimateText = '';
            if (!this.canGenerate()) { this.estBusy = false; return; }
            this.estBusy = true;
            this._estTimer = setTimeout(() => this.estimate(true), 600);
        },
        payload() {
            return {
                strategy_id:@js(request('strategy_id')),quote:this.estQuote,campaign_id:@js($campaign?->id), request: this.brief,
                mode: this.mode,
                composition: this.mode === 'kit' && this.composition.length
                    ? this.composition.map((r) => ({ kind: r.kind, count: Math.max(1, parseInt(r.count, 10) || 1), purpose: (r.purpose || '').trim() }))
                    : null,
                bulk_kind: this.mode === 'bulk' ? this.bulkKind : null,
                bulk_count: this.mode === 'bulk' ? this.bulkCount : null,
                brand_kit_id: this.brandKitId || null,
                brand_name: this.brandName, brand_colors: this.brandColors,
                brand_voice: this.brandVoice, brand_description: this.brandDescription,
            };
        },
        async post(url) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify(this.payload()),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.message || 'Something went wrong. Please try again.');
            return json;
        },
        async estimate(auto = false) {
            if (!auto) { this.error = ''; }
            this.estimateText = '';
            const seq = ++this._estSeq;
            this.estBusy = true;
            try {
                const j = await this.post(@js(route('user.brand-studio.estimate')));
                if (seq !== this._estSeq) return;
                this.estQuote=j.quote;this.estCredits = j.estimated_credits;
                this.estBalance = j.balance;
                this.estimateText = `≈ ${j.estimated_credits} coins (you have ${j.balance})`;
            } catch (e) {
                if (seq !== this._estSeq) return;
                this.estCredits = null;
                if (!auto) this.error = e.message;
            } finally {
                if (seq === this._estSeq) this.estBusy = false;
            }
        },
        async plan() {
            this.error = ''; this.busy = true;
            try {
                const j = await this.post(@js(route('user.brand-studio.plan')));
                window.location.href = j.redirect;
            } catch (e) { this.error = e.message; this.busy = false;this.estCredits=null;this.estQuote=null; }
        },
    };
}
</script>