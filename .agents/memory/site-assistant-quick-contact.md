---
name: Site Assistant login gate + multi-channel quick-contact
description: Lockstep surfaces for gating the Zio Bot assistant behind login and the callback/whatsapp/email quick-contact feature.
---

# Site Assistant login gate + quick-contact

The "Zio Bot" site assistant is login-gated server-side, and a multi-channel
quick-contact (Call back / WhatsApp call / Email) exists BOTH inside the
assistant handoff AND as a "Contact us" entry point INSIDE the assistant panel
(header button → contact view). There is NO LONGER a standalone floating
quick-contact widget — it was folded into the assistant so each corner has a
single launcher. The contact view is reachable regardless of the chat login
gate (it posts to the anonymous-friendly endpoint, separate from chat).

## Login gate
- Backend is authoritative: the controller returns 401 on message/stream/choice/handoff for anonymous callers, and bootstrap returns `auth_required` + `auth_required_note` + `login_url`. The chrome note is an admin-editable SiteAssistantSettings string.
- **Why:** UI-only gating is bypassable; the cross-origin marketing React widget is always anonymous, so it always shows the gate.
- Front-ends swap the composer for a login CTA when `auth_required` is true (blade widget reads bootstrap; React forces it since marketing is anonymous).

## Quick-contact channel/validation lockstep
A change to channels/validation must touch ALL of:
1. `QuickContactService` (backend validate/create/channelLabel; `CHANNELS` const; callback=Indian phone, whatsapp=country-coded phone, email).
2. Blade assistant widget — `QC_CHANNELS` in `common/partials/site-assistant.blade.php`, used by BOTH the handoff form AND the in-panel "Contact us" view (`openContact`/`buildContactForm`, posts to `ds.quickContactUrl` with honeypot `website` field + `elapsed_ms` time-trap).
3. React assistant widget — `QuickContactFields` in `components/site-assistant.tsx` (exported + reused by the handoff form AND the in-panel `AssistantContactView`; React side has NO honeypot field, only `elapsed_ms`).
4. Mobile (see below).

- The standalone widgets (`common/partials/quick-contact.blade.php` + `components/quick-contact.tsx`) were DELETED; their includes/mounts removed from home.blade.php, public/layouts/site.blade.php, user/layouts/app.blade.php, and App.tsx.
- Submissions land in the admin Contact Inbox (`contact_messages.contact_channel` + `contact_phone`, additive guarded migration) AND trigger an admin email via the service. Inbox view shows a channel badge + phone.
- **How to apply:** the quick-contact endpoint `assistant/quick-contact` is NOT login-gated (anonymous can submit); only the assistant chat/handoff is gated. The in-panel "Contact us" view must stay outside the login gate (own header button + separate pane), since marketing visitors are anonymous.

## Mobile parity (Expo)
- Mobile reuses the SAME contract via `/api/v1/assistant/quick-contact` (routed straight to `SiteAssistantController::quickContact`, wrapped in `api.optional_auth` + `throttle:10,1`), so NO new controller/service — it shares QuickContactService validation/inbox/email. A 6th front-end surface lives at `artifacts/1inme-mobile/app/info/contact.tsx` (lib `lib/api/assistant.ts`), reachable from the Profile INFO_PAGES list + the Help page.
- Mobile has NO assistant CHAT surface, so the login gate is N/A there; quick-contact is anonymous-capable but apiFetch attaches the bearer token so signed-in name/email default in.
