---
name: Workspace-scoped creator profiles
description: How per-workspace creator profiles (creator_profiles table) overlay onto User, mirror to users.*, and the lockstep surfaces for handles/follows.
---

# Workspace-scoped creator profiles

Creator profiles (handle, bio, tagline, socials, showcase, publish flag, theme color…) live in `creator_profiles` (unique `workspace_id`, LOWER(handle) partial unique index), not on `users`.

**Overlay pattern:** public/@handle and editor surfaces resolve `CreatorProfile::ownerUserForHandle()` / `forWorkspace()` then `applyToUser()` — sets raw attributes with sync so nothing is dirty and `$user->save()` can never leak profile values into `users.*`. ~30 downstream consumers keep reading `$creator->tagline` etc. unchanged. The overlaid profile is attached as the `activeCreatorProfile` relation; link queries on the public page scope by its `workspace_id` **with a fallback OR for legacy links whose `workspace_id` is NULL** (or old accounts lose their showcase).

**users.\* is a mirror of the PERSONAL workspace profile only** (`mirrorToOwner()`, saveQuietly) so legacy consumers (creators directory, watermark, DM routing) keep working. Team-workspace saves never touch users.*.

**Why:** one account needs N public /@handle pages (one per workspace) without rewriting every consumer of the old users-table fields.

**How to apply / lockstep surfaces:**
- Any handle-setting surface must use `CreatorProfile::uniqueHandleRule($ignoreProfileId, $ignoreUserId)` (checks creator_profiles + users case-insensitively) + NotBannedName. Surfaces: web editor claimHandle, web/API profile update, API register, ClaimedHandle (sign-up claim), HandleAvailability, HandleSuggester.
- API paths skip SetActiveWorkspace — resolve the active profile via `WorkspaceContext::resolve($user)` (session pointer wins over `active_workspace_id`; in feature tests forget the singleton + session between requests or workspace switches are ignored).
- Follows carry `creator_profile_id` (default = personal profile; the /@handle follow button passes `handle` to pin the page's profile) and profile `followers_count` is incremented alongside `users.followers_count`.
- Mobile: workspace switch invalidates profile/auth-me/creator-profile-settings/cp-preview-url react-query keys.
- Unfollow must decrement the profile stored on the follow row's `creator_profile_id`, never the current page's handle context, or counters drift permanently.
- In tests, `Link::create(['workspace_id' => ...])` is ignored (stamped from the bound workspace context) — forceFill + saveQuietly to pin it.
