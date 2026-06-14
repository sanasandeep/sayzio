---
name: Cookie-consent modal backdrop blocks all nav clicks (1inme)
description: The recurring "marketing menu/buttons don't work" complaint = the cookie-consent full-page backdrop, not Alpine.
---

# "Menus and buttons don't work" on 1inme public pages = the cookie-consent wall

The recurring complaint that nav menus/buttons "don't work" on the 1inme Laravel
marketing/public pages was caused by the **cookie-consent overlay**, not Alpine
and not the nav code.

**Mechanism:** `resources/views/common/partials/cookie-consent.blade.php` renders
a `.cc-host` at `z-index:2147483600` (`pointer-events:none`). For `modal` /
`takeover` layouts with `backdrop.show`, it injects a `.cc-backdrop`
(`position:absolute; inset:0; pointer-events:auto`) that covers the WHOLE
viewport — including the top nav. Every click lands on the backdrop, so menus
never open; even a force-click opens the dropdown *behind* the backdrop (dropdown
z is `z-[60]` ≪ 2.1B), so it's invisible. Returning visitors don't see it because
the `1inme_cookie_consent` cookie (remember_days 180) suppresses it — which is
why it "works for me" but is broken for every fresh visitor.

**Why it was easy to misdiagnose:** there is NO console error. Alpine is fully
booted (verified: `window.Alpine` object v3.14.9, `[x-cloak]`=0, nav root
`_x_dataStack` has openMenu/mobileOpen/authOpen/authTab). The ONLY tell is
`document.elementFromPoint(<nav button x,y>)` returning `<div class="cc-backdrop">`.

**Fix applied:** default consent layout changed from `modal` to `banner` in
`app/Modules/Common/Support/CookieConsentConfig.php` `defaults()`. Banner (and
corner/inline/pill) create NO backdrop, so nav is clickable immediately. Config
is admin-editable and stored in AppSetting `cookie_consent_config`; there was no
DB override, so editing `defaults()` was sufficient.
**Why banner over modal:** user wants visitors to browse right away; `block_until_
consent` only gates tracking pixels (pixel-scripts / marketing-tracking blades),
NOT navigation, so it can stay `true` for privacy while the banner stays non-blocking.
**How to apply:** if a public-page interaction "doesn't work" with no console
error, check for a full-page overlay first (elementFromPoint). Never re-enable a
modal/takeover consent backdrop without confirming the user wants a blocking wall.
