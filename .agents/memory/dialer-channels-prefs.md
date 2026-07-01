---
name: Dialer preferred messaging channels
description: Where the per-user dialer channel selection lives and the surfaces that must stay in lockstep.
---

# Dialer preferred messaging channels

Users pick which messaging channels (call/sms/whatsapp/telegram/signal/viber) show on
the dialer's one-tap rows. `App\Modules\User\Support\DialerChannels` is the single
source of truth: a `CATALOG` (key → label/short/color/fa/feather/js deep-link mode),
`DEFAULTS`, and `sanitize/forUser/forget/enabledFor/payloadFor`. Storage is
`User.settings['dialer_channels']` (JSON), memoized per-user; mutations must call
`DialerChannels::forget($userId)` after save.

**Why:** the old dialer hardcoded WhatsApp/Telegram everywhere and shipped a
"WhatsApp call" button that had no real deep link (it just re-opened the same wa.me
chat) — a misleading duplicate. That action is removed; there is no public WhatsApp
call deep link.

**How to apply — adding/changing a channel or the picker touches 5 lockstep surfaces:**
1. `DialerChannels::CATALOG` (PHP, source of truth — fa + feather icon + `js` mode).
2. Web server row `resources/views/user/dialer/_channel_actions.blade.php` (iterates `enabledFor`).
3. Web `resources/views/user/dialer/index.blade.php` JS: `DIALER_CH_CATALOG/ENABLED`,
   `chanUrl(mode,v)`, `chanOpen`, `renderKeypadChannels`, `channelActions`, and the
   `openChannelPicker/saveChannelPicker` modal (POST `user.dialer.channels`).
4. Mobile `artifacts/1inme-mobile/app/dialer.tsx`: `FALLBACK_CHANNELS` mirrors CATALOG,
   `chanOpen(mode,v)` deep-link switch, `ChannelPrefsContext`, `ChannelActions`,
   `ChannelPickerModal`. Feather names come from the catalog `feather` field.
5. Mobile client `artifacts/1inme-mobile/lib/api/dialer.ts`: `getDialerChannels`/
   `updateDialerChannels` (GET/PUT `/dialer/channels`, apiFetch returns RAW `{data}`).

Deep-link `js` modes: tel:{raw}, sms:{raw}, wa=https://wa.me/{digits},
tg=https://t.me/+{digits}, signal=https://signal.me/#p/+{digits},
viber=viber://chat?number=%2B{digits}. API `updateChannels` returns full `payloadFor`
(catalog+enabled); web POST returns only `{enabled}`.
