@extends('user.layouts.app')
@section('title', 'AI Marketing Studio')

@section('content')
@include('user.marketing-studio.styles')
<div class="ms-legacy max-w-6xl mx-auto px-4 py-8 space-y-6" x-data="brandStudio()">
    @if(session('status'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="flex items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white"><i class="fas fa-wand-magic-sparkles text-primary-300 mr-2"></i>AI Marketing Studio</h1>
            <p class="text-sm text-white/50 mt-1">Build a mixed set of pages and assets. Choose types, quantities, knowledge and a destination, then review estimated coins before generating.</p>
            @if($aiEnabled)<p class="text-[11px] text-white/40 mt-1">{{ number_format($balance) }} coins</p>@endif
        </div>
    </div>

    @if(!$allowed)
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-8 text-center space-y-3">
            <i class="fas fa-lock text-3xl text-white/30"></i>
            <h2 class="text-white font-semibold">AI Marketing Studio isn't included in your plan</h2>
            <p class="text-sm text-white/50 max-w-md mx-auto">Upgrade to turn one brief into a complete set of on-brand links, pages, QR codes and forms - created together in a single run.</p>
            <a href="{{ route('user.upgrade') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary-500 hover:bg-primary-400 text-white text-sm font-medium"><i class="fas fa-arrow-up"></i> See upgrade options</a>
        </div>
    @elseif(!$aiEnabled)
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-8 text-center text-sm text-white/50">The AI engine is currently disabled. Please check back later.</div>
    @else
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-6 space-y-4">
            <div class="grid md:grid-cols-2 gap-4">
                <div class="space-y-3">
                    <label class="block text-sm text-white/70 font-medium">Brand context</label>
                    <select x-model="brandKitId" class="w-full rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2.5">
                        <option value="">No saved brand kit - describe the brand below</option>
                        @foreach($brandKits as $bk)
                            <option value="{{ $bk->id }}">{{ $bk->name }}</option>
                        @endforeach
                    </select>
                    <template x-if="!brandKitId">
                        <div class="space-y-2">
                            <input type="text" x-model="brandName" maxlength="160" placeholder="Brand name" class="w-full rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2.5 placeholder-white/30">
                            <input type="text" x-model="brandColors" maxlength="300" placeholder="Brand colors (e.g. #0f172a and gold)" class="w-full rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2.5 placeholder-white/30">
                            <input type="text" x-model="brandVoice" maxlength="500" placeholder="Voice &amp; tone (e.g. playful, expert, minimal)" class="w-full rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2.5 placeholder-white/30">
                            <textarea x-model="brandDescription" maxlength="1000" rows="2" placeholder="What the brand does (optional)" class="w-full rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2.5 placeholder-white/30"></textarea>
                        </div>
                    </template>
                </div>
                <div class="space-y-3">
                    <label class="block text-sm text-white/70 font-medium">What do you want to create?</label>
                    <textarea x-model="brief" maxlength="4000" rows="5" placeholder="e.g. Launching our summer sale - I need a landing bio page, short links for the sale and our socials, QR codes for posters, and a lead form." class="w-full rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2.5 placeholder-white/30"></textarea>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex rounded-xl border border-white/10 overflow-hidden text-sm">
                            <button type="button" @click="mode='kit'" :class="mode==='kit' ? 'bg-primary-500 text-white' : 'bg-white/[0.04] text-white/60'" class="px-3 py-2">Mixed combination</button>
                            <button type="button" @click="mode='bulk'" :class="mode==='bulk' ? 'bg-primary-500 text-white' : 'bg-white/[0.04] text-white/60'" class="px-3 py-2">Same-type variations</button>
                        </div>
                        <template x-if="mode==='bulk'">
                            <div class="flex items-center gap-2">
                                <select x-model="bulkKind" class="rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2">
                                    <option value="short_link">Short links</option>
                                    <option value="qr_code">QR codes</option>
                                    <option value="biolink">Link in Bio pages</option>
                                    <option value="form">Forms</option>
                                    <option value="vcard">Digital cards</option><option value="calendar">Calendar pages</option><option value="restaurant_menu">Menu pages</option><option value="store_menu">Store pages</option>
                                </select>
                                <input type="number" x-model.number="bulkCount" min="1" max="{{ $bulkCap }}" class="w-20 rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2">
                                <span class="text-[11px] text-white/40">max {{ $bulkCap == -1 ? '∞' : $bulkCap }} / run</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            @include('user.marketing-studio.connections')
            <template x-if="mode==='kit'">
                <div class="space-y-3 border-t border-white/10 pt-4">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <label class="block text-sm text-white/70 font-medium">Mix page types & assets <span class="text-white/35 font-normal">(optional)</span></label>
                            <p class="text-[11px] text-white/40 mt-0.5">Add different types and quantities: for example, 1 calendar + 1 menu + 1 store + 1 Link in Bio page.</p>
                        </div>
                        <button type="button" x-show="composition.length" @click="composition = []" class="text-[11px] text-white/40 hover:text-white/70 underline decoration-white/20">Clear composition</button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <template x-for="p in presets" :key="p.label">
                            <button type="button" @click="applyPreset(p)"
                                    class="px-3 py-1.5 rounded-full text-[12px] border border-white/10 bg-white/[0.04] text-white/60 hover:border-primary-500/50 hover:text-white">
                                <i class="fas fa-layer-group mr-1 text-white/30"></i><span x-text="p.label"></span>
                            </button>
                        </template>
                        <template x-for="p in savedPresets" :key="p.id">
                            <span class="inline-flex items-center rounded-full border border-primary-500/30 bg-primary-500/[0.08] overflow-hidden">
                                <template x-if="renamingId !== p.id">
                                    <span class="inline-flex items-center">
                                        <button type="button" @click="applyPreset(p)"
                                                class="pl-3 pr-1.5 py-1.5 text-[12px] text-white/70 hover:text-white">
                                            <i class="fas fa-bookmark mr-1 text-primary-300/70"></i><span x-text="p.label"></span>
                                        </button>
                                        <button type="button" @click="startRename(p)" :title="`Rename “${p.label}”`"
                                                class="px-1 py-1.5 text-white/35 hover:text-white text-[11px]"><i class="fas fa-pen"></i></button>
                                        <button type="button" @click="deletePreset(p)" :title="`Delete “${p.label}”`"
                                                class="pr-2.5 pl-1 py-1.5 text-white/35 hover:text-red-300 text-[11px]"><i class="fas fa-times"></i></button>
                                    </span>
                                </template>
                                <template x-if="renamingId === p.id">
                                    <span class="inline-flex items-center gap-1 pl-2 pr-1.5 py-1">
                                        <input type="text" x-model="renameName" maxlength="60" x-init="$el.focus(); $el.select()"
                                               @keydown.enter.prevent="renamePreset(p)" @keydown.escape.prevent="cancelRename()"
                                               class="w-40 rounded-lg bg-white/[0.08] border border-white/15 text-white text-[12px] px-2 py-1 placeholder-white/30"
                                               placeholder="Combo name">
                                        <button type="button" @click="renamePreset(p)" :disabled="renameBusy || !renameName.trim()"
                                                class="px-1.5 py-1 text-primary-300 hover:text-primary-200 disabled:opacity-50 text-[11px]" title="Save name">
                                            <i class="fas" :class="renameBusy ? 'fa-circle-notch fa-spin' : 'fa-check'"></i>
                                        </button>
                                        <button type="button" @click="cancelRename()" class="px-1 py-1 text-white/35 hover:text-white text-[11px]" title="Cancel"><i class="fas fa-times"></i></button>
                                    </span>
                                </template>
                            </span>
                        </template>
                    </div>
                    <p class="text-sm text-red-300" x-show="renameError" x-cloak x-text="renameError"></p>

                    <template x-for="(row, i) in composition" :key="i">
                        <div class="flex items-center gap-2 flex-wrap">
                            <select x-model="row.kind" class="rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2">
                                <template x-for="(label, kind) in kindLabels" :key="kind">
                                    <option :value="kind" x-text="label" :selected="row.kind === kind"></option>
                                </template>
                            </select>
                            <div class="inline-flex items-center rounded-xl border border-white/10 overflow-hidden">
                                <button type="button" @click="row.count = Math.max(1, row.count - 1)" class="px-2.5 py-2 bg-white/[0.04] text-white/60 hover:text-white text-sm">−</button>
                                <span class="px-3 py-2 text-white text-sm tabular-nums" x-text="row.count"></span>
                                <button type="button" @click="row.count = Math.min(kitCaps[row.kind] || 1, row.count + 1)" class="px-2.5 py-2 bg-white/[0.04] text-white/60 hover:text-white text-sm">+</button>
                            </div>
                            <input type="text" x-model="row.purpose" maxlength="120" placeholder="Purpose (e.g. for the product page)"
                                   class="flex-1 min-w-[180px] rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2 placeholder-white/30">
                            <button type="button" @click="composition.splice(i, 1)" class="px-2.5 py-2 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-300 text-sm"><i class="fas fa-times"></i></button>
                        </div>
                    </template>

                    <div class="flex items-center gap-3 flex-wrap">
                        <button type="button" @click="addRow()" class="text-[12px] text-primary-300 hover:text-primary-200"><i class="fas fa-plus mr-1"></i>Add asset</button>
                        <button type="button" x-show="composition.length && !compositionError()" @click="savingPreset = !savingPreset; presetError = ''"
                                class="text-[12px] text-white/50 hover:text-white"><i class="fas fa-bookmark mr-1"></i>Save this combo</button>
                        <span class="text-sm text-amber-300" x-show="compositionError()" x-text="compositionError()"></span>
                    </div>

                    <div x-show="savingPreset" x-cloak class="flex items-center gap-2 flex-wrap">
                        <input type="text" x-model="presetName" maxlength="60" placeholder="Combo name (e.g. Event kit)"
                               @keydown.enter.prevent="savePreset()"
                               class="rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2 placeholder-white/30 min-w-[220px]">
                        <button type="button" @click="savePreset()" :disabled="presetBusy || !presetName.trim()"
                                class="px-3 py-2 rounded-xl bg-primary-500 hover:bg-primary-400 disabled:opacity-50 text-white text-[12px] font-medium">
                            <span x-text="presetBusy ? 'Saving…' : 'Save combo'"></span>
                        </button>
                        <button type="button" @click="savingPreset = false; presetError = ''" class="text-[12px] text-white/40 hover:text-white/70">Cancel</button>
                        <span class="text-sm text-red-300" x-show="presetError" x-text="presetError"></span>
                    </div>
                </div>
            </template>

            <div class="flex items-center gap-3 flex-wrap">
                <button type="button" @click="plan()" :disabled="busy || estBusy || estCredits===null || lowBalance() || !canGenerate()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary-500 hover:bg-primary-400 disabled:opacity-50 text-white text-sm font-medium">
                    <i class="fas" :class="busy ? 'fa-circle-notch fa-spin' : 'fa-wand-magic-sparkles'"></i>
                    <span x-text="busy ? 'Planning your kit…' : 'Generate plan'"></span>
                </button>
                <button type="button" @click="estimate()" :disabled="busy || !canGenerate()"
                        class="text-sm text-white/60 hover:text-white underline decoration-white/20">Estimate cost</button>
                <span class="text-[11px] text-white/40" x-show="estBusy">Estimating cost…</span>
                <span class="text-[11px] text-white/40" x-show="!estBusy && estimateText" x-text="estimateText"></span>
                <span class="text-sm text-red-300" x-show="error" x-text="error"></span>
            </div>
            <template x-if="!estBusy && estCredits !== null && mode === 'bulk'">
                <p class="text-[11px] text-white/40">
                    <i class="fas fa-layer-group mr-1 text-white/30"></i>
                    <span x-text="`${bulkVariants()} variant${bulkVariants() === 1 ? '' : 's'} × ~${perVariantCredits()} coins each ≈ ${estCredits} coins total`"></span>
                </p>
            </template>
            <div x-show="!estBusy && lowBalance()" x-cloak
                 class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-sm flex items-start gap-2">
                <i class="fas fa-triangle-exclamation mt-0.5"></i>
                <span x-text="`This run needs about ${estCredits} coins but you only have ${estBalance}. Top up your coins before generating, or reduce the scope.`"></span>
            </div>
            <p class="text-[11px] text-white/35">You'll review the full plan before anything is created. Planning uses coins; a failed run is automatically refunded.</p>
        </div>

        <div class="space-y-3">
            <h2 class="text-white font-semibold">Your kits</h2>
            @forelse($kits as $k)
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <a href="{{ route('user.brand-studio.show', $k) }}" class="text-white font-medium hover:underline">{{ $k->name }}</a>
                        <p class="text-[11px] text-white/40 mt-0.5">
                            {{ $k->mode === 'bulk' ? 'Bulk variations' : 'Full kit' }} ·
                            {{ count($k->isCreated() ? $k->createdAssets() : $k->proposedAssets()) }} asset(s) ·
                            {{ $k->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($k->isCreated())
                            <span class="px-2.5 py-1 rounded-full text-[11px] bg-emerald-500/10 border border-emerald-500/20 text-emerald-300">Created</span>
                        @else
                            <span class="px-2.5 py-1 rounded-full text-[11px] bg-amber-500/10 border border-amber-500/20 text-amber-300">Awaiting review</span>
                        @endif
                        <a href="{{ route('user.brand-studio.show', $k) }}" class="px-3 py-1.5 rounded-xl bg-white/[0.06] hover:bg-white/[0.1] text-white/80 text-sm">{{ $k->isCreated() ? 'View results' : 'Review' }}</a>
                        <form method="POST" action="{{ route('user.brand-studio.destroy', $k) }}" onsubmit="return confirm('Delete this kit record? Created assets are kept.');">
                            @csrf @method('DELETE')
                            <button class="px-3 py-1.5 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-300 text-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-white/40">No kits yet - describe what you need above and generate your first plan.</p>
            @endforelse
        </div>
    @endif
</div>

@include('user.marketing-studio.builder-script')
@endsection
