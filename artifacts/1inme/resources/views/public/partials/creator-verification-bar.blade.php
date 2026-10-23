@if($creator->isVerified() || ($__cpLive ?? false))
<div class="cp-verification-bar" data-cp-verification-strip role="img" aria-label="Verified creator" style="--cp-verified:{{ $creator->verificationTickType?->color ?? '#1d9bf0' }};{{ $creator->isVerified() ? '' : 'display:none' }}"></div>
@endif
