import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";

import { apiFetch } from "@/lib/api";
import {
  getStoredUser,
  getToken,
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
};

const AuthContext = createContext<Ctx | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [state, setState] = useState<AuthState>({
    ready: false,
    user: null,
    token: null,
  });

  useEffect(() => {
    (async () => {
      const [token, user] = await Promise.all([
        getToken(),
        getStoredUser<AuthUser>(),
      ]);
      setState({ ready: true, token: token ?? null, user: user ?? null });
    })();
  }, []);

  const applySession = useCallback(async (token: string, user: AuthUser) => {
    await Promise.all([setToken(token), setStoredUser(user)]);
    setState({ ready: true, token, user });
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
    await Promise.all([setToken(null), setStoredUser(null)]);
    setState({ ready: true, token: null, user: null });
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
    }),
    [state, signOut, applySession, refresh, sendOtp, verifyOtp, demoLogin, socialLogin],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): Ctx {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside <AuthProvider>");
  return ctx;
}
