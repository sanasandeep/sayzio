---
name: Biolink in-page product storefront
description: How the native-checkout Product block storefront is wired across surfaces, and the easy-to-break constraints.
---

The Product biolink block can act as a native in-page storefront (price/currency/digital|physical/digital file). Public render: single product → Buy Now, multiple → Add to Cart + a per-creator cart drawer (Alpine `$store.bioStore`) + combined checkout. Purchase needs a logged-in ViewerSession; guest POSTs return `401 {login_required, creator_id}` which the drawer JS turns into an `open-viewer-login` event.

**Constraints that are easy to break:**
- Storefront routes live at root (`/store/{alias}/...`, `/store/order/{order}/...`) and MUST be registered **before** the `/{alias}` biolink catch-all in `routes/web.php`, or the catch-all swallows them.
- Server re-reads the block on add-to-cart/buy for authoritative price/type — never trust the client-posted amount.
- Cart is session-backed, keyed by creator id then `"{linkId}:{blockId}"`; single-currency orders only (mismatched-currency lines are dropped).
- The DM thread route a creator clicks from an order is `user.inbox.dms.thread` (not `user.messages.*`).

**Earnings auto-surface:** `CreatorMonetizationController::earnings()` builds `$bySource` with a generic `groupBy('source')`, so any new `CreatorPaymentEvent` source (e.g. `SOURCE_PRODUCT`) shows up automatically — you only add the label/icon entry to the earnings view's `$sources` + `$iconMap`, no controller change.
