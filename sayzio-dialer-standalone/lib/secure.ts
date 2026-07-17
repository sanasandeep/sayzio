import AsyncStorage from "@react-native-async-storage/async-storage";
import * as SecureStore from "expo-secure-store";
import { Platform } from "react-native";

const TOKEN_KEY = "1inme.auth.token";
const USER_KEY = "1inme.auth.user";
const NEEDS_NAME_KEY = "1inme.auth.needsName";
const ONBOARDING_KEY = "1inme.onboarding.complete";
const THEME_KEY = "1inme.theme";
const BIOMETRIC_ENABLED_KEY = "1inme.auth.biometric.enabled";
const VOICE_WAKE_WORD_ENABLED_KEY = "1inme.voice.wakeWord.enabled";
const BIOMETRIC_PROMPT_DISMISSED_KEY = "1inme.auth.biometric.prompt.dismissed";
const IDLE_TIMEOUT_MS_KEY = "1inme.auth.idle.timeout.ms";
const LAST_CUSTOM_IDLE_TIMEOUT_MS_KEY = "1inme.auth.idle.timeout.lastCustom.ms";
const LOCK_WARNING_LEAD_MS_KEY = "1inme.auth.lock.warning.lead.ms";
const AUTO_SHORTEN_ENABLED_KEY = "1inme.import.autoShorten";

// Default idle re-lock window when biometric unlock is on. 5 minutes feels
// like a reasonable middle-ground between security and convenience.
export const DEFAULT_IDLE_TIMEOUT_MS = 5 * 60 * 1000;
// Allowed presets shown in Settings (plus 0 = off).
export const IDLE_TIMEOUT_PRESETS_MS = [
  0,
  2 * 60 * 1000,
  5 * 60 * 1000,
  10 * 60 * 1000,
] as const;

// Bounds for the "Custom…" picker. 15 seconds is short enough for a kiosk
// device but long enough to avoid accidental re-locks; 60 minutes is the
// upper end before "auto-lock" stops feeling like a meaningful guard.
export const IDLE_TIMEOUT_CUSTOM_MIN_MS = 15 * 1000;
export const IDLE_TIMEOUT_CUSTOM_MAX_MS = 60 * 60 * 1000;

// Format an idle-timeout duration into a short human-friendly string used
// in helper text and segmented-control labels. 0 maps to "Off".
export function formatIdleTimeout(ms: number): string {
  if (ms <= 0) return "Off";
  const totalSec = Math.round(ms / 1000);
  if (totalSec < 60) return `${totalSec} sec`;
  const min = Math.floor(totalSec / 60);
  const sec = totalSec % 60;
  if (sec === 0) return `${min} min`;
  return `${min} min ${sec} sec`;
}

async function setItem(key: string, value: string | null) {
  if (value == null) {
    if (Platform.OS === "web") {
      try {
        window.localStorage.removeItem(key);
      } catch {}
      return;
    }
    try {
      await SecureStore.deleteItemAsync(key);
    } catch {
      // Deletion failure is non-fatal — the key is already gone or unreadable.
    }
    return;
  }
  if (Platform.OS === "web") {
    try {
      window.localStorage.setItem(key, value);
    } catch {}
    return;
  }
  try {
    await SecureStore.setItemAsync(key, value);
  } catch (e) {
    if (__DEV__) console.warn(`[secure] setItem("${key}") failed:`, e);
  }
}

async function getItem(key: string): Promise<string | null> {
  if (Platform.OS === "web") {
    try {
      return window.localStorage.getItem(key);
    } catch {
      return null;
    }
  }
  try {
    return await SecureStore.getItemAsync(key);
  } catch (e) {
    // On Android the keystore can become unreadable after an OS upgrade, a
    // factory reset that didn't fully wipe secure storage, or a corrupted
    // Keymaster state. Treat any read failure as a missing key so the app
    // degrades gracefully to a logged-out / first-run state instead of
    // crashing. Attempt a best-effort deletion so subsequent boots skip the
    // failed read path.
    if (__DEV__) console.warn(`[secure] getItem("${key}") failed, treating as null:`, e);
    try { await SecureStore.deleteItemAsync(key); } catch {}
    return null;
  }
}

export const getToken = () => getItem(TOKEN_KEY);
export const setToken = (v: string | null) => setItem(TOKEN_KEY, v);

// Sticky "this account still needs a display name" flag. Set true right
// after an account is auto-created (OTP verify / social sign-in returns
// `needs_name`), and cleared only once the user actually submits a name.
// Persisted alongside the token so the mandatory name prompt survives a
// dismissed modal, a backgrounded app, or a full cold launch — the
// profile/me endpoint intentionally does NOT echo this flag, so it can't
// be recovered from the server on the mobile (token) auth path.
export const getNeedsName = async () =>
  (await getItem(NEEDS_NAME_KEY)) === "1";
export const setNeedsName = (v: boolean) =>
  setItem(NEEDS_NAME_KEY, v ? "1" : null);

export const getStoredUser = async <T = unknown>(): Promise<T | null> => {
  const raw = await getItem(USER_KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as T;
  } catch {
    return null;
  }
};
export const setStoredUser = (user: unknown | null) =>
  setItem(USER_KEY, user ? JSON.stringify(user) : null);

export const getOnboardingComplete = async () =>
  (await getItem(ONBOARDING_KEY)) === "1";
export const setOnboardingComplete = (v: boolean) =>
  setItem(ONBOARDING_KEY, v ? "1" : null);

export type ThemePref = "system" | "light" | "dark";
export const getThemePref = async (): Promise<ThemePref> => {
  const v = await getItem(THEME_KEY);
  if (v === "light" || v === "dark") return v;
  return "system";
};
export const setThemePref = (v: ThemePref) =>
  setItem(THEME_KEY, v === "system" ? null : v);

export const getBiometricEnabled = async () =>
  (await getItem(BIOMETRIC_ENABLED_KEY)) === "1";
export const setBiometricEnabled = (v: boolean) =>
  setItem(BIOMETRIC_ENABLED_KEY, v ? "1" : null);

// Wake-word listening for the Voice Assistant. Off by default — the
// user must explicitly opt in from Settings because it keeps the mic
// hot in the foreground.
export const getVoiceWakeWordEnabled = async () =>
  (await getItem(VOICE_WAKE_WORD_ENABLED_KEY)) === "1";
export const setVoiceWakeWordEnabled = async (v: boolean) => {
  await setItem(VOICE_WAKE_WORD_ENABLED_KEY, v ? "1" : null);
  // Notify in-process subscribers (e.g. the floating Voice Assistant)
  // so toggling from Settings starts/stops the wake loop immediately
  // rather than waiting for the next mount or focus cycle.
  for (const fn of voiceWakeWordListeners) {
    try { fn(v); } catch { /* noop */ }
  }
};

const voiceWakeWordListeners = new Set<(v: boolean) => void>();
export function onVoiceWakeWordEnabledChange(
  listener: (v: boolean) => void,
): () => void {
  voiceWakeWordListeners.add(listener);
  return () => voiceWakeWordListeners.delete(listener);
}

export const getBiometricPromptDismissed = async () =>
  (await getItem(BIOMETRIC_PROMPT_DISMISSED_KEY)) === "1";
export const setBiometricPromptDismissed = (v: boolean) =>
  setItem(BIOMETRIC_PROMPT_DISMISSED_KEY, v ? "1" : null);

// Persisted idle timeout in ms. 0 = off. Returns the default when the
// key is unset or unparseable, and clamps negative values to the default.
// Any other non-negative integer is honored as-is so a future "Custom…"
// option can persist arbitrary values without needing to extend presets.
export const getIdleTimeoutMs = async (): Promise<number> => {
  const raw = await getItem(IDLE_TIMEOUT_MS_KEY);
  if (raw == null) return DEFAULT_IDLE_TIMEOUT_MS;
  const n = Number.parseInt(raw, 10);
  if (!Number.isFinite(n) || n < 0) return DEFAULT_IDLE_TIMEOUT_MS;
  return n;
};
export const setIdleTimeoutMs = (ms: number) =>
  setItem(IDLE_TIMEOUT_MS_KEY, String(Math.max(0, Math.floor(ms))));

// Most recently chosen "Custom…" auto-lock value, persisted separately from
// the active timeout so the segmented control can offer one-tap recall after
// the user switches to a built-in preset. Returns null when nothing has been
// stored or the stored value is unparseable / non-positive.
export const getLastCustomIdleTimeoutMs = async (): Promise<number | null> => {
  const raw = await getItem(LAST_CUSTOM_IDLE_TIMEOUT_MS_KEY);
  if (raw == null) return null;
  const n = Number.parseInt(raw, 10);
  if (!Number.isFinite(n) || n <= 0) return null;
  return n;
};
export const setLastCustomIdleTimeoutMs = (ms: number) =>
  setItem(
    LAST_CUSTOM_IDLE_TIMEOUT_MS_KEY,
    String(Math.max(0, Math.floor(ms))),
  );

// How far ahead of the auto-lock the warning banner appears. The idle
// timer caps this against half the configured idle window, so the
// persisted choice is just the user's preferred upper bound.
export const DEFAULT_LOCK_WARNING_LEAD_MS = 10_000;
export const LOCK_WARNING_LEAD_PRESETS_MS = [
  5_000,
  10_000,
  30_000,
] as const;

// Format a lock-warning lead duration for compact labels (e.g. "5s").
export function formatLockWarningLead(ms: number): string {
  const totalSec = Math.max(1, Math.round(ms / 1000));
  if (totalSec < 60) return `${totalSec}s`;
  const min = Math.floor(totalSec / 60);
  const sec = totalSec % 60;
  return sec === 0 ? `${min}m` : `${min}m ${sec}s`;
}

// Persisted lock-warning lead in ms. Falls back to the default when
// unset/unparseable, and clamps non-positive stored values to the default
// so a corrupt/zero value can never silently disable the warning.
export const getLockWarningLeadMs = async (): Promise<number> => {
  const raw = await getItem(LOCK_WARNING_LEAD_MS_KEY);
  if (raw == null) return DEFAULT_LOCK_WARNING_LEAD_MS;
  const n = Number.parseInt(raw, 10);
  if (!Number.isFinite(n) || n <= 0) return DEFAULT_LOCK_WARNING_LEAD_MS;
  return n;
};
export const setLockWarningLeadMs = (ms: number) =>
  setItem(
    LOCK_WARNING_LEAD_MS_KEY,
    String(Math.max(1000, Math.floor(ms))),
  );

// Whether the Import screen should auto-shorten a shared URL immediately
// on arrival (on by default). The user can toggle this from the Import
// screen; the preference is remembered per-device.
export const getAutoShortenEnabled = async (): Promise<boolean> => {
  const v = await getItem(AUTO_SHORTEN_ENABLED_KEY);
  // Default true — opted out only when explicitly stored as "0".
  return v !== "0";
};
export const setAutoShortenEnabled = (v: boolean) =>
  setItem(AUTO_SHORTEN_ENABLED_KEY, v ? null : "0");

// ---------------------------------------------------------------------------
// Onboarding intro-slide cache.
//
// The last successfully fetched admin-managed slides are persisted so repeat
// launches (e.g. after the user resets the intro) render the real content
// instantly — even offline — instead of the bundled fallback set. Stored in
// AsyncStorage (not SecureStore): the payload is non-sensitive JSON that can
// exceed SecureStore's small per-item size limits on native.
// ---------------------------------------------------------------------------
const ONBOARDING_SLIDES_CACHE_KEY = "1inme.onboarding.slides.cache.v2";

// Minimal structural shape we validate before trusting a cached entry, kept
// intentionally loose so additive API fields never invalidate the cache.
type CachedSlideLike = {
  id: number;
  slug: string;
  category: string;
  title: string;
};

function isCachedSlideLike(s: unknown): s is CachedSlideLike {
  if (!s || typeof s !== "object") return false;
  const o = s as Record<string, unknown>;
  return (
    typeof o.id === "number" &&
    typeof o.slug === "string" &&
    typeof o.category === "string" &&
    typeof o.title === "string"
  );
}

// Returns the cached slide list, or null when nothing valid is stored
// (never throws — a corrupt entry is treated as a cache miss). Corrupt or
// structurally invalid entries are proactively deleted so they can't be
// re-parsed on every launch; individual malformed slides are filtered out
// while the remaining valid ones are still served.
export const getCachedOnboardingSlides = async <
  T extends CachedSlideLike = CachedSlideLike,
>(): Promise<T[] | null> => {
  try {
    const raw = await AsyncStorage.getItem(ONBOARDING_SLIDES_CACHE_KEY);
    if (!raw) return null;
    let parsed: unknown;
    try {
      parsed = JSON.parse(raw);
    } catch {
      // Corrupt JSON — clear it so future launches skip the parse attempt.
      await clearCorruptOnboardingSlidesCache();
      return null;
    }
    if (!Array.isArray(parsed) || parsed.length === 0) {
      // Wrong shape (object/string/…) or a useless empty array — clear it.
      await clearCorruptOnboardingSlidesCache();
      return null;
    }
    const valid = parsed.filter(isCachedSlideLike);
    if (valid.length === 0) {
      // Every entry is malformed — treat like a corrupt cache and clear.
      await clearCorruptOnboardingSlidesCache();
      return null;
    }
    return valid as T[];
  } catch {
    // Storage read failure — never let caching break the intro flow.
    return null;
  }
};

// Best-effort removal of a corrupt cache entry; failures are swallowed so
// a broken storage layer can never turn a cache miss into a crash.
async function clearCorruptOnboardingSlidesCache(): Promise<void> {
  try {
    await AsyncStorage.removeItem(ONBOARDING_SLIDES_CACHE_KEY);
  } catch {
    // noop
  }
}

// Persist the latest successfully fetched slides. Passing null/[] clears
// the cache. Best-effort: storage failures are swallowed so caching can
// never break the intro flow.
export const setCachedOnboardingSlides = async (
  slides: readonly CachedSlideLike[] | null,
): Promise<void> => {
  try {
    if (!slides || slides.length === 0) {
      await AsyncStorage.removeItem(ONBOARDING_SLIDES_CACHE_KEY);
      return;
    }
    await AsyncStorage.setItem(
      ONBOARDING_SLIDES_CACHE_KEY,
      JSON.stringify(slides),
    );
  } catch {
    // noop — cache is an optimization only.
  }
};
