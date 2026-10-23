@if($creator->isVerified())
<div class="cp-verification-bar" aria-label="Verified creator" style="--cp-verified:{{ $creator->verificationTickType?->color ?? '#1d9bf0' }}">
    @if($creator->verificationTickType){!! $creator->verificationTickType->tickHtml('text-sm') !!}@else<i class="fas fa-circle-check" aria-hidden="true"></i>@endif
    <span>Verified{{ $creator->verificationTickType ? ' · '.$creator->verificationTickType->name : '' }}</span>
    @if($creator->profile_verification_status === 'pending_reverification')<span class="cp-verification-note">Update under review</span>@endif
</div>
@endif
