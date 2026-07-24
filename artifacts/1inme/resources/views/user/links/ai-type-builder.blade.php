@extends('user.layouts.app')
@section('title', 'Build with AI')

@section('content')
<div class="max-w-2xl mx-auto" x-data="aiTypeBuilder()">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ $editorUrl }}" class="text-white/30 hover:text-white transition-colors" title="Skip and open the editor"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-wand-magic-sparkles text-blue-400"></i> Build your {{ $typeLabel }} with AI
            </h1>
            <p class="text-xs text-white/40 mt-0.5">Describe what you want and let AI draft it. You can refine everything in the editor afterwards.</p>
        </div>
    </div>

    @if(!$aiEnabled)
        <div class="glass rounded-2xl p-6 text-center">
            <i class="fas fa-robot text-3xl text-white/20 mb-3"></i>
            <p class="text-white/60 text-sm">The AI Engine is currently disabled. You can still build your {{ strtolower($typeLabel) }} manually.</p>
            <a href="{{ $editorUrl }}" class="inline-block mt-4 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-all">Open the editor</a>
        </div>
    @else
    <form @submit.prevent="generate">
        <div class="glass rounded-2xl p-6 mb-5 space-y-5">
            {{-- Description --}}
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1.5">What should it contain? <span class="text-red-400">*</span></label>
                <textarea x-model="description" rows="5" maxlength="4000"
                          placeholder="{{ $link->type === 'restaurant_menu' ? 'e.g. A cozy Italian trattoria: antipasti, fresh pasta, wood-fired pizza, desserts and a small wine list. Mid-range prices in EUR.' : ($link->type === 'store_menu' ? 'e.g. A small handmade-candle store: scented candles, gift sets and wax melts, prices around $10-40.' : ($link->type === 'service_booking' ? 'e.g. A barbershop: haircuts, beard trims, hot-towel shaves and kids cuts. 30-60 minute slots, prices in USD.' : ($link->type === 'resume' ? 'e.g. Senior frontend engineer, 8 years experience with React and TypeScript, led a team of 5 at Acme Corp, based in Berlin…' : 'e.g. A 6-slide pitch for my freelance photography business: intro, portfolio highlights, services, pricing, testimonials, contact.'))) }}"
                          class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none transition-all resize-y"></textarea>
                <div class="flex items-center justify-between mt-1">
                    <p class="text-xs text-white/30">The more detail you give, the better the result.</p>
                    <p class="text-[11px] text-white/25" x-text="description.length + ' / 4000'"></p>
                </div>
            </div>

            @if($supportsLinks)
            {{-- Links --}}
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1.5">Your links <span class="text-white/30 font-normal">(optional)</span></label>
                <p class="text-xs text-white/30 mb-2">Paste URLs the AI may use. It will never invent links you didn't supply.</p>
                <div class="space-y-2">
                    <template x-for="(l, i) in links" :key="i">
                        <div class="flex items-center gap-2">
                            <input type="url" x-model="links[i]" placeholder="https://…" maxlength="2048"
                                   class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none">
                            <button type="button" @click="links.splice(i, 1)" class="text-white/30 hover:text-red-400 transition-colors w-8 h-8 flex items-center justify-center" title="Remove">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="if (links.length < {{ $maxLinks }}) links.push('')"
                        class="mt-2 text-xs text-blue-300 hover:text-blue-200 transition-colors">
                    <i class="fas fa-plus mr-1"></i> Add a link
                </button>
            </div>
            @endif

            @if($supportsImages)
            {{-- Image URLs --}}
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1.5">Image URLs <span class="text-white/30 font-normal">(optional)</span></label>
                <p class="text-xs text-white/30 mb-2">Add image URLs the AI may place. Only images you supply here are ever used.</p>
                <div class="space-y-2">
                    <template x-for="(img, i) in images" :key="i">
                        <div class="flex items-center gap-2">
                            <input type="url" x-model="images[i]" placeholder="https://…" maxlength="2048"
                                   class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-blue-500/40 outline-none">
                            <button type="button" @click="images.splice(i, 1)" class="text-white/30 hover:text-red-400 transition-colors w-8 h-8 flex items-center justify-center" title="Remove">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="if (images.length < {{ $maxImages }}) images.push('')"
                        class="mt-2 text-xs text-blue-300 hover:text-blue-200 transition-colors">
                    <i class="fas fa-plus mr-1"></i> Add an image URL
                </button>
            </div>
            @endif
        </div>

        {{-- Cost + submit --}}
        <div class="glass rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="text-xs text-white/40">
                    Your balance: <span class="text-white/80 font-semibold">{{ number_format($balance) }}</span> <i class="fas fa-coins text-yellow-400/70 ml-0.5"></i>
                </div>
                <div class="text-xs text-white/40" x-show="estimate !== null" x-cloak>
                    Estimated cost: <span class="text-white/80 font-semibold" x-text="estimate"></span> <i class="fas fa-coins text-yellow-400/70 ml-0.5"></i>
                </div>
            </div>

            <div x-show="error" x-cloak class="mb-4 text-sm text-red-300 bg-red-500/10 border border-red-500/20 rounded-xl px-4 py-3" x-text="error"></div>

            <div class="flex items-center gap-3">
                <button type="button" @click="runEstimate" :disabled="!canSubmit || estimating"
                        class="px-4 py-2.5 rounded-xl text-sm font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/20 transition-all disabled:opacity-40 disabled:cursor-not-allowed">
                    <span x-show="!estimating">Estimate cost</span>
                    <span x-show="estimating" x-cloak><i class="fas fa-circle-notch fa-spin mr-1"></i> Estimating…</span>
                </button>
                <button type="submit" :disabled="!canSubmit || generating"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition-all disabled:opacity-40 disabled:cursor-not-allowed">
                    <span x-show="!generating"><i class="fas fa-wand-magic-sparkles mr-1.5"></i> Build with AI</span>
                    <span x-show="generating" x-cloak><i class="fas fa-circle-notch fa-spin mr-1.5"></i> Building your {{ strtolower($typeLabel) }}…</span>
                </button>
            </div>
            <p class="text-[11px] text-white/25 mt-3">Coins are only spent on successful builds; failed builds are refunded automatically.</p>
        </div>
    </form>
    @endif
</div>

@if($aiEnabled)
<script>
function aiTypeBuilder() {
    return {
        description: '',
        links: [],
        images: [],
        estimate: null,
        estimating: false,
        generating: false,
        error: '',

        get cleanLinks() {
            return this.links.map(l => l.trim()).filter(l => l.length > 0);
        },
        get cleanImages() {
            return this.images.map(i => i.trim()).filter(i => i.length > 0);
        },
        get canSubmit() {
            return this.description.trim().length >= 10;
        },

        async runEstimate() {
            if (!this.canSubmit || this.estimating) return;
            this.estimating = true;
            this.error = '';
            try {
                const data = await this.post(@json(route('user.links.ai-type-builder.estimate', $link)));
                if (data.ok) {
                    this.estimate = data.body.estimated_credits;
                } else {
                    this.error = data.body.message || 'Could not estimate the cost. Please try again.';
                }
            } catch (e) {
                this.error = 'Could not estimate the cost. Please try again.';
            } finally {
                this.estimating = false;
            }
        },

        async generate() {
            if (!this.canSubmit || this.generating) return;
            this.generating = true;
            this.error = '';
            try {
                const data = await this.post(@json(route('user.links.ai-type-builder.generate', $link)));
                if (data.ok && data.body.redirect) {
                    window.location.href = data.body.redirect;
                    return;
                }
                if (data.status === 402) {
                    this.error = data.body.message || 'Not enough coins. Top up and try again.';
                } else {
                    this.error = data.body.message || 'Something went wrong building your page. Please try again.';
                }
            } catch (e) {
                this.error = 'Something went wrong building your page. Please try again.';
            } finally {
                this.generating = false;
            }
        },

        async post(url) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    description: this.description.trim(),
                    links: this.cleanLinks,
                    images: this.cleanImages,
                }),
            });
            const body = await res.json().catch(() => ({}));
            return { ok: res.ok, status: res.status, body };
        },
    };
}
</script>
@endif
@endsection
