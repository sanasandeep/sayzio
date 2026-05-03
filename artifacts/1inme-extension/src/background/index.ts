import { browser } from "../lib/browser";
import { api, ApiError } from "../lib/api";
import { clearAuth, getSettings, setSettings } from "../lib/storage";

interface ExtractedPayload {
  title: string;
  description: string;
  canonical: string;
  ogImage: string | null;
  links: Array<{ url: string; label: string; social?: string | null }>;
}

const SOCIAL_HOSTS: Record<string, string> = {
  "instagram.com": "instagram",
  "twitter.com": "twitter",
  "x.com": "twitter",
  "tiktok.com": "tiktok",
  "youtube.com": "youtube",
  "youtu.be": "youtube",
  "facebook.com": "facebook",
  "fb.com": "facebook",
  "linkedin.com": "linkedin",
  "github.com": "github",
  "twitch.tv": "twitch",
  "spotify.com": "spotify",
  "soundcloud.com": "soundcloud",
  "pinterest.com": "pinterest",
  "snapchat.com": "snapchat",
  "discord.com": "discord",
  "discord.gg": "discord",
  "threads.net": "threads",
  "medium.com": "medium",
  "patreon.com": "patreon",
};

function detectSocial(url: string): string | null {
  try {
    const host = new URL(url).hostname.replace(/^www\./, "");
    for (const [domain, label] of Object.entries(SOCIAL_HOSTS)) {
      if (host === domain || host.endsWith(`.${domain}`)) return label;
    }
  } catch { /* ignore */ }
  return null;
}

function notify(title: string, message: string) {
  try {
    browser.notifications?.create({
      type: "basic",
      iconUrl: browser.runtime.getURL("icons/icon-128.png"),
      title,
      message,
    });
  } catch { /* notifications permission may be missing */ }
}

async function shortenAndCopy(url: string, title?: string, openTabId?: number, autoPixel?: boolean): Promise<{ ok: true; shortUrl: string; linkId: number } | { ok: false; error: string }> {
  const settings = await getSettings();
  if (!settings.token) return { ok: false, error: "Not signed in" };
  try {
    const result = await api.createShortLink(url, title, settings.workspaceId, autoPixel);
    const alias = result.link.alias;
    const shortUrl = result.link.short_url || `${settings.webBaseUrl}/${alias}`;

    // Try to copy via the active tab content script. The background's
    // service worker doesn't have a clipboard API on its own.
    if (openTabId !== undefined) {
      try {
        await browser.scripting.executeScript({
          target: { tabId: openTabId },
          func: (text: string) => {
            try {
              navigator.clipboard?.writeText?.(text);
            } catch {
              const t = document.createElement("textarea");
              t.value = text;
              document.body.appendChild(t);
              t.select();
              try { document.execCommand("copy"); } catch { /* ignore */ }
              t.remove();
            }
          },
          args: [shortUrl],
        });
      } catch { /* host permission may be missing on chrome:// pages */ }
    }

    notify("Shortened with 1INME", shortUrl);
    return { ok: true, shortUrl, linkId: result.link.id };
  } catch (e) {
    const msg = e instanceof ApiError ? e.message : (e as Error).message || "Shorten failed";
    return { ok: false, error: msg };
  }
}

async function pageToBiolink(tabId: number): Promise<{ ok: true; alias: string } | { ok: false; error: string }> {
  const settings = await getSettings();
  if (!settings.token) return { ok: false, error: "Not signed in" };

  try {
    const results = await browser.scripting.executeScript({
      target: { tabId },
      files: ["content-extract.js"],
    });
    const payload = (results?.[0]?.result ?? null) as ExtractedPayload | null;
    if (!payload) return { ok: false, error: "Could not extract page content" };

    const titleStr = payload.title || "Untitled";
    const biolink = await api.createBiolink(titleStr, payload.description || undefined, payload.ogImage || undefined, settings.workspaceId);
    const linkId = biolink.link.id;
    const alias = biolink.link.alias;

    let order = 0;
    await api.addBlock(linkId, "header", {
      title: titleStr,
      subtitle: payload.description || "",
      avatar_url: payload.ogImage || null,
      cover_url: payload.ogImage || null,
    }, order++);

    for (const item of payload.links.slice(0, 25)) {
      const blockType = item.social ? "social" : "link";
      const settingsBlock: Record<string, unknown> = item.social
        ? { platform: item.social, url: item.url, label: item.label || item.social }
        : { url: item.url, label: item.label || item.url, link: item.url };
      try {
        await api.addBlock(linkId, blockType, settingsBlock, order++);
      } catch { /* skip individual block failures */ }
    }

    const editorUrl = `${settings.webBaseUrl}/dashboard/biolinks/${linkId}/edit`;
    await browser.tabs.create({ url: editorUrl });
    notify("Bio-link draft created", `${alias} — opening editor`);
    return { ok: true, alias };
  } catch (e) {
    const msg = e instanceof ApiError ? e.message : (e as Error).message || "Bio-link create failed";
    return { ok: false, error: msg };
  }
}

async function setupContextMenus() {
  try {
    await browser.contextMenus.removeAll();
    browser.contextMenus.create({
      id: "1inme-shorten-page",
      title: "Shorten this page with 1INME",
      contexts: ["page"],
    });
    browser.contextMenus.create({
      id: "1inme-shorten-link",
      title: "Shorten link with 1INME",
      contexts: ["link"],
    });
    browser.contextMenus.create({
      id: "1inme-page-to-biolink",
      title: "Turn page into 1INME bio-link",
      contexts: ["page"],
    });
    browser.contextMenus.create({
      id: "1inme-save-contact",
      title: "Save contact with 1INME",
      contexts: ["page", "selection"],
    });
  } catch { /* context menus permission missing */ }
}

async function extractContactCandidate(tabId: number): Promise<{ ok: true; candidate: any } | { ok: false; error: string }> {
  try {
    const results = await browser.scripting.executeScript({
      target: { tabId },
      files: ["content-extract-contact.js"],
    });
    const resp = results?.[0]?.result as any;
    if (!resp || resp.ok !== true) return { ok: false, error: resp?.error || "Could not extract contact." };
    return { ok: true, candidate: resp.candidate };
  } catch (e) {
    return { ok: false, error: (e as Error).message || "Extraction failed" };
  }
}

async function stashContactCandidateAndOpenPopup(tabId: number) {
  const result = await extractContactCandidate(tabId);
  if (!result.ok) {
    notify("1INME — error", result.error);
    return;
  }
  await browser.storage.local.set({
    pendingContactCandidate: { candidate: result.candidate, at: Date.now() },
  });
  // Open the popup (Chrome MV3) — falls back gracefully on Firefox where
  // openPopup is sometimes unavailable from a context-menu click.
  try {
    if ((browser.action as any)?.openPopup) {
      await (browser.action as any).openPopup();
    } else {
      notify("1INME", "Open the 1INME extension popup to review the contact.");
    }
  } catch {
    notify("1INME", "Open the 1INME extension popup to review the contact.");
  }
}

// Dynamically register the handshake content script against whatever
// webBaseUrl the user has configured, so the SSO flow works against
// production (1inme.com) AND a local dev workflow (replit.dev /
// localhost / custom domain). The static manifest still covers the
// production URL so first-install handshake works before any settings
// are written.
async function refreshHandshakeMatches() {
  if (!browser.scripting?.registerContentScripts) return;
  try {
    const { webBaseUrl } = await getSettings();
    let pattern = "";
    try {
      const u = new URL(webBaseUrl);
      pattern = `${u.protocol}//${u.host}/extension/handshake*`;
    } catch { /* invalid URL — skip */ }
    try { await browser.scripting.unregisterContentScripts({ ids: ["1inme-handshake"] }); } catch { /* not registered yet */ }
    if (!pattern) return;
    await browser.scripting.registerContentScripts([
      {
        id: "1inme-handshake",
        matches: [pattern],
        js: ["content-handshake.js"],
        runAt: "document_idle",
        persistAcrossSessions: true,
      },
    ]);
  } catch { /* registerContentScripts may fail if host permission missing — fall back to manifest static match */ }
}

browser.runtime.onInstalled.addListener(() => { setupContextMenus(); refreshHandshakeMatches(); });
browser.runtime.onStartup?.addListener?.(() => { setupContextMenus(); refreshHandshakeMatches(); });
browser.storage.onChanged.addListener((changes: any, area: string) => {
  if (area === "local" && changes.webBaseUrl) refreshHandshakeMatches();
});

browser.contextMenus?.onClicked.addListener(async (info, tab) => {
  if (!tab?.id) return;
  if (info.menuItemId === "1inme-shorten-page") {
    const result = await shortenAndCopy(tab.url || "", tab.title, tab.id);
    if (!result.ok) notify("1INME — error", result.error);
  } else if (info.menuItemId === "1inme-shorten-link") {
    const url = info.linkUrl || "";
    const result = await shortenAndCopy(url, undefined, tab.id);
    if (!result.ok) notify("1INME — error", result.error);
  } else if (info.menuItemId === "1inme-page-to-biolink") {
    const result = await pageToBiolink(tab.id);
    if (!result.ok) notify("1INME — error", result.error);
  } else if (info.menuItemId === "1inme-save-contact") {
    await stashContactCandidateAndOpenPopup(tab.id);
  }
});

browser.runtime.onMessage.addListener(async (msg: any, sender: any) => {
  if (!msg || typeof msg !== "object") return;
  switch (msg.type) {
    case "SHORTEN_URL": {
      const tabId = sender.tab?.id;
      let activeTabId = tabId;
      if (activeTabId === undefined) {
        const tabs = await browser.tabs.query({ active: true, currentWindow: true });
        activeTabId = tabs[0]?.id;
      }
      return shortenAndCopy(msg.url, msg.title, activeTabId, msg.autoPixel);
    }
    case "PAGE_TO_BIOLINK": {
      const tabId = msg.tabId ?? sender.tab?.id;
      if (!tabId) return { ok: false, error: "No active tab" };
      return pageToBiolink(tabId);
    }
    case "SIGN_OUT": {
      try { await api.logout(); } catch { /* ignore */ }
      await clearAuth();
      return { ok: true };
    }
    case "AUTH_HANDSHAKE": {
      // Sent from the handshake content script on 1inme.com after a
      // successful sign-in. Carries the freshly-issued Sanctum token.
      const { token, user, workspaces } = msg;
      if (!token) return { ok: false, error: "No token in handshake" };
      await setSettings({
        token,
        user: user || null,
        workspaces: Array.isArray(workspaces) ? workspaces : [],
        workspaceId: workspaces?.[0]?.id ?? null,
      });
      return { ok: true };
    }
  }
});

setupContextMenus();
refreshHandshakeMatches();
