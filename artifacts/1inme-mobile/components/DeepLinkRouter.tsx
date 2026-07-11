import { useQueryClient } from "@tanstack/react-query";
import * as Linking from "expo-linking";
import * as WebBrowser from "expo-web-browser";
import { useRouter } from "expo-router";
import { useEffect } from "react";

import { useAuth } from "@/contexts/AuthContext";
import { getBiolink } from "@/lib/api/biolinks";

// Hostnames whose `/{single-segment}` URLs we even consider routing as
// biolinks. Anything else is left to the OS/browser. Keeps the app from
// hijacking arbitrary https URLs that happen to reach it.
const APP_HOSTS = new Set<string>(["sayzio.app", "www.sayzio.app", "1in.me", "www.1in.me"]);

// First-segment paths the website owns that are NOT biolink aliases. The
// list mirrors the regex on the web's catch-all routes plus a few defensive
// extras. Matching is case-insensitive.
//
// We still do not trust this list as the only check — a probe to the
// backend confirms whether the alias resolves to a real public biolink
// before we route in-app, and otherwise we hand the URL back to the
// browser via expo-web-browser (which bypasses app-link interception).
const APP_RESERVED = new Set<string>([
  "",
  "user", "admin", "qr", "storage", "sanctum", "api", "f", "webhooks",
  "login", "register", "logout",
  "features", "how-it-works", "about", "contact", "faqs",
  "terms", "refunds", "privacy", "gdpr", "cookies",
  "discovery", "creators", "creators-feed", "feed",
  "workspace-team", "buzz", "docs", "newsletter",
  "pricing", "plans", "blog", "help", "support",
  "sitemap", "robots.txt", "manifest.json", "favicon.ico",
  "biolink", "oauth-callback", "info", "dialer", "links", "call", "import-url",
]);

type Parsed = { host: string | null; alias: string | null };

export function _aliasFromUrl(url: string): Parsed {
  try {
    const parsed = Linking.parse(url);
    // Custom-scheme deep links (`sayzio://...`) are routed by expo-router
    // itself; nothing to do here.
    if (parsed.scheme && parsed.scheme !== "https" && parsed.scheme !== "http") {
      return { host: null, alias: null };
    }
    const host = (parsed.hostname ?? "").toLowerCase();
    if (!APP_HOSTS.has(host)) return { host: host || null, alias: null };

    const path = (parsed.path ?? "").replace(/^\/+|\/+$/g, "");
    if (!path) return { host, alias: null };
    const segments = path.split("/");
    if (segments.length !== 1) return { host, alias: null };
    const seg = segments[0]!;
    if (APP_RESERVED.has(seg.toLowerCase())) return { host, alias: null };
    if (!/^[a-zA-Z0-9._-]{1,64}$/.test(seg)) return { host, alias: null };
    return { host, alias: seg };
  } catch {
    return { host: null, alias: null };
  }
}

// True for the post-payment hand-off deep link the website fires after a
// successful upgrade completed in the external browser
// (`sayzio://billing/refresh`). Parsed with expo-linking so scheme/host/path
// casing and stray slashes are all normalized.
export function _isBillingRefreshUrl(url: string): boolean {
  try {
    const parsed = Linking.parse(url);
    if (parsed.scheme !== "sayzio") return false;
    const host = (parsed.hostname ?? "").toLowerCase();
    const path = (parsed.path ?? "").replace(/^\/+|\/+$/g, "").toLowerCase();
    return host === "billing" && path === "refresh";
  } catch {
    return false;
  }
}

export function DeepLinkRouter() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const { refresh } = useAuth();

  useEffect(() => {
    let cancelled = false;

    async function handle(url: string | null) {
      if (cancelled || !url) return;

      // Post-payment hand-off: the website fires sayzio://billing/refresh once
      // an upgrade completes in the external browser. Invalidate the cached
      // billing queries and re-pull the signed-in user so the app reflects the
      // new plan immediately instead of waiting for a manual pull-to-refresh.
      // (`+native-intent.ts` routes this link to the billing/plans screen.)
      if (_isBillingRefreshUrl(url)) {
        await Promise.allSettled([
          queryClient.invalidateQueries({ queryKey: ["billing", "plans"] }),
          queryClient.invalidateQueries({
            queryKey: ["billing", "subscription"],
          }),
          refresh(),
        ]);
        return;
      }

      const { host, alias } = _aliasFromUrl(url);
      if (!alias) return;
      // Probe the backend before routing in-app. If the alias doesn't
      // resolve to a public biolink (404, auth-gated, or any other failure
      // we can't recover from in-app), kick the URL back to the browser
      // so the website can handle it. expo-web-browser uses Custom Tabs
      // / SFSafariViewController, which bypasses our app-link intent
      // filter and won't loop back into the app.
      try {
        await getBiolink(alias);
        if (cancelled) return;
        router.push(`/biolink/${alias}` as never);
      } catch {
        if (cancelled) return;
        if (host) {
          try {
            await WebBrowser.openBrowserAsync(url);
          } catch {
            /* noop — last-resort, do nothing */
          }
        }
      }
    }

    Linking.getInitialURL().then((u) => handle(u));
    const sub = Linking.addEventListener("url", ({ url }) => {
      void handle(url);
    });
    return () => {
      cancelled = true;
      sub.remove();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return null;
}
