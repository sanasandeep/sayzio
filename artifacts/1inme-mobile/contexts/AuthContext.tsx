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
import { clearPushRegistration } from "@/lib/push";
import {
  DEFAULT_IDLE_TIMEOUT_MS,
  DEFAULT_LOCK_WARNING_LEAD_MS,
  getBiometricEnabled,
  getBiometricPromptDismissed,
  getIdleTimeoutMs,
  getImpersonator,
  getLockWarningLeadMs,
  getStoredUser,
  getToken,
  setImpersonator,
  setBiometricEnabled as persistBiometricEnabled,
  setBiometricPromptDismissed as persistBiometricPromptDismissed,
  setIdleTimeoutMs as persistIdleTimeoutMs,
  setLockWarningLeadMs as persistLockWarningLeadMs,
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
  // ISO-8601 timestamp when the email was verified, or null/absent if not.
  // Drives the in-app "verify your email" reminder (mobile parity with web).
  email_verified_at?: string | null;
  // True only when email is a usable sign-in method under the current login
  // policy — mirrors the web banner's visibility rule so the nudge never
  // shows for accounts that can never meaningfully verify.
  email_verification_meaningful?: boolean;
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
  // User-chosen upper bound on how early the pre-lock warning surfaces.
  // The actual lead time is still capped against half the idle window,
  // so this is a preference rather than a guarantee.
  lockWarningLeadMs: number;
  // While the idle timer is about to fire, this holds the whole-second
  // countdown shown by the warning banner. `null` means no warning visible.
  lockWarningSecondsRemaining: number | null;
  // True while an admin operator is impersonating another user (the active
  // token belongs to the impersonated user). Drives the persistent "Viewing
  // as …" banner and the "Stop impersonating" action.
  impersonating: boolean;
  // Display name of the user currently being impersonated (for the banner).
  impersonatedName: string | null;
};

type Ctx = AuthState & {
  signOut: () => Promise<void>;
  applySession: (token: string, user: AuthUser) => Promise<void>;
  impersonate: (token: string, user: AuthUser) => Promise<void>;
  stopImpersonating: () => Promise<void>;
  refresh: () => Promise<void>;
  sendOtp: (input: {
    channel: "email" | "mobile";
    identifier: string;
  }) => Promise<{ demoReveal: string | null }>;
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
  setLockWarningLeadMs: (ms: number) => Promise<void>;
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
    lockWarningLeadMs: DEFAULT_LOCK_WARNING_LEAD_MS,
    lockWarningSecondsRemaining: null,
    impersonating: false,
    impersonatedName: null,
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
      const [
        token,
        user,
        biometricEnabled,
        capability,
        idleTimeoutMs,
        lockWarningLeadMs,
        impersonator,
      ] = await Promise.all([
        getToken(),
        getStoredUser<AuthUser>(),
        getBiometricEnabled(),
        getBiometricCapability(),
        getIdleTimeoutMs(),
        getLockWarningLeadMs(),
        getImpersonator<AuthUser>(),
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
        lockWarningLeadMs,
        lockWarningSecondsRemaining: null,
        impersonating: !!(token && impersonator),
        impersonatedName:
          token && impersonator ? (user?.display_name ?? null) : null,
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
    // Cap the user-chosen lead time so a tiny configured idle window
    // still leaves some pre-warning quiet period.
    const leadMs = Math.min(
      state.lockWarningLeadMs,
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
  }, [
    state.token,
    state.biometricEnabled,
    state.locked,
    state.idleTimeoutMs,
    state.lockWarningLeadMs,
  ]);

  const setIdleTimeoutMs = useCallback(async (ms: number) => {
    const clamped = Math.max(0, Math.floor(ms));
    await persistIdleTimeoutMs(clamped);
    lastActivityRef.current = Date.now();
    setState((s) => ({ ...s, idleTimeoutMs: clamped }));
  }, []);

  const setLockWarningLeadMs = useCallback(async (ms: number) => {
    // Floor at 1 second so a corrupt 0 can't silently hide the warning.
    const clamped = Math.max(1000, Math.floor(ms));
    await persistLockWarningLeadMs(clamped);
    lastActivityRef.current = Date.now();
    setState((s) => ({ ...s, lockWarningLeadMs: clamped }));
  }, []);

  const applySession = useCallback(async (token: string, user: AuthUser) => {
    // A fresh sign-in always clears any impersonation stash — you can't be
    // "returning" to an operator session you just replaced by logging in.
    await Promise.all([
      setToken(token),
      setStoredUser(user),
      setImpersonator(null),
    ]);
    setState((s) => ({
      ...s,
      ready: true,
      token,
      user,
      // Fresh sign-in counts as already unlocked.
      locked: false,
      lockWarningSecondsRemaining: null,
      impersonating: false,
      impersonatedName: null,
    }));
  }, []);

  // Begin impersonating another user: stash the operator's own session so it
  // can be restored later, then swap in the impersonated user's token. The
  // token already authorizes the target's dashboard, so no re-login happens.
  const impersonate = useCallback(
    async (token: string, user: AuthUser) => {
      const [currentToken, currentUser] = await Promise.all([
        getToken(),
        getStoredUser<AuthUser>(),
      ]);
      if (currentToken) {
        await setImpersonator({ token: currentToken, user: currentUser });
      }
      await Promise.all([setToken(token), setStoredUser(user)]);
      setState((s) => ({
        ...s,
        ready: true,
        token,
        user,
        locked: false,
        lockWarningSecondsRemaining: null,
        impersonating: true,
        impersonatedName: user?.display_name ?? null,
      }));
    },
    [],
  );

  // Stop impersonating: restore the operator's stashed session. Best-effort
  // revoke of the impersonation token happens before the swap (while it is
  // still the active bearer token) so it doesn't linger server-side.
  const stopImpersonating = useCallback(async () => {
    const stash = await getImpersonator<AuthUser>();
    if (!stash) {
      // Nothing to restore — just clear the flag.
      setState((s) => ({ ...s, impersonating: false, impersonatedName: null }));
      return;
    }
    try {
      await apiFetch("/auth/logout", { method: "POST" });
    } catch {
      /* best-effort — never block restoring the operator session */
    }
    await Promise.all([
      setToken(stash.token),
      setStoredUser(stash.user ?? null),
      setImpersonator(null),
    ]);
    setState((s) => ({
      ...s,
      ready: true,
      token: stash.token,
      user: (stash.user as AuthUser) ?? null,
      locked: false,
      lockWarningSecondsRemaining: null,
      impersonating: false,
      impersonatedName: null,
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
    // Detach this device's push token while the bearer token is still
    // valid, so a shared device stops receiving the previous user's
    // notifications (best-effort — never blocks sign-out).
    await clearPushRegistration();
    try {
      await apiFetch("/auth/logout", { method: "POST" });
    } catch {}
    // Signing out also clears the biometric preference and the
    // "don't ask again" flag — next sign-in starts from a clean slate.
    await Promise.all([
      setToken(null),
      setStoredUser(null),
      setImpersonator(null),
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
      impersonating: false,
      impersonatedName: null,
    }));
  }, []);

  const sendOtp = useCallback(
    async (input: { channel: "email" | "mobile"; identifier: string }) => {
      // Backend OtpController + OpenAPI require `{ identifier, type }`.
      // The previous payload `{ channel, [channel]: identifier }` was the
      // source of broken email/SMS login — it 422'd on every request.
      // `demo_reveal` is populated only when the admin "Demo mode" toggle is
      // on AND a real code was issued; the screen surfaces it on-screen.
      const res = await apiFetch<{
        data?: { demo_reveal?: string | null };
        demo_reveal?: string | null;
      }>("/auth/otp/send", {
        method: "POST",
        body: JSON.stringify({
          identifier: input.identifier,
          type: input.channel,
        }),
      });
      return { demoReveal: res.data?.demo_reveal ?? res.demo_reveal ?? null };
    },
    [],
  );

  const verifyOtp = useCallback(
    async (input: {
      channel: "email" | "mobile";
      identifier: string;
      code: string;
    }) => {
      // AuthSuccess wraps `{ token, user }` inside `data` per OpenAPI.
      const res = await apiFetch<{
        data?: { token: string; user: AuthUser };
        token?: string;
        user?: AuthUser;
      }>("/auth/otp/verify", {
        method: "POST",
        body: JSON.stringify({
          identifier: input.identifier,
          type: input.channel,
          code: input.code,
        }),
      });
      const token = res.data?.token ?? res.token;
      const user = res.data?.user ?? res.user;
      if (!token || !user) {
        throw new Error("Sign-in response was missing a token or user.");
      }
      await applySession(token, user);
    },
    [applySession],
  );

  const demoLogin = useCallback(
    async (role: "user" | "admin" | "super_admin" = "user") => {
      const res = await apiFetch<{
        data?: { token: string; user: AuthUser };
        token?: string;
        user?: AuthUser;
      }>("/auth/demo", {
        method: "POST",
        body: JSON.stringify({ role }),
      });
      const token = res.data?.token ?? res.token;
      const user = res.data?.user ?? res.user;
      if (!token || !user) throw new Error("Demo sign-in response was empty.");
      await applySession(token, user);
    },
    [applySession],
  );

  const socialLogin = useCallback(
    async (input: {
      provider: "google" | "apple";
      id_token?: string;
      access_token?: string;
    }) => {
      const res = await apiFetch<{
        data?: { token: string; user: AuthUser };
        token?: string;
        user?: AuthUser;
      }>("/auth/social", { method: "POST", body: JSON.stringify(input) });
      const token = res.data?.token ?? res.token;
      const user = res.data?.user ?? res.user;
      if (!token || !user) {
        throw new Error("Social sign-in response was missing a token or user.");
      }
      await applySession(token, user);
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
    const res = await promptBiometric("Unlock Sayzio");
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
      impersonate,
      stopImpersonating,
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
      setLockWarningLeadMs,
      noteActivity,
    }),
    [
      state,
      signOut,
      applySession,
      impersonate,
      stopImpersonating,
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
      setLockWarningLeadMs,
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
