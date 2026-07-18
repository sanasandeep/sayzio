/**
 * Tab state store for the renderer process.
 * Bridges between IPC events from main and React state.
 */
import { useState, useCallback, useEffect, useRef } from 'react';
import type { TabState, RecentlyClosedEntry } from '../../main/tab-manager';

export interface TabRecord extends Partial<TabState> {
  id: string;
  url: string;
  title: string;
  isLoading: boolean;
  pinned: boolean;
}

interface TabStoreState {
  tabs: Record<string, TabRecord>;
  tabOrder: string[];
  activeTabId: string | null;
  recentlyClosed: RecentlyClosedEntry[];
  initTabs: () => Promise<void>;
  createTab: (url?: string) => Promise<string | null>;
  closeTab: (id: string) => Promise<void>;
  activateTab: (id: string) => Promise<void>;
  navigate: (id: string, input: string) => Promise<void>;
  goBack: (id: string) => Promise<void>;
  goForward: (id: string) => Promise<void>;
  reload: (id: string, force?: boolean) => Promise<void>;
  stop: (id: string) => Promise<void>;
  pinTab: (id: string, pinned: boolean) => Promise<void>;
  duplicateTab: (id: string) => Promise<string | null>;
  closeOtherTabs: (id: string) => Promise<void>;
  closeTabsToRight: (id: string) => Promise<void>;
  muteAllTabs: (muted?: boolean) => Promise<void>;
  reopenClosedTab: () => Promise<string | null>;
  reopenFromRecent: (url: string) => Promise<string | null>;
}

// Singleton state using module-level variables + React state sync
let tabsState: Record<string, TabRecord> = {};
let tabOrderState: string[] = [];
let activeTabIdState: string | null = null;
let recentlyClosedState: RecentlyClosedEntry[] = [];
const listeners = new Set<() => void>();

function notify(): void {
  listeners.forEach(l => l());
}

function updateTab(id: string, updates: Partial<TabRecord>): void {
  tabsState = { ...tabsState, [id]: { ...tabsState[id], id, ...updates } };
  notify();
}

// Wire up IPC events once
let ipcWired = false;
function wireIpc(): void {
  if (ipcWired || typeof window === 'undefined' || !window.zio) return;
  ipcWired = true;

  window.zio.on('tab:created', (tabId: unknown) => {
    const id = tabId as string;
    updateTab(id, { id, url: '', title: 'New Tab', isLoading: false, pinned: false });
    tabOrderState = [...tabOrderState, id];
    notify();
  });

  window.zio.on('tab:closed', (tabId: unknown) => {
    const id = tabId as string;
    const next = { ...tabsState };
    delete next[id];
    tabsState = next;
    tabOrderState = tabOrderState.filter(t => t !== id);
    if (activeTabIdState === id) activeTabIdState = tabOrderState[tabOrderState.length - 1] ?? null;
    notify();
  });

  window.zio.on('tab:activated', (tabId: unknown) => {
    activeTabIdState = tabId as string;
    notify();
  });

  window.zio.on('tab:state-changed', (tabId: unknown, state: unknown) => {
    const id = tabId as string;
    updateTab(id, state as Partial<TabRecord>);
  });

  window.zio.on('tab:navigated', (tabId: unknown, url: unknown, title: unknown) => {
    updateTab(tabId as string, { url: url as string, title: title as string });
    void window.zio.history.record(url as string, title as string);
  });

  // Authoritative tab order from main (emitted on pin/unpin)
  window.zio.on('tab:order-changed', (order: unknown) => {
    tabOrderState = order as string[];
    notify();
  });

  // Recently closed stack updates from main
  window.zio.on('tab:recently-closed-changed', (entries: unknown) => {
    recentlyClosedState = entries as RecentlyClosedEntry[];
    notify();
  });
}

export function useTabStore(): TabStoreState {
  const [, rerender] = useState(0);
  const registeredRef = useRef(false);

  useEffect(() => {
    if (!registeredRef.current) {
      registeredRef.current = true;
      const listener = () => rerender(n => n + 1);
      listeners.add(listener);
      return () => {
        listeners.delete(listener);
      };
    }
    return undefined;
  }, []);

  const initTabs = useCallback(async () => {
    wireIpc();
    const [order, active] = await Promise.all([
      window.zio.tabs.getOrder() as Promise<string[]>,
      window.zio.tabs.getActive() as Promise<string | null>,
    ]);
    tabOrderState = order;
    activeTabIdState = active;
    const stateResults = await Promise.all(
      order.map(id => window.zio.tabs.getState(id) as Promise<TabState | null>),
    );
    const newTabs: Record<string, TabRecord> = {};
    for (let i = 0; i < order.length; i++) {
      const id = order[i];
      const state = stateResults[i];
      if (id && state) {
        newTabs[id] = {
          ...state,
          id,
          url: state.url ?? '',
          title: state.title ?? 'New Tab',
          isLoading: state.isLoading ?? false,
          pinned: state.pinned ?? false,
        };
      }
    }
    tabsState = newTabs;

    // Load initial recently-closed list
    const rc = await (window.zio.tabs.recentlyClosed() as Promise<RecentlyClosedEntry[]>);
    recentlyClosedState = rc ?? [];

    notify();
  }, []);

  const createTab = useCallback(async (url?: string): Promise<string | null> => {
    return window.zio.tabs.create(url) as Promise<string>;
  }, []);

  const closeTab = useCallback(async (id: string): Promise<void> => {
    await window.zio.tabs.close(id);
  }, []);

  const activateTab = useCallback(async (id: string): Promise<void> => {
    await window.zio.tabs.activate(id);
  }, []);

  const navigate = useCallback(async (id: string, input: string): Promise<void> => {
    await window.zio.tabs.navigate(id, input);
  }, []);

  const goBack = useCallback(async (id: string): Promise<void> => {
    await window.zio.tabs.back(id);
  }, []);

  const goForward = useCallback(async (id: string): Promise<void> => {
    await window.zio.tabs.forward(id);
  }, []);

  const reload = useCallback(async (id: string, force?: boolean): Promise<void> => {
    await window.zio.tabs.reload(id, force);
  }, []);

  const stop = useCallback(async (id: string): Promise<void> => {
    await window.zio.tabs.stop(id);
  }, []);

  const pinTab = useCallback(async (id: string, pinned: boolean): Promise<void> => {
    await window.zio.tabs.pin(id, pinned);
  }, []);

  const duplicateTab = useCallback(async (id: string): Promise<string | null> => {
    return window.zio.tabs.duplicate(id) as Promise<string | null>;
  }, []);

  const closeOtherTabs = useCallback(async (id: string): Promise<void> => {
    await window.zio.tabs.closeOthers(id);
  }, []);

  const closeTabsToRight = useCallback(async (id: string): Promise<void> => {
    await window.zio.tabs.closeToRight(id);
  }, []);

  const muteAllTabs = useCallback(async (muted?: boolean): Promise<void> => {
    await window.zio.tabs.muteAll(muted);
  }, []);

  const reopenClosedTab = useCallback(async (): Promise<string | null> => {
    return window.zio.tabs.reopenClosed() as Promise<string | null>;
  }, []);

  const reopenFromRecent = useCallback(async (url: string): Promise<string | null> => {
    return window.zio.tabs.reopenFromRecent(url) as Promise<string | null>;
  }, []);

  return {
    tabs: tabsState,
    tabOrder: tabOrderState,
    activeTabId: activeTabIdState,
    recentlyClosed: recentlyClosedState,
    initTabs,
    createTab,
    closeTab,
    activateTab,
    navigate,
    goBack,
    goForward,
    reload,
    stop,
    pinTab,
    duplicateTab,
    closeOtherTabs,
    closeTabsToRight,
    muteAllTabs,
    reopenClosedTab,
    reopenFromRecent,
  };
}
