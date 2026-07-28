{{-- Page Stickers card — decorative emoji/image overlays placed anywhere on
     the page. Lives INSIDE the page-settings form: the sticker list is
     serialized into the hidden `stickers_json` input, and every mutation
     dispatches a bubbling `input` event so the existing live draft-preview
     auto-binder (device-preview.blade.php) pushes the change to the iframe.
     Server-side bounds/sanitization: App\Modules\User\Support\BiolinkStickers. --}}
@php
    $__stickers = \App\Modules\User\Support\BiolinkStickers::sanitize($bs['stickers'] ?? []);
    $__stickersMax = \App\Modules\User\Support\BiolinkStickers::MAX_STICKERS;
@endphp
<div class="card-premium p-6" x-data="pageStickersCard(@js($__stickers), {{ $__stickersMax }})">
    <div class="flex items-center gap-3 mb-1">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(250,204,21,0.1);"><i class="fas fa-icons text-yellow-400 text-xs"></i></div>
        <h3 class="text-sm font-bold" style="color: var(--text-primary);">Page Stickers</h3>
        <span class="text-[10px] px-1.5 py-0.5 rounded-full" style="background: rgba(255,255,255,0.05); color: var(--text-faint);" x-text="stickers.length + ' / ' + max"></span>
    </div>
    <p class="text-[11px] mb-4" style="color: var(--text-faint);">Decorate your page with tilted emojis or small images. They float over the page and never block taps.</p>

    <input type="hidden" name="stickers_json" x-ref="json" :value="JSON.stringify(stickers)">

    {{-- Add: emoji palette + custom emoji + image URL --}}
    <div class="mb-4" x-show="stickers.length < max">
        <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Add a sticker</label>
        <div class="flex flex-wrap gap-1.5 mb-2">
            <template x-for="em in palette" :key="em">
                <button type="button" class="w-9 h-9 rounded-lg text-lg flex items-center justify-center transition-transform hover:scale-110"
                        style="background: rgba(255,255,255,0.04); border: 1px solid var(--border-glass);"
                        @click="addEmoji(em)" x-text="em"></button>
            </template>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <div class="flex gap-2">
                <input type="text" x-model="customEmoji" maxlength="16" class="theme-input flex-1 text-sm" placeholder="Any emoji, e.g. 🪩">
                <button type="button" class="btn-secondary px-3 py-2 text-xs whitespace-nowrap" @click="addEmoji(customEmoji); customEmoji = ''">Add emoji</button>
            </div>
            <div class="flex gap-2">
                <input type="url" x-model="customImageUrl" class="theme-input flex-1 text-sm" placeholder="Image URL (https://… or /f/… vault file)">
                <button type="button" class="btn-secondary px-3 py-2 text-xs whitespace-nowrap" @click="addImage()">Add image</button>
            </div>
        </div>
    </div>
    <p class="text-[11px] mb-3" style="color: #fbbf24;" x-show="stickers.length >= max" x-cloak>Sticker limit reached — remove one to add another.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" x-show="stickers.length" x-cloak>
        {{-- Drag pad: proportional phone-shaped stage. Drag stickers to place
             them; positions are stored as viewport percentages. --}}
        <div>
            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Position <span style="color: var(--text-faint);">(drag on the pad)</span></label>
            <div x-ref="pad" class="relative w-full rounded-xl overflow-hidden select-none"
                 style="aspect-ratio: 375 / 700; background: rgba(255,255,255,0.03); border: 1px dashed var(--border-glass); touch-action: none;"
                 @pointermove="dragMove($event)" @pointerup="dragEnd($event)" @pointercancel="dragEnd($event)">
                <template x-for="(st, i) in stickers" :key="i">
                    <div class="absolute cursor-grab active:cursor-grabbing"
                         :style="'left:' + st.x + '%; top:' + st.y + '%; transform: translate(-50%,-50%) rotate(' + st.rotation + 'deg) scale(' + Math.min(st.scale, 1.6) + '); outline:' + (i === selected ? '2px dashed rgba(61,107,255,0.8)' : 'none') + '; outline-offset: 3px; border-radius: 6px;'"
                         @pointerdown.prevent="dragStart($event, i)">
                        <span x-show="st.kind === 'emoji'" class="text-2xl leading-none" x-text="st.value"></span>
                        <img x-show="st.kind === 'image'" :src="st.kind === 'image' ? st.value : ''" alt="" class="w-8 h-8 object-contain pointer-events-none" draggable="false">
                    </div>
                </template>
            </div>
        </div>

        {{-- Selected sticker controls + list --}}
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Stickers</label>
                <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                    <template x-for="(st, i) in stickers" :key="i">
                        <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg cursor-pointer"
                             :style="'background:' + (i === selected ? 'rgba(61,107,255,0.12)' : 'rgba(255,255,255,0.03)') + '; border: 1px solid ' + (i === selected ? 'rgba(61,107,255,0.35)' : 'var(--border-glass)')"
                             @click="selected = i">
                            <span class="text-lg leading-none" x-show="st.kind === 'emoji'" x-text="st.value"></span>
                            <img x-show="st.kind === 'image'" :src="st.kind === 'image' ? st.value : ''" alt="" class="w-5 h-5 object-contain" draggable="false">
                            <span class="text-[10px] flex-1 truncate" style="color: var(--text-faint);" x-text="st.kind === 'image' ? st.value : (st.layer === 'back' ? 'Behind content' : 'Above content')"></span>
                            <button type="button" class="text-[10px] px-1" style="color: var(--text-faint);" title="Move up" @click.stop="moveSticker(i, -1)" x-show="i > 0"><i class="fas fa-arrow-up"></i></button>
                            <button type="button" class="text-[10px] px-1" style="color: var(--text-faint);" title="Move down" @click.stop="moveSticker(i, 1)" x-show="i < stickers.length - 1"><i class="fas fa-arrow-down"></i></button>
                            <button type="button" class="text-[10px] px-1" style="color: var(--text-faint);" title="Duplicate" @click.stop="duplicateSticker(i)" x-show="stickers.length < max"><i class="fas fa-copy"></i></button>
                            <button type="button" class="text-[10px] px-1 text-red-400" title="Remove" @click.stop="removeSticker(i)"><i class="fas fa-trash"></i></button>
                        </div>
                    </template>
                </div>
            </div>

            <template x-if="selected !== null && stickers[selected]">
                <div class="space-y-3">
                    <div>
                        <label class="flex items-center justify-between text-xs font-medium mb-1" style="color: var(--text-muted);">
                            <span>Tilt</span><span class="tabular-nums" style="color: var(--text-faint);" x-text="stickers[selected].rotation + '°'"></span>
                        </label>
                        <input type="range" min="-180" max="180" step="1" class="w-full"
                               :value="stickers[selected].rotation"
                               @input="stickers[selected].rotation = parseInt($event.target.value); sync()">
                    </div>
                    <div>
                        <label class="flex items-center justify-between text-xs font-medium mb-1" style="color: var(--text-muted);">
                            <span>Size</span><span class="tabular-nums" style="color: var(--text-faint);" x-text="Math.round(stickers[selected].scale * 100) + '%'"></span>
                        </label>
                        <input type="range" min="0.4" max="3" step="0.05" class="w-full"
                               :value="stickers[selected].scale"
                               @input="stickers[selected].scale = parseFloat($event.target.value); sync()">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--text-muted);">Layer</label>
                        <div class="flex gap-2">
                            <button type="button" class="px-3 py-1.5 rounded-lg text-[11px]"
                                    :style="stickers[selected].layer !== 'back' ? 'background: rgba(61,107,255,0.15); color: #90acff; border: 1px solid rgba(61,107,255,0.3);' : 'background: rgba(255,255,255,0.04); color: var(--text-faint); border: 1px solid var(--border-glass);'"
                                    @click="stickers[selected].layer = 'front'; sync()">Above content</button>
                            <button type="button" class="px-3 py-1.5 rounded-lg text-[11px]"
                                    :style="stickers[selected].layer === 'back' ? 'background: rgba(61,107,255,0.15); color: #90acff; border: 1px solid rgba(61,107,255,0.3);' : 'background: rgba(255,255,255,0.04); color: var(--text-faint); border: 1px solid var(--border-glass);'"
                                    @click="stickers[selected].layer = 'back'; sync()">Behind content</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function pageStickersCard(initial, max) {
    return {
        stickers: Array.isArray(initial) ? initial : [],
        max: max,
        selected: (Array.isArray(initial) && initial.length) ? 0 : null,
        customEmoji: '',
        customImageUrl: '',
        dragging: null,
        palette: ['😀','😍','🤩','😎','🥳','🔥','✨','⭐','💖','💫','🌈','🌸','🍀','🎯','🎉','🎵','☕','🚀','👑','💎','🦋','🌙','⚡','🫶'],

        sync() {
            // Update the hidden input and let the event bubble to the form so
            // the live draft-preview binder picks the change up.
            this.$nextTick(() => {
                this.$refs.json.value = JSON.stringify(this.stickers);
                this.$refs.json.dispatchEvent(new Event('input', { bubbles: true }));
            });
        },
        addSticker(st) {
            if (this.stickers.length >= this.max) return;
            this.stickers.push(st);
            this.selected = this.stickers.length - 1;
            this.sync();
        },
        addEmoji(value) {
            value = (value || '').trim();
            if (!value) return;
            this.addSticker({ kind: 'emoji', value: value.slice(0, 16), x: this.spawnX(), y: this.spawnY(), rotation: -12, scale: 1, layer: 'front' });
        },
        addImage() {
            var url = (this.customImageUrl || '').trim();
            if (!url) return;
            if (!/^https?:\/\//i.test(url) && !/^\/f\//.test(url)) {
                alert('Image stickers need an https:// URL or a /f/ vault file path.');
                return;
            }
            this.addSticker({ kind: 'image', value: url, x: this.spawnX(), y: this.spawnY(), rotation: -8, scale: 1, layer: 'front' });
            this.customImageUrl = '';
        },
        // Spread new stickers around so they don't all pile up in one spot.
        spawnX() { return [18, 82, 25, 75, 50][this.stickers.length % 5]; },
        spawnY() { return [15, 22, 78, 70, 45][this.stickers.length % 5]; },
        removeSticker(i) {
            this.stickers.splice(i, 1);
            if (this.selected !== null) {
                if (!this.stickers.length) this.selected = null;
                else if (this.selected >= this.stickers.length) this.selected = this.stickers.length - 1;
            }
            this.sync();
        },
        duplicateSticker(i) {
            if (this.stickers.length >= this.max) return;
            var copy = JSON.parse(JSON.stringify(this.stickers[i]));
            copy.x = Math.min(100, copy.x + 6);
            copy.y = Math.min(100, copy.y + 6);
            this.stickers.splice(i + 1, 0, copy);
            this.selected = i + 1;
            this.sync();
        },
        moveSticker(i, dir) {
            var j = i + dir;
            if (j < 0 || j >= this.stickers.length) return;
            var tmp = this.stickers[i];
            this.stickers.splice(i, 1);
            this.stickers.splice(j, 0, tmp);
            this.selected = j;
            this.sync();
        },
        dragStart(ev, i) {
            this.selected = i;
            this.dragging = i;
            if (ev.target.setPointerCapture) {
                try { this.$refs.pad.setPointerCapture(ev.pointerId); } catch (e) {}
            }
            this.dragMove(ev);
        },
        dragMove(ev) {
            if (this.dragging === null) return;
            var rect = this.$refs.pad.getBoundingClientRect();
            var x = ((ev.clientX - rect.left) / rect.width) * 100;
            var y = ((ev.clientY - rect.top) / rect.height) * 100;
            this.stickers[this.dragging].x = Math.round(Math.max(0, Math.min(100, x)) * 10) / 10;
            this.stickers[this.dragging].y = Math.round(Math.max(0, Math.min(100, y)) * 10) / 10;
        },
        dragEnd() {
            if (this.dragging === null) return;
            this.dragging = null;
            this.sync();
        }
    };
}
</script>
