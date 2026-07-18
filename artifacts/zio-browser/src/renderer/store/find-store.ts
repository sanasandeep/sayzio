/**
 * Find-in-page store — per-session singleton that tracks find bar state
 * and bridges renderer actions to the main process findInPage API.
 */
import { useState, useCallback, useEffect, useRef } from 'react';

interface FindState {
  isOpen: boolean;
  query: string;
  matchCase: boolean;
  activeMatch: number;
  matchCount: number;
}

export interface FindStoreAPI extends FindState {
  openFind: () => void;
  closeFind: (activeTabId: string | null) => void;
  setQuery: (q: string, activeTabId: string | null) => void;
  toggleMatchCase: (activeTabId: string | null) => void;
  searchNext: (activeTabId: string | null) => void;
  searchPrev: (activeTabId: string | null) => void;
}

// Module-level singleton — shared across all useFindStore hook instances
let findState: FindState = {
  isOpen: false,
  query: '',
  matchCase: false,
  activeMatch: 0,
  matchCount: 0,
};

const storeListeners = new Set<() => void>();

function patchState(updates: Partial<FindState>): void {
  findState = { ...findState, ...updates };
  storeListeners.forEach(l => l());
}

// Wire IPC events once at module load time
let ipcWired = false;

function wireIpc(): void {
  if (ipcWired || typeof window === 'undefined' || !window.zio) return;
  ipcWired = true;

  // Menu / keyboard shortcut opens the bar
  window.zio.on('find:open', () => {
    patchState({ isOpen: true });
  });

  // Match count / active match updates from the main process webContents
  window.zio.on('tab:find-result', (...args: unknown[]) => {
    const r = args[0] as { activeMatchOrdinal: number; matches: number; finalUpdate: boolean } | null;
    if (!r) return;
    patchState({
      activeMatch: r.activeMatchOrdinal,
      matchCount: r.matches,
    });
  });

  // Navigation clears match state (the main process also stops the find)
  window.zio.on('tab:navigated', () => {
    patchState({ activeMatch: 0, matchCount: 0 });
  });

  // Switching tabs resets match state; re-run search if bar is open and has a query
  window.zio.on('tab:activated', (...args: unknown[]) => {
    const tabId = args[0] as string | null;
    patchState({ activeMatch: 0, matchCount: 0 });
    if (findState.isOpen && findState.query && tabId) {
      void window.zio.tabs.find(tabId, findState.query, true, findState.matchCase);
    }
  });
}

function doFind(activeTabId: string | null, forward: boolean): void {
  if (!activeTabId || !findState.query) return;
  void window.zio.tabs.find(activeTabId, findState.query, forward, findState.matchCase);
}

export function useFindStore(): FindStoreAPI {
  const [, rerender] = useState(0);
  const listenerRef = useRef<(() => void) | null>(null);

  useEffect(() => {
    wireIpc();
    if (!listenerRef.current) {
      listenerRef.current = () => rerender(n => n + 1);
      storeListeners.add(listenerRef.current);
    }
    return () => {
      if (listenerRef.current) {
        storeListeners.delete(listenerRef.current);
        listenerRef.current = null;
      }
    };
  }, []);

  const openFind = useCallback(() => {
    patchState({ isOpen: true });
  }, []);

  const closeFind = useCallback((activeTabId: string | null) => {
    if (activeTabId) void window.zio.tabs.findStop(activeTabId);
    patchState({ isOpen: false, activeMatch: 0, matchCount: 0 });
  }, []);

  const setQuery = useCallback((q: string, activeTabId: string | null) => {
    patchState({ query: q, activeMatch: 0, matchCount: 0 });
    if (activeTabId && q) {
      void window.zio.tabs.find(activeTabId, q, true, findState.matchCase);
    } else if (activeTabId && !q) {
      void window.zio.tabs.findStop(activeTabId);
    }
  }, []);

  const toggleMatchCase = useCallback((activeTabId: string | null) => {
    const newMatchCase = !findState.matchCase;
    patchState({ matchCase: newMatchCase, activeMatch: 0, matchCount: 0 });
    if (activeTabId && findState.query) {
      void window.zio.tabs.find(activeTabId, findState.query, true, newMatchCase);
    }
  }, []);

  const searchNext = useCallback((activeTabId: string | null) => {
    doFind(activeTabId, true);
  }, []);

  const searchPrev = useCallback((activeTabId: string | null) => {
    doFind(activeTabId, false);
  }, []);

  return {
    ...findState,
    openFind,
    closeFind,
    setQuery,
    toggleMatchCase,
    searchNext,
    searchPrev,
  };
}
