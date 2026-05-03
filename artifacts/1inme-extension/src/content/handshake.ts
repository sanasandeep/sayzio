// Auth handshake content script. Runs on the 1INME extension handshake
// page (https://<webBaseUrl>/extension/handshake). The page exposes a
// JSON payload containing the Sanctum token, user, and workspaces in a
// <script id="extension-handshake" type="application/json"> tag once the
// user is signed in. We forward that payload to the background script
// which persists it in browser.storage.local, then close the tab.

import { browser } from "../lib/browser";

(async () => {
  const node = document.getElementById("extension-handshake");
  if (!node || !node.textContent) return;
  let payload: any;
  try { payload = JSON.parse(node.textContent); } catch { return; }
  if (!payload || !payload.token) return;
  try {
    await browser.runtime.sendMessage({
      type: "AUTH_HANDSHAKE",
      token: payload.token,
      user: payload.user || null,
      workspaces: payload.workspaces || [],
    });
    // Visual confirmation, then close.
    const banner = document.createElement("div");
    banner.textContent = "Signed in to 1INME extension. You can close this tab.";
    banner.style.cssText = "position:fixed;top:0;left:0;right:0;background:#047857;color:white;padding:12px;text-align:center;z-index:2147483647;font-family:sans-serif;";
    document.body.appendChild(banner);
    setTimeout(() => { try { window.close(); } catch { /* ignore */ } }, 1200);
  } catch { /* ignore */ }
})();
