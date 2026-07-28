---
name: Admin template visual design session (draft-link bridge)
description: How the /admin/templates visual editor works via a hidden draft biolink, and the hiding/concurrency guards it requires.
---

Admin page-template visual editing materializes the template snapshot onto a draft biolink owned by the admin's bridged web user, opens the REAL biolink editor, then captures back into the snapshot on save (marker `settings['_template_draft']={template_id,...}` stripped before snapshot write).

**Rules learned from architect review:**
- Draft links are ordinary `is_active` rows, so they leak by default. Two lockstep guards required: (1) user "My Links" query excludes `whereNull('settings->_template_draft')`; (2) public `/{alias}` render 404s unless the web-session viewer IS the owning user — must NOT be a blanket 404 because the biolink editor's live-preview iframe loads the public page in the same session.
- Concurrency: `findDesignDraft` must be deterministic — `orderBy('id')`, oldest wins, delete duplicate extras (double-open race creates them).
- Bridging admin→web guard: skip if already impersonating; test setup needs `actingAs(admin,'admin')` then `actingAs(user,'web')` (web LAST).

**How to apply:** any future "edit X in the real editor via a hidden working-copy link" feature needs the same three guards (list exclusion, owner-gated public render, deterministic draft pick).
