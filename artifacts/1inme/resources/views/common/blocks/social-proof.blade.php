    @php
        // Ownership-scoped lookup: a user can only embed their own active campaigns
        $sp = !empty($s['social_proof_id'])
            ? \App\Modules\User\Models\SocialProof::where('id', $s['social_proof_id'])
                ->where('user_id', $link->user_id)
                ->where('is_active', true)
                ->first()
            : null;
    @endphp
    @if($sp)
        <script src="{{ url('/sp/' . $sp->uuid . '.js') }}" async></script>
    @endif
