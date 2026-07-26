---
name: Zio Browser renderer-drawn internal pages
description: How about:sayzio / about:zio internal pages stay consistent with tab state; omnibox scheme gotcha.
---

Internal pages (about:sayzio / about:zio) are renderer-drawn like about:newtab: the native WebContentsView never loads them (tab kept `isNewTabPage=true` so the view stays detached), and `App.tsx` renders `AboutPage` when the active tab's url matches.

**Rules learned:**
- The tab must carry a canonical `internalUrl` field. `getTabState()` and `closeTab()`'s recently-closed capture must prefer it — otherwise `wc.getURL()` returns the STALE previous real page after re-hydration or close, and state silently reverts.
- **Omnibox scheme gotcha:** `parseOmniboxInput`'s SCHEME_PATTERN requires `://`, so `about:zio` (no slashes) parses as a *search query*. `navigate()` must check `isInternalPageUrl(rawInput.trim())` BEFORE parsing.
- A real `did-navigate` (non-about:blank) clears `internalUrl`; navigating to a normal URL also clears it before `loadURL`.
- Loading-tab animation: ChromeBar swaps the tab favicon for the pulsing Zio icon (`.zio-loading-icon`) whenever `tab.isLoading` — in BOTH pinned and normal tab renders.

**How to apply:** any new renderer-drawn internal URL goes into `src/shared/internal-pages.ts` (`INTERNAL_PAGE_URLS`); tab-manager, App.tsx and tests then pick it up. Tests live in `tests/tab-manager.test.ts` ("Renderer-drawn internal pages" describe).
