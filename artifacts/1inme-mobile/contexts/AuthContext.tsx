import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import { AppState, type AppStateStatus } from "react-native";

import { apiFetch } from "@/lib/api";
import {
  getBiometricCapability,
  promptBiometric,
  type BiometricCapability,
} from "@/lib/biometrics";
import {
  getBiometricEnabled,
  getBiometricPromptDismissed,
  getStoredUser,
  getToken,
  setBiometricEnabled as persistBiometricEnabled,
  setBiometricPromptDismissed as persistBiometricPromptDismissed,
  setStoredUser,
  setToken,
} from "@/lib/secure";

export type AuthUser = {
  id: number | string;
  display_name?: string | null;
  email?: string | null;
  mobile?: string | null;
  role?: string | null;
  avatar_url?: string | null;
};

type AuthState = {
  ready: boolean;
  user: AuthUser | null;
  token: string | null;
  locked: boolean;
  biometricEnabled: boolean;
  biometricCapability: BiometricCapability | null;
};

type Ctx = AuthState & {
  signOut: () => Promise<void>;
  applySession: (token: string, user: AuthUser) => Promise<void>;
  refresh: () => Promise<void>;
  sendOtp: (input: {
    channel: "email" | "mobile";
    identifier: string;
  }) => Promise<void>;
  verifyOtp: (input: {
    channel: "email" | "mobile";
    identifier: string;
    code: string;
  }) => Promise<void>;
  demoLogin: (role?: "user" | "admin" | "super_admin") => Promise<void>;
  socialLogin: (input: {
    provider: "google" | "apple";
    id_token?: string;
    access_token?: string;
  }) => Promise<void>;
  enableBiometricUnlock: () => Promise<
    { ok: true } | { ok: false; reason: string; message?: string }
  >;
  disableBiometricUnlock: () => Promise<void>;
  unlockWithBiometrics: () => Promise<
    { ok: true } | { ok: false; reason: string; message?: string }
  >;
  refreshBiometricCapability: () => Promise<BiometricCapability>;
  shouldOfferBiometricEnrollment: () => Promise<boolean>;
  dismissBiometricEnrollmentPrompt: () => Promise<void>;
};

const AuthContext = createContext<Ctx | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [state, setState] = useState<AuthState>({
    ready: false,
    user: null,
    token: null,
    locked: false,
    biometricEnabled: false,
    biometricCapability: null,
  });
  const appStateRef = useRef<AppStateStatus>(AppState.currentState);

  // Initial load: read token, user, biometric pref, and capability.
  // If we have a session AND biometric unlock is enabled AND device still
  // supports it, start in `locked` state so the gate screen redirects to
  // the lock screen before any authenticated UI is shown.
  useEffect(() => {
    let cancelled = false;
    (async () => {
      const [token, user, biometricEnabled, capability] = await Promise.all([
        getToken(),
        getStoredUser<AuthUser>(),
        getBiometricEnabled(),
        getBiometricCapability(),
      ]);
      if (cancelled) return;

      let enabled = biometricEnabled;
      // If biometrics were enabled previously but the device no longer
      // supports them (hardware removed, all enrollments cleared), treat
      // as disabled and clear the persisted flag — task spec edge case.
      if (enabled && !capability.supported) {
        enabled = false;
        await persistBiometricEnabled(false);
      }
      const locked = !!token && enabled;
      setState({
        ready: true,
        token: token ?? null,
        user: user ?? null,
        locked,
        biometricEnabled: enabled,
        biometricCapability: capability,
      });
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  // Re-lock on return from background when biometric unlock is enabled.
  useEffect(() => {
    const sub = AppState.addEventListener("change", (next) => {
      const prev = appStateRef.current;
      appStateRef.current = next;
      if (
        (prev === "background" || prev === "inactive") &&
        next === "active"
      ) {
        setState((s) => {
          if (!s.token || !s.biometricEnabled) return s;
          if (s.locked) return s;
          return { ...s, locked: true };
        });
      }
    });
    return () => sub.remove();
  }, []);

  const applySession = useCallback(async (token: string, user: AuthUser) => {
    await Promise.all([setToken(token), setStoredUser(user)]);
    setState((s) => ({
      ...s,
      ready: true,
      token,
      user,
      // Fresh sign-in counts as already unlocked.
      locked: false,
    }));
  }, []);

  // Re-pull the signed-in user from the API and persist it locally.
  // Called after server-side state changes (e.g. RevenueCat activation
  // bumps the plan) so any cached `user.plan_id` reflects reality.
  const refresh = useCallback(async () => {
    try {
      const res = await apiFetch<{ user: AuthUser }>("/auth/me");
      if (res?.user) {
        await setStoredUser(res.user);
        setState((s) => ({ ...s, ready: true, user: res.user }));
      }
    } catch {
      /* swallow — refresh is best-effort */
    }
  }, []);

  const signOut = useCallback(async () => {
    try {
      await apiFetch("/auth/logout", { method: "POST" });
    } catch {}
    // Signing out also clears the biometric preference and the
    // "don't ask again" flag — next sign-in starts from a clean slate.
    await Promise.all([
      setToken(null),
      setStoredUser(null),
      persistBiometricEnabled(false),
      persistBiometricPromptDismissed(false),
    ]);
    setState((s) => ({
      ...s,
      ready: true,
      token: null,
      user: null,
      locked: false,
      biometricEnabled: false,
    }));
  }, []);

  const sendOtp = useCallback(
    async (input: { channel: "email" | "mobile"; identifier: string }) => {
      await apiFetch("/auth/otp/send", {
        method: "POST",
        body: JSON.stringify({
          channel: input.channel,
          [input.channel]: input.identifier,
        }),
      });
    },
    [],
  );

  const verifyOtp = useCallback(
    async (input: {
      channel: "email" | "mobile";
      identifier: string;
      code: string;
    }) => {
      const res = await apiFetch<{ token: string; user: AuthUser }>(
        "/auth/otp/verify",
        {
          method: "POST",
          body: JSON.stringify({
            channel: input.channel,
            [input.channel]: input.identifier,
            code: input.code,
          }),
        },
      );
      await applySession(res.token, res.user);
    },
    [applySession],
  );

  const demoLogin = useCallback(
    async (role: "user" | "admin" | "super_admin" = "user") => {
      const res = await apiFetch<{ token: string; user: AuthUser }>(
        "/auth/demo",
        {
          method: "POST",
          body: JSON.stringify({ role }),
        },
      );
      await applySession(res.token, res.user);
    },
    [applySession],
  );

  const socialLogin = useCallback(
    async (input: {
      provider: "google" | "apple";
      id_token?: string;
      access_token?: string;
    }) => {
      const res = await apiFetch<{ token: string; user: AuthUser }>(
        "/auth/social",
        { method: "POST", body: JSON.stringify(input) },
      );
      await applySession(res.token, res.user);
    },
    [applySession],
  );

  const refreshBiometricCapability = useCallback(async () => {
    const cap = await getBiometricCapability();
    setState((s) => ({ ...s, biometricCapability: cap }));
    return cap;
  }, []);

  const enableBiometricUnlock = useCallback(async () => {
    const cap = await getBiometricCapability();
    setState((s) => ({ ...s, biometricCapability: cap }));
    if (!cap.supported) {
      return {
        ok: false as const,
        reason: "unavailable",
        message: !cap.hasHardware
          ? "This device doesn't support biometric unlock."
          : "Set up a fingerprint or face in your device settings first.",
      };
    }
    const res = await promptBiometric(`Confirm to enable ${cap.label}`);
    if (!res.ok) return res;
    await persistBiometricEnabled(true);
    setState((s) => ({ ...s, biometricEnabled: true }));
    return { ok: true as const };
  }, []);

  const disableBiometricUnlock = useCallback(async () => {
    await persistBiometricEnabled(false);
    setState((s) => ({ ...s, biometricEnabled: false, locked: false }));
  }, []);

  const unlockWithBiometrics = useCallback(async () => {
    const cap = await getBiometricCapability();
    setState((s) => ({ ...s, biometricCapability: cap }));
    // If the device's biometric setup changed since enabling (e.g. all
    // fingerprints removed), treat as disabled and force a fresh login.
    if (!cap.supported) {
      await persistBiometricEnabled(false);
      await Promise.all([setToken(null), setStoredUser(null)]);
      setState((s) => ({
        ...s,
        biometricEnabled: false,
        token: null,
        user: null,
        locked: false,
      }));
      return {
        ok: false as const,
        reason: "unavailable",
        message: "Biometrics are no longer available on this device.",
      };
    }
    const res = await promptBiometric("Unlock 1INME");
    if (!res.ok) return res;
    setState((s) => ({ ...s, locked: false }));
    return { ok: true as const };
  }, []);

  const shouldOfferBiometricEnrollment = useCallback(async () => {
    const [cap, dismissed, enabled] = await Promise.all([
      getBiometricCapability(),
      getBiometricPromptDismissed(),
      getBiometricEnabled(),
    ]);
    setState((s) => ({ ...s, biometricCapability: cap }));
    return cap.supported && !dismissed && !enabled;
  }, []);

  const dismissBiometricEnrollmentPrompt = useCallback(async () => {
    await persistBiometricPromptDismissed(true);
  }, []);

  const value = useMemo<Ctx>(
    () => ({
      ...state,
      signOut,
      applySession,
      refresh,
      sendOtp,
      verifyOtp,
      demoLogin,
      socialLogin,
      enableBiometricUnlock,
      disableBiometricUnlock,
      unlockWithBiometrics,
      refreshBiometricCapability,
      shouldOfferBiometricEnrollment,
      dismissBiometricEnrollmentPrompt,
    }),
    [
      state,
      signOut,
      applySession,
      refresh,
      sendOtp,
      verifyOtp,
      demoLogin,
      socialLogin,
      enableBiometricUnlock,
      disableBiometricUnlock,
      unlockWithBiometrics,
      refreshBiometricCapability,
      shouldOfferBiometricEnrollment,
      dismissBiometricEnrollmentPrompt,
    ],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): Ctx {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside <AuthProvider>");
  return ctx;
}
