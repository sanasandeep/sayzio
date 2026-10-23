# Creator profile presentation and sharing

Settings → Creator Profile now offers a main-biolink picker, Paper/Midnight/Warm studio/Fresh mint themes, and photo shape, size and horizontal/vertical framing. By default, the selected biolink appears as the Open biolink button and `/@handle` renders the creator profile. Users can alternatively choose “Redirect directly to a link” and select any active workspace link. This issues a temporary, private/no-store 302 through the normal link URL after publication, block, country and age checks. Destination link protections still apply. Missing or disabled targets fall back to the creator profile. Switching modes retains profile content; sharing downloads and embedded editor previews do not redirect. Link selection is restricted to active biolink-family pages in the current workspace. A selected link that is later disabled no longer appears publicly.

Presentation and the selected link ID are stored in the existing workspace creator profile's `profile_showcase` JSON. Partial saves preserve these keys. Verified identity photo replacement remains locked; presentation controls do not change that lock. The editor preview updates theme, shape, size, framing and local photo uploads before saving.

Under Visible sections, Posts and Subscriptions, tips & paid posts can be switched off independently. The monetization switch hides profile sales prompts and paid posts; it never changes subscription state, payment endpoints or purchases. Paid-page feed consumers retain their existing behavior. Profile completeness does not require a post when Posts is disabled.

The editor and public profile offer native sharing (copy fallback), a copy-link button, WhatsApp/Telegram/SMS links, vCard download and a QR code. Sharing opens the user's chosen application; messages are not sent automatically.

`GET /@handle/contact.vcf` and `GET /@handle/share-qr.svg` use the profile page's existing publication, block and region gates, enforce the age gate and return private/no-store responses. The vCard contains the public name and profile URL, plus only explicitly published email/call/WhatsApp fields while the relevant contact/CTA/social sections are enabled. It never reads account email or phone. QR generation is local and encodes only the profile URL.

## Editor organization and layouts

The editor groups controls into Design, About you, Links & contact, Visibility, and Publish & safety. The All options tab has been removed; validation errors open the relevant section. Switching sections keeps fields mounted so saving retains selections. Each visibility key has one checkbox in Visibility; other sections link back there. Sharing tools collapse into one disclosure above the editor. Destination settings show only the relevant profile-button or redirect selector.

Design offers Classic (left portrait, rounded cards), Spotlight (centered portrait, soft cards), Editorial (right portrait, crisp cards), and Compact (inline portrait, clean cards). Aurora, Sunset, Lagoon, and Twilight gradients each support explicit light and dark palettes. Existing theme keys remain compatible; legacy Midnight defaults to dark and other legacy themes default to light. New `presentation.layout` and `presentation.mode` keys use existing JSON storage, validation and partial-save preservation. No migration is needed. Layout, gradient, and appearance selections update the owner preview before saving; the preview appearance toggle is temporary.

## Cover photo and verification

Design includes a cover-photo upload (up to 5 MB), image URL, local preview, and removal back to the selected gradient. URL input accepts HTTP/HTTPS. The owner iframe previews the image without saving it.

Verification has a dedicated editor section linking to the existing account-verification status and request form. This reuses the existing reviewer permission and queue; no new staff access is granted. Admins choose a final badge type at approval and manage its catalog color, including grey and green. Creator profile themes cannot grant or recolor verification. The approved request records the admin-selected badge type, and the badge stays visible in the small preview.

Applications now store up to five supporting HTTP/HTTPS URLs and additional information (4,000 characters) alongside the existing official name, purpose, attachments, and reviewer message. Web and API submissions persist the new fields; the reviewer detail API exposes them. The initial API submission also accepts a message and up to five proof files. Existing pending-request follow-up messages and attachments remain available. Evidence is shown on the owner status page and admin review, not the public creator page. A migration adds nullable supporting_urls and additional_info columns to profile_verification_requests.

## Visibility controls

Profile navigation tabs can be switched off to show enabled content as one continuous page. Posts controls the entire profile feed, while Monetization only controls paid posts/subscription/tip surfaces. “Simple profile: no posts or tabs” turns off Posts, Monetization and navigation tabs. “Hide all optional sections” switches every visibility key off, including sharing and related creators. These are profile presentation settings, not deletion or global unpublishing of individual posts.

Visibility changes refresh the owner's iframe using owner-only, in-memory query overrides. Visitors cannot use these overrides. Save profile applies the choices publicly. Disabled Posts is absent even with a stale `?tab=posts` URL. Disabled Contact hides email/call/WhatsApp action buttons, public email social links, messaging prompts, and the Save contact button. Statistics is the master for profile metric strips and featured-link counts; Highlights selects the compact layout and its individual fields. Unchecked highlight values are explicitly posted as zero and remain off on partial saves. Hidden bio/social/biolink metadata is omitted from the profile's structured data.
