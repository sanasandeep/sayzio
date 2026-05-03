// Page-to-contact extractor. Returned object is consumed by the popup,
// which previews it (with per-field provenance) and lets the creator
// save it to their 1INME Contacts.
//
// Priority order:
//   1. vCard file linked on the page (<a href="*.vcf">) — fetched + parsed
//   2. hCard / microformats2 (h-card, p-name, u-email, p-tel, p-org, u-url)
//   3. Schema.org JSON-LD (@type: Person | Organization)
//   4. Heuristic scrape — OG meta + regex over visible text + outbound links
//
// The script runs as an IIFE returning the candidate so it can be
// injected via browser.scripting.executeScript({ files: [...] }).

interface FieldVal<T> { value: T; source: ExtractionSource }
type ExtractionSource = "vcard" | "hcard" | "jsonld" | "scraped" | "manual";
interface CandidateField {
  display_name: string | null;
  given_name: string | null;
  family_name: string | null;
  organization: string | null;
  job_title: string | null;
  website: string | null;
  notes: string | null;
}
interface ContactCandidate extends CandidateField {
  emails: Array<{ value: string; label?: string; source: ExtractionSource }>;
  phones: Array<{ value: string; label?: string; country?: string; source: ExtractionSource }>;
  socials: Record<string, string>;
  source_url: string;
  source_title: string;
  /** Per-field provenance (UI badges). */
  provenance: Record<string, ExtractionSource>;
  /** Whether *any* extractor produced a structured result (vcard/hcard/jsonld). */
  structured: boolean;
}

type CandidateMessage =
  | { ok: true; candidate: ContactCandidate }
  | { ok: false; error: string };

(async (): Promise<CandidateMessage> => {
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
    "mastodon.social": "mastodon",
  };

  const GENERIC_EMAIL_LOCAL = new Set([
    "info", "support", "contact", "hello", "sales", "press", "team", "help",
    "admin", "office", "noreply", "no-reply", "donotreply", "marketing",
    "billing", "abuse", "webmaster", "postmaster",
  ]);

  function meta(name: string): string | null {
    const sel = `meta[name="${name}" i], meta[property="${name}" i]`;
    const el = document.querySelector(sel) as HTMLMetaElement | null;
    return el?.content?.trim() || null;
  }

  function detectSocial(url: string): { platform: string; handle: string } | null {
    try {
      const u = new URL(url, document.baseURI);
      const host = u.hostname.replace(/^www\./, "");
      for (const [domain, label] of Object.entries(SOCIAL_HOSTS)) {
        if (host === domain || host.endsWith(`.${domain}`)) {
          // Best-effort handle extraction: first non-empty path segment.
          const seg = u.pathname.split("/").filter(Boolean)[0] || "";
          const handle = seg ? seg.replace(/^@/, "") : u.toString();
          return { platform: label, handle };
        }
      }
    } catch { /* ignore */ }
    return null;
  }

  // ─── 1. vCard ────────────────────────────────────────────────────
  function unfoldVcard(text: string): string[] {
    // RFC 6350: lines starting with whitespace are continuations.
    const raw = text.replace(/\r\n/g, "\n").split("\n");
    const out: string[] = [];
    for (const line of raw) {
      if (/^[ \t]/.test(line) && out.length) out[out.length - 1] += line.slice(1);
      else out.push(line);
    }
    return out;
  }

  function parseVcardEntry(text: string): Partial<ContactCandidate> | null {
    const lines = unfoldVcard(text);
    if (!lines.some((l) => /^BEGIN:VCARD/i.test(l))) return null;
    const cand: Partial<ContactCandidate> & { emails: any[]; phones: any[]; socials: Record<string, string> } = {
      emails: [], phones: [], socials: {},
    };
    for (const line of lines) {
      const idx = line.indexOf(":");
      if (idx < 0) continue;
      const left = line.slice(0, idx);
      const value = line.slice(idx + 1).trim();
      const [propRaw] = left.split(";");
      const prop = (propRaw || "").toUpperCase();
      const params = left.split(";").slice(1).map((s) => s.toUpperCase());
      const labelOf = () => {
        const t = params.find((p) => p.startsWith("TYPE="));
        if (!t) return undefined;
        return t.slice(5).split(",")[0].toLowerCase();
      };
      switch (prop) {
        case "FN": cand.display_name = value; break;
        case "N": {
          const [family, given] = value.split(";");
          if (family) cand.family_name = family;
          if (given)  cand.given_name = given;
          break;
        }
        case "ORG": cand.organization = value.split(";")[0]; break;
        case "TITLE": cand.job_title = value; break;
        case "URL": if (!cand.website) cand.website = value; break;
        case "NOTE": cand.notes = value; break;
        case "EMAIL": cand.emails!.push({ value, label: labelOf(), source: "vcard" }); break;
        case "TEL":   cand.phones!.push({ value, label: labelOf(), source: "vcard" }); break;
        case "X-SOCIALPROFILE":
        case "IMPP": {
          const social = detectSocial(value);
          if (social) cand.socials![social.platform] = value;
          break;
        }
      }
    }
    return cand;
  }

  async function tryVcard(): Promise<Partial<ContactCandidate> | null> {
    const link = document.querySelector<HTMLAnchorElement>('a[href$=".vcf" i], a[href*=".vcf?" i]');
    if (!link?.href) return null;
    try {
      const resp = await fetch(link.href, { credentials: "omit" });
      if (!resp.ok) return null;
      const text = await resp.text();
      return parseVcardEntry(text);
    } catch {
      return null;
    }
  }

  // ─── 2. hCard / microformats2 ────────────────────────────────────
  function tryHcard(): Partial<ContactCandidate> | null {
    const root = document.querySelector(".h-card, [class*='h-card']") as HTMLElement | null;
    if (!root) return null;
    const cand: Partial<ContactCandidate> & { emails: any[]; phones: any[]; socials: Record<string, string> } = {
      emails: [], phones: [], socials: {},
    };
    const text = (sel: string) => {
      const el = root.querySelector(sel) as HTMLElement | null;
      return el?.innerText?.trim() || el?.getAttribute("content")?.trim() || null;
    };
    const href = (sel: string) => {
      const el = root.querySelector(sel) as HTMLAnchorElement | null;
      return el?.href || el?.getAttribute("href") || null;
    };
    cand.display_name = text(".p-name");
    cand.given_name   = text(".p-given-name");
    cand.family_name  = text(".p-family-name");
    cand.organization = text(".p-org");
    cand.job_title    = text(".p-job-title");
    cand.notes        = text(".p-note");
    cand.website      = href(".u-url");
    root.querySelectorAll(".u-email").forEach((el) => {
      const a = el as HTMLAnchorElement;
      const raw = (a.href || a.getAttribute("href") || a.textContent || "").trim();
      const value = raw.replace(/^mailto:/i, "");
      if (value) cand.emails!.push({ value, source: "hcard" });
    });
    root.querySelectorAll(".p-tel, .u-tel").forEach((el) => {
      const a = el as HTMLAnchorElement;
      const raw = (a.getAttribute("href") || a.textContent || "").trim();
      const value = raw.replace(/^tel:/i, "");
      if (value) cand.phones!.push({ value, source: "hcard" });
    });
    if (!cand.display_name && !cand.emails!.length && !cand.phones!.length) return null;
    return cand;
  }

  // ─── 3. JSON-LD Person / Organization ────────────────────────────
  function tryJsonLd(): Partial<ContactCandidate> | null {
    const blocks = document.querySelectorAll<HTMLScriptElement>('script[type="application/ld+json"]');
    for (const block of Array.from(blocks)) {
      let data: any;
      try { data = JSON.parse(block.textContent || ""); } catch { continue; }
      const queue: any[] = Array.isArray(data) ? [...data] : [data];
      while (queue.length) {
        const node = queue.shift();
        if (!node || typeof node !== "object") continue;
        if (Array.isArray(node["@graph"])) queue.push(...node["@graph"]);
        const t = node["@type"];
        const types = Array.isArray(t) ? t : [t];
        if (!types.some((x) => typeof x === "string" && /^(Person|Organization|Corporation|LocalBusiness)$/i.test(x))) {
          continue;
        }
        const cand: Partial<ContactCandidate> & { emails: any[]; phones: any[]; socials: Record<string, string> } = {
          emails: [], phones: [], socials: {},
        };
        cand.display_name = node.name || null;
        cand.given_name   = node.givenName || null;
        cand.family_name  = node.familyName || null;
        cand.organization = node.worksFor?.name || node.affiliation?.name || (typeof t === "string" && /Person/i.test(t) ? null : node.name);
        cand.job_title    = node.jobTitle || null;
        cand.website      = (typeof node.url === "string" ? node.url : null) || null;
        cand.notes        = node.description || null;
        const pushEmail = (e: any) => { if (typeof e === "string") cand.emails!.push({ value: e.replace(/^mailto:/i, ""), source: "jsonld" }); };
        const pushPhone = (p: any) => { if (typeof p === "string") cand.phones!.push({ value: p.replace(/^tel:/i, ""), source: "jsonld" }); };
        if (Array.isArray(node.email)) node.email.forEach(pushEmail); else pushEmail(node.email);
        if (Array.isArray(node.telephone)) node.telephone.forEach(pushPhone); else pushPhone(node.telephone);
        if (Array.isArray(node.contactPoint)) {
          for (const cp of node.contactPoint) { pushEmail(cp.email); pushPhone(cp.telephone); }
        }
        const sameAs: string[] = Array.isArray(node.sameAs) ? node.sameAs : (node.sameAs ? [node.sameAs] : []);
        for (const url of sameAs) {
          const social = detectSocial(url);
          if (social) cand.socials![social.platform] = url;
        }
        if (cand.display_name || cand.emails!.length || cand.phones!.length) return cand;
      }
    }
    return null;
  }

  // ─── 4. Heuristic scrape ─────────────────────────────────────────
  function tryHeuristic(): Partial<ContactCandidate> {
    const cand: Partial<ContactCandidate> & { emails: any[]; phones: any[]; socials: Record<string, string> } = {
      emails: [], phones: [], socials: {},
    };
    cand.display_name = (meta("og:title") || document.title || "").trim().slice(0, 200) || null;
    cand.notes        = (meta("og:description") || meta("description") || "").trim().slice(0, 500) || null;
    cand.organization = meta("og:site_name") || null;

    // Try the host as a fallback company name.
    if (!cand.organization) {
      try {
        const host = location.hostname.replace(/^www\./, "");
        const root = host.split(".").slice(-2, -1)[0];
        if (root) cand.organization = root.charAt(0).toUpperCase() + root.slice(1);
      } catch { /* ignore */ }
    }

    // Mailto / tel links first — usually the canonical contact info.
    document.querySelectorAll<HTMLAnchorElement>('a[href^="mailto:" i]').forEach((a) => {
      const value = (a.getAttribute("href") || "").replace(/^mailto:/i, "").split("?")[0].trim();
      if (value && !cand.emails!.some((e) => e.value === value)) {
        cand.emails!.push({ value, source: "scraped" });
      }
    });
    document.querySelectorAll<HTMLAnchorElement>('a[href^="tel:" i]').forEach((a) => {
      const value = (a.getAttribute("href") || "").replace(/^tel:/i, "").trim();
      if (value && !cand.phones!.some((p) => p.value === value)) {
        cand.phones!.push({ value, source: "scraped" });
      }
    });

    // Visible-text regex fallback. Cap to keep things sane on huge pages.
    const text = (document.body?.innerText || "").slice(0, 50_000);
    const emailRe = /[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/g;
    let m: RegExpExecArray | null;
    let count = 0;
    while ((m = emailRe.exec(text)) && count < 10) {
      const value = m[0];
      const local = value.split("@")[0].toLowerCase();
      if (GENERIC_EMAIL_LOCAL.has(local)) continue;
      if (cand.emails!.some((e) => e.value === value)) continue;
      cand.emails!.push({ value, source: "scraped" });
      count++;
    }
    // Phone regex — looser; we let the server do the real validation.
    const phoneRe = /(\+?\d[\d\-\.\s\(\)]{7,16}\d)/g;
    count = 0;
    while ((m = phoneRe.exec(text)) && count < 6) {
      const value = m[1].trim();
      const digits = value.replace(/\D+/g, "");
      if (digits.length < 8 || digits.length > 15) continue;
      if (cand.phones!.some((p) => p.value === value)) continue;
      cand.phones!.push({ value, source: "scraped" });
      count++;
    }

    // Outbound social links → socials map.
    const here = location.hostname.replace(/^www\./, "");
    document.querySelectorAll<HTMLAnchorElement>("a[href]").forEach((a) => {
      if (!/^https?:/i.test(a.href)) return;
      let host = "";
      try { host = new URL(a.href).hostname.replace(/^www\./, ""); } catch { return; }
      if (host === here) return;
      const social = detectSocial(a.href);
      if (social && !cand.socials![social.platform]) cand.socials![social.platform] = a.href;
    });

    cand.website = location.origin + "/";
    return cand;
  }

  // ─── Compose ─────────────────────────────────────────────────────
  function merge(base: Partial<ContactCandidate>, fill: Partial<ContactCandidate>, source: ExtractionSource, prov: Record<string, ExtractionSource>) {
    const fields: (keyof CandidateField)[] = ["display_name", "given_name", "family_name", "organization", "job_title", "website", "notes"];
    for (const f of fields) {
      if (!base[f] && fill[f]) {
        (base as any)[f] = fill[f];
        prov[f] = (fill as any)[`${f}_source`] || source;
      }
    }
    base.emails = base.emails || [];
    base.phones = base.phones || [];
    base.socials = base.socials || {};
    for (const e of (fill.emails || [])) {
      if (!base.emails.some((x) => x.value.toLowerCase() === e.value.toLowerCase())) base.emails.push(e);
    }
    for (const p of (fill.phones || [])) {
      if (!base.phones.some((x) => x.value === p.value)) base.phones.push(p);
    }
    for (const [k, v] of Object.entries(fill.socials || {})) {
      if (!base.socials[k]) base.socials[k] = v;
    }
  }

  try {
    const provenance: Record<string, ExtractionSource> = {};
    const base: Partial<ContactCandidate> = { emails: [], phones: [], socials: {} };

    let structured = false;

    const vcard = await tryVcard();
    if (vcard) { merge(base, vcard, "vcard", provenance); structured = true; }

    const hcard = tryHcard();
    if (hcard) { merge(base, hcard, "hcard", provenance); structured = true; }

    const jsonld = tryJsonLd();
    if (jsonld) { merge(base, jsonld, "jsonld", provenance); structured = true; }

    const heur = tryHeuristic();
    merge(base, heur, "scraped", provenance);

    // Tag every email/phone item with its source for the per-field badge.
    const candidate: ContactCandidate = {
      display_name: base.display_name || null,
      given_name:   base.given_name || null,
      family_name:  base.family_name || null,
      organization: base.organization || null,
      job_title:    base.job_title || null,
      website:      base.website || null,
      notes:        base.notes || null,
      emails:       base.emails || [],
      phones:       base.phones || [],
      socials:      base.socials || {},
      source_url:   location.href,
      source_title: document.title || "",
      provenance,
      structured,
    };
    return { ok: true, candidate };
  } catch (e) {
    return { ok: false, error: (e as Error)?.message || "extract failed" };
  }
})();
