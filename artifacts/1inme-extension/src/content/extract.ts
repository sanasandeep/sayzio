// Page-to-biolink extractor. Returned object is consumed by the
// background script which calls the 1INME API to seed a draft biolink.
//
// Detached IIFE so the bundled file can be injected via
// browser.scripting.executeScript({ files: [...] }) and the last
// expression becomes the script result.

(function extract() {
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

  function meta(name: string): string | null {
    const sel = `meta[name="${name}" i], meta[property="${name}" i]`;
    const el = document.querySelector(sel) as HTMLMetaElement | null;
    return el?.content?.trim() || null;
  }

  function detectSocial(url: string): string | null {
    try {
      const host = new URL(url, document.baseURI).hostname.replace(/^www\./, "");
      for (const [domain, label] of Object.entries(SOCIAL_HOSTS)) {
        if (host === domain || host.endsWith(`.${domain}`)) return label;
      }
    } catch { /* ignore */ }
    return null;
  }

  const here = location.hostname.replace(/^www\./, "");

  const title = (meta("og:title") || document.title || "").trim().slice(0, 200);
  const description = (meta("og:description") || meta("description") || "").trim().slice(0, 500);
  const canonicalEl = document.querySelector('link[rel="canonical"]') as HTMLLinkElement | null;
  const canonical = (canonicalEl?.href || meta("og:url") || location.href).trim();
  const ogImage = meta("og:image") || meta("twitter:image") || null;

  const seen = new Set<string>();
  const links: Array<{ url: string; label: string; social: string | null }> = [];

  document.querySelectorAll<HTMLAnchorElement>("a[href]").forEach((a) => {
    const href = a.href;
    if (!href) return;
    if (!/^https?:/i.test(href)) return;
    let host = "";
    try { host = new URL(href).hostname.replace(/^www\./, ""); } catch { return; }
    if (!host || host === here) return;
    if (seen.has(href)) return;
    seen.add(href);

    const label = (a.innerText || a.title || a.getAttribute("aria-label") || host).trim().slice(0, 80);
    const social = detectSocial(href);
    if (links.length < 50) {
      links.push({ url: href, label, social });
    }
  });

  // Bias: socials first, then everything else.
  links.sort((a, b) => (a.social && !b.social ? -1 : !a.social && b.social ? 1 : 0));

  const payload = {
    title,
    description,
    canonical,
    ogImage,
    links: links.slice(0, 30),
  };
  return payload;
})();
