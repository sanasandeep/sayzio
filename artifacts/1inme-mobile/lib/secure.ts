import * as SecureStore from "expo-secure-store";
import { Platform } from "react-native";

const TOKEN_KEY = "1inme.auth.token";
const USER_KEY = "1inme.auth.user";
const ONBOARDING_KEY = "1inme.onboarding.complete";
const THEME_KEY = "1inme.theme";
const BIOMETRIC_ENABLED_KEY = "1inme.auth.biometric.enabled";
const BIOMETRIC_PROMPT_DISMISSED_KEY = "1inme.auth.biometric.prompt.dismissed";
const IDLE_TIMEOUT_MS_KEY = "1inme.auth.idle.timeout.ms";

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
    await SecureStore.deleteItemAsync(key);
    return;
  }
  if (Platform.OS === "web") {
    try {
      window.localStorage.setItem(key, value);
    } catch {}
    return;
  }
  await SecureStore.setItemAsync(key, value);
}

async function getItem(key: string): Promise<string | null> {
  if (Platform.OS === "web") {
    try {
      return window.localStorage.getItem(key);
    } catch {
      return null;
    }
  }
  return SecureStore.getItemAsync(key);
}

export const getToken = () => getItem(TOKEN_KEY);
export const setToken = (v: string | null) => setItem(TOKEN_KEY, v);

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
