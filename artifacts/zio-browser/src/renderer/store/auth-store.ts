/**
 * Auth state store for the renderer process.
 */
import { useState, useCallback, useRef, useEffect } from 'react';
import { ApiClient } from '../../shared/api-client';
import type { ApiUser } from '../../shared/api-client';

const API_BASE_URL = 'https://1in.me';

interface AuthState {
  user: ApiUser | null;
  token: string | null;
  isLoading: boolean;
  init: () => Promise<void>;
  setAuth: (user: ApiUser, token: string) => Promise<void>;
  clearAuth: () => Promise<void>;
  /** Re-fetch the profile from the server to refresh a stale cached name/avatar. */
  refreshUser: () => Promise<void>;
}

let userState: ApiUser | null = null;
let tokenState: string | null = null;
let loadingState = false;
const authListeners = new Set<() => void>();

function notifyAuth(): void {
  authListeners.forEach(l => l());
}

export function useAuthStore(): AuthState {
  const [, rerender] = useState(0);
  const registeredRef = useRef(false);

  useEffect(() => {
    const listener = () => rerender(n => n + 1);
    if (!registeredRef.current) {
      registeredRef.current = true;
      authListeners.add(listener);
    }
    return () => { authListeners.delete(listener); };
  }, []);

  const init = useCallback(async (): Promise<void> => {
    if (typeof window === 'undefined' || !window.zio) return;
    loadingState = true;
    notifyAuth();
    try {
      const [token, user] = await Promise.all([
        window.zio.auth.getToken() as Promise<string | null>,
        window.zio.auth.getUser() as Promise<Record<string, unknown> | null>,
      ]);
      tokenState = token;
      userState = user ? (user as unknown as ApiUser) : null;
    } finally {
      loadingState = false;
      notifyAuth();
    }
  }, []);

  const setAuth = useCallback(async (user: ApiUser, token: string): Promise<void> => {
    if (typeof window === 'undefined' || !window.zio) return;
    await Promise.all([
      window.zio.auth.storeToken(token),
      window.zio.auth.storeUser(user as unknown as Record<string, unknown>),
    ]);
    userState = user;
    tokenState = token;
    notifyAuth();
  }, []);

  const clearAuth = useCallback(async (): Promise<void> => {
    if (typeof window === 'undefined' || !window.zio) return;
    await window.zio.auth.clear();
    userState = null;
    tokenState = null;
    notifyAuth();
  }, []);

  const refreshUser = useCallback(async (): Promise<void> => {
    if (typeof window === 'undefined' || !window.zio || !tokenState) return;
    try {
      const client = new ApiClient({ baseUrl: API_BASE_URL, token: tokenState });
      const { user } = await client.me();
      if (!user) return;
      userState = user;
      await window.zio.auth.storeUser(user as unknown as Record<string, unknown>);
      notifyAuth();
    } catch {
      // Offline or expired token — keep showing the cached identity.
    }
  }, []);

  return {
    user: userState,
    token: tokenState,
    isLoading: loadingState,
    init,
    setAuth,
    clearAuth,
    refreshUser,
  };
}
