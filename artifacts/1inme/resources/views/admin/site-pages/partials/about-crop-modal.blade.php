{{--
    Crop modal markup. Must be rendered inside an `aboutPhotoUploader(...)`
    Alpine scope — it reads/writes that scope's `cropping`, `previewUrl`,
    `imgStyle`, `zoom`, `vpW`, `vpH` and crop methods.
--}}
<div x-show="cropping" x-cloak
     class="fixed inset-0 z-[200] flex items-center justify-center bg-black/80 p-4"
     @keydown.escape.window="cropping && cancelCrop()">
    <div class="bg-[#1a1d2a] border border-white/10 rounded-2xl p-5 max-w-md w-full" @click.stop>
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-white">Crop photo</h4>
            <button type="button" @click="cancelCrop()" class="text-white/60 hover:text-white" title="Close"><i class="fas fa-times"></i></button>
        </div>
        <p class="text-xs text-white/50 mb-3" x-text="isCircle ? 'Drag the photo and use the slider to zoom. The card on /about is shown as a circle, so the area inside the ring is what visitors will see.' : 'Drag the photo and use the slider to zoom. The area inside the frame is what visitors will see on /about.'"></p>
        <div class="mx-auto bg-black border border-white/10 rounded-lg overflow-hidden relative select-none touch-none cursor-move"
             :style="`width:${vpW}px;height:${vpH}px;`"
             @mousedown="startDrag($event)"
             @mousemove.window="moveDrag($event)"
             @mouseup.window="endDrag()"
             @touchstart.prevent="startDrag($event)"
             @touchmove.window.prevent="moveDrag($event)"
             @touchend.window="endDrag()">
            <template x-if="previewUrl">
                <img :src="previewUrl" :crossorigin="previewUrl && previewUrl.indexOf('blob:') === 0 ? null : 'anonymous'" alt="" draggable="false" class="absolute left-1/2 top-1/2 max-w-none pointer-events-none" :style="imgStyle">
            </template>
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute inset-0 ring-2 ring-white/70 shadow-[0_0_0_9999px_rgba(0,0,0,0.45)]" :class="isCircle ? 'rounded-full' : 'rounded-md'"></div>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <i class="fas fa-search-minus text-white/40 text-xs"></i>
            <input type="range" min="1" max="4" step="0.01" :value="zoom" @input="onZoom($event.target.value)" class="flex-1 accent-violet-500">
            <i class="fas fa-search-plus text-white/40 text-xs"></i>
        </div>
        <p x-show="error" x-text="error" class="text-xs text-red-400 mt-2"></p>
        <div class="mt-4 flex items-center justify-between gap-2">
            <button type="button" x-show="pendingFile" @click="skipCrop()" class="text-xs px-3 py-1.5 text-white/70 hover:text-white" title="Upload the original photo without cropping">Skip cropping</button>
            <span x-show="!pendingFile" class="text-xs text-white/40">Re-cropping current photo</span>
            <div class="flex items-center gap-2">
                <button type="button" @click="cancelCrop()" class="text-xs px-3 py-1.5 text-white/70 hover:text-white">Cancel</button>
                <button type="button" @click="confirmCrop()" :disabled="!natW" class="text-xs px-3 py-1.5 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 rounded-lg text-white inline-flex items-center gap-1">
                    <i class="fas fa-crop"></i><span>Save crop</span>
                </button>
            </div>
        </div>
    </div>
</div>
