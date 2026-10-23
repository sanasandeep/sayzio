@if($creator->isVerified())
<div class="cp-verification-bar" role="img" aria-label="Verified creator" style="--cp-verified:{{ $creator->verificationTickType?->color ?? '#1d9bf0' }}"></div>
@endif
