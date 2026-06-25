@php
    $locked = $lockedFn($tpl->plan_tier);
    $previewData = [
        'id'         => $tpl->id,
        'name'       => $tpl->name,
        'tier'       => $tpl->plan_tier,
        'locked'     => $locked,
        'previewUrl' => route('user.onboarding.template.preview', $tpl->id),
        'upgradeUrl' => route('user.upgrade'),
    ];
@endphp
<button type="button"
        @click='openPreview(@json($previewData))'
        class="text-left glass rounded-2xl border border-white/10 overflow-hidden hover:border-blue-500/40 hover:-translate-y-0.5 transition group flex flex-col">
    <div class="aspect-[4/3] flex items-center justify-center overflow-hidden relative" style="background: linear-gradient(135deg, rgba(61,107,255,0.12), rgba(92,131,255,0.04));">
        @if($tpl->thumbnail_url)
            <img src="{{ $tpl->thumbnail_url }}" alt="{{ $tpl->name }}" class="w-full h-full object-cover">
        @else
            <img src="{{ asset('template-placeholders/page.svg') }}" alt="{{ $tpl->name }} preview" class="w-full h-full object-cover">
        @endif
        @if($locked)
            <div class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/90 text-white"><i class="fas fa-lock mr-1"></i>{{ $tpl->plan_tier }}</div>
        @endif
        <div class="absolute inset-0 flex items-center justify-center bg-black/0 group-hover:bg-black/30 transition opacity-0 group-hover:opacity-100">
            <span class="px-3 py-1.5 rounded-lg bg-white/90 text-slate-900 text-xs font-semibold inline-flex items-center gap-1.5">
                <i class="fas fa-eye"></i> Live preview
            </span>
        </div>
    </div>
    <div class="p-3 flex-1">
        <h3 class="text-sm font-semibold text-white mb-0.5 truncate">{{ $tpl->name }}</h3>
        <p class="text-[11px] text-white/40">{{ ucfirst($tpl->category) }} · {{ count($tpl->snapshot['blocks'] ?? []) }} blocks</p>
        @if($tpl->description)
            <p class="text-[11px] text-white/50 mt-1 line-clamp-2">{{ $tpl->description }}</p>
        @endif
    </div>
</button>
