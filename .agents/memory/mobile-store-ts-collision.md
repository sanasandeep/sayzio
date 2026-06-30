---
name: Mobile store.ts namespace collision
description: Two unrelated "store" clients in artifacts/1inme-mobile; keep them in separate modules.
---

`artifacts/1inme-mobile/lib/api/store.ts` is the client for the **biolink in-page
Product storefront** (native checkout — exports buyProduct/checkoutCart/
getOwnerOrders/getOrder + types ProductOrder/OwnerOrder/OrderItem). It is consumed
by `app/biolink/[handle].tsx`, `app/orders.tsx`, and `app/store/order/[id].tsx`.

The `store_menu` link type (catalog + order-requests, mirrors restaurant_menu) is a
DIFFERENT feature. Its mobile client lives in `lib/api/storeMenu.ts` and its public
screen is `app/store/[alias].tsx` (coexists with `app/store/order/[id].tsx`).

**Why:** a `store_menu` session overwrote `store.ts` wholesale, breaking the three
product-storefront consumers (only caught by `pnpm --filter @workspace/1inme-mobile
run typecheck`). Recovered the original via `git checkout HEAD -- ...`.

**How to apply:** when adding a mobile API client whose obvious name is `store.ts`,
check whether it already exists first; if so, pick a distinct module name. Run the
mobile typecheck after any client edit — the collision is invisible until then.
