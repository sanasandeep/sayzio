---
name: Reusable social-account autofill picker
description: Generic one-click autofill component for pulling a user's connected social accounts into any form field pair (handle/URL/label).
---

`user/partials/social-autofill-picker.blade.php` generalizes the connection-id `<select>` originally hardcoded into the socials block form. It renders a `<select>` of the caller-supplied connections with `data-platform/handle/url/label` attributes on each `<option>`, and takes a raw `onSelect` JS/Alpine expression string (evaluated with `opt` bound to the selected option) so it makes zero assumptions about the surrounding form's field shape — it works both inside an Alpine `x-data` array model (vCard socials) and against plain `document.getElementById` targets in a flat form (short-link create form).

**Why:** Task 3588 required generalizing biolink-socials autofill to vCard/digital card, contact links, and other link-type forms. Re-implementing the `<select>` + data-attribute pattern per form would drift; a single partial with an injectable `onSelect` string keeps every consumer in lockstep and lets each form decide how to apply the picked value.

**How to apply:** When a new form needs "autofill from connected account," `@include('user.partials.social-autofill-picker', ['connections' => $myConnections, 'onSelect' => '<js expr using opt.dataset.*>'])` rather than writing a new picker. `$myConnections` should be `SocialAccountConnection::where('user_id', auth()->id())->get()` scoped to the current user (no searchable filter needed here — autofill uses ALL of the user's own connections, unlike the public search/caller-ID surfaces which require `is_searchable`).
