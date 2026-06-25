@if($boards->isEmpty())
    <div class="rounded-2xl border p-8 text-center" style="background: var(--bg-card); border-color: var(--border-soft); color: var(--text-muted);">
        {{ $emptyMsg }}
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($boards as $b)
            <a href="{{ route('user.tasks.show', $b) }}"
               class="group block rounded-2xl border p-5 transition hover:shadow-lg"
               style="background: var(--bg-card); border-color: var(--border-soft); border-left: 4px solid {{ $b->color ?: '#5c83ff' }};">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="text-base font-bold group-hover:text-blue-500" style="color: var(--text-primary);">{{ $b->name }}</h3>
                    @if($b->scope === 'personal')
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                              style="background: rgba(92,131,255,0.12); color:#3d6bff;">PRIVATE</span>
                    @endif
                </div>
                @if($b->description)
                    <p class="text-xs mt-1 line-clamp-2" style="color: var(--text-muted);">{{ $b->description }}</p>
                @endif
                <div class="mt-4 text-xs" style="color: var(--text-faint);">
                    <i class="fas fa-list-check mr-1"></i> {{ $b->open_cards_count ?? 0 }} open
                </div>
            </a>
        @endforeach
    </div>
@endif
