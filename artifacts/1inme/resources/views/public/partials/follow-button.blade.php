{{--
    Reusable follow toggle for /@handle. Reuses the existing
    viewer.follow.toggle endpoint so creators don't have to learn a new
    surface to manage their followers list.
--}}
<button type="button"
        x-data="{
            following: {{ $isFollowing ? 'true' : 'false' }},
            count: {{ (int) ($creator->followers_count ?? 0) }},
            busy: false,
            async toggle() {
                @if(!$viewer)
                    window.dispatchEvent(new CustomEvent('open-viewer-login'));
                    return;
                @endif
                if (this.busy) return;
                this.busy = true;
                try {
                    const res = await fetch('{{ route('viewer.follow.toggle', ['creator' => $creator->id]) }}', {
                        method: 'POST',
                        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept':'application/json'}
                    });
                    const j = await res.json();
                    if (j.success) {
                        this.following = !!j.following;
                        this.count = j.followers_count;
                    } else if (j.message) {
                        alert(j.message);
                    }
                } finally { this.busy = false; }
            }
        }"
        @click="toggle()"
        :disabled="busy"
        :class="following
            ? 'px-3.5 py-2 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold border border-slate-200 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200'
            : 'px-3.5 py-2 rounded-lg bg-violet-600 text-white text-xs font-semibold hover:bg-violet-700'">
    <template x-if="following"><span><i class="fas fa-check mr-1"></i> Following</span></template>
    <template x-if="!following"><span><i class="fas fa-user-plus mr-1"></i> Follow</span></template>
</button>
