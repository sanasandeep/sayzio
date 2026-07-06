---
name: Inbox link-reference tokens ({{link:ID}})
description: How attaching/rendering a user's own link in the AI inbox works across app/email/DM, and the Blade escaping gotcha it required
---

- A message body can embed `{{link:ID}}`; `LinkReferenceRenderer` (Common\Services) parses/renders it per channel: `renderApp()` (thread view), `renderEmail()` (table-based HTML for mail clients), `renderDm()` (viewer-DM widget), `renderPlain()` (AI/plaintext contexts). All four always return safe escaped+linkified output — never nullable — because a DM widget rendering `body_html || body` with a nullable renderer would let raw unescaped body through as an XSS fallback.
- Cards resolve strictly against `Link::where('user_id', $ownerUserId)`, so a tampered/foreign token silently drops (renders nothing) instead of leaking another user's link — verified: `renderApp('{{link:X}}', wrongOwnerId)` returns empty string.
- Cards always link through `Link::getShortUrl()`, never the raw destination, so clicks keep routing through existing redirect/click-tracking with no new tracking code needed.
- **Blade gotcha:** literal `{{` in inline JS inside a `.blade.php` file (e.g. building the token client-side: `` `{{link:` + id + `}}` ``) must be escaped as `@{{` or Blade's compiler tries to parse it as an echo statement and corrupts the file. PHP double-quoted strings/heredocs (e.g. server-side prompt-building in a drafter service) have no such issue — `"{{link:{$l->id}}}"` interpolates fine.
- **Why to apply:** any future "attach a real record via inline token" feature (not just links) can reuse this exact pattern — parse via regex, resolve scoped to the owner, render distinctly per channel, always fail closed (empty) on an unresolvable/foreign id.
