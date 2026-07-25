---
name: Zio Browser chrome-overlay for dropdowns
description: How renderer dropdown menus avoid being occluded by native WebContentsViews in the Electron browser
---

Native WebContentsViews (tab views AND the dashboard view) sit ABOVE the renderer DOM, so any chrome dropdown menu extending into their region gets its clicks swallowed. Hiding tabs alone is NOT enough in dashboard/split modes — the dashboard view must be detached too.

**Rule:** dropdowns use the main-process chrome-overlay API (`window.zio.window.setChromeOverlay(open)`): open → detach all native views; close → re-apply current mode to restore. The main process ref-counts open/close calls (restore only at count 0) because multiple header menus can overlap during menu-to-menu transitions; any `setMode` implicitly restores views (used by "picked" paths that skip the close call). Components must release the overlay on unmount.

**How to apply:** any new chrome dropdown/menu in Zio Browser must use this pattern (see ModeSwitcher/AccountButton/NewTabButton), never raw `tabs.hideAll`.
