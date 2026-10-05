@extends('user.layouts.app')
{{-- The breadcrumb and the browser tab come from here, and they said
     "Build with AI" on a page whose heading and button said Modify. Found
     by a test asserting the words "Build with AI" were gone from a modify
     screen; they were not. --}}
@section('title', ($hasContent ?? false) ? 'Modify with AI' : 'Build with AI')

@section('content')
@php
    // Sana, 2026-10-05: "when already created... it should show like modify
    // with AI and also all features like blocks and all should be
    // possible...... live right side should be shown".
    //
    // All three. The shared editor shell gives the main tab row -- so Blocks
    // and Settings are one click away instead of this being a dead end --
    // and the live page sits beside the form the way it does on every other
    // editor screen.
    $aiVerb  = ($hasContent ?? false) ? 'Modify' : 'Build';
    $aiVerbL = strtolower($aiVerb);
@endphp
<div class="w-full max-w-7xl mx-auto" x-data="aiTypeBuilder()">
    @include('user.links.partials.editor-header', [
        'link' => $link,
        'activeMainTab' => 'ai',
    ])

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    <div class="lg:col-span-7">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ $editorUrl }}" class="text-white/30 hover:text-white transition-colors" title="Skip and open the editor"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-wand-magic-sparkles text-blue-400"></i> {{ $aiVerb }} your {{ $typeLabel }} with AI
            </h1>
            <p class="text-xs text-white/40 mt-0.5">
                @if($hasContent ?? false)
                    Say what you want changed. What is already here is sent along with it, so the rest stays as it is.
                @else
                    Describe what you want and let AI draft it. You can refine everything in the editor afterwards.
                @endif
            </p>
        </div>
    </div>

    @if($hasContent ?? false)
        {{-- Said plainly, because the build REPLACES the catalogue: the
             service deletes every section and item and writes the response
             in their place. The model is now sent the current content and
             told that anything it omits is deleted, which is what makes
             "modify" a true word -- but a model can still drop something,
             and somebody about to spend coins on an eighty-dish menu is
             owed that sentence rather than a reassuring one. --}}
        <div class="mb-5 rounded-xl px-4 py-3 text-sm flex items-start gap-3"
             style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); color: #f59e0b;">
            <i class="fas fa-triangle-exclamation mt-0.5"></i>
            <span>
                <b>This rewrites the whole {{ strtolower($typeLabel) }}.</b>
                What is on it now is sent to the AI and it is asked to keep everything you did not ask it to change —
                but the result replaces what is there, so check it afterwards before anybody orders from it.
            </span>
        </div>
    @endif

    @if(!$aiEnabled)
        <div class="glass rounded-2xl p-6 text-center">
            <i class="fas fa-robot text-3xl text-white/20 mb-3"></i>
            <p class="text-white/60 text-sm">The AI Engine is currently disabled. You can still build your {{ strtolower($typeLabel) }} manually.</p>
            <a href="{{ $editorUrl }}" class="inline-block mt-4 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-all">Open the editor</a>
        </div>
    @else
    <form @submit.prevent="generate">
        <div class="glass rounded-2xl p-6 mb-5 space-y-5">
            @if($readsImages)
            {{-- Photograph the card you already have.

                 Sana, 2026-09-23: "here it should have modified version of
                 extracting content with pdf or multiple scanned images of
                 menu card.... thats usuall used by restro"

                 A restaurant has a laminated card, not a brief. Typing it in
                 again is the reason the menu never gets onto the platform.

                 These are read, not referenced. The image URLs further down
                 are a different thing entirely: those get hung on dishes as
                 photos and the model never looks at them. --}}
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1.5">
                    Photograph of your menu <span class="text-white/30 font-normal">(optional)</span>
                </label>
                <p class="text-xs text-white/30 mb-2">
                    Up to {{ $maxScans }} photos or scans of the card you already have. It is read
                    as printed, so check the prices afterwards.
                </p>
                <input type="file" accept="image/png,image/jpeg,image/webp" multiple
                       @change="addScans($event)"
                       class="block w-full text-xs text-white/60 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-medium file:bg-white/10 file:text-white/80 hover:file:bg-white/15 cursor-pointer">
                <div class="flex flex-wrap gap-2 mt-3" x-show="scans.length">
                    <template x-for="(sc, si) in scans" :key="si">
                        <div class="relative">
                            <img :src="sc" alt="" class="w-20 h-20 object-cover rounded-xl border border-white/10">
                            <button type="button" @click="scans.splice(si, 1); estimate = null"
                                    class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-black/80 border border-white/20 text-white/70 hover:text-red-400 text-xs"
                                    title="Remove">&times;</button>
                        </div>
                    </template>
                </div>
                <p class="text-[11px] mt-2 text-amber-300/80" x-show="scans.length">
                    Anything unreadable comes back priced at zero rather than guessed at, so it
                    is obvious what to fix.
                </p>
                <p class="text-[11px] mt-2 text-red-400" x-show="scanError" x-text="scanError"></p>
            </div>
            @endif

            {{-- Description --}}
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1.5">
                    What should it contain?
                    @if($readsImages)
                        <span class="text-red-400" x-show="!scans.length">*</span>
                        <span class="text-white/30 font-normal" x-show="scans.length" x-cloak>(optional when you have uploaded a card)</span>
                    @else
                        <span class="text-red-400">*</span>
                    @endif
                </label>
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
                    <span x-show="!generating"><i class="fas fa-wand-magic-sparkles mr-1.5"></i> {{ $aiVerb }} with AI</span>
                    <span x-show="generating" x-cloak><i class="fas fa-circle-notch fa-spin mr-1.5"></i> {{ $aiVerb === 'Modify' ? 'Rewriting' : 'Building' }} your {{ strtolower($typeLabel) }}…</span>
                </button>
            </div>
            <p class="text-[11px] text-white/25 mt-3">Coins are only spent on successful {{ $aiVerbL }}s; failed ones are refunded automatically.</p>
        </div>
    </form>
    @endif
    </div>

    {{-- The page as it stands, beside the brief. Sana: "live right side
         should be shown" -- and on a modify it is the thing you are about
         to change, which is the one screen where seeing it matters most. --}}
    <div class="lg:col-span-5 hidden lg:block lg:self-stretch lg:h-full">
        @include('user.links.partials.device-preview', ['link' => $link])
    </div>
    </div>
</div>

@if($aiEnabled)
<script>
function aiTypeBuilder() {
    return {
        description: '',
        links: [],
        images: [],
        scans: [],
        scanError: '',
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
            // A card IS a brief. Requiring ten words on top of a photograph
            // of the thing is a gate with nothing behind it.
            return this.description.trim().length >= 10 || this.scans.length > 0;
        },

        async addScans(e) {
            const files = Array.from(e.target.files || []);
            e.target.value = '';
            this.scanError = '';
            for (const file of files) {
                if (this.scans.length >= {{ $maxScans ?? 4 }}) {
                    this.scanError = 'That is as many as can be read in one go.';
                    break;
                }
                // 6MB, because the whole thing travels in the request body.
                if (file.size > 6 * 1024 * 1024) {
                    this.scanError = file.name + ' is too large. 6MB each.';
                    continue;
                }
                try {
                    this.scans.push(await new Promise((resolve, reject) => {
                        const r = new FileReader();
                        r.onload = () => resolve(r.result);
                        r.onerror = reject;
                        r.readAsDataURL(file);
                    }));
                } catch (err) {
                    this.scanError = 'That image could not be read.';
                }
            }
            // The estimate was for a different set of inputs.
            this.estimate = null;
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
                    scans: this.scans,
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
