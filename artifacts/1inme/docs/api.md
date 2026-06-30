# Sayzio REST API (v1)

Base URL: `/api/v1` &nbsp;·&nbsp; Auth: `Authorization: Bearer <token>` (Sanctum personal access token)

> **Related docs:** [Mobile app](../../1inme-mobile/docs/mobile-app.md) · [Browser extension](../../1inme-extension/README.md) · [Coin & AI-credit audit](./billing-ai-credit-audit.md)

All responses are JSON.

* Success → `{ "data": ... }` (or `204 No Content`)
* Failure → `{ "error": { "message": str, "code": str, "details"?: any } }`

This reference is generated from `routes/api.php` (the `v1` group) and is the
authoritative list of every currently-exposed endpoint group. The **Auth**
column means: `—` public (no token), `opt` optional bearer token (anonymous
works but a token unlocks visibility/ownership), `yes` bearer token required
(`auth:sanctum`). Authenticated routes also run `TouchSessionToken` (keeps the
token's session row warm) and `MeterApiUsage` (meters developer API-key calls;
see [API usage metering](#api-usage-metering)).

---

## Contents

- [Authentication](#authentication) · [OTP / social / demo](#otp-social--demo-auth) · [Sessions & security](#sessions--security)
- [Profile](#profile) · [Account merge](#account-merge) · [Onboarding](#onboarding) · [Dashboard & notifications](#dashboard--notifications) · [Push tokens](#push-tokens)
- [Links](#links-own-resources) · [Link-in-bio wizard](#guided-link-in-bio-wizard) · [A/B links](#ab-links) · [Smart links & rules](#smart-links--rules) · [AI-chat links](#ai-chat-links) · [Conversational links](#conversational-links) · [Card templates](#card-templates) · [Page templates](#page-templates) · [NFC writes](#nfc-writes)
- [Biolinks (public)](#biolinks-public-visibility-aware) · [Blocks](#biolink-blocks-authoring) · [Block live limits & interactions](#block-live-limits--interactions) · [Biolink themes](#biolink-themes)
- [Reviews (public)](#reviews-public) · [Reviews moderation (owner)](#reviews-moderation-owner)
- [Feed](#feed) · [Follows](#follows) · [Subscribers](#subscribers) · [Discovery](#discovery-public) · [Creator profile](#creator-profile-public) · [Paid pages](#paid-pages-public) · [Creator monetization](#creator-monetization) · [Product storefront](#product-storefront) · [Posts](#posts-creator-feed) · [Paid DMs](#paid-dms)
- [QR Studio](#qr-studio) · [Forms](#forms) · [Contacts & dialer](#contacts) · [Google Contacts sync](#google-contacts-sync) · [Bulk import](#bulk-import-preview-workflow) · [Resume](#resume--portfolio) · [Projects](#projects)
- [Wallet & coins](#wallet--coins) · [AI](#ai-credits-knowledge-bases-voice-account-assistant-chat-widgets) · [Creator payouts](#creator-payouts) · [18+ adult content](#adult-content) · [Billing](#billing) · [Plans & RevenueCat](#plans--revenuecat)
- [Domains](#custom-domains) · [Splash pages](#splash-pages) · [Restaurant menu](#restaurant-menu) · [Workspaces](#workspaces) · [Team](#team--staff) · [Client portals](#client-portals) · [Vault](#vault) · [Inbox](#inbox-biolink-dms) · [Spam settings](#spam-settings) · [Forwarding](#forwarding)
- [Social connections & proofs](#social-connections--proofs) · [Integrations](#integrations) · [Calendar](#calendar) · [Verification](#verification)
- [Admin (mobile back-office)](#admin-mobile-back-office) · [Banned names / reserved handles](#banned-names--reserved-handles) · [Plan editor](#plan-editor) · [Admin mail / SMTP](#admin-mail--smtp-settings)
- [Extension surface](#browser-extension-surface) (properties, backlinks, pixels, thank-yous) · [Pixel tracking](#pixel-tracking) · [Health](#health)
- [Error codes](#error-codes) · [Pagination](#pagination-shape) · [API usage metering](#api-usage-metering)

---

## Authentication

| Method | Path                | Auth | Description                                  |
| ------ | ------------------- | ---- | -------------------------------------------- |
| POST   | `/auth/register`    | —    | Create account, returns user + token. Throttle: `auth-register`. |
| POST   | `/auth/login`       | —    | Returns user + token. `device` field optional. Throttle: `auth-credentials`. |
| GET    | `/auth/config`      | —    | Which login methods are available (email-only by default; WhatsApp/mobile behind an admin toggle, with allowed country codes). |
| POST   | `/auth/logout`      | yes  | Revokes the current token.                   |
| GET    | `/auth/me`          | yes  | Current user details.                        |

```bash
# Register
curl -X POST $BASE/auth/register \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"name":"Jane","email":"jane@example.com","password":"password123"}'

# Login (returns token)
TOKEN=$(curl -s -X POST $BASE/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"jane@example.com","password":"password123"}' \
  | jq -r '.data.token')

# Use token
curl $BASE/auth/me -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

## OTP / social / demo auth

Used mainly by the mobile app for passwordless and native sign-in.

| Method | Path                  | Auth | Description                                                            |
| ------ | --------------------- | ---- | --------------------------------------------------------------------- |
| POST   | `/auth/otp/send`      | —    | Send a one-time code to an email/phone. Throttle: `otp-send`.         |
| POST   | `/auth/otp/verify`    | —    | Verify a code; returns user + token for an existing account. Throttle: `otp-verify`. |
| POST   | `/auth/otp/register`  | —    | Verify a code and create a new account. Throttle: `auth-register`.    |
| POST   | `/auth/social`        | —    | Exchange a native Apple/Google identity token for a Sayzio token. Throttle: 20/min. |
| POST   | `/auth/demo`          | —    | Demo login (non-prod). Mirrors the web "Try as Demo" button. Throttle: 20/min. |

> **Registration pause.** When an administrator pauses new sign-ups, every
> account-creation path — `/auth/register`, `/auth/otp/register`, an OTP verify of
> an unknown account, and `/auth/social` for a new user — returns **403** with code
> `registration_paused`. Sign-in for existing accounts is unaffected.

## Sessions & security

| Method | Path                          | Auth | Description                                                     |
| ------ | ----------------------------- | ---- | ------------------------------------------------------------- |
| GET    | `/auth/sessions`              | yes  | List active devices/sessions for the current user.            |
| DELETE | `/auth/sessions/others`       | yes  | Revoke every session except the current one.                  |
| DELETE | `/auth/sessions/{id}`         | yes  | Revoke a single session.                                      |
| GET    | `/security/logins`            | yes  | Recent-logins history (mobile parity for the suspicious-login email). |
| POST   | `/security/logins/{id}/revoke`| yes  | "This wasn't me" — revoke a recent login.                     |

## Profile

| Method | Path        | Auth | Notes                                                                |
| ------ | ----------- | ---- | -------------------------------------------------------------------- |
| GET    | `/profile`  | yes  | Same payload as `/auth/me`.                                          |
| PATCH  | `/profile`  | yes  | Update `name`, `bio`, `handle`, `avatar`, `phone`, `timezone`, etc.  |

## Account merge

Stateless Bearer-token mirror of the web `/user/merge` flow, so a mobile user
can absorb a duplicate account they also control without hopping to the browser.
The flow is **challenge → verify → preview → confirm**; the proven secondary
account id rides between steps inside an encrypted `merge_token` (no session
state). All four steps run as the authenticated **primary** user.

| Method | Path                        | Auth | Description                                                                       |
| ------ | --------------------------- | ---- | -------------------------------------------------------------------------------- |
| POST   | `/account/merge/challenge`  | yes  | Send an OTP to the secondary identifier. Body: `{ kind: "email"\|"phone", value }`. Throttled (`otp-send`). |
| POST   | `/account/merge/verify`     | yes  | Verify the OTP. Body: `{ kind, value, code }`. Returns `{ merge_token, preview }`. Throttled (`otp-verify`). |
| POST   | `/account/merge/preview`    | yes  | Re-fetch the move preview for a `merge_token`. Returns `{ merge_token, preview }`. |
| POST   | `/account/merge/confirm`    | yes  | Run the merge. Body: `{ merge_token, keep_plan_from: "primary"\|"secondary" }`. Returns `{ merged, records_moved, kept_plan_from, user }`. Throttle: 10/min. |

The `preview` payload reports `total_records`, the per-type `items` to be moved,
the secondary account's `identifiers` (emails/phones that become linked
identifiers on the primary), and the `primary`/`secondary` account summaries.

## Onboarding

The mobile client reads admin-managed splash `slides` and the user's onboarding
status/completion here. The **persona + starter-template** picker is a web-only
single-page wizard (see [knowledge base](knowledge-base.md)); there is no
persona/template API surface — mobile onboarding completes once the user has a
handle and a first link.

| Method | Path                    | Auth | Description                                              |
| ------ | ----------------------- | ---- | ------------------------------------------------------- |
| GET    | `/onboarding/slides`    | —    | Admin-managed mobile splash slides (`{ items: [{ id, title, body, image_url, image_urls }] }`). |
| GET    | `/onboarding`           | yes  | Current onboarding status: `onboarded_at`, `has_handle`, `email_verified`, `has_links`, `has_biolink`. |
| POST   | `/onboarding/complete`  | yes  | Mark onboarding complete (stamps `onboarded_at`).       |

## Dashboard & notifications

| Method | Path                              | Auth | Description                                          |
| ------ | --------------------------------- | ---- | --------------------------------------------------- |
| GET    | `/dashboard`                      | yes  | Summary cards for the mobile home tab.              |
| GET    | `/notifications`                  | yes  | Paginated in-app notifications.                     |
| POST   | `/notifications/read-all`         | yes  | Mark all notifications read.                        |
| POST   | `/notifications/{id}/read`        | yes  | Mark one notification read.                         |
| DELETE | `/notifications/{id}`             | yes  | Dismiss one notification (soft delete — restorable).|
| POST   | `/notifications/{id}/restore`     | yes  | Restore a previously dismissed notification.        |
| GET    | `/notifications/dismissed`        | yes  | Paginated recently dismissed (last 30 days).        |
| GET    | `/me/notification-preferences`    | yes  | Per-channel notification preferences.              |
| PUT    | `/me/notification-preferences`    | yes  | Update notification preferences.                   |

## Push tokens

| Method | Path                | Auth | Description                                       |
| ------ | ------------------- | ---- | ------------------------------------------------ |
| POST   | `/me/push-tokens`   | yes  | Register an Expo push token for this device.     |
| DELETE | `/me/push-tokens`   | yes  | Remove a push token (sign-out / disable).        |

---

## Links (own resources)

| Method | Path             | Auth | Description                                                                          |
| ------ | ---------------- | ---- | ------------------------------------------------------------------------------------ |
| GET    | `/links`         | yes  | Paginated list. Filters: `type`, `q`, `per_page`.                                    |
| POST   | `/links`         | yes  | Body: `type` (short/biolink/file/qr/event/vcard/social/sms/wifi/pdf/ai_chat/…), `alias?`, `title?`, `long_url?`, `visibility?`. |
| GET    | `/links/{id}`    | yes  | Show single link you own.                                                            |
| PATCH  | `/links/{id}`    | yes  | Partial update.                                                                      |
| DELETE | `/links/{id}`    | yes  | Delete link.                                                                         |
| GET    | `/links/{id}/analytics` | yes | Click/visit analytics for a link.                                            |
| GET    | `/links/{id}/analytics/blocks/{blockId}` | yes | Per-block analytics for a biolink.                          |
| GET    | `/links/{id}/heatmap`      | yes | Aggregated click-origin coordinate points for the heatmap window (web parity). |
| GET    | `/links/{id}/heatmap/live` | yes | Pollable "recent points since a cursor" feed: the first poll seeds the last few minutes, later polls return only newer points. |
| POST   | `/links/{id}/reset` | yes | Reset a link's analytics counters.                                              |
| GET    | `/links/{id}/rate-limit`   | yes | Per-biolink visitor rate-limit override (read).                          |
| PATCH  | `/links/{id}/rate-limit`   | yes | Per-biolink visitor rate-limit override (update).                        |
| GET    | `/links/{id}/rsvps`        | yes | RSVP responses for an event link (see [Calendar](#calendar)).            |

### Guided Link-in-bio wizard

Mobile parity for the web `user.links.wizard.*` flow. Stateless — the client
drives the steps and submits all answers to `/generate`. Literal `wizard`
segments win over the integer-guarded `/links/{id}` matcher.

| Method | Path                       | Auth | Description                                            |
| ------ | -------------------------- | ---- | ----------------------------------------------------- |
| GET    | `/links/wizard/taxonomy`   | yes  | Category/goal taxonomy that drives the question flow.  |
| GET    | `/links/wizard/questions`  | yes  | Wizard questions (uses `BiolinkWizardQuestions`).      |
| POST   | `/links/wizard/generate`   | yes  | Generate a biolink page from collected answers.        |

### A/B links

Literal `links/ab` segments are registered before `/links/{id}` so they win over the integer-id matcher.

| Method | Path                              | Auth | Description                                  |
| ------ | --------------------------------- | ---- | -------------------------------------------- |
| GET    | `/links/ab`                       | yes  | List A/B tests.                              |
| POST   | `/links/ab`                       | yes  | Create an A/B test.                          |
| GET    | `/links/{id}/ab`                  | yes  | Show an A/B test's variants/stats.           |
| POST   | `/links/{id}/ab/declare-winner`   | yes  | Promote a variant to the winner.             |

### Smart links & rules

| Method | Path                  | Auth | Description                                                  |
| ------ | --------------------- | ---- | ----------------------------------------------------------- |
| POST   | `/links/smart`        | yes  | Create a short link with geo/device/language/time/AB rules. |
| GET    | `/links/{id}/rules`   | yes  | Get routing rules on any owned link.                        |
| PUT    | `/links/{id}/rules`   | yes  | Replace routing rules.                                      |

### AI-chat links

Full-page AI chat link editor (`links.type = ai_chat`); reuses the AI Chat / Chat Widget infra via `AiChatPageManager`.

| Method | Path                    | Auth | Description                          |
| ------ | ----------------------- | ---- | ----------------------------------- |
| GET    | `/links/{id}/ai-chat`   | yes  | Load the AI-chat page config.       |
| PUT    | `/links/{id}/ai-chat`   | yes  | Save the AI-chat page config.       |

### Conversational links

Conversational-flow editor (`links.type = conversational`); mirrors the web
`user.links.conversational.{editor,save}` routes via the shared flow
validation/persistence helpers.

| Method | Path                              | Auth | Description                          |
| ------ | -------------------------------- | ---- | ----------------------------------- |
| GET    | `/links/{id}/conversational`     | yes  | Load the conversational flow config. |
| PUT    | `/links/{id}/conversational`     | yes  | Save the conversational flow config. |

### Card templates

| Method | Path                              | Auth | Description                              |
| ------ | --------------------------------- | ---- | --------------------------------------- |
| GET    | `/links/{id}/card-templates`      | yes  | List card templates for a link.         |
| POST   | `/links/{id}/card-templates/apply`| yes  | Apply a card template to a link.        |

### Page templates

Mobile parity for the web full-page template picker. Applying **replaces** the
link's blocks, so `apply` honours an overwrite-confirmation guard (HTTP **409**
when the link already has blocks).

| Method | Path                                       | Auth | Description                                                  |
| ------ | ------------------------------------------ | ---- | ----------------------------------------------------------- |
| GET    | `/links/{id}/page-templates`               | yes  | List available page templates for a link.                   |
| GET    | `/links/{id}/page-templates/{template}`    | yes  | Full block tree for one template (no writes) for preview.   |
| POST   | `/links/{id}/page-templates/apply`         | yes  | Apply a page template (409 if the link already has blocks).  |

### NFC writes

| Method | Path                                | Auth | Description                                   |
| ------ | ----------------------------------- | ---- | -------------------------------------------- |
| GET    | `/links/{id}/nfc-writes`            | yes  | NFC write history for a link.                |
| POST   | `/links/{id}/nfc-writes`            | yes  | Record an NFC write. Throttle: 60/min.       |
| DELETE | `/links/{id}/nfc-writes/{writeId}`  | yes  | Delete an NFC write record.                  |
| GET    | `/nfc-writes/summary`               | yes  | Cross-link NFC write summary.                |

---

## Biolinks (public, visibility-aware)

| Method | Path                              | Auth | Description                                                                                              |
| ------ | --------------------------------- | ---- | -------------------------------------------------------------------------------------------------------- |
| GET    | `/biolinks/{alias}`               | opt  | Returns `{biolink, owner, blocks[]}`. Honors visibility tier:<br>• `public` — open<br>• `registered` — 401 if anon<br>• `followers` — 403 if anon/non-follower<br>• `subscribers` — 403 if anon/non-subscriber |
| POST   | `/biolinks/{alias}/subscribe`     | —    | Public subscribe to creator's email list. Body: `email`, `name?`. Throttle: 10/min.                    |

A passed bearer token is honored for the visibility checks on the `show` route.

### Biolink blocks (authoring)

| Method | Path                                  | Auth | Description                                                  |
| ------ | ------------------------------------- | ---- | ----------------------------------------------------------- |
| GET    | `/block-catalog`                      | yes  | Block-type palette (categories + picker types, per-user `locked` flag). |
| GET    | `/links/{id}/blocks`                  | yes  | List blocks on a biolink.                                   |
| POST   | `/links/{id}/blocks`                  | yes  | Create a block (seeds first-paint defaults).               |
| PATCH  | `/links/{id}/blocks/{blockId}`        | yes  | Update a block (clears the placeholder flag on first save). |
| DELETE | `/links/{id}/blocks/{blockId}`        | yes  | Delete a block.                                             |
| POST   | `/links/{id}/blocks/reorder`          | yes  | Reorder blocks.                                             |

### Block live limits & interactions

Best-effort tracking + native interactions for in-app (mobile) biolink viewers. All optional-auth so logged-in viewers are deduped by `user_id` and visibility gates can identify followers/subscribers/owners; anonymous viewers still work on public biolinks.

| Method | Path                                              | Auth | Description                                                |
| ------ | ------------------------------------------------- | ---- | -------------------------------------------------------- |
| POST   | `/biolinks/{alias}/visit`                         | opt  | Page-visit ping (mirrors web `RedirectController::track`). Throttle: 120/min. |
| POST   | `/biolinks/{alias}/slides/view`                   | opt  | Slide-view ping for the mobile slides viewer. Throttle: 240/min. |
| POST   | `/biolinks/{alias}/blocks/{blockId}/tap`          | opt  | Block-tap counter. Throttle: 120/min.                    |
| GET    | `/biolinks/{alias}/blocks/limits`                 | opt  | Live limits snapshot (countdowns + remaining counts) for every block. Throttle: 120/min. |
| POST   | `/biolinks/{alias}/blocks/{blockId}/poll-vote`    | opt  | Cast a native poll vote. Throttle: 30/min.               |
| GET    | `/biolinks/{alias}/blocks/{blockId}/poll-results` | opt  | Aggregated poll tallies (visibility-gated). Throttle: 60/min. |
| POST   | `/biolinks/{alias}/blocks/{blockId}/rsvp`         | opt  | Submit an RSVP from a biolink RSVP block. Throttle: 10/min. |

### Biolink themes

Saved looks + scheduled application. Public viewers always see the currently-active theme via `/biolinks/{alias}`.

| Method | Path                                                  | Auth | Description                          |
| ------ | ---------------------------------------------------- | ---- | ----------------------------------- |
| GET    | `/links/{id}/themes`                                  | yes  | List saved themes.                  |
| POST   | `/links/{id}/themes`                                  | yes  | Save the current look as a theme.   |
| DELETE | `/links/{id}/themes/{themeId}`                        | yes  | Delete a saved theme.               |
| POST   | `/links/{id}/themes/schedules`                        | yes  | Schedule a theme for a date range.  |
| PATCH  | `/links/{id}/themes/schedules/{scheduleId}`           | yes  | Update a theme schedule.            |
| POST   | `/links/{id}/themes/schedules/{scheduleId}/cancel`    | yes  | Cancel/end a schedule early.        |

---

## Reviews (public)

Google-style reviews for a standalone **Reviews page** (`reviews` link type) or a
**`reviews_wall`** biolink block. Reviews are scoped to the creator and stamped
with the originating `link_id`. The unified feed merges native (approved) reviews
with imported 3rd-party reviews (Google / Trustpilot). `{alias}` is the alias of
the Reviews page (or the biolink hosting the `reviews_wall` block).

| Method | Path                          | Auth | Description                                                                                       |
| ------ | ----------------------------- | ---- | ------------------------------------------------------------------------------------------------ |
| GET    | `/reviews/{alias}`            | opt  | Unified review feed + summary. Query: `source` (`native`/`external`/`both`), `sort` (`recent`/`rating`), `limit` (1–100). |
| GET    | `/reviews/{alias}/summary`    | opt  | Rating summary only (`average`, `total`, `native`, `external`, `breakdown`). Query: `source`.     |
| POST   | `/reviews/{alias}`            | —    | Submit a review (no login). Throttle: 10/min. Honeypot + SpamChecker applied server-side.         |

**`index` response** (`GET /reviews/{alias}`):

```json
{
  "data": {
    "reviews": [
      {
        "id": "n12", "source": "native", "source_label": "Sayzio",
        "author_name": "Jane", "author_avatar": null, "rating": 5,
        "body": "Loved it!", "reply": "Thank you!", "source_url": null,
        "pinned": true, "created_at": "2026-06-15T10:00:00+00:00",
        "media": [{ "type": "image", "url": "https://…", "meta": {} }],
        "answers": [{ "prompt": "Would you recommend us?", "answer": "Yes" }]
      }
    ],
    "summary": { "average": 4.8, "total": 24, "native": 20, "external": 4, "breakdown": { "5": 18, "4": 4, "3": 1, "2": 0, "1": 1 } }
  }
}
```

**`submit` body** (`POST /reviews/{alias}`, `multipart/form-data` for media):

| Field          | Type        | Notes                                                          |
| -------------- | ----------- | ------------------------------------------------------------- |
| `author_name`  | string?     | ≤120 chars.                                                   |
| `author_email` | email?      | Stored privately; only kept when the page collects email.    |
| `rating`       | int? (1–5)  | At least one of `rating`, `body`, or `answers` is required.   |
| `body`         | string?     | ≤5000 chars.                                                  |
| `answers[id]`  | string?     | Keyed by custom-question id; ≤2000 chars each.               |
| `media[]`      | file[]?     | ≤6 files, image/audio/video, ≤50 MB each.                   |
| `website`      | string      | **Honeypot** — must stay empty (bots fill it → flagged spam). |

Returns `201` with `{data: {status, pending, message}}`. `status` is `approved`,
`pending` (awaiting moderation), or `hidden` (caught by spam heuristics; the
visitor still gets a success message). `404` if the page isn't a Reviews page /
has no `reviews_wall` block; `403` (`submissions_closed`) when submissions are
off; `422` (`empty_review`) when nothing was provided.

---

## Reviews moderation (owner)

Bearer-token parity for the web `/user/.../reviews/*` moderation actions so a
creator can triage reviews from the mobile app. All endpoints are
**owner-scoped**: they only touch **native** reviews owned by the authenticated
user. Imported 3rd-party reviews (Google / Trustpilot) live in a separate table
and are read-only, so they are not listed or moderatable here. `{review}` is the
numeric review id.

| Method | Path                          | Auth | Description                                                                                       |
| ------ | ----------------------------- | ---- | ------------------------------------------------------------------------------------------------ |
| GET    | `/me/reviews`                 | yes  | The owner's reviews across all statuses. Query: `status` (`pending`/`approved`/`hidden`/`unverified`), `per_page` (1–100, default 30). |
| POST   | `/me/reviews/{review}/approve`| yes  | Publish a review (`status` → `approved`, clears the spam flag).                                    |
| POST   | `/me/reviews/{review}/hide`   | yes  | Hide a review (`status` → `hidden`).                                                               |
| POST   | `/me/reviews/{review}/pin`    | yes  | Toggle the pinned flag.                                                                            |
| POST   | `/me/reviews/{review}/reply`  | yes  | Set the public owner reply. Body: `reply` (string?, ≤2000). Empty/absent clears the reply.         |
| DELETE | `/me/reviews/{review}`        | yes  | Permanently delete a review.                                                                       |

**`mine` response** (`GET /me/reviews`):

```json
{
  "data": {
    "reviews": [
      {
        "id": "12", "status": "pending", "is_spam": false, "spam_reason": null,
        "pinned": false, "author_name": "Jane", "author_email": "jane@example.com",
        "author_avatar": null, "rating": 5, "body": "Loved it!", "reply": null,
        "replied_at": null, "verified": true, "created_at": "2026-06-15T10:00:00+00:00",
        "link": { "id": "7", "title": "My reviews", "alias": "my-reviews" },
        "media": [{ "type": "image", "url": "https://…", "meta": {} }],
        "answers": [{ "prompt": "Would you recommend us?", "answer": "Yes" }]
      }
    ],
    "counts": { "pending": 3, "approved": 20, "hidden": 1, "unverified": 0 },
    "meta": { "total": 24, "per_page": 30, "current_page": 1, "last_page": 1 }
  }
}
```

The single-review moderation endpoints (`approve` / `hide` / `pin` / `reply`)
return `200` with `{data: <review>}` using the same owner-review shape as the
`reviews[]` items above. `DELETE` returns `200` with `{data: {id, deleted: true}}`.
`404` (`not_found`) when the review doesn't exist; `403` (`forbidden`) when it
belongs to another creator.

---

## Feed

| Method | Path                          | Auth | Notes                                                                            |
| ------ | ----------------------------- | ---- | -------------------------------------------------------------------------------- |
| GET    | `/feed`                       | opt  | Global feed. Anon viewers see only `public`. Authed viewers also see `registered`, `followers` (only creators they follow), `subscribers` (only creators they subscribe to). |
| GET    | `/creators/{handle}/feed`     | opt  | Same filtering, scoped to one creator.                                           |

## Follows

| Method | Path                       | Auth | Description                       |
| ------ | -------------------------- | ---- | --------------------------------- |
| POST   | `/follows/{userId}`        | yes  | Follow a creator.                 |
| DELETE | `/follows/{userId}`        | yes  | Unfollow.                         |
| GET    | `/follows/following`       | yes  | Paginated creators you follow.    |
| GET    | `/follows/followers`       | yes  | Paginated users following you.    |

## Subscribers

| Method | Path                      | Auth | Description                                                  |
| ------ | ------------------------- | ---- | ------------------------------------------------------------ |
| GET    | `/subscribers`            | yes  | List of YOUR subscribers. Filters: `status`, `q`, `per_page`. |
| DELETE | `/subscribers/{id}`       | yes  | Mark a subscriber as unsubscribed (soft).                    |

## Discovery (public)

| Method | Path                                  | Auth | Description                              |
| ------ | ------------------------------------- | ---- | ---------------------------------------- |
| GET    | `/discovery/creators`                 | opt  | Paginated discoverable creators. `q?`.   |
| GET    | `/discovery/creators/{handle}`        | opt  | Public profile by handle.                |

## Creator profile (public)

JSON mirror of the `/@handle` web surface so the app can render the same page.

| Method | Path                                                  | Auth | Description                                       |
| ------ | ---------------------------------------------------- | ---- | ------------------------------------------------ |
| GET    | `/creator-profile/{handle}`                          | opt  | Profile header + tabs.                           |
| GET    | `/creator-profile/{handle}/posts`                    | opt  | Paginated post feed.                             |
| GET    | `/creator-profile/{handle}/posts/{post}/comments`    | opt  | Comments on a post.                              |
| POST   | `/creator-profile/{handle}/posts/{post}/react`       | opt  | React to a post. Throttle: 120/min.             |
| POST   | `/creator-profile/{handle}/posts/{post}/comment`     | opt  | Comment on a post. Throttle: 60/min.            |

## Paid pages (public)

Standalone Paid Page (`links.type = paid_page`) resolved by link alias so the app can render the bold per-link themed design natively. The feed interactions reuse the handle-keyed react/comment endpoints under [Creator profile](#creator-profile-public) (the show response returns the creator handle).

| Method | Path                                | Auth | Description                                       |
| ------ | ----------------------------------- | ---- | ------------------------------------------------ |
| GET    | `/paid-page/{alias}`                | opt  | Paid-page header + theme + tabs by link alias.   |
| GET    | `/paid-page/{alias}/posts`          | opt  | Paginated paid-page post feed.                   |

## Creator monetization

Public per-creator endpoints; listing tiers is unauthenticated, while subscribing/unlocking/tipping require a bearer token. Owner dashboards require the creator's own token.

| Method | Path                                          | Auth | Description                                         |
| ------ | --------------------------------------------- | ---- | -------------------------------------------------- |
| GET    | `/creators/{handle}/tiers`                    | —    | List a creator's subscription tiers.               |
| POST   | `/creators/{handle}/subscribe`                | yes  | Subscribe to a tier. Throttle: 30/min.             |
| POST   | `/creators/{handle}/posts/{post}/unlock`      | yes  | Unlock a paid post. Throttle: 30/min.              |
| POST   | `/creators/{handle}/tip`                      | yes  | Tip a creator. Throttle: 30/min.                   |
| GET    | `/creators/{handle}/my-subscription`          | opt  | Your subscription status for a creator.            |
| POST   | `/creators/{handle}/my-subscription/cancel`   | opt  | Cancel your subscription.                          |
| GET    | `/me/creator/earnings`                        | opt  | Owner earnings summary.                            |
| GET    | `/me/creator/subscribers`                     | opt  | Owner subscriber list.                             |
| GET    | `/me/creator/payments`                        | opt  | Owner payment history.                             |
| GET    | `/me/creator/tiers`                           | opt  | Owner tier management list.                        |

## Product storefront

Native-checkout product storefront for biolink Product blocks. The cart lives in the app (no session on the Sanctum path) and is posted as line items. Buying/checkout require a bearer token; owner order management requires the creator's own token.

| Method | Path                                       | Auth | Description                                       |
| ------ | ------------------------------------------ | ---- | ------------------------------------------------ |
| POST   | `/store/{alias}/buy`                       | yes  | Single-product quick buy. Throttle: 30/min.      |
| POST   | `/store/{alias}/checkout`                  | yes  | Checkout a cart of line items. Throttle: 30/min. |
| GET    | `/store/orders/{order}`                    | opt  | Order status/receipt by id.                      |
| GET    | `/me/creator/orders`                       | yes  | Owner: list incoming product orders.             |
| POST   | `/me/creator/orders/{order}/fulfill`       | yes  | Owner: mark an order fulfilled.                  |

## Posts (creator feed)

| Method | Path                   | Auth | Description               |
| ------ | ---------------------- | ---- | ------------------------- |
| GET    | `/posts`               | yes  | List your posts.          |
| POST   | `/posts`               | yes  | Create a post.            |
| PATCH  | `/posts/{id}`          | yes  | Update a post.            |
| DELETE | `/posts/{id}`          | yes  | Delete a post.            |
| POST   | `/posts/{id}/pin`      | yes  | Pin a post.               |
| POST   | `/posts/{id}/unpin`    | yes  | Unpin a post.             |

## Paid DMs

Mobile-facing wrappers around the same controller the web modal uses; Sanctum Bearer (CSRF-exempt by design). The web surface keeps using the cookie-authed `/viewer/dm/*` routes.

| Method | Path                                          | Auth | Description                                   |
| ------ | --------------------------------------------- | ---- | -------------------------------------------- |
| GET    | `/dm/profile/{handle}/access`                 | yes  | Whether the viewer can DM this creator.      |
| GET    | `/dm/profile/{handle}/thread`                 | yes  | The DM thread with a creator.                |
| POST   | `/dm/profile/{handle}/send`                   | yes  | Send a DM. Throttle: 30/min.                 |
| POST   | `/dm/attachments/{attachment}/unlock`         | yes  | Unlock a paid attachment. Throttle: 30/min.  |
| POST   | `/dm/threads/{conversation}/tip`              | yes  | Tip inside a thread. Throttle: 20/min.       |

---

## QR Studio

QR codes mirror the web builder pipeline exactly: the same design sanitizer
(`QrCodeDesignSanitizer`) and type registry (`QrCodeTypeRegistry`) validate and
normalise every payload, so the API and UI never diverge. Every QR object
includes an `encoded` field — the exact string a scanner will read (the attached
link's short URL when `link_id` is set, otherwise the registry-built payload
string).

| Method      | Path                  | Auth | Description                                                                                  |
| ----------- | --------------------- | ---- | -------------------------------------------------------------------------------------------- |
| GET         | `/qr-codes`           | yes  | List your QR codes (newest first).                                                            |
| GET         | `/qr-codes/catalog`   | yes  | Shared design catalog: `dots`, `outer_eyes`, `inner_eyes`, `frames`, `fonts`, `types`, `presets` (30+ templates), `default_design`. |
| POST        | `/qr-codes`           | yes  | Create. Body: `name`, `type`, `payload?`, `design?`, `link_id?`, `project_id?`.              |
| POST        | `/qr-codes/bulk`      | yes  | Bulk create (max 500). Body: `{items: [...]}`. Each item validated independently; the whole batch is rejected (422 with per-index `details`) if any item is invalid. |
| GET         | `/qr-codes/{id}`      | yes  | Show single QR you own.                                                                       |
| PUT / PATCH | `/qr-codes/{id}`      | yes  | Partial update (name, type, payload, design, link_id, project_id).                            |
| DELETE      | `/qr-codes/{id}`      | yes  | Delete.                                                                                       |

`type` is one of the registry types: `text`, `url`, `phone`, `sms`, `email`,
`whatsapp`, `facetime`, `location`, `wifi`, `event`, `vcard`, `crypto`,
`paypal`, `upi`, `epc`, `pix`. Type-specific `payload` rules are enforced unless
the QR is link-backed (`link_id` set), in which case the QR encodes the link.

`design` accepts the full builder vocabulary, including per-corner eyes:
`eyes_per_corner` (bool) plus `eye_corners` (array of 3 — TL/TR/BL — each with
`outer_shape`, `outer_color`, `inner_shape`, `inner_color`). Unknown keys are
dropped by the sanitizer.

**Scan analytics:** attach a trackable link to a QR (`link_id`). Scans then flow
through the standard link-click pipeline (geo, device, browser, OS, heatmap).

```bash
# Create a styled QR from a preset template
PRESET=$(curl -s $BASE/qr-codes/catalog -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  | jq '.data.presets[0].design')
curl -X POST $BASE/qr-codes -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d "{\"name\":\"Launch\",\"type\":\"url\",\"payload\":{\"url\":\"https://1inme.com\"},\"design\":$PRESET}"
```

## Forms

Mobile can list + create-on-the-spot from the block editor; richer editing lives on web.

| Method | Path                                 | Auth | Description                          |
| ------ | ------------------------------------ | ---- | ----------------------------------- |
| GET    | `/forms`                             | yes  | List forms.                         |
| POST   | `/forms`                             | yes  | Create a form.                      |
| GET    | `/forms/{id}`                        | yes  | Show a form.                        |
| GET    | `/forms/{id}/submissions`            | yes  | List submissions.                   |
| GET    | `/forms/{id}/submissions.csv`        | yes  | Export submissions as CSV.          |

```bash
curl $BASE/forms -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

Forms can charge on submission — a **fixed** price and/or **per-field** pricing
(`mode = per_field`), plus a dedicated **Pricing/Package** field. Totals and line
items are computed and charged **server-side** in minor units, so a client must
not trust a client-supplied total.

## Contacts

| Method | Path                          | Auth | Description                                              |
| ------ | ----------------------------- | ---- | ------------------------------------------------------- |
| GET    | `/contacts`                   | yes  | Paginated address book.                                 |
| POST   | `/contacts`                   | yes  | Create a contact. Throttle: 120/min.                    |
| POST   | `/contacts/validate`          | yes  | Validate a candidate before saving. Throttle: 120/min. |
| POST   | `/contacts/bulk`              | yes  | Bulk import contacts.                                   |
| GET    | `/contacts/{id}`              | yes  | Show a contact.                                         |
| PATCH  | `/contacts/{id}`              | yes  | Update a contact.                                       |
| POST   | `/contacts/{id}/manual-profile` | yes | Attach/override a manual biolink profile on a contact. Body: `{ channels:[{type,label,value}], socials:[…], location:{…} }`. Returns `{ manual_profile }`. |
| POST   | `/contacts/{id}/merge`        | yes  | Merge into another contact. Throttle: 60/min.          |
| DELETE | `/contacts/{id}`              | yes  | Delete a contact.                                       |

### Google Contacts sync

Mobile parity for the web two-way Google Contacts sync (People API). Connect /
disconnect happens via the hosted OAuth flow on web; the mobile API exposes
status plus a manual sync trigger.

| Method | Path                          | Auth | Description                                                                |
| ------ | ----------------------------- | ---- | ------------------------------------------------------------------------- |
| GET    | `/contacts/google/status`     | yes  | Connection status: `{ connected, email, last_sync_at, is_syncing, … }`.   |
| POST   | `/contacts/google/sync`       | yes  | Trigger an incremental sync now. Throttle: 12/min.                        |
| PATCH  | `/contacts/google`            | yes  | Update sync preferences (e.g. direction).                                 |
| DELETE | `/contacts/google`            | yes  | Disconnect the linked Google account.                                     |

### Bulk import (preview workflow)

A staged CSV/vCard import: **parse** the upload to a token-keyed preview, fix or
skip individual rows, then **confirm** to enqueue the job and poll its status.
The preview is what makes mobile parity with the web import wizard.

| Method | Path                                            | Auth | Description                                                              |
| ------ | ----------------------------------------------- | ---- | ---------------------------------------------------------------------- |
| POST   | `/contacts/import/parse`                        | yes  | Upload a file → parsed, token-keyed preview. Throttle: 30/min.         |
| GET    | `/contacts/import/preview/{token}`              | yes  | Fetch the parsed preview (rows + validation flags).                    |
| PATCH  | `/contacts/import/preview/{token}/rows/{index}` | yes  | Edit one previewed row before import.                                  |
| DELETE | `/contacts/import/preview/{token}/rows/{index}` | yes  | Skip one previewed row.                                                |
| DELETE | `/contacts/import/preview/{token}`              | yes  | Cancel the staged import.                                              |
| POST   | `/contacts/import/preview/{token}/confirm`      | yes  | Commit the preview and enqueue the import job.                         |
| GET    | `/contacts/import/status/{id}`                  | yes  | Poll job progress: `{ status, total_rows, processed_rows, created_count, updated_count, error_count, finished_at }`. |

### Dialer

The dialer is an everyday tool with full web parity: caller-ID lookup, speed-dial
favorites, smart grouped recents + a frequently-contacted strip, per-user spam/block
flags, a call-log mini-CRM (outcome/note/tag), and call-back reminders. All responses
use the unified `{data}` / `{error}` envelope.

| Method | Path                       | Auth | Description                                                                    |
| ------ | -------------------------- | ---- | ----------------------------------------------------------------------------- |
| POST   | `/dialer/lookup`           | yes  | Resolve an E.164 number → caller-ID. Returns `is_spam`/`is_blocked`/`is_favorite`, matched `contact`, `biolink`, and recent `activity`. Throttle: 60/min. |
| GET    | `/dialer/history`          | yes  | `{ recents, frequent }` — grouped recents (by number, with call counts, last-call time, outcome/note/tag, spam/block) plus the frequently-contacted strip. |
| GET    | `/dialer/favorites`        | yes  | Ordered speed-dial favorites (`{ items }`).                                    |
| POST   | `/dialer/favorites`        | yes  | Add a favorite by `contact_id` or `number` (+ optional `label`). Returns `{ favorite, already? }`. |
| POST   | `/dialer/favorites/reorder`| yes  | Persist favorite order from an `order` array of favorite ids.                  |
| DELETE | `/dialer/favorites/{id}`   | yes  | Remove a favorite.                                                             |
| POST   | `/dialer/flag`             | yes  | Set per-user `is_spam` / `is_blocked` for an E.164 `number`. Returns the merged flag state. |
| POST   | `/dialer/log`              | yes  | Log a call against a `number` (+ optional `contact_id`, `outcome`, `note`, `tag`). Returns `{ log }`. |
| POST   | `/dialer/callback`         | yes  | Set a call-back reminder (`number`, future `callback_at`, optional `note`). Delivered in-app + scheduled. Returns `{ callback }`. |
| DELETE | `/dialer/callback/{id}`    | yes  | Clear a pending call-back reminder.                                            |

`outcome` is one of `called`, `messaged`, `no_answer`, `voicemail`, `busy`,
`wrong_number`, `completed`. Call-back reminders are delivered via the
`dialer.callback_due` notification, swept every five minutes by the
`dialer:send-callback-reminders` scheduled command.

```bash
# Resolve a number to its caller-ID profile
curl -X POST $BASE/dialer/lookup -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"number_e164":"+14155551234"}'

# Log a call outcome with a note + tag
curl -X POST $BASE/dialer/log -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"number":"+14155551234","outcome":"completed","note":"Sent quote","tag":"lead"}'

# Save a contact from the mobile dialer
curl -X POST $BASE/contacts -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"name":"Sam","phone":"+14155551234"}'
```

## Resume / Portfolio

Single resume per user — resolved from the bearer token, so the URL never carries a resume id.

| Method | Path                                       | Auth | Description                                |
| ------ | ------------------------------------------ | ---- | ----------------------------------------- |
| GET    | `/resume`                                  | yes  | The current user's resume.                |
| PUT    | `/resume/header`                           | yes  | Update header fields.                     |
| POST   | `/resume/header/photo`                     | yes  | Upload a header photo.                    |
| DELETE | `/resume/header/photo`                     | yes  | Remove the header photo.                  |
| PUT    | `/resume/summary`                          | yes  | Update the summary.                       |
| PUT    | `/resume/template`                         | yes  | Switch template.                          |
| PUT    | `/resume/color-theme`                      | yes  | Switch color theme.                       |
| POST   | `/resume/items`                            | yes  | Add a resume item.                        |
| PUT    | `/resume/items/{item}`                     | yes  | Update an item.                           |
| DELETE | `/resume/items/{item}`                     | yes  | Delete an item.                           |
| POST   | `/resume/items/reorder`                    | yes  | Reorder items.                            |
| PUT    | `/resume/publishing`                       | yes  | Publishing/visibility settings.           |
| PUT    | `/resume/public-pdf`                       | yes  | Public-PDF settings.                      |
| POST   | `/resume/share/revoke`                     | yes  | Revoke a share link.                      |
| GET    | `/resume/views`                            | yes  | View analytics.                           |
| GET    | `/resume/versions`                         | yes  | List versions.                            |
| POST   | `/resume/versions`                         | yes  | Create a version.                         |
| PUT    | `/resume/versions/{version}`               | yes  | Rename a version.                         |
| DELETE | `/resume/versions/{version}`               | yes  | Delete a version.                         |
| POST   | `/resume/versions/{version}/duplicate`     | yes  | Duplicate a version.                      |
| POST   | `/resume/versions/{version}/default`       | yes  | Set the default version.                  |

## AI Brand Kit

Mobile parity for the web `/user/brand-kits` flow. AI crafts a cohesive brand identity (palette, fonts, voice, taglines, bio and a recommended block theme) from a prompt and optional website/logo URL, then applies it to a biolink or QR code. Kits are owned by the bearer-token user; generation is gated by the per-plan `max_brand_kits` quantity cap and charged in AI credits, with an automatic refund if generation fails (handled in `AiBrandKitService`).

| Method | Path                                          | Auth | Description                                                                 |
| ------ | --------------------------------------------- | ---- | -------------------------------------------------------------------------- |
| GET    | `/brand-kits`                                 | yes  | List kits + apply targets (biolinks, QR codes), plan cap/gating, credit balance and allowed block themes. |
| POST   | `/brand-kits/estimate`                        | yes  | Upfront, worst-case credit cost. Body: `prompt?`, `website_url?`, `logo_url?`. Throttle 30/min. |
| POST   | `/brand-kits/generate`                        | yes  | Run generation and save a kit. Same body as estimate. Throttle 10/min. `402 insufficient_credits` when the wallet can't cover the charge. |
| DELETE | `/brand-kits/{brandKit}`                      | yes  | Delete a kit.                                                               |
| POST   | `/brand-kits/{brandKit}/apply/biolink/{link}` | yes  | Apply a kit (palette, fonts, block theme) to one of the user's biolinks.    |
| POST   | `/brand-kits/{brandKit}/apply/qr/{qrCode}`    | yes  | Apply a kit's palette to one of the user's QR codes.                        |

Plan-gated rejections use the standard `{error:{code:"plan_limit", details:{recommended_plan, recommended_plan_name, feature}}}` envelope; `503 ai_unavailable` when the AI engine is disabled.

## Projects

| Method | Path               | Auth | Description           |
| ------ | ------------------ | ---- | --------------------- |
| GET    | `/projects`        | yes  | List projects.        |
| POST   | `/projects`        | yes  | Create a project.     |
| PATCH  | `/projects/{id}`   | yes  | Update a project.     |
| DELETE | `/projects/{id}`   | yes  | Delete a project.     |

---

## Wallet & coins

| Method | Path                    | Auth | Description                            |
| ------ | ----------------------- | ---- | ------------------------------------- |
| GET    | `/wallet`               | yes  | Coin balance.                         |
| GET    | `/wallet/transactions`  | yes  | Wallet ledger.                        |
| GET    | `/wallet/packages`      | yes  | Coin packages for purchase.           |
| POST   | `/wallet/purchase`      | yes  | Start a coin-package purchase.        |

```bash
curl $BASE/wallet -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

## AI (credits, Knowledge Bases, voice, Account Assistant, Chat Widgets)

See [Coin & AI-credit audit](./billing-ai-credit-audit.md) for who pays for each AI feature.

### AI credits

| Method | Path                          | Auth | Description                  |
| ------ | ----------------------------- | ---- | --------------------------- |
| GET    | `/ai/credits`                 | yes  | AI-credit balance.          |
| GET    | `/ai/credits/transactions`    | yes  | AI-credit ledger.           |
| GET    | `/ai/credits/packs`           | yes  | Credit packs for purchase.  |
| POST   | `/ai/credits/purchase`        | yes  | Buy a credit pack.          |

### Knowledge Bases & feature defaults

| Method | Path                          | Auth | Description                                         |
| ------ | ----------------------------- | ---- | ------------------------------------------------- |
| GET    | `/ai/minds`                   | yes  | Available Knowledge Bases for the picker.         |
| GET    | `/ai/{feature}/defaults`      | yes  | Default Knowledge Base for `persona` or `coach`.  |
| PUT    | `/ai/{feature}/defaults`      | yes  | Save default Knowledge Base for `persona` or `coach`. |
| DELETE | `/ai/{feature}/defaults`      | yes  | Clear default Knowledge Base for `persona` or `coach`. |

### Voice assistant

Same orchestrator as the web — STT/LLM/TTS each charge their own ledger row.

| Method | Path                       | Auth | Description                                          |
| ------ | -------------------------- | ---- | --------------------------------------------------- |
| GET    | `/ai/voice/capabilities`   | yes  | Voice-assistant capabilities.                       |
| POST   | `/ai/voice/turn`           | yes  | One conversational turn (STT + reply + TTS). Throttle: 30/min. |
| POST   | `/ai/voice/wake-check`     | yes  | Wake-word check on a short clip (not billed). Throttle: 60/min. |

### Account Assistant

Data-aware self-support chatbot.

| Method | Path                                          | Auth | Description                          |
| ------ | --------------------------------------------- | ---- | ----------------------------------- |
| GET    | `/ai/ask-coach/threads`                       | yes  | List Account Assistant threads.     |
| POST   | `/ai/ask-coach/threads`                       | yes  | Create a thread.                    |
| GET    | `/ai/ask-coach/threads/{thread}`              | yes  | Messages in a thread.               |
| POST   | `/ai/ask-coach/threads/{thread}/send`         | yes  | Send a message. Throttle: 30/min.   |
| DELETE | `/ai/ask-coach/threads/{thread}`              | yes  | Delete a thread.                    |
| POST   | `/ai/ask-coach/messages/{message}/feedback`   | yes  | Thumbs up/down a reply. Throttle: 30/min. |

### Chat Widgets

Biolink Chat Widgets — list/AI Agent lookup + create-on-the-spot for the block editor's "AI" picker (richer editing lives on web).

| Method | Path                          | Auth | Description                          |
| ------ | ----------------------------- | ---- | ----------------------------------- |
| GET    | `/ai-companions`              | yes  | List Chat Widgets.                  |
| GET    | `/ai-companions/personas`     | yes  | List AI Agents.                     |
| POST   | `/ai-companions/personas`     | yes  | Create an AI Agent.                 |
| POST   | `/ai-companions`              | yes  | Create a Chat Widget.               |

---

## Creator payouts

Mobile parity for the "Earnings & Payouts" dashboard. The hosted-onboarding URL is returned to the app to open in an in-app browser; webhooks + return parsing remain server-side. Providers: Stripe Connect, PayPal, Razorpay Route, CCBill, Segpay.

| Method | Path                              | Auth | Description                                    |
| ------ | --------------------------------- | ---- | --------------------------------------------- |
| GET    | `/payouts`                        | yes  | Payout connections + status.                  |
| POST   | `/payouts/{provider}/connect`     | yes  | Begin hosted onboarding; returns a URL.       |
| POST   | `/payouts/{connection}/sync`      | yes  | Re-sync a connection's status.                |
| POST   | `/payouts/{connection}/default`   | yes  | Set the default payout connection.            |
| DELETE | `/payouts/{connection}`           | yes  | Disconnect a payout provider.                 |

```bash
# Connect a payout provider (returns a hosted-onboarding URL)
curl -X POST $BASE/payouts/stripe/connect \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

## Adult content

18+ creator toggle (requires a three-checkbox consent dialog client-side; the server stamps consent timestamps for audit).

| Method | Path                | Auth | Description                                      |
| ------ | ------------------- | ---- | ----------------------------------------------- |
| GET    | `/adult-content`    | yes  | Current 18+ status + consent timestamps.        |
| POST   | `/adult-content`    | yes  | Enable/disable 18+ (with consent payload).      |

```bash
curl -X POST $BASE/adult-content \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"enabled":true,"consent_age":true,"consent_legal":true,"consent_processor":true}'
```

## Billing

| Method | Path                              | Auth | Description                          |
| ------ | --------------------------------- | ---- | ----------------------------------- |
| GET    | `/billing/subscription`           | yes  | Current subscription.               |
| GET    | `/billing/invoices`               | yes  | List invoices you've issued.        |
| GET    | `/billing/invoices/{id}`          | yes  | Show an invoice.                    |
| POST   | `/billing/invoices`               | yes  | Create an invoice.                  |
| PATCH  | `/billing/invoices/{id}`          | yes  | Update an invoice.                  |
| DELETE | `/billing/invoices/{id}`          | yes  | Delete an invoice.                  |
| POST   | `/billing/invoices/{id}/send`     | yes  | Email an invoice. Throttle: 30/min. |

## Plans & RevenueCat

| Method | Path                              | Auth | Description                                              |
| ------ | --------------------------------- | ---- | ------------------------------------------------------- |
| GET    | `/plans`                          | —    | Public plan catalog. Excludes internal (admin-only) plans. |
| GET    | `/billing/plans`                  | yes  | Plan + addon catalog priced for the signed-in user. Excludes internal plans. |
| POST   | `/billing/currency`               | yes  | Set preferred currency. Throttle: 60/min.               |
| POST   | `/billing/revenuecat/activate`    | yes  | RevenueCat receipt-verification hook (post-purchase/restore). Throttle: 30/min. |

---

## Custom domains

| Method | Path                          | Auth | Description                  |
| ------ | ----------------------------- | ---- | --------------------------- |
| GET    | `/domains`                    | yes  | List custom domains.        |
| GET    | `/domains/available`          | yes  | Domains the user can attach a link to: their own verified domains **plus** any admin-provisioned global domains. Returns `{ items:[{ id, domain, is_verified, is_global, … }], primary_domain_id, default_host, can_manage }`. |
| POST   | `/domains`                    | yes  | Add a domain.               |
| POST   | `/domains/{id}/primary`       | yes  | Make a domain primary.      |
| DELETE | `/domains/{id}`               | yes  | Remove a domain.            |

## Splash pages

| Method | Path                          | Auth | Description           |
| ------ | ----------------------------- | ---- | --------------------- |
| GET    | `/splash-pages`               | yes  | List splash pages.    |
| POST   | `/splash-pages`               | yes  | Create a splash page. |
| GET    | `/splash-pages/{id}`          | yes  | Show a splash page.   |
| PATCH  | `/splash-pages/{id}`          | yes  | Update a splash page. |
| DELETE | `/splash-pages/{id}`          | yes  | Delete a splash page. |

## Restaurant menu

Public ordering surface plus the owner-facing builder and orders dashboard.
Builder endpoints mirror the web `RestaurantMenuController` editor so a
restaurant-menu link can be created and fully populated from mobile. The
`{link}` segment is the numeric link id.

| Method | Path                                                      | Auth     | Description                                            |
| ------ | -------------------------------------------------------- | -------- | ----------------------------------------------------- |
| GET    | `/restaurant/{alias}`                                    | optional | Public menu by alias (`?t={code}` for a table). Includes `menu.tax`. |
| POST   | `/restaurant/{alias}/quote`                             | optional | Estimated-bill quote for a cart (coupon + GST). Throttle: 120/min. |
| POST   | `/restaurant/{alias}/order`                              | optional | Place a guest order (order mode), optional `coupon_code`. Throttle: 30/min. |
| GET    | `/restaurant/orders/{token}/status`                      | no       | Poll a guest order by its public token.               |
| GET    | `/restaurant/links/{link}/menu`                          | yes      | Owner: full menu (settings, categories+items, tables).|
| POST   | `/restaurant/links/{link}/menu/settings`                | yes      | Owner: update mode/currency/accent color.             |
| POST   | `/restaurant/links/{link}/menu/photo`                   | yes      | Owner: upload an item photo (multipart `photo`).      |
| POST   | `/restaurant/links/{link}/menu/categories`              | yes      | Owner: create a category.                             |
| PUT    | `/restaurant/links/{link}/menu/categories/{category}`   | yes      | Owner: update a category.                             |
| DELETE | `/restaurant/links/{link}/menu/categories/{category}`   | yes      | Owner: delete a category (and its items).             |
| POST   | `/restaurant/links/{link}/menu/items`                   | yes      | Owner: create an item.                                |
| PUT    | `/restaurant/links/{link}/menu/items/{item}`            | yes      | Owner: update an item.                                |
| DELETE | `/restaurant/links/{link}/menu/items/{item}`            | yes      | Owner: delete an item.                                |
| POST   | `/restaurant/links/{link}/menu/tables`                  | yes      | Owner: create a table (auto QR code/url).             |
| DELETE | `/restaurant/links/{link}/menu/tables/{table}`          | yes      | Owner: delete a table.                                |
| GET    | `/restaurant/links/{link}/orders`                       | yes      | Owner: recent orders + open count.                    |
| GET    | `/restaurant/links/{link}/orders/poll`                  | yes      | Owner: incremental poll (`?since=` cursor).           |
| POST   | `/restaurant/links/{link}/orders/{order}/status`        | yes      | Owner: advance an order's status.                     |

**Estimated bill (coupons + GST/tax).** `menu.tax` exposes `{enabled, rate,
inclusive, label}`. `POST /restaurant/{alias}/quote` accepts `{items:[{item_id,
quantity}], coupon_code?}` and returns a `bill` object: `subtotal`,
`coupon_code`, `coupon_applied`, `coupon_error`, `discount_amount`,
`tax_enabled`, `tax_inclusive`, `tax_rate`, `tax_label`, `tax_amount`, `total`,
`currency`, `is_estimate`. Guest and owner order payloads carry the snapshotted
`coupon_code`, `discount_amount`, `tax_rate`, `tax_inclusive`, `tax_amount`,
`total`, and `is_estimate`. A single coupon and one menu-level tax rate apply;
the figures are an **estimate, not the actual bill** — no payment is collected.

## Workspaces

| Method | Path                          | Auth | Description                |
| ------ | ----------------------------- | ---- | ------------------------- |
| GET    | `/workspaces`                 | yes  | List your workspaces.     |
| GET    | `/workspaces/{id}/members`    | yes  | List workspace members.   |

## Team & staff

Operates on the active workspace.

| Method | Path                              | Auth | Description                       |
| ------ | --------------------------------- | ---- | -------------------------------- |
| GET    | `/team`                           | yes  | List members + invites.          |
| POST   | `/team/invite`                    | yes  | Invite a teammate. Throttle: 30/min. |
| DELETE | `/team/invites/{invite}`          | yes  | Revoke an invite.                |
| DELETE | `/team/members/{member}`          | yes  | Remove a member.                 |

## Client portals

| Method | Path                                  | Auth | Description                                |
| ------ | ------------------------------------- | ---- | ----------------------------------------- |
| GET    | `/client-portals`                     | yes  | List portals.                             |
| POST   | `/client-portals`                     | yes  | Create a portal.                          |
| GET    | `/client-portals/{id}`                | yes  | Show a portal.                            |
| PATCH  | `/client-portals/{id}`                | yes  | Update a portal.                          |
| DELETE | `/client-portals/{id}`                | yes  | Delete a portal.                          |
| POST   | `/client-portals/{id}/links`          | yes  | Send a link to a portal client. Throttle: 30/min. |

## Vault

Read-only on mobile; secret reveal stays on web.

| Method | Path                    | Auth | Description                |
| ------ | ----------------------- | ---- | ------------------------- |
| GET    | `/vault/clients`        | yes  | List vault clients.       |
| GET    | `/vault/credentials`    | yes  | List vault credentials.   |

## Inbox (biolink DMs)

DM threads on owned biolinks.

| Method | Path                                      | Auth | Description                          |
| ------ | ----------------------------------------- | ---- | ----------------------------------- |
| GET    | `/inbox/threads`                          | yes  | Thread list.                        |
| GET    | `/inbox/conversations`                    | yes  | Conversation list.                  |
| GET    | `/inbox/conversations/{id}`               | yes  | Show a conversation.                |
| POST   | `/inbox/conversations/{id}/reply`         | yes  | Reply to a conversation.            |
| PATCH  | `/inbox/conversations/{id}/status`        | yes  | Set conversation status.            |
| POST   | `/inbox/conversations/{id}/assign`        | yes  | Assign to a teammate.               |
| DELETE | `/inbox/conversations/{id}`               | yes  | Delete a conversation.              |
| GET    | `/inbox/teammates`                        | yes  | Assignable teammates.               |

### Spam settings

Mobile parity for the web Spam Settings page. The unified inbox runs every
inbound message through `SpamChecker` at intake; matches are flagged
(`is_spam`) with a human-readable `spam_reason` (e.g. the matched keyword).
State lives in `user.settings['spam']`: the user's own `blocked_keywords`, a set
of built-in default keywords (individually disable-able with an audit trail),
and `trusted_emails` / `trusted_phones` allowlists.

| Method | Path                                       | Auth | Description                                                                |
| ------ | ------------------------------------------ | ---- | ------------------------------------------------------------------------ |
| GET    | `/inbox/spam-settings`                     | yes  | Current config: `{ spam, defaults, disabled_defaults }`.                  |
| PUT    | `/inbox/spam-settings`                     | yes  | Replace `blocked_keywords`, `disabled_default_keywords`, `trusted_emails`, `trusted_phones`. |
| POST   | `/inbox/spam-settings/disable-keyword`     | yes  | Disable one built-in default keyword. Body: `{ keyword }`.                |
| POST   | `/inbox/spam-settings/enable-keyword`      | yes  | Re-enable a previously disabled default keyword. Body: `{ keyword }`.     |
| POST   | `/inbox/spam-settings/import-trusted`      | yes  | Bulk-import trusted senders from a `csv` upload. Returns `{ stats, spam }`. |

### Forwarding

Auto-forward inbound inbox items to an email address or webhook, optionally
filtered by source. Each destination keeps a delivery log; deliveries can be
test-fired or retried.

| Method | Path                                       | Auth | Description                                                                |
| ------ | ------------------------------------------ | ---- | ------------------------------------------------------------------------ |
| GET    | `/inbox/forwards`                          | yes  | List destinations + recent deliveries (`{ destinations, deliveries, source_labels }`). |
| POST   | `/inbox/forwards`                          | yes  | Create a destination. Body: `{ label, type: "email"\|"webhook", target, method?, sources?, … }`. |
| PUT    | `/inbox/forwards/{id}`                     | yes  | Update a destination.                                                     |
| POST   | `/inbox/forwards/{id}/toggle`              | yes  | Enable/disable a destination.                                            |
| DELETE | `/inbox/forwards/{id}`                     | yes  | Delete a destination.                                                     |
| POST   | `/inbox/forwards/{id}/test`               | yes  | Send a test forward. Returns `{ sent, message, delivery }`.              |
| POST   | `/inbox/forward-deliveries/{id}/retry`     | yes  | Retry a single failed delivery.                                          |

---

## Social connections & proofs

| Method | Path                                      | Auth | Description                          |
| ------ | ----------------------------------------- | ---- | ----------------------------------- |
| GET    | `/social/connections`                     | yes  | Connected social accounts.          |
| POST   | `/social/connections`                     | yes  | Connect a social account.           |
| POST   | `/social/connections/{id}/refresh`        | yes  | Refresh a connection.               |
| DELETE | `/social/connections/{id}`                | yes  | Disconnect.                         |
| GET    | `/social/proofs`                          | yes  | Social-proof widgets.               |
| POST   | `/social/proofs`                          | yes  | Create a social-proof widget.       |
| PATCH  | `/social/proofs/{id}`                     | yes  | Update a widget.                    |
| DELETE | `/social/proofs/{id}`                     | yes  | Delete a widget.                    |

## Integrations

| Method | Path                    | Auth | Description                |
| ------ | ----------------------- | ---- | ------------------------- |
| GET    | `/integrations`         | yes  | List connected integrations. |
| DELETE | `/integrations/{id}`    | yes  | Disconnect an integration.   |

## Calendar

| Method | Path                              | Auth | Description                          |
| ------ | --------------------------------- | ---- | ----------------------------------- |
| GET    | `/calendar/accounts`              | yes  | Connected calendar accounts.        |
| DELETE | `/calendar/accounts/{id}`         | yes  | Disconnect a calendar account.      |
| GET    | `/links/{id}/rsvps`               | yes  | RSVP responses for an event link.   |

Two-way calendar sync (mirroring a published `calendar` link's events to the
owner's connected calendar) requires the `calendar_sync` plan feature; event
links and visitor RSVP work without it.

## Verification

| Method | Path                | Auth | Description                       |
| ------ | ------------------- | ---- | -------------------------------- |
| GET    | `/verifications`    | yes  | Creator-badge verification status. |
| POST   | `/verifications`    | yes  | Submit a verification request.    |

---

## Admin (mobile back-office)

Mobile parity for the back-office role / admin-access tooling and impersonation. The operator's authority comes from their email-linked back-office Admin record, so a mobile user with an active admin account reaches these with the same Sanctum token (no re-login). Each action is gated behind the same admin-guard permission the web routes use.

| Method | Path                                       | Auth | Description                                          |
| ------ | ------------------------------------------ | ---- | --------------------------------------------------- |
| GET    | `/admin/context`                           | yes  | Operator's admin context (linked Admin + permissions). |
| GET    | `/admin/users`                             | yes  | List users for role / admin-access management.      |
| GET    | `/admin/users/{user}/roles`                | yes  | Show a user's roles.                                |
| PUT    | `/admin/users/{user}/roles`                | yes  | Update a user's roles.                              |
| POST   | `/admin/users/{user}/admin-access`         | yes  | Grant back-office admin access to a user.           |
| DELETE | `/admin/users/{user}/admin-access`         | yes  | Revoke back-office admin access.                    |
| POST   | `/admin/users/{user}/impersonate`          | yes  | Impersonate a user. Throttle: 20/min.              |

### Banned names / reserved handles

Mobile parity for the admin banned-names manager. The list of reserved words
(plus the built-in defaults) is enforced by the `NotBannedName` rule on every
handle/alias submit and by the live availability checker, so a banned word can
never be claimed as a handle. `force_rename_on_login` flags an entry that forces
any existing holder to pick a new handle on their next login. `conflicts`
reports accounts/links currently using a reserved word. Gated behind the same
admin permission as the web manager.

| Method | Path                                        | Auth | Description                                                              |
| ------ | ------------------------------------------- | ---- | ---------------------------------------------------------------------- |
| GET    | `/admin/banned-names`                       | yes  | List entries (`{ items:[{ id, name, note, force_rename_on_login, conflicts }], can_manage }`). |
| POST   | `/admin/banned-names`                       | yes  | Add an entry. Body: `{ name, note?, force_rename_on_login? }`.          |
| POST   | `/admin/banned-names/bulk`                  | yes  | Bulk add from `names` text or an uploaded `file`. Returns `{ stats, imported, … }`. |
| POST   | `/admin/banned-names/restore-defaults`      | yes  | Restore the built-in default reserved words.                           |
| GET    | `/admin/banned-names/export`                | yes  | Export the list.                                                        |
| GET    | `/admin/banned-names/{id}/conflicts`        | yes  | Accounts/links currently using this reserved word.                     |
| POST   | `/admin/banned-names/{id}/force-rename`     | yes  | Toggle force-rename-on-login for an entry.                              |
| DELETE | `/admin/banned-names/{id}`                  | yes  | Delete an entry.                                                        |

### Plan editor

Mobile parity for the back-office plan management. Unlike the public `/plans` catalog, the admin listing includes the admin-only `is_internal` flag and is **not** filtered to public plans — internal (admin/staff-only) plans stay visible/manageable here. Create/update accept `is_internal` (set/clear). Duplicate deep-copies the plan (features + polymorphic price rows + addons) and forces the copy internal + inactive with a "(Copy)" name. Gated behind `plans.view` (list) / `plans.manage` (write).

| Method | Path                                       | Auth | Description                                          |
| ------ | ------------------------------------------ | ---- | --------------------------------------------------- |
| GET    | `/admin/plans`                             | yes  | List all plans (incl. `is_internal`). `plans.view`. |
| POST   | `/admin/plans`                             | yes  | Create a plan (accepts `is_internal`). `plans.manage`. |
| PUT    | `/admin/plans/{plan}`                      | yes  | Update a plan (set/clear `is_internal`). `plans.manage`. |
| POST   | `/admin/plans/{plan}/duplicate`            | yes  | Deep-copy a plan; copy is internal + inactive. `plans.manage`. |

## Admin mail / SMTP settings

Super-admin parity for the mail transport editor, gated behind `settings.manage`. Saving runs a live SMTP handshake; the test action sends a real email.

| Method | Path                              | Auth | Description                                      |
| ------ | --------------------------------- | ---- | ----------------------------------------------- |
| GET    | `/admin/mail-settings`            | yes  | Current mail config + status.                   |
| PUT    | `/admin/mail-settings`            | yes  | Update the SMTP transport.                      |
| POST   | `/admin/mail-settings/test`       | yes  | Send a test email. Throttle: 10/min.            |

---

## Browser extension surface

Endpoints the [browser extension](../../1inme-extension/README.md) relies on (also usable by any client). Backlink-radar properties + persistence, workspace tracking pixels, and per-workspace thank-you templates/queue.

| Method | Path                          | Auth | Description                                                  |
| ------ | ----------------------------- | ---- | ----------------------------------------------------------- |
| GET    | `/me/properties`              | yes  | The creator's "known properties" feed (short-link hosts, biolink username path, verified custom domains, hashed slug prefixes) for backlink matching. |
| GET    | `/backlinks`                  | yes  | Saved backlinks.                                            |
| GET    | `/backlinks/export.csv`       | yes  | Export backlinks as CSV.                                    |
| POST   | `/backlinks`                  | yes  | Save a discovered backlink. Throttle: 120/min.             |
| DELETE | `/backlinks/{id}`             | yes  | Delete a backlink.                                         |
| GET    | `/workspace/pixels`           | yes  | Workspace Meta/TikTok/Google Ads pixel IDs.               |
| PUT    | `/workspace/pixels`           | yes  | Update workspace pixel IDs.                                |
| GET    | `/me/thank-templates`         | yes  | Per-workspace thank-you templates (synced across browsers). |
| PUT    | `/me/thank-templates`         | yes  | Save thank-you templates (optimistic-concurrency; 409 on conflict). |
| GET    | `/me/pending-thanks`          | yes  | Queued thank-yous (the "Pending thanks" panel).            |
| PUT    | `/me/pending-thanks`          | yes  | Update the pending-thanks queue.                           |
| GET    | `/me/api-usage`               | yes  | Developer API-usage summary (mobile mirror of the web meter). |

## Pixel tracking

| Method | Path                          | Auth | Description                                                  |
| ------ | ----------------------------- | ---- | ----------------------------------------------------------- |
| POST   | `/links/{alias}/pixel-fire`   | —    | Auto-pixel interstitial fire beacon (anonymous visitors). Records one `link_pixel_fires` row. Throttle: 120/min. |

## Health

| Method | Path        | Auth | Description                                  |
| ------ | ----------- | ---- | -------------------------------------------- |
| GET    | `/health`   | —    | Liveness check, returns `{status, time}`.    |

---

## Error codes

| Status | code                  | When                                                    |
| ------ | --------------------- | ------------------------------------------------------- |
| 400    | (varies)              | Bad request.                                            |
| 401    | `unauthenticated`     | Missing/invalid bearer token on protected route.        |
| 401    | `auth_required`       | Biolink visibility = `registered/followers/subscribers` and viewer is anon. |
| 401    | `invalid_credentials` | Login failed.                                           |
| 402    | (varies)              | API-key overage can't be covered by the coin wallet (see [API usage metering](#api-usage-metering)). |
| 403    | `follow_required`     | Biolink visibility = `followers` and viewer is not following. |
| 403    | `subscribe_required`  | Biolink visibility = `subscribers` and viewer is not subscribed. |
| 403    | `forbidden`           | General authorization failure.                          |
| 403    | `registration_paused` | New-account creation is paused by an administrator (any register / OTP-register / social-new path). |
| 404    | `not_found`           | Unknown route or resource.                              |
| 405    | `method_not_allowed`  | Wrong HTTP method.                                      |
| 409    | (varies)              | Optimistic-concurrency conflict (e.g. thank-templates push). |
| 422    | `validation_failed`   | Body validation. `details` is `{field: [messages]}`.    |
| 429    | `rate_limited`        | Throttled. Per-route limits noted in the tables above.  |

## Pagination shape

```json
{
  "data": {
    "items": [ /* ... */ ],
    "meta":  { "current_page": 1, "per_page": 20, "total": 53, "last_page": 3 }
  }
}
```

## API usage metering

Developer API-key calls (tokens stamped `client_kind = 'api_key'`) are metered
monthly by the `MeterApiUsage` middleware against the plan's `api_calls_monthly`
allowance (`-1`/bypass = unlimited). Overage beyond the allowance is paid from
the coin wallet (1 coin buys `wallet.api_overage_calls_per_coin` calls) and
rejected with HTTP **402** when the wallet is disabled or out of coins. Per-(user,
month) state lives in `api_usage_counters`. Proactive warnings (email + in-app
via the `api.usage_warning` notification type) fire once per period at 80% of the
allowance, at 100% (now on overage), and when overage can no longer be covered.
Tokens minted for the first-party web/mobile apps are **not** stamped as
`api_key` and bypass this meter.
