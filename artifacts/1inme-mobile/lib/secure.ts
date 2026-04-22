import * as SecureStore from "expo-secure-store";
import { Platform } from "react-native";

const TOKEN_KEY = "1inme.auth.token";
const USER_KEY = "1inme.auth.user";
const ONBOARDING_KEY = "1inme.onboarding.complete";
const THEME_KEY = "1inme.theme";
const BIOMETRIC_ENABLED_KEY = "1inme.auth.biometric.enabled";
const BIOMETRIC_PROMPT_DISMISSED_KEY = "1inme.auth.biometric.prompt.dismissed";

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
