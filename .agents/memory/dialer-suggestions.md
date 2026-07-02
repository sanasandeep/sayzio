---
name: Dialer suggestions
description: Pre-query empty-state suggestions contract, group sources, and cross-surface rendering pattern.
---

# Dialer Suggestions

## Rule
`DialerSuggestions::forUser()` is the single source of truth for the 5 groups (favorites, recents, new_followers, following, new_leads). Returns the same `{total, groups[]}` contract as `DialerSearch::universal()` so ALL renderers (web `renderGroups()`, mobile grouped results, standalone search) work with zero extra code.

## Group sources
- **favorites** — `DialerData::favorites()`
- **recents** — `DialerData::groupedRecents()`
- **new_followers** — `Follow` where `creator_id=$user->id`, ordered by `created_at desc`
- **following** — `Follow` where `follower_id=$user->id`, ordered by `created_at desc`
- **new_leads** — active `Subscriber` + completed `FormSubmission` (owned via form relationship), merged by date

## Live refresh
Suggestions piggyback on the dialer live poll (`DialerData::liveSignature/liveState`) — the cursor folds in favorites, flags, call log, follows (both directions) and subscribers (count + max(id) each), so `changed=true` is the single "re-fetch suggestions" trigger on web (`refreshSuggestions()` in the dialer blade, empty-state-guarded) and the standalone mobile poll. No dedicated suggestions-freshness endpoint; `changed=false` cycles make zero extra calls.

## Gotchas
- `Follow` and `FormSubmission` use `BelongsToWorkspace` trait — must use `withoutGlobalScope('workspace')` or queries return nothing.
- `FormSubmission` has no direct `user_id`; scope via `whereHas('form', fn($q) => $q->where('user_id', $user->id))`.
- People groups (new_followers/following) go through the same suspended/blocked gate as `DialerSearch::peopleItems()`.

**Why:** Reuses the shared renderer contract so suggestions and live search results are visually identical. Follow scoping was the key gotcha — global workspace scope silently returns empty results.

**How to apply:** When adding a new group, add a group builder method, call it in `forUser()`, and return items in the same normalized shape as `DialerSearchItem` (type, category, id, title, subtitle, type_label, initials, badge, verified, verified_label, action).

- New-leads group is actionable-only: filter no-contact subscribers (SQL) and blank-payload form submissions (over-fetch LIMIT*4 + extract + filter) BEFORE the merged take(LIMIT), or dead rows starve out older actionable leads.
