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
  DEFAULT_IDLE_TIMEOUT_MS,
  getBiometricEnabled,
  getBiometricPromptDismissed,
  getIdleTimeoutMs,
  getStoredUser,
  getToken,
  setBiometricEnabled as persistBiometricEnabled,
  setBiometricPromptDismissed as persistBiometricPromptDismissed,
  setIdleTimeoutMs as persistIdleTimeoutMs,
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
  // Idle re-lock window in ms while the app is in the foreground.
  // 0 means the idle timer is disabled (only background re-lock applies).
  idleTimeoutMs: number;
  // While the idle timer is about to fire, this holds the whole-second
  // countdown shown by the warning banner. `null` means no warning visible.
  lockWarningSecondsRemaining: number | null;
};

// How far ahead of the auto-lock we surface the warning banner. Capped
// against half the configured idle window so a very short window still
// gets a brief unobtrusive countdown rather than being all-warning.
const LOCK_WARNING_LEAD_MS = 10_000;

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
  setIdleTimeoutMs: (ms: number) => Promise<void>;
  noteActivity: () => void;
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
    idleTimeoutMs: DEFAULT_IDLE_TIMEOUT_MS,
    lockWarningSecondsRemaining: null,
  });
  const appStateRef = useRef<AppStateStatus>(AppState.currentState);
  // Tracks the last user-interaction timestamp. Updated on touch/navigation
  // via `noteActivity` and read by the idle re-lock timer.
  const lastActivityRef = useRef<number>(Date.now());

  // Initial load: read token, user, biometric pref, and capability.
  // If we have a session AND biometric unlock is enabled AND device still
  // supports it, start in `locked` state so the gate screen redirects to
  // the lock screen before any authenticated UI is shown.
  useEffect(() => {
    let cancelled = false;
    (async () => {
      const [token, user, biometricEnabled, capability, idleTimeoutMs] =
        await Promise.all([
          getToken(),
          getStoredUser<AuthUser>(),
          getBiometricEnabled(),
          getBiometricCapability(),
          getIdleTimeoutMs(),
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
        idleTimeoutMs,
        lockWarningSecondsRemaining: null,
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

  const noteActivity = useCallback(() => {
    lastActivityRef.current = Date.now();
    setState((s) =>
      s.lockWarningSecondsRemaining == null
        ? s
        : { ...s, lockWarningSecondsRemaining: null },
    );
  }, []);

  // Foreground idle re-lock: while signed-in with biometric unlock on and
  // an idle timeout configured, re-lock if no activity has happened within
  // the window. We use a self-rescheduling timer that checks elapsed time
  // against `lastActivityRef`, so individual taps don't churn timers.
  useEffect(() => {
    if (
      !state.token ||
      !state.biometricEnabled ||
      state.locked ||
      state.idleTimeoutMs <= 0
    ) {
      return;
    }
    let cancelled = false;
    let timeoutId: ReturnType<typeof setTimeout> | null = null;
    // Treat the moment the timer is (re)armed as fresh activity so a user
    // who just unlocked or just toggled the setting gets a full window.
    lastActivityRef.current = Date.now();
    // Cap the lead time so a tiny configured idle window still leaves
    // some pre-warning quiet period.
    const leadMs = Math.min(
      LOCK_WARNING_LEAD_MS,
      Math.max(1000, Math.floor(state.idleTimeoutMs / 2)),
    );
    const tick = () => {
      if (cancelled) return;
      const elapsed = Date.now() - lastActivityRef.current;
      const remaining = state.idleTimeoutMs - elapsed;
      if (remaining <= 0) {
        setState((s) => {
          if (!s.token || !s.biometricEnabled || s.locked) {
            return s.lockWarningSecondsRemaining == null
              ? s
              : { ...s, lockWarningSecondsRemaining: null };
          }
          return { ...s, locked: true, lockWarningSecondsRemaining: null };
        });
        return;
      }
      if (remaining <= leadMs) {
        // Inside the warning window — surface the countdown and re-tick
        // every second until the lock fires (or activity resets us).
        const seconds = Math.max(1, Math.ceil(remaining / 1000));
        setState((s) =>
          s.lockWarningSecondsRemaining === seconds
            ? s
            : { ...s, lockWarningSecondsRemaining: seconds },
        );
        const nextDelay = remaining - (seconds - 1) * 1000;
        timeoutId = setTimeout(tick, Math.max(50, nextDelay));
        return;
      }
      // Still well before the warning window — sleep until just before it.
      timeoutId = setTimeout(tick, remaining - leadMs + 50);
    };
    const initialDelay = Math.max(
      50,
      state.idleTimeoutMs - leadMs + 50,
    );
    timeoutId = setTimeout(tick, initialDelay);
    return () => {
      cancelled = true;
      if (timeoutId) clearTimeout(timeoutId);
      // The idle timer is being torn down (sign-out, biometric toggle,
      // window changed, lock fired, etc.) — drop any stale countdown so
      // the banner can't linger after the lock condition is gone.
      setState((s) =>
        s.lockWarningSecondsRemaining == null
          ? s
          : { ...s, lockWarningSecondsRemaining: null },
      );
    };
  }, [state.token, state.biometricEnabled, state.locked, state.idleTimeoutMs]);

  const setIdleTimeoutMs = useCallback(async (ms: number) => {
    const clamped = Math.max(0, Math.floor(ms));
    await persistIdleTimeoutMs(clamped);
    lastActivityRef.current = Date.now();
    setState((s) => ({ ...s, idleTimeoutMs: clamped }));
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
      lockWarningSecondsRemaining: null,
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
      lockWarningSecondsRemaining: null,
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
    setState((s) => ({
      ...s,
      biometricEnabled: false,
      locked: false,
      lockWarningSecondsRemaining: null,
    }));
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
        lockWarningSecondsRemaining: null,
      }));
      return {
        ok: false as const,
        reason: "unavailable",
        message: "Biometrics are no longer available on this device.",
      };
    }
    const res = await promptBiometric("Unlock 1INME");
    if (!res.ok) return res;
    setState((s) => ({
      ...s,
      locked: false,
      lockWarningSecondsRemaining: null,
    }));
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
      setIdleTimeoutMs,
      noteActivity,
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
      setIdleTimeoutMs,
      noteActivity,
    ],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): Ctx {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside <AuthProvider>");
  return ctx;
}
