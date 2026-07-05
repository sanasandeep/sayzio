---
name: Delivery Project two-way comments
description: How client/buyer↔team comments + milestone emails are wired across web/portal/public/API/mobile
---
Delivery Projects support two-way comments (client/buyer ↔ workspace team) plus milestone emails.

**Central funnel:** all notifications AND client emails go through one service, `DeliveryProjectNotifier` (Common/Services) — never notify/email directly from a controller or command, or the surfaces drift.
**Why:** comments can be posted from 4 independent surfaces (team web, client portal, anonymous public share-token page, REST API) and milestone triggers live in task/project status transitions + the warranty command; only a single funnel keeps notification + email behavior consistent.

**Durable rules:**
- Client milestone/reply emails only fire when `project->client_email` is set — the email helper no-ops otherwise (don't assume the client always gets mailed).
- Client-facing links in emails ALWAYS point at the public share page (`delivery-project.share`, no login), never the team-only show route.
- The anonymous public post route must stay throttled (buyers are unauthenticated).
- Email template keys must match EmailTemplateRegistry exactly, and the in-app `delivery_project.comment` notification payload uses `message`/`snippet`/`url` keys (NOT author_name/project_title) — the notifications index renders those; header dropdowns render generic `message`, so no per-type branch is needed there.
