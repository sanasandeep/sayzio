<fieldset x-show="editorTab === 'verification' || editorTab === 'all'" class="cp-editor-panel">
    <legend>Verification &amp; trust</legend>
    <div class="flex items-center gap-3 mb-3 flex-wrap">
        @if($user->isVerified() && $user->verificationTickType)
            {!! $user->verificationTickType->tickHtml('text-xl') !!}
            <strong>{{ $user->verificationTickType->name }} · verified</strong>
        @else
            <strong>{{ ucwords(str_replace('_',' ', $user->profile_verification_status ?: 'unverified')) }}</strong>
        @endif
    </div>
    <p class="cp-editor-hint">Your creator profile uses your account’s admin-approved verification badge. Grey, green and other badge colors are managed by the review team, independently of your profile theme.</p>
    <div class="flex flex-wrap gap-2">
        <a class="cp-share-button" href="{{ route('user.profile-verification.index') }}">Verification status &amp; requests</a>
        @if(!in_array($user->profile_verification_status,['verified','pending','pending_reverification'],true))
        <a class="cp-share-button" href="{{ route('user.profile-verification.request') }}">Request verification</a>
        @endif
        @if($user->hasPermission('user.verifications.review'))
        <a class="cp-share-button" href="{{ route('user.profile-verification.admin.index') }}">Review requests</a>
        <a class="cp-share-button" href="{{ route('user.profile-verification.admin.tick-types') }}">Manage badge colors</a>
        @endif
    </div>
    <p class="text-xs mt-3" style="color:var(--text-dimmed)">Include your official name, supporting URLs, proof attachments and a message. Approval locks your verified account name and photo; later identity changes require review.</p>
</fieldset>
