/**
 * Renderer-side profile store.
 * Tracks the active browser profile and the list of available profiles.
 * Profiles map 1:1 to Sayzio workspaces (plus a personal/default profile).
 */
import { useState, useCallback, useEffect, useRef } from 'react';
import type { BrowserProfile } from '../../shared/profile-store';
import { DEFAULT_PROFILE_ID, defaultProfile } from '../../shared/profile-store';

interface ProfileState {
  profiles: BrowserProfile[];
  activeProfileId: string;
  isLoading: boolean;
  /** Load profiles from main + optionally sync workspace list from the API. */
  init: (token?: string | null) => Promise<void>;
  /** Switch to a profile by ID. */
  switchProfile: (profileId: string) => Promise<void>;
  /** Sync workspace list from the Sayzio API and upsert them as profiles. */
  syncWorkspaces: (token: string) => Promise<void>;
}

let profilesState: BrowserProfile[] = [defaultProfile()];
let activeProfileIdState: string = DEFAULT_PROFILE_ID;
let loadingState = false;
const profileListeners = new Set<() => void>();

function notifyProfiles(): void {
  profileListeners.forEach(l => l());
}

export function useProfileStore(): ProfileState {
  const [, rerender] = useState(0);
  const registeredRef = useRef(false);

  useEffect(() => {
    const listener = () => rerender(n => n + 1);
    if (!registeredRef.current) {
      registeredRef.current = true;
      profileListeners.add(listener);
    }

    // Listen for profile changes pushed by the main process (e.g. when another
    // component triggers a switch via IPC).
    const handleProfileChanged = (...args: unknown[]) => {
      const newId = args[0];
      if (typeof newId === 'string') {
        activeProfileIdState = newId;
        notifyProfiles();
      }
    };
    window.zio.on('profile:changed', handleProfileChanged);

    return () => {
      profileListeners.delete(listener);
      window.zio.off('profile:changed', handleProfileChanged);
    };
  }, []);

  const init = useCallback(async (token?: string | null): Promise<void> => {
    if (typeof window === 'undefined' || !window.zio) return;
    loadingState = true;
    notifyProfiles();
    try {
      const [storedProfiles, activeId] = await Promise.all([
        window.zio.profiles.list() as Promise<BrowserProfile[]>,
        window.zio.profiles.getActive() as Promise<string>,
      ]);
      profilesState = storedProfiles.length > 0 ? storedProfiles : [defaultProfile()];
      activeProfileIdState = activeId ?? DEFAULT_PROFILE_ID;

      // Kick off workspace sync in the background if we have a token
      if (token) {
        void syncWorkspacesImpl(token);
      }
    } finally {
      loadingState = false;
      notifyProfiles();
    }
  }, []);

  const switchProfile = useCallback(async (profileId: string): Promise<void> => {
    if (typeof window === 'undefined' || !window.zio) return;
    await window.zio.profiles.switch(profileId);
    activeProfileIdState = profileId;
    // Also pre-warm the session so cookies are ready for new tabs
    await window.zio.profiles.warmSession(profileId);
    notifyProfiles();
  }, []);

  const syncWorkspaces = useCallback(async (token: string): Promise<void> => {
    await syncWorkspacesImpl(token);
  }, []);

  return {
    profiles: profilesState,
    activeProfileId: activeProfileIdState,
    isLoading: loadingState,
    init,
    switchProfile,
    syncWorkspaces,
  };
}

async function syncWorkspacesImpl(token: string): Promise<void> {
  if (typeof window === 'undefined' || !window.zio) return;
  try {
    const prefs = await window.zio.prefs.all() as Record<string, string>;
    const apiBase = prefs['sayzio_api_base_url'] ?? 'https://1in.me';
    const resp = await fetch(`${apiBase}/api/v1/workspaces`, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    });
    if (!resp.ok) return;
    const json = await resp.json() as { data?: { items?: unknown[] } };
    const items = (json?.data?.items ?? []) as Array<{ id: number | string; name: string; is_personal?: boolean }>;

    const upserted: BrowserProfile[] = await Promise.all(
      items.map(ws => window.zio.profiles.upsertFromWorkspace(ws) as Promise<BrowserProfile>),
    );

    // Merge: personal profile first, then workspace profiles
    const personalProfile = defaultProfile();
    const wsProfiles = upserted.filter(p => !p.isPersonal);
    profilesState = [personalProfile, ...wsProfiles];
    notifyProfiles();
  } catch {
    // Network or parse error — keep existing profiles
  }
}
