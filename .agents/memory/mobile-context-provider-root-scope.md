---
name: Mobile context providers backing root-stack screens must live at root
description: Expo-router context providers scoped to (tabs) leave sibling root-stack screens with the empty default context
---

Any React context provider whose data is consumed by BOTH tab screens AND
non-tab root-stack screens (e.g. `WorkspaceProvider` feeding the drawer switcher
*and* `app/workspaces.tsx` / `app/workspace-edit.tsx`) must be mounted at the
ROOT layout (`app/_layout.tsx`, wrapping the root `Stack`), not inside
`app/(tabs)/_layout.tsx`.

**Why:** React context is scoped by tree position, not by "what is mounted
somewhere". `/workspaces` and `/workspace-edit` are SIBLINGS of `(tabs)` in the
root stack, so if the provider only wraps `(tabs)` those screens always receive
the `createContext` default value — no navigation trick (SPA vs hard reload)
changes that. Symptom seen: workspace-edit always rendered its "Can't edit this
workspace" EmptyState (empty `workspaces` list) and creating a workspace never
fired `POST /workspaces/{id}/activate` (its `switchWorkspace` was a no-op on the
default context). This was a real shipped bug the e2e test surfaced, fixed by
hoisting `WorkspaceProvider` to wrap `RootLayoutNav` (still inside
`AuthProvider`/`QueryClientProvider`) and removing it from the tabs layout.

**How to apply:** Before scoping a provider to `(tabs)`, check whether any
route it feeds lives OUTSIDE `(tabs)` in the root stack. If so, mount it at root.
Safe to hoist when the provider only needs ancestors already at root
(QueryClientProvider, AuthProvider) and renders `{children}` transparently.
