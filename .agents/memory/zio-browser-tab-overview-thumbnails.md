---
name: Zio Browser Tab Overview thumbnails
description: How exposé-style tab thumbnails are captured in the Electron multi-view architecture
---

Background tabs' WebContentsViews are detached (not painted), so `capturePage()` returns blank for them. The Tab Overview therefore serves background tabs from a `thumbnailCache` that is populated by snapshotting the **outgoing** tab inside `activateTab()` before it is detached; only the active tab is captured fresh at overview-open time (`captureThumbnails()`), and `closeTab` evicts the cache entry.

**Why:** capture-at-open for all tabs is impossible once a view is detached; snapshot-on-deactivate is the only moment the frame is still live.

**How to apply:** any future feature needing per-tab imagery (tab hover previews, session snapshots) should reuse `tabManager.captureThumbnails()` / the cache, not call `capturePage` on background tabs. Overview UI holds the ref-counted chrome overlay (`useChromeOverlay`) while open, like every other DOM overlay in ChromeBar.
