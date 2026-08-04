---
name: Form delivery-file & vault anonymous access
description: Signed post-submit download links; why vault files need the feature's own serve path, not /f/{id}/{filename}.
---

# Deliver-a-file after form submit

- Forms can deliver a file post-submit: `settings['delivery_file']` (`enabled`/`url`/`label`), helpers on `Form` (`deliveryFileConfig/deliveryFileUrl/deliverySignedUrl`). The unlock is a `temporarySignedRoute('forms.public.delivery', ...)` bound to one submission; the route re-checks signature, spam, and rejects `payment_status='pending'` rows (paid-form ordering). Paid returns carry the link via a `dl` query param on the checkout success URL — the success view only renders `dl` values pointing at the form's OWN delivery route.

**Why the vault gate matters:** the generic vault serve endpoint `/f/{id}/{filename}` (`UserFileController::serve`) only allows ANONYMOUS access when the file is referenced by an allow-listed set of public records. New feature settings keys are NOT in that list, so redirecting an anonymous respondent there 403s. Any feature that hands a vault file to logged-out visitors must serve the file itself under its own authorization (resolve UserFile, honor scan gates `isPendingScan/isFlagged`, then S3 `temporaryUrl` redirect or local `response()->file()`), not bounce through the public serve route.

**How to apply:** when adding another "give the visitor this vault file" surface, copy the direct-serve pattern in `FormController::deliveryDownload` or extend `isReferencedByPublicRecord` deliberately.

Also: `user_files.scan_status` defaults to `'pending'` — test fixtures inserting UserFile rows directly must forceFill `'clean'` or every serve path 403s/423s. The `public` filesystem disk is S3-backed in this project, so "serve the file" tests must accept a 302 to a temporary storage URL.
