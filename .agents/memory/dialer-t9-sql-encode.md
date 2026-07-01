---
name: Dialer T9 SQL encoding
description: T9 keypad-name matching is pushed into SQL via DialerT9::sqlEncode; the expression must stay byte-identical between query and functional index.
---

# Dialer T9 in SQL (not in-memory)

The dialer's T9 smart-dial (a keypad digit sequence like `526` matching a
keypad-spelled name like "Jan") is matched **in SQL**, not by loading candidate
rows into PHP and looping with `DialerT9::matches()`.

`DialerT9::sqlEncode($columnExpr)` returns a Postgres expression that reproduces
`DialerT9::encode()` exactly:
`regexp_replace(translate(lower(<expr>), 'a…z', '2223334…9999'), '[^0-9]', '', 'g')`.
Callers do `whereRaw(DialerT9::sqlEncode('name') . ' LIKE ?', ['%'.$seq.'%'])`.
`DialerT9::CONTACT_NAME_SQL` is the immutable SQL mirror of
`Contact::nameForDisplay()`'s name portion (`display_name ?: given+family`).

**Why:** the old fallback did a fixed 200-user / 300-contact fetch + PHP loop on
every keystroke, which became a latency cliff for creators following/importing
thousands of accounts. SQL matching never scales with the reachable-set size and
is index-backed.

**How to apply:**
- `LIKE '%seq%'` == `str_contains(encode(name), seq)` only because `seq` is
  `preg_replace('/\D+/','',$q)` — pure digits, so it carries no LIKE
  metacharacters. Keep `seq` digit-only or this equivalence breaks.
- The functional GIN (`gin_trgm_ops`) index is built on the **identical**
  `sqlEncode(...)` expression. If you change `sqlEncode()` or `CONTACT_NAME_SQL`,
  the index no longer matches and the planner silently falls back to a seq scan —
  bump/recreate the index in lockstep (migration
  `*_add_t9_indexes_for_dialer_search`).
- Every function used is IMMUTABLE (lower/translate/regexp_replace/coalesce/
  nullif/trim/||); `concat`/`concat_ws` are only STABLE and would reject the
  functional index — never use them here.
- Surfaces: `DialerSearch::peopleItems()` (users.name), `contactsAdvanced()` and
  `DialerController::searchContacts()` (contacts). All fold T9 into the main
  `WHERE` via `orWhereRaw`; there is no post-fetch in-memory loop anymore.
