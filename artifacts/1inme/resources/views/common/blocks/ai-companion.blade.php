    @php
        $s = $block->settings ?? [];
        $cmpId = $s['companion_id'] ?? null;
        // Ownership-scoped lookup — a user can only render their own
        // companion on their own biolink. Without this guard, anyone
        // who could write the block's settings (e.g. via a tampered
        // import) could bind to an arbitrary companion and burn
        // somebody else's AI credits.
        $cmp = $cmpId ? \App\Modules\User\Models\AiCompanion::query()
            ->where('id', $cmpId)
            ->where('user_id', $link->user_id)
            ->where('placement', 'biolink')
            ->where('is_disabled', false)
            ->first() : null;
    @endphp
    @if($cmp)
        @php
            $cfg = $cmp->effectiveConfig();
            $inline = !empty($cfg['inline']);
        @endphp
        @if($inline)
            <div class="rounded-2xl overflow-hidden border border-white/10">
                <iframe src="{{ route('public.companion.iframe', ['publicId' => $cmp->public_id]) }}"
                        title="{{ $cmp->name }}"
                        style="border:0;width:100%;height:480px;display:block;"></iframe>
            </div>
        @else
            <script src="{{ url('/embed/companion.js') }}"
                    data-companion="{{ $cmp->public_id }}"
                    data-accent="{{ $cfg['accent'] ?? '#7c3aed' }}"
                    data-position="{{ $cfg['position'] ?? 'bottom-right' }}"
                    data-label="{{ $cfg['launcher_label'] ?? 'Chat' }}"
                    data-greeting="{{ $cfg['greeting_bubble'] ?? '' }}"
                    data-placeholder="{{ $cfg['placeholder'] ?? 'Ask me anything…' }}"
                    data-theme="{{ $cfg['theme'] ?? 'auto' }}"
                    defer></script>
        @endif
    @else
        <div class="text-xs text-white/40 italic text-center py-2">AI Companion not configured.</div>
    @endif
