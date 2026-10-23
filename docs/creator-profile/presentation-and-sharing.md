# Creator profile presentation and sharing

Settings → Creator Profile now offers a main-biolink picker, Paper/Midnight/Warm studio/Fresh mint themes, and photo shape, size and horizontal/vertical framing. The selected biolink appears as the Open biolink button; `/@handle` continues to render the creator profile. Link selection is restricted to active biolink-family pages in the current workspace. A selected link that is later disabled no longer appears publicly.

Presentation and the selected link ID are stored in the existing workspace creator profile's `profile_showcase` JSON. Partial saves preserve these keys. Verified identity photo replacement remains locked; presentation controls do not change that lock. The editor preview updates theme, shape, size, framing and local photo uploads before saving.

Under Visible sections, Posts and Subscriptions, tips & paid posts can be switched off independently. The monetization switch hides profile sales prompts and paid posts; it never changes subscription state, payment endpoints or purchases. Paid-page feed consumers retain their existing behavior. Profile completeness does not require a post when Posts is disabled.

The editor and public profile offer native sharing (copy fallback), a copy-link button, WhatsApp/Telegram/SMS links, vCard download and a QR code. Sharing opens the user's chosen application; messages are not sent automatically.

`GET /@handle/contact.vcf` and `GET /@handle/share-qr.svg` use the profile page's existing publication, block and region gates, enforce the age gate and return private/no-store responses. The vCard contains the public name and profile URL, plus only explicitly published email/call/WhatsApp fields while the relevant contact/CTA/social sections are enabled. It never reads account email or phone. QR generation is local and encodes only the profile URL.
