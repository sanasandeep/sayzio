<details class="rounded-2xl border p-4 mb-5" style="background:var(--bg-card);border-color:var(--border-soft)">
    <summary class="cursor-pointer font-semibold">Workspace phone identity</summary>
    <p class="text-sm mt-3" style="color:var(--text-muted)">Each number can identify only one creator profile across Sayzio. This allocation belongs to {{ $workspaceProfile->workspace->name }}. Contact and WhatsApp buttons are separate from caller identity.</p>
    @foreach($identityPhones as $phone)<p class="font-semibold mt-2">{{ $phone->phone }} · Attached to this profile</p>@endforeach
    <form method="POST" action="{{ route('user.creator-profile.phone.attach') }}" class="flex flex-wrap gap-2 mt-4">@csrf<input type="hidden" name="creator_profile_id" value="{{ $workspaceProfile->id }}"><label class="sr-only" for="identity-phone">Verified phone number</label><input id="identity-phone" name="phone" type="tel" required placeholder="+91 98765 43210" maxlength="60" class="rounded-xl border p-3 min-w-0 flex-1" style="background:var(--bg-input)"><button class="rounded-xl border p-3">Attach verified number</button></form>
    @error('phone')<p role="alert" class="text-sm text-red-500 mt-2">{{ $message }}</p>@enderror
    <p class="text-xs mt-2" style="color:var(--text-muted)">First verify the number in <a class="underline" href="{{ route('user.identifiers.index') }}">Connected Accounts</a>. Already attached elsewhere, or unable to verify? Request an admin ownership review below.</p>
    <details class="mt-4"><summary class="cursor-pointer text-sm font-semibold">Request number ownership review</summary>
        <form method="POST" enctype="multipart/form-data" action="{{ route('user.creator-profile.phone.dispute') }}" class="mt-3 space-y-3">@csrf<input type="hidden" name="creator_profile_id" value="{{ $workspaceProfile->id }}">
            <label class="block text-sm">Number with country code<input type="tel" name="phone" required maxlength="60" class="block w-full rounded-xl border p-3" style="background:var(--bg-input)"></label>
            <label class="block text-sm">Explain your ownership<textarea name="message" required maxlength="3000" rows="3" class="block w-full rounded-xl border p-3" style="background:var(--bg-input)"></textarea></label>
            <label class="block text-sm">Supporting URLs (one per line, up to 5)<textarea name="supporting_urls" maxlength="6000" rows="2" class="block w-full rounded-xl border p-3" style="background:var(--bg-input)"></textarea></label>
            <label class="block text-sm">Proof attachments (up to 5)<input type="file" name="proof_files[]" multiple class="block mt-2"></label>
            <p class="text-xs" style="color:var(--text-muted)">Admin reviews the evidence before revoking another profile’s allocation. This does not change account login access.</p>
            <button class="rounded-xl border px-4 py-2">Submit ownership request</button>
        </form>
    </details>
    @foreach($phoneClaims as $claim)<p class="text-xs mt-3">{{ $claim->phone }} · {{ ucfirst($claim->status) }}@if($claim->admin_notes) · {{ $claim->admin_notes }}@endif</p>@endforeach
</details>
