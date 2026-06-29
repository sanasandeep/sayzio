---
name: Site Assistant login gate + multi-channel quick-contact
description: Lockstep surfaces for gating the Zio Bot assistant behind login and the callback/whatsapp/email quick-contact feature.
---

# Site Assistant login gate + quick-contact

The "Zio Bot" site assistant is login-gated server-side, and a multi-channel
quick-contact (Call back / WhatsApp call / Email) exists BOTH inside the
assistant handoff AND as a standalone always-anonymous widget.

## Login gate
- Backend is authoritative: the controller returns 401 on message/stream/choice/handoff for anonymous callers, and bootstrap returns `auth_required` + `auth_required_note` + `login_url`. The chrome note is an admin-editable SiteAssistantSettings string.
- **Why:** UI-only gating is bypassable; the cross-origin marketing React widget is always anonymous, so it always shows the gate.
- Front-ends swap the composer for a login CTA when `auth_required` is true (blade widget reads bootstrap; React forces it since marketing is anonymous).

## Quick-contact is a 5-surface lockstep
A change to channels/validation must touch ALL of:
1. `QuickContactService` (backend validate/create/channelLabel; `CHANNELS` const; callback=Indian phone, whatsapp=country-coded phone, email).
2. Blade assistant widget handoff form (`QC_CHANNELS` in `common/partials/site-assistant.blade.php`).
3. React assistant widget — `QuickContactFields` in `components/site-assistant.tsx` (exported + reused).
4. Standalone blade partial `common/partials/quick-contact.blade.php` (included in home.blade.php, public/layouts/site.blade.php, user/layouts/app.blade.php).
5. Standalone React `components/quick-contact.tsx` (mounted in App.tsx; reuses exported `QuickContactFields`/`assistantTokens`/`postJson`/`useIsDark`).

- Submissions land in the admin Contact Inbox (`contact_messages.contact_channel` + `contact_phone`, additive guarded migration) AND trigger an admin email via the service. Inbox view shows a channel badge + phone.
- **How to apply:** the standalone quick-contact endpoint `assistant/quick-contact` is NOT login-gated (anonymous can submit); only the assistant chat/handoff is gated.
