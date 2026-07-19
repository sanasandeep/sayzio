{{-- Task #3525 — "Customize dashboard" chooser: 5 curated presets or
     "Design with AI". Preset apply + AI estimate/generate all hit the
     dashboard.layout.* / dashboard.ai.* JSON endpoints; on success we
     just reload so every server-rendered tab/widget picks up the new
     layout — no client-side re-render of the whole page. --}}
{{-- Light-mode legibility: the backdrop + card previously used a hardcoded
     dark color (and a `--bg-glass-card` variable that is never actually
     defined anywhere, so its fallback #14162a always won) while every text
     element inside used the theme's `--text-*` variables, which flip to
     dark values in light mode — dark text on a dark card. `.dcm-backdrop` /
     `.dcm-card` keep the original dark look by default and pick up a proper
     light surface under `html.light-mode`, mirroring the same pattern used
     for the global search modal (`.gsm-backdrop` / `.gsm-panel` in
     theme-styles.blade.php). This is a plain inline `<style>` tag rather than
     `@push('styles')` because this partial is included mid-body, after
     `app.blade.php` has already flushed the head's `@stack('styles')`. --}}
<style>
    .dcm-backdrop { background: rgba(8,10,20,0.72); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }
    html.light-mode .dcm-backdrop { background: var(--overlay-bg); }

    .dcm-card {
        background: linear-gradient(180deg, rgba(20,20,32,0.96), rgba(13,13,20,0.98));
        box-shadow: 0 24px 64px -12px rgba(0,0,0,0.55);
    }
    html.light-mode .dcm-card {
        background: var(--bg-card);
        box-shadow: var(--card-shadow-hover);
    }
</style>
<div x-data="dashboardCustomizer()"
     x-show="open"
     x-cloak
     @open-dashboard-customize.window="openModal($event.detail?.step)"
     @keydown.escape.window="close()"
     class="dcm-backdrop fixed inset-0 z-[999] flex items-center justify-center p-4">
    <div @click.outside="close()"
         class="dcm-card w-full max-w-2xl max-h-[88vh] overflow-y-auto rounded-2xl"
         style="border: 1px solid var(--border-glass);">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4" style="border-bottom: 1px solid var(--border-subtle);">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background: rgba(61,107,255,0.1); border: 1px solid rgba(61,107,255,0.15);">
                    <i class="fas fa-sliders text-blue-400 text-xs"></i>
                </div>
                <h2 class="text-sm font-bold" style="color: var(--text-primary);">AI Dashboard Settings</h2>
            </div>
            <button type="button" @click="close()" class="w-8 h-8 rounded-lg flex items-center justify-center transition-all hover:bg-white/5" style="color: var(--text-faint);">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <div class="p-6">
            {{-- ===== STEP: quick (layout badge shortcut — preset switch only, no AI) ===== --}}
            <template x-if="step === 'quick'">
                <div>
                    <p class="text-xs mb-4" style="color: var(--text-faint);">Quickly switch between preset dashboard layouts.</p>

                    @include('user.dashboard.partials.preset-grid')

                    <p x-show="errorMsg" x-text="errorMsg" class="text-[11px] text-red-400 mb-3"></p>

                    <button type="button" @click="step = 'picker'; errorMsg = ''" :disabled="busy"
                            class="w-full text-[11px] font-semibold py-2.5 rounded-xl transition-all disabled:opacity-50"
                            style="background: var(--bg-glass-input); border: 1px solid var(--border-subtle); color: var(--text-muted);">
                        <i class="fas fa-sliders text-[10px] mr-1.5"></i> More customization options
                    </button>
                </div>
            </template>

            {{-- ===== STEP: picker (presets + AI entry point) ===== --}}
            <template x-if="step === 'picker'">
                <div>
                    <p class="text-xs mb-4" style="color: var(--text-faint);">Pick a preset view, or let AI design one around what matters to you. You can switch back anytime.</p>

                    @include('user.dashboard.partials.preset-grid')

                    <div class="pt-4" style="border-top: 1px solid var(--border-subtle);">
                        @if($dashboardAiAllowed)
                        <button type="button" @click="step = 'ai-form'" :disabled="busy"
                                class="w-full flex items-center gap-3 p-4 rounded-xl transition-all disabled:opacity-50"
                                style="background: linear-gradient(135deg, rgba(61,107,255,0.12), rgba(61,107,255,0.10)); border: 1px solid rgba(61,107,255,0.25);">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: rgba(61,107,255,0.15);">
                                <i class="fas fa-wand-magic-sparkles text-blue-300"></i>
                            </div>
                            <div class="text-left">
                                <p class="text-xs font-bold" style="color: var(--text-primary);">Design with AI</p>
                                <p class="text-[11px]" style="color: var(--text-faint);">Describe your goal, AI picks the widgets that matter, charged from your coin wallet.</p>
                            </div>
                            <i class="fas fa-chevron-right text-[10px] ml-auto" style="color: var(--text-faint);"></i>
                        </button>
                        @else
                        <div class="p-4 rounded-xl text-center" style="background: var(--bg-glass-input); border: 1px solid var(--border-subtle);">
                            <p class="text-[11px]" style="color: var(--text-faint);">
                                <i class="fas fa-wand-magic-sparkles mr-1"></i>
                                "Design with AI" isn't available on your current plan.
                            </p>
                        </div>
                        @endif
                    </div>

                    <p x-show="errorMsg" x-text="errorMsg" class="text-[11px] text-red-400 mt-3"></p>
                </div>
            </template>

            {{-- ===== STEP: AI questionnaire ===== --}}
            <template x-if="step === 'ai-form'">
                <div>
                    <button type="button" @click="step = 'picker'; errorMsg = ''" class="text-[11px] mb-4 inline-flex items-center gap-1.5" style="color: var(--text-faint);">
                        <i class="fas fa-arrow-left text-[9px]"></i> Back
                    </button>

                    <label class="block text-[11px] font-semibold mb-1.5" style="color: var(--text-muted);">What should your dashboard focus on?</label>
                    <textarea x-model="answers.goal" rows="2" maxlength="800" placeholder="e.g. I want to keep an eye on my growth and how my content is performing"
                              class="w-full rounded-xl px-3 py-2.5 text-xs mb-3" style="background: var(--bg-glass-input); border: 1px solid var(--border-subtle); color: var(--text-primary);"></textarea>

                    <label class="block text-[11px] font-semibold mb-1.5" style="color: var(--text-muted);">Anything else you'd prioritize? (optional)</label>
                    <input type="text" x-model="priorityInput" @keydown.enter.prevent="addPriority()" placeholder="Type a priority and press Enter"
                           class="w-full rounded-xl px-3 py-2.5 text-xs mb-2" style="background: var(--bg-glass-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                    <div class="flex flex-wrap gap-1.5 mb-3" x-show="answers.priorities.length">
                        <template x-for="(p, idx) in answers.priorities" :key="idx">
                            <span class="badge inline-flex items-center gap-1.5" style="background: rgba(255,255,255,0.04); color: var(--text-muted); border: 1px solid var(--border-subtle);">
                                <span x-text="p"></span>
                                <i class="fas fa-times text-[8px] cursor-pointer" @click="answers.priorities.splice(idx, 1)"></i>
                            </span>
                        </template>
                    </div>

                    <label class="block text-[11px] font-semibold mb-1.5" style="color: var(--text-muted);">Density</label>
                    <div class="flex gap-2 mb-4">
                        <template x-for="opt in ['minimal', 'balanced', 'detailed']" :key="opt">
                            <button type="button" @click="answers.density = opt"
                                    class="flex-1 text-[11px] font-semibold py-2 rounded-lg capitalize transition-all"
                                    :style="answers.density === opt ? 'background: linear-gradient(135deg, #3d6bff, #5c83ff); color: #fff;' : 'background: var(--bg-glass-input); color: var(--text-muted); border: 1px solid var(--border-subtle);'"
                                    x-text="opt"></button>
                        </template>
                    </div>

                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-[11px] font-semibold" style="color: var(--text-muted);">Must-have widgets (optional)</label>
                        <span class="text-[10px]" style="color: var(--text-faint);" x-show="answers.selected_widgets.length" x-text="answers.selected_widgets.length + ' selected'"></span>
                    </div>
                    <p class="text-[10px] mb-2" style="color: var(--text-faint);">Pick specific widgets to guarantee they're included, the AI still designs the rest of the layout around your goal.</p>
                    <div class="rounded-xl mb-4 overflow-hidden" style="border: 1px solid var(--border-subtle);">
                        <template x-for="group in groupedCatalog" :key="group.tab">
                            <div style="border-top: 1px solid var(--border-subtle);" class="first:border-t-0">
                                <div class="px-3 pt-2.5 pb-1.5 text-[10px] font-bold uppercase tracking-wide" style="color: var(--text-faint);" x-text="group.label"></div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 px-3 pb-2.5">
                                    <template x-for="widget in group.widgets" :key="widget.key">
                                        <label class="flex items-start gap-1.5 text-[11px] rounded-lg px-2 py-1.5 cursor-pointer transition-all"
                                               :style="answers.selected_widgets.includes(widget.key) ? 'background: rgba(61,107,255,0.14); border: 1px solid rgba(61,107,255,0.4); color: var(--text-primary);' : 'background: var(--bg-glass-input); border: 1px solid var(--border-subtle); color: var(--text-muted);'">
                                            <input type="checkbox" class="sr-only"
                                                   :checked="answers.selected_widgets.includes(widget.key)"
                                                   @change="toggleWidget(widget.key)">
                                            <i class="fas text-[10px] w-3.5 text-center mt-0.5"
                                               :class="answers.selected_widgets.includes(widget.key) ? 'fa-square-check' : ('fa-square ' + widget.icon)"
                                               :style="answers.selected_widgets.includes(widget.key) ? 'color: #5c83ff;' : ''"></i>
                                            <span class="min-w-0">
                                                <span class="block font-semibold truncate" x-text="widget.label"></span>
                                                <span class="block text-[10px] leading-snug" style="color: var(--text-faint);" x-text="widget.description"></span>
                                            </span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <p x-show="errorMsg" x-text="errorMsg" class="text-[11px] text-red-400 mb-3"></p>

                    <button type="button" @click="estimate()" :disabled="busy || answers.goal.trim().length < 5"
                            class="w-full py-2.5 rounded-xl text-xs font-bold transition-all disabled:opacity-50"
                            style="background: linear-gradient(135deg, #3d6bff, #5c83ff); color: #fff;">
                        <span x-show="!busy"><i class="fas fa-wand-magic-sparkles mr-1.5"></i> Preview cost</span>
                        <span x-show="busy"><i class="fas fa-spinner fa-spin mr-1.5"></i> Estimating&hellip;</span>
                    </button>
                </div>
            </template>

            {{-- ===== STEP: AI confirm cost ===== --}}
            <template x-if="step === 'ai-confirm'">
                <div>
                    <button type="button" @click="step = 'ai-form'; errorMsg = ''" class="text-[11px] mb-4 inline-flex items-center gap-1.5" style="color: var(--text-faint);">
                        <i class="fas fa-arrow-left text-[9px]"></i> Back
                    </button>
                    <div class="p-4 rounded-xl mb-4 text-center" style="background: var(--bg-glass-input); border: 1px solid var(--border-subtle);">
                        <p class="text-[11px] mb-1" style="color: var(--text-faint);">This will cost</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);"><span x-text="estimatedCredits"></span> <span class="text-blue-300 text-sm">coins</span></p>
                        <p class="text-[11px] mt-1" style="color: var(--text-faint);">Wallet balance: <span x-text="balance"></span> coins</p>
                    </div>
                    <p x-show="errorMsg" x-text="errorMsg" class="text-[11px] text-red-400 mb-3"></p>
                    <button type="button" @click="generate()" :disabled="busy"
                            class="w-full py-2.5 rounded-xl text-xs font-bold transition-all disabled:opacity-50"
                            style="background: linear-gradient(135deg, #3d6bff, #5c83ff); color: #fff;">
                        <span x-show="!busy"><i class="fas fa-check mr-1.5"></i> Design my dashboard</span>
                        <span x-show="busy"><i class="fas fa-spinner fa-spin mr-1.5"></i> Designing&hellip;</span>
                    </button>
                </div>
            </template>

            {{-- ===== STEP: AI result ===== --}}
            <template x-if="step === 'ai-result'">
                <div class="text-center py-4">
                    <div class="w-14 h-14 rounded-2xl mx-auto mb-4 flex items-center justify-center" style="background: rgba(61,107,255,0.12);">
                        <i class="fas fa-check text-blue-300 text-xl"></i>
                    </div>
                    <p class="text-sm font-bold mb-1" style="color: var(--text-primary);">Your dashboard is ready</p>
                    <p class="text-[11px] mb-4" style="color: var(--text-faint);">Reloading with your new layout&hellip;</p>
                </div>
            </template>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function dashboardCustomizer() {
        return {
            open: false,
            busy: false,
            step: 'picker',
            errorMsg: '',
            currentPreset: @json($dashboardCurrentPreset),
            isCustom: @json($dashboardIsCustom),
            estimatedCredits: 0,
            balance: 0,
            priorityInput: '',
            answers: { goal: '', priorities: [], density: 'balanced', notes: '', selected_widgets: [] },
            groupedCatalog: @json($dashboardGroupedCatalog),

            toggleWidget(key) {
                const i = this.answers.selected_widgets.indexOf(key);
                if (i === -1) {
                    this.answers.selected_widgets.push(key);
                } else {
                    this.answers.selected_widgets.splice(i, 1);
                }
            },

            openModal(step) {
                this.open = true;
                this.step = (step === 'quick' || step === 'picker') ? step : 'picker';
                this.errorMsg = '';
            },
            close() {
                this.open = false;
            },
            addPriority() {
                const v = this.priorityInput.trim();
                if (v && this.answers.priorities.length < 10) {
                    this.answers.priorities.push(v);
                }
                this.priorityInput = '';
            },
            csrf() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            },
            async postJson(url, body) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(body || {}),
                });
                let data = {};
                try { data = await res.json(); } catch (e) {}
                if (!res.ok) {
                    const err = new Error(data.message || 'Something went wrong.');
                    err.data = data;
                    throw err;
                }
                return data;
            },
            async applyPreset(key) {
                this.busy = true;
                this.errorMsg = '';
                try {
                    await this.postJson('{{ route('user.dashboard.layout.preset') }}', { preset: key });
                    window.location.reload();
                } catch (e) {
                    this.errorMsg = e.message;
                    this.busy = false;
                }
            },
            async estimate() {
                this.busy = true;
                this.errorMsg = '';
                try {
                    const data = await this.postJson('{{ route('user.dashboard.ai.estimate') }}', this.answers);
                    this.estimatedCredits = data.estimated_credits;
                    this.balance = data.balance;
                    this.step = 'ai-confirm';
                } catch (e) {
                    this.errorMsg = e.message;
                } finally {
                    this.busy = false;
                }
            },
            async generate() {
                this.busy = true;
                this.errorMsg = '';
                try {
                    await this.postJson('{{ route('user.dashboard.ai.generate') }}', this.answers);
                    this.step = 'ai-result';
                    setTimeout(() => window.location.reload(), 900);
                } catch (e) {
                    if (e.data && e.data.required) {
                        this.errorMsg = `${e.message} You need ${e.data.required} coins (balance: ${e.data.balance}).`;
                    } else {
                        this.errorMsg = e.message;
                    }
                    this.step = 'ai-confirm';
                } finally {
                    this.busy = false;
                }
            },
        };
    }
</script>
@endpush
