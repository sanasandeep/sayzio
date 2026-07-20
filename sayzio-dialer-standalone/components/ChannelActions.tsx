import { Feather } from "@expo/vector-icons";
import AsyncStorage from "@react-native-async-storage/async-storage";
import { type ComponentProps, useEffect, useSyncExternalStore } from "react";
import { Alert, Linking, Platform, Pressable, View } from "react-native";

import { type DialerChannelDef, getDialerChannels } from "@/lib/api/dialer";
import { placeRealCall } from "@/lib/placeCall";
import { ZioTelephony } from "@/modules/zio-telephony";

// ── Direct channel actions (shared across every dialer surface) ──────
// Config-independent device handoffs (tel/sms/wa.me/t.me/signal.me/viber) —
// no Google Contacts or any integration needed. The channel catalog + the
// user's preferred (enabled) channels are the single source of truth shared
// with the web dialer via the server (App\Modules\User\Support\DialerChannels),
// so the keypad, favourites, frequent, recents, search, contacts, profile and
// call-screen rows never drift. There is no public API to check whether a
// number is registered on WhatsApp/Telegram; the buttons open the chat
// directly and the app itself reports unknown numbers.

export function digitsOf(v: string): string {
  return (v || "").replace(/[^0-9]/g, "");
}

async function openUrl(url: string): Promise<void> {
  try {
    await Linking.openURL(url);
  } catch {
    Alert.alert("Can't open", "No app is available to handle this action.");
  }
}

// ── WhatsApp vs WhatsApp Business ────────────────────────────────────
// When both apps are installed, honor the user's preference (set from the
// dialer's "Customize channels" sheet) or ask per handoff. Package-visibility
// <queries> entries come from plugins/withAndroidQueries.js.
export const WA_PACKAGE = "com.whatsapp";
export const WA_BUSINESS_PACKAGE = "com.whatsapp.w4b";
const WA_PREF_KEY = "1inme.dialer.waApp.v1";

/** "ask" (default) or the preferred WhatsApp package name. */
export type WaPref = "ask" | typeof WA_PACKAGE | typeof WA_BUSINESS_PACKAGE;

export async function getWaPref(): Promise<WaPref> {
  try {
    const raw = await AsyncStorage.getItem(WA_PREF_KEY);
    return raw === WA_PACKAGE || raw === WA_BUSINESS_PACKAGE ? raw : "ask";
  } catch {
    return "ask";
  }
}

export async function setWaPref(pref: WaPref): Promise<void> {
  try {
    await AsyncStorage.setItem(WA_PREF_KEY, pref);
  } catch {
    /* non-fatal */
  }
}

/** Both WhatsApp apps installed? (false when native probing is unavailable) */
export function hasBothWhatsAppApps(): boolean {
  if (Platform.OS !== "android" || !ZioTelephony) return false;
  try {
    return (
      ZioTelephony.isPackageInstalled(WA_PACKAGE) &&
      ZioTelephony.isPackageInstalled(WA_BUSINESS_PACKAGE)
    );
  } catch {
    return false;
  }
}

async function openWhatsAppUrl(url: string): Promise<void> {
  if (Platform.OS === "android" && ZioTelephony) {
    try {
      const wa = ZioTelephony.isPackageInstalled(WA_PACKAGE);
      const wab = ZioTelephony.isPackageInstalled(WA_BUSINESS_PACKAGE);
      if (wa && wab) {
        const pref = await getWaPref();
        if (pref !== "ask" && ZioTelephony.openUrlWithPackage(pref, url)) return;
        if (pref === "ask") {
          Alert.alert(
            "Open with",
            "You have both WhatsApp apps. Set a default from Customize channels.",
            [
              { text: "WhatsApp", onPress: () => void ZioTelephony?.openUrlWithPackage(WA_PACKAGE, url) },
              { text: "WhatsApp Business", onPress: () => void ZioTelephony?.openUrlWithPackage(WA_BUSINESS_PACKAGE, url) },
              { text: "Cancel", style: "cancel" },
            ],
            { cancelable: true },
          );
          return;
        }
      } else if (wab && !wa) {
        // Business-only phones: wa.me links otherwise dead-end in a browser.
        if (ZioTelephony.openUrlWithPackage(WA_BUSINESS_PACKAGE, url)) return;
      }
    } catch {
      /* fall through to the generic open */
    }
  }
  await openUrl(url);
}

/** Build + open the deep-link for a channel `js` mode and typed value. */
export function chanOpen(mode: string, v: string): void {
  const t = (v || "").trim();
  const d = digitsOf(v);
  let url = "";
  switch (mode) {
    case "tel":
      // Route through placeRealCall so the user's "Calling" preference
      // (Direct call vs Open phone app) is respected on every call surface.
      if (t) void placeRealCall(t);
      return;
    case "sms":    url = t ? `sms:${t}` : ""; break;
    case "wa":
      if (d) void openWhatsAppUrl(`https://wa.me/${d}`);
      return;
    case "tg":     url = d ? `https://t.me/+${d}` : ""; break;
    case "signal": url = d ? `https://signal.me/#p/+${d}` : ""; break;
    case "viber":  url = d ? `viber://chat?number=%2B${d}` : ""; break;
  }
  if (url) void openUrl(url);
}

// ── Username quick actions (abc keypad mode) ─────────────────────────
// Typed handles (not phone numbers) can be opened directly on apps with a
// public username scheme. WhatsApp has no username deep link, so it's not
// listed here — WhatsApp handoffs stay number-based via chanOpen("wa").
export type UsernameChannelDef = {
  key: string;
  label: string;
  color: string;
  feather: ComponentProps<typeof Feather>["name"];
  pkg: string;
};

export const USERNAME_CHANNELS: UsernameChannelDef[] = [
  { key: "telegram", label: "Telegram", color: "#3390ec", feather: "send", pkg: "org.telegram.messenger" },
  { key: "instagram", label: "Instagram", color: "#e1306c", feather: "instagram", pkg: "com.instagram.android" },
];

/** Sanitized handle from typed text, or null when it can't be a username. */
export function usernameOf(v: string): string | null {
  const t = (v || "").trim().replace(/^@/, "");
  if (!/^[a-zA-Z][a-zA-Z0-9._]{1,31}$/.test(t)) return null;
  if (!/[a-zA-Z]/.test(t)) return null;
  return t;
}

/** Open a typed username directly on Telegram / Instagram. */
export function chanOpenUsername(key: string, username: string): void {
  const u = usernameOf(username);
  if (!u) return;
  const def = USERNAME_CHANNELS.find((c) => c.key === key);
  if (!def) return;
  const url =
    key === "telegram" ? `https://t.me/${u}` : `https://instagram.com/${u}`;
  if (Platform.OS === "android" && ZioTelephony) {
    try {
      if (
        ZioTelephony.isPackageInstalled(def.pkg) &&
        ZioTelephony.openUrlWithPackage(def.pkg, url)
      ) {
        return;
      }
    } catch {
      /* fall through */
    }
  }
  void openUrl(url);
}

// Fallback catalog so channel rows work before the prefs fetch resolves (or
// offline). Mirrors the server DialerChannels catalog; the live payload
// overrides this once loaded.
export const FALLBACK_CHANNELS: DialerChannelDef[] = [
  { key: "call", label: "Call", short: "Call", color: "#22c55e", fa: "fas fa-phone", feather: "phone", js: "tel" },
  { key: "sms", label: "Text message", short: "Text", color: "#38bdf8", fa: "fas fa-comment-sms", feather: "message-square", js: "sms" },
  { key: "whatsapp", label: "Chat on WhatsApp", short: "WhatsApp", color: "#25d366", fa: "fab fa-whatsapp", feather: "message-circle", js: "wa" },
  { key: "telegram", label: "Open in Telegram", short: "Telegram", color: "#3390ec", fa: "fab fa-telegram", feather: "send", js: "tg" },
  { key: "signal", label: "Message on Signal", short: "Signal", color: "#3a76f0", fa: "fab fa-signal-messenger", feather: "shield", js: "signal" },
  { key: "viber", label: "Message on Viber", short: "Viber", color: "#7360f2", fa: "fab fa-viber", feather: "phone-forwarded", js: "viber" },
];
export const FALLBACK_ENABLED = ["call", "sms", "whatsapp", "telegram"];

export type ChannelPrefs = {
  catalog: DialerChannelDef[];
  enabled: string[];
};

// ── Tiny app-wide prefs store ────────────────────────────────────────
// Screens outside the dialer tab (search, contacts, profile, call) also render
// channel rows, so the resolved prefs live in a module-level store every
// mounted ChannelActions subscribes to. The first subscriber triggers the
// fetch; saving from the channel picker publishes the new selection so every
// visible row updates instantly.
let storedPrefs: ChannelPrefs = {
  catalog: FALLBACK_CHANNELS,
  enabled: FALLBACK_ENABLED,
};
let fetchStarted = false;
const listeners = new Set<() => void>();

function subscribe(listener: () => void): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

/** Push freshly-resolved prefs (initial fetch or picker save) to all rows. */
export function publishChannelPrefs(next: ChannelPrefs | null | undefined): void {
  if (!next?.catalog?.length) return;
  fetchStarted = true;
  storedPrefs = { catalog: next.catalog, enabled: next.enabled };
  listeners.forEach((l) => l());
}

/** Current prefs, live-updating; kicks off the server fetch on first use. */
export function useChannelPrefs(): ChannelPrefs {
  const prefs = useSyncExternalStore(subscribe, () => storedPrefs);
  useEffect(() => {
    if (fetchStarted) return;
    fetchStarted = true;
    getDialerChannels()
      .then(publishChannelPrefs)
      .catch(() => {
        // Offline / transient — allow a later mount to retry.
        fetchStarted = false;
      });
  }, []);
  return prefs;
}

/** Resolve enabled channel keys to full catalog rows, in preference order. */
export function resolveChannels(prefs: ChannelPrefs): DialerChannelDef[] {
  const byKey = new Map(prefs.catalog.map((c) => [c.key, c]));
  return prefs.enabled
    .map((k) => byKey.get(k))
    .filter((c): c is DialerChannelDef => !!c);
}

// A Feather name for a channel — the catalog carries a `feather` field, but it
// arrives as a plain string, so we cast to the icon-name union here.
export function featherName(
  c: DialerChannelDef,
): ComponentProps<typeof Feather>["name"] {
  return c.feather as ComponentProps<typeof Feather>["name"];
}

/**
 * Shared direct-action cluster (mirrors web user/dialer/_channel_actions.blade.php):
 * only the channels the user picked, rendered everywhere a phone number is
 * shown. Renders nothing for empty numbers so number-less rows never show
 * broken buttons.
 */
export function ChannelActions({
  number,
  size = "md",
  align = "center",
}: {
  number: string;
  size?: "sm" | "md";
  align?: "center" | "flex-start";
}) {
  const prefs = useChannelPrefs();
  const channels = resolveChannels(prefs);
  const n = (number || "").trim();
  if (!n || channels.length === 0) return null;
  const d = size === "sm" ? 26 : 32;
  const ico = size === "sm" ? 13 : 16;
  return (
    <View
      style={{
        flexDirection: "row",
        flexWrap: "wrap",
        gap: 6,
        justifyContent: align,
      }}
    >
      {channels.map((c) => (
        <Pressable
          key={c.key}
          onPress={() => chanOpen(c.js, n)}
          hitSlop={6}
          accessibilityLabel={c.label}
          style={{
            width: d,
            height: d,
            borderRadius: d / 2,
            alignItems: "center",
            justifyContent: "center",
            backgroundColor: `${c.color}22`,
          }}
        >
          <Feather name={featherName(c)} size={ico} color={c.color} />
        </Pressable>
      ))}
    </View>
  );
}
