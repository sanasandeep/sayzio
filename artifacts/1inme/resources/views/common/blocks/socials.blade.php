    @php
        $sz = $s['size'] ?? 'md';
        $szClass = match($sz) { 'sm' => 'w-9 h-9', 'lg' => 'w-14 h-14', default => 'w-11 h-11' };
        $allPlatforms = $s['platforms'] ?? [];
        if ($block->type === 'socials_multi' && isset($s['groups'])) {
            $allPlatforms = [];
            foreach ($s['groups'] as $group) {
                $allPlatforms = array_merge($allPlatforms, $group['platforms'] ?? []);
            }
        }

        // Resolve any connection-backed entries in a single query — we serve
        // ONLY the cached follower count; refreshes are scheduled / deferred.
        $connIds = collect($allPlatforms)->pluck('connection_id')->filter()->unique()->values()->all();
        $connMap = [];
        if (! empty($connIds)) {
            // Scope to the biolink owner so a tampered block cannot expose
            // another user's connection data.
            $connMap = \App\Modules\User\Models\SocialAccountConnection::whereIn('id', $connIds)
                ->where('user_id', $link->user_id)
                ->get()->keyBy('id');
            $connIds = $connMap->keys()->all();
        }

        // Track pageView for lazy-refresh hook (RedirectController reads this).
        if (! empty($connIds)) {
            $existingRefs = app()->bound('biolink.referenced_social_connections')
                ? (array) app('biolink.referenced_social_connections')
                : [];
            app()->instance('biolink.referenced_social_connections',
                array_values(array_unique(array_merge($existingRefs, $connIds)))
            );
        }

        $alias = $alias ?? ($link->primary_alias ?? $link->alias ?? null);
    @endphp
    <div class="flex justify-center gap-2 mb-4 flex-wrap">
        @foreach($allPlatforms as $platform)
            @php
                $name    = $platform['name'] ?? '';
                $display = $platform['display'] ?? 'icon';
                $iconDef = ($socialIcons ?? [])[$name] ?? ['fas fa-link', '#7c3aed'];

                // Connection-backed entries override URL + brand metadata + count.
                $conn = ! empty($platform['connection_id']) ? ($connMap[$platform['connection_id']] ?? null) : null;
                $brandIcon  = $conn ? $conn->brandIcon()  : $iconDef[0];
                $brandColor = $conn ? $conn->brandColor() : $iconDef[1];
                $rawUrl     = $platform['url'] ?? '';
                if ($conn && empty($rawUrl)) $rawUrl = $conn->resolvedProfileUrl();
                if (! $rawUrl) $rawUrl = '#';

                // Route through the existing block click tracker so taps are
                // counted — handleBlockClick validates the ?to= scheme.
                $href = $alias && $rawUrl !== '#'
                    ? route('redirect.block', ['alias' => $alias, 'blockId' => $block->id]) . '?to=' . urlencode($rawUrl)
                    : $rawUrl;

                $count = $conn ? $conn->follower_count : null;
                $countLabel = \App\Modules\User\Models\SocialAccountConnection::formatCount($count);
                $showCount = $display === 'follow_count' && $countLabel !== null;

                $platformLabel = $conn
                    ? \App\Modules\User\Models\SocialAccountConnection::platformLabel($conn->platform)
                    : ucfirst($name ?: 'Link');
                $btnLabel = match (true) {
                    $name === 'youtube' || ($conn && $conn->platform === 'youtube') => 'Subscribe',
                    default => 'Follow',
                };
            @endphp

            @if($display === 'icon' || $display === '')
                <a href="{{ $href }}" target="_blank" rel="noopener"
                   class="{{ $szClass }} {{ ($s['style'] ?? '') === 'square' ? 'rounded-lg' : 'rounded-full' }} glass-block flex items-center justify-center transition-all hover:scale-110 hover:-translate-y-1"
                   style="color: {{ $brandColor }}"
                   aria-label="{{ $platformLabel }}">
                    <i class="{{ $brandIcon }} {{ $sz === 'lg' ? 'text-xl' : 'text-lg' }}"></i>
                </a>
            @else
                {{-- Follow button (with optional cached count). --}}
                <a href="{{ $href }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 px-3 py-2 rounded-full text-xs font-semibold transition-all hover:-translate-y-0.5"
                   style="background: {{ $brandColor }}; color: #fff; box-shadow: 0 6px 18px {{ $brandColor }}55;"
                   aria-label="{{ $btnLabel }} on {{ $platformLabel }}">
                    <i class="{{ $brandIcon }}"></i>
                    <span>{{ $btnLabel }}</span>
                    @if($showCount)
                        <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold"
                              style="background: rgba(255,255,255,0.18); color: #fff;">
                            {{ $countLabel }}
                        </span>
                    @endif
                </a>
            @endif
        @endforeach
    </div>
