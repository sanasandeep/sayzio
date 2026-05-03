<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($items as $tpl)
        @php $locked = $lockedFn($tpl->plan_tier); @endphp
        <div class="glass rounded-2xl border border-white/10 overflow-hidden hover:border-violet-500/40 transition group">
            <div class="aspect-[4/3] flex items-center justify-center overflow-hidden relative" style="background: linear-gradient(135deg, rgba(124,58,237,0.12), rgba(139,92,246,0.04));">
                @if($tpl->thumbnail_url)
                    <img src="{{ $tpl->thumbnail_url }}" alt="{{ $tpl->name }}" class="w-full h-full object-cover">
                @else
                    <img src="{{ asset('template-placeholders/page.svg') }}" alt="{{ $tpl->name }} preview" class="w-full h-full object-cover">
                @endif
                @if($locked)
                    <div class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/90 text-white"><i class="fas fa-lock mr-1"></i>{{ $tpl->plan_tier }}</div>
                @endif
            </div>
            <div class="p-4">
                <h3 class="text-sm font-semibold text-white mb-1">{{ $tpl->name }}</h3>
                <p class="text-xs text-white/40 mb-1">{{ ucfirst($tpl->category) }} · {{ count($tpl->snapshot['blocks'] ?? []) }} blocks</p>
                @if($tpl->description)
                    <p class="text-xs text-white/50 mb-3 line-clamp-2">{{ $tpl->description }}</p>
                @endif
                @if($locked)
                    <a href="{{ route('user.upgrade') }}" class="block text-center w-full py-2 text-xs font-semibold rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 hover:bg-amber-500/20 transition">
                        <i class="fas fa-lock mr-1"></i>Upgrade to "{{ $tpl->plan_tier }}" to use
                    </a>
                @else
                    <form method="POST" action="{{ route('user.onboarding.template.apply') }}">
                        @csrf
                        <input type="hidden" name="template_id" value="{{ $tpl->id }}">
                        <button type="submit" class="w-full py-2 text-xs font-semibold rounded-xl bg-violet-600 hover:bg-violet-700 text-white transition">
                            Use this template
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach
</div>
