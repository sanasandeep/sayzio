---
name: Two parallel contact_messages systems
description: 1INME has two unrelated contact_messages tables/inboxes in different schemas — don't conflate them.
---

There are TWO independent contact-message stacks that share the table name `contact_messages`:

1. **Laravel** (`public.contact_messages`): for Laravel-served marketing pages (`site_pages`). Has Eloquent model `App\Modules\Common\Models\ContactMessage`, a full admin inbox (`ContactInboxController` + `admin/contact-inbox` views + `/admin/contact-inbox` routes gated by `settings.manage`), columns include `ip` + `status` (new/read/archived).

2. **Node/Drizzle** (`drizzle.contact_messages`, the `drizzle` pgSchema): for the React `1inme-com` marketing site. Populated by api-server `POST /api/contact`. Its admin inbox lives at `1inme-com` `/admin/inbox` consuming gated api-server endpoints (`GET/PATCH /api/contact/messages`).

**Why:** They live in different Postgres schemas (Laravel owns `public`, drizzle owns `drizzle` — see drizzle-schema.ts), so the same name does NOT collide. A task about one is not about the other.

**How to apply:** When a task mentions `drizzle.contact_messages` or the api-server/openapi contact flow, work the Node side only; ignore the Laravel ContactInbox. Vice-versa for Laravel marketing-page contact work.
