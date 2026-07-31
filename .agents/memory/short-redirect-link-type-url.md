---
name: Plain redirect short links are type 'url', not 'short'
description: Why manually seeded "short" links 404 on the public /{alias} route and how sayzio.app alias namespaces bind.
---

Plain redirect short links must be created with `links.type = 'url'`. The public RedirectController type dispatch has NO `'short'` arm — an unlisted type falls into `default => abort(404)`, which renders the GENERIC "Page not found" page (while a truly missing alias renders the distinct "Link not found" view; use that title difference to tell "route matched but type unhandled" from "alias not found").

**Also:** on production, aliases meant to resolve on sayzio.app should be bound to the sayzio.app global domain row (`domain_id` of the `domains` row where domain='sayzio.app'), matching how the app itself creates them; existing prod links all carry that domain_id.

**How to apply:** when seeding links directly via the model layer (tinker scripts), set `type => 'url'` and the platform `domain_id`; verify with a live curl expecting a 301 to the destination.
