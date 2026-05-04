    @php
        // Ownership-scoped lookup: a user can only embed their own active campaigns
        $sp = !empty($s['social_proof_id'])
            ? \App\Modules\User\Models\SocialProof::where('id', $s['social_proof_id'])
                ->where('user_id', $link->user_id)
                ->where('is_active', true)
                ->first()
            : null;
        // Per-biolink privacy (task #1114): when the page owner has chosen
        // to hide public visitor counts on this page, suppress the
        // social-proof embed entirely. It surfaces live visitor / recent
        // activity counters that would otherwise leak the very signal
        // the toggle is meant to keep private.
        // Privacy-first default: when the owner hasn't saved a Privacy
        // panel yet, hide live counters by default. Explicit `false`
        // (creator opted in) still shows the embed.
        $__spHideExplicit = data_get($link->settings, 'biolink.privacy.hide_public_visitor_counts', null);
        $__spHidden = $__spHideExplicit === null ? true : (bool) $__spHideExplicit;
    @endphp
    @if($sp && !$__spHidden)
        <script src="{{ url('/sp/' . $sp->uuid . '.js') }}" async></script>
    @endif
