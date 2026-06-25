{{ config('app.name') }} — Privacy request

@switch($stage)
@case('verify')
We received a request to {{ $pr->isDeletion() ? 'permanently delete your account' : 'export a copy of all your data' }} associated with {{ $pr->email }}.

To confirm this was you, please verify your request by opening the link below. This link expires in {{ \App\Modules\Common\Models\PrivacyRequest::VERIFY_TTL_HOURS }} hours.

{{ $actionUrl }}

If you did not make this request, you can safely ignore this email — nothing will happen unless the link is opened.
@break

@case('received')
Thanks — we've received your {{ $typeLabel }} request and our team will review it shortly.

@if($pr->isDeletion())
If approved, your account and personal data will be permanently removed after a short cooling-off period of {{ \App\Modules\Common\Models\PrivacyRequest::DELETION_GRACE_DAYS }} days. We'll email you at each step.
@else
If approved, we'll generate a secure archive of your data and email you a download link.
@endif

We aim to fulfil verified requests within 30 days, as required by law.
@break

@case('verified')
Your email has been confirmed and your {{ $typeLabel }} request is now queued for staff review. We'll email you again as soon as a decision is made.
@break

@case('approved')
Good news — your {{ $typeLabel }} request has been approved.

@if($pr->isDeletion())
Your account is scheduled for permanent deletion after a {{ \App\Modules\Common\Models\PrivacyRequest::DELETION_GRACE_DAYS }}-day cooling-off period{{ $pr->scheduled_at ? ' (on or after '.$pr->scheduled_at->toDayDateTimeString().' UTC)' : '' }}. If you change your mind before then, contact our support team.
@else
We're now preparing your data archive. You'll receive a separate email with a secure download link once it's ready.
@endif
@break

@case('rejected')
After review, we were unable to complete your {{ $typeLabel }} request.

@if($pr->rejection_reason)Reason: {{ $pr->rejection_reason }}@endif

If you believe this was a mistake, please reply to our support team.
@break

@case('completed')
Your account and associated personal data have been permanently removed from {{ config('app.name') }}, as requested. Some records may be retained in anonymised form where required for legal or financial compliance.

We're sorry to see you go.
@break

@case('ready')
Your data export is ready. Download your archive using the secure link below. For your security, this link expires on {{ optional($pr->download_expires_at)->toDayDateTimeString() }} UTC.

{{ $actionUrl }}

The archive contains a structured copy of your account data and your uploaded files.
@break

@default
There has been an update to your {{ $typeLabel }} request.
@endswitch

— The {{ config('app.name') }} team
