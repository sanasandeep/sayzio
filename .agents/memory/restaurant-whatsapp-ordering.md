---
name: Restaurant menu WhatsApp ordering
description: How optional wa.me click-to-chat ordering is wired across web + mobile for the restaurant_menu link type
---

Optional WhatsApp click-to-chat ordering for the `restaurant_menu` link type.

The WhatsApp link is ALWAYS built server-side via `App\Modules\Common\Services\WhatsappOrderLink::build($menu, $order, $linkTitle)` — never construct wa.me URLs client-side. It returns `['number','message','url']` or `null` when no/invalid number.

- Number stored in menu `settings['whatsapp_number']` (JSON, **no migration**); only meaningful in Order mode.
- `normalizeNumber()` is digits-only, valid length 7-15 (else null); `reference()` = `#` + 6 uppercase hex chars of the order's `public_token`.
- Order placement records normally regardless; WhatsApp is purely additive (a button on the confirmation/done modal).

**Why:** out of scope = WhatsApp Business/Cloud API, automated sends, two-way messaging, display-mode sending. It is a plain wa.me deep link only.

**How to apply (lockstep surfaces):**
- Web owner editor: `RestaurantMenuController::saveSettings` validates/normalizes; `editor.blade.php` field x-show order mode.
- Web public: `PublicRestaurantController::placeOrder` + `orderStatus` add `whatsapp` to JSON; `restaurant-menu.blade.php` done modal `#waBtn`.
- Mobile API: `Api\RestaurantController::saveMenuSettings` (owner) + `guestOrder($order,$menu,$link)` adds `whatsapp`; `ownerMenuPayload()` exposes `whatsapp_number`. `guestOrder` resolves menu/link from relations when callers (orderStatus) don't pass them.
- Mobile app: `lib/api/restaurant.ts` types (`WhatsappOrderLink`, GuestOrder.whatsapp, OwnerMenu.whatsapp_number); `restaurant/[alias].tsx` Linking.openURL button; `links/[id]/restaurant-menu.tsx` builder field (order mode).

**Demo/marketing showcase:** there is NO demo/explainer surface that renders the order flow — `demo-type-*` pages (incl. `demo-type-restaurant-menu`) and the marketing site are static biolink explainers. To showcase WhatsApp ordering, `LinkTypeExplainerSeeder` seeds a REAL working `restaurant_menu` link at `/demo-restaurant` (order mode + sample `whatsapp_number`, 4 cats/12 items/1 table) and the restaurant explainer's CTA links to it. Idempotent: menu config re-converged each run, items built only when empty. The explainer SEED_VERSION bump refreshes all 11 pages, so a full seed is heavy over distant RDS — drains across repeated idempotent runs (post-merge self-heals); the demo-menu step runs LAST so a single timed-out run won't reach it.
