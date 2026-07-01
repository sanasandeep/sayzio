---
name: Dialer everyday tool architecture
description: How the web + mobile dialer share logic and where caller-ID enrichment lives
---

The dialer (favorites/speed-dial, grouped recents, frequent strip, T9, spam/block
flags, call-log mini-CRM, callback reminders) keeps web + REST + mobile in parity
through one shared read helper.

- **Single source of truth:** `app/Modules/User/Support/DialerData.php` holds every
  read/transform (favorites, groupedRecents, frequent, activityFor, transformLog,
  transformFavorite). Both `User\Controllers\DialerController` (web) and
  `Api\Controllers\DialerController` (REST) call it — never re-derive shapes per
  controller, change them here once.

- **History shape is `{ recents, frequent }`**, not a flat list. `GET /dialer/history`
  returns grouped recents (deduped by E.164, with `calls` count, `last_human`,
  outcome/note/tag, is_spam/is_blocked) plus the frequently-contacted strip.

- **Caller-ID enrichment only comes from `POST /dialer/lookup`.** The mobile `Contact`
  type (`lib/api/contacts.ts`) has NO biolink field — `listContacts` won't tell you if
  a number maps to a 1INME user. Only the lookup endpoint returns `biolink`/`is_spam`/
  `is_blocked`/`is_favorite`/`activity`.

- **Mobile recents are layered:** local AsyncStorage recents (numbers dialed before a
  server roundtrip, or non-E.164 rejects) merge with server `recents`; only local rows
  are removable on-device.

- **Callbacks:** `dialer.callback_due` notification, swept by the
  `dialer:send-callback-reminders` command (everyFiveMinutes). Stamped via
  `callback_at` / `callback_notified_at` columns on `dialer_lookups`.

- Mobile has no datetimepicker dep → callback uses fixed quick-pick presets
  (1h/3h/tomorrow/3d), not a calendar.

## Universal finder (dialer search)

- **Single source of truth is `app/Modules/User/Support/DialerSearch.php`** —
  `universal(User,$q,$filters)` returns `{q,filter,total,groups[{key,label,items[]}]}`;
  `contactsAdvanced()` for the richer contacts-only path. Web
  (`User\Controllers\DialerController@search`, returns `{data}`), REST
  (`Api\Controllers\DialerController@search` via `ApiResponses::ok`, `GET /api/v1/dialer/search`)
  and mobile (`lib/api/dialer.ts` `dialerSearch`) ALL call it — never re-derive the
  grouped shape per surface.
- **Groups**: Contacts, People, My links, Followed, Workspaces. `GROUP_LIMIT=12`.
  People scope = self + `Follow.creator_id` + `contacts.biolink_user_id`.
- **Visibility gating** mirrors biolink enforcement (`canViewLink()`): public/registered/
  followers pass for an authed dialer user; `subscribers` needs an active `Subscriber`
  with email; only biolink-family + `['url','file','ics','vcf','reviews','paid_page','brand_kit']`
  are gated. Keywords come from `settings #>> '{biolink,meta,keywords}'`; alias back-halves
  from `LinkAlias`.
- **Verification badge** = `links.is_verified` + `verified_name`; a *person* is "verified"
  if they own any `is_verified` link. Filter chips: `verified` (query param `filter=verified`)
  and `has_biolink` ("On Sayzio").
- **Keypad toggle (T9 grid ↔ alphanumeric keyboard)** exists on BOTH web
  (`setKeypadMode` in `user/dialer/index.blade.php`) and mobile (`keypadMode` state in
  `app/dialer.tsx`); both modes write one query that feeds the same universal search.
  T9 smart-dial stays server-side (mobile dropped its client-side `runT9Search` render).
- **Workspace-scope opt-out for cross-workspace groups**: the web dialer runs under
  `workspace.scope`, so `BelongsToWorkspace`'s global scope narrows `Link` queries to the
  active workspace. BOTH `followedLinkItems()` (cross-account) AND `myLinkItems()`
  (same-user, other workspaces) call `Link::withoutGlobalScope('workspace')` so web matches
  the Sanctum API/mobile (which bind no workspace). Ownership/visibility is still enforced
  by the `user_id` predicate + `canViewLink()`; the opt-out never widens who is seen.
- **Mobile action routing** (`openUniversalItem`): `type==='contact'` → in-app
  `openProfile(number,...)`; anything with `action.url` → `Linking.openURL`; workspaces
  have no mobile switch target (informational only — web uses the POST switch route).
