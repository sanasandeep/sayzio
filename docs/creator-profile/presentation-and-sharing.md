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

## Image sources and verification selection

Both creator avatar and cover use the existing shared image picker with URL, Upload, and My Files sources. URL/My Files selections become validated files through the same existing media service and multipart upload fields; the creator editor listens to its media-source-selected event for live previews. Avatar size remains 2 MB and cover size 5 MB. Verified avatar replacement stays locked. Existing legacy cover-image URL and avatar gallery parameters remain accepted for older clients.

Eligible users can choose a verification category on the status page to open a preselected application. Only active, publicly requestable categories can be preselected; inactive and admin-only types cannot be chosen through a query parameter. Pending/verified accounts continue through their existing status flow. The application shows visible radio inputs and a clear colored selection outline. Approved creators display a 4 px admin-colored strip above the cover without visible text. Changing the account name or personal creator handle invalidates verification and supersedes pending reviews; users must submit a fresh application. Settings warn before the change and show a reapply alert afterward. Avatar changes remain locked.

## Workspace identity and exclusive phone allocations

Account Profile Settings (web and `/api/v1/profile`) always targets the personal profile. The creator editor and `/api/v1/me/creator-profile` target the active workspace. Newly created workspace profiles are hydrated before display, and null fields override personal values rather than inheriting the personal handle, photo, biography or verification. Team names, design, visibility, organizer and safety settings are independent. Personal profile fields continue mirroring to the account for legacy consumers. Forms include the profile id so a workspace switch in another tab cannot silently save to the wrong profile.

Non-personal creator verification uses the workspace profile's status and a `creator_profile_id` on requests. Personal verification retains the account path; existing approvals remain personal. Review screens identify the target workspace. Account API verification requests are restricted to the personal scope. Superseded requests cannot be approved or rejected to restore a removed badge.

Admins with `user.verifications.review` and the existing full-identity privacy requirements can use `/admin/verified-directory` to search verified personal profiles, workspace profiles and all link types. Revocation requires a reason, records actor/guard/target/time, supersedes pending requests for that target, and removes verified biolink block badges while preserving their text/images. No content is deleted.

`creator_phone_assignments` is the exclusive caller-identity registry: canonical international numbers (`+` and country code; formatting and `00` prefix normalized) have a database UNIQUE constraint. A creator profile belongs to one workspace, and separate workspaces can attach different numbers. Self-service attachment requires an already verified linked phone on the acting account. Numbers claimed by another profile cannot be attached even by the same account. Ordinary contact/WhatsApp fields do not establish caller-ID ownership.

A user can submit number ownership evidence, URLs and a message from the workspace's creator editor. Admins review these at `/admin/creator-phone-claims`, approve reassignment atomically or reject with notes. Reassignment revokes the prior allocation and records it; competing pending claims are superseded. Admins can also revoke an attachment with a reason. These operations never transfer or delete account login identifiers. Future dialer identity lookup should consume this registry; this change does not replace the existing dialer lookup behavior or migrate legacy contacts into ownership claims.

Deploy both `2029_11_11_000001` and `2029_11_11_000002` migrations before using these screens. No phone allocations are inferred from unreviewed contact fields.
