/**
 * Tab state store for the renderer process.
 * Bridges between IPC events from main and React state.
 */
import { useState, useCallback, useEffect, useRef } from 'react';
import type { TabState } from '../../main/tab-manager';

export interface TabRecord extends Partial<TabState> {
  id: string;
  url: string;
  title: string;
  isLoading: boolean;
}

interface TabStoreState {
  tabs: Record<string, TabRecord>;
  tabOrder: string[];
  activeTabId: string | null;
  initTabs: () => Promise<void>;
  createTab: (url?: string) => Promise<string | null>;
  closeTab: (id: string) => Promise<void>;
  activateTab: (id: string) => Promise<void>;
  navigate: (id: string, input: string) => Promise<void>;
  goBack: (id: string) => Promise<void>;
  goForward: (id: string) => Promise<void>;
  reload: (id: string, force?: boolean) => Promise<void>;
  stop: (id: string) => Promise<void>;
}

// Singleton state using module-level variables + React state sync
let tabsState: Record<string, TabRecord> = {};
let tabOrderState: string[] = [];
let activeTabIdState: string | null = null;
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
    updateTab(id, { id, url: '', title: 'New Tab', isLoading: false });
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
    // Record in history
    void window.zio.history.record(url as string, title as string);
  });
}

export function useTabStore(): TabStoreState {
  const [, rerender] = useState(0);
  const registeredRef = useRef(false);

  useEffect(() => {
    if (!registeredRef.current) {
      registeredRef.current = true;
      listeners.add(() => rerender(n => n + 1));
    }
    return () => {
      listeners.delete(() => rerender(n => n + 1));
    };
  }, []);

  const initTabs = useCallback(async () => {
    wireIpc();
    const [order, active] = await Promise.all([
      window.zio.tabs.getOrder() as Promise<string[]>,
      window.zio.tabs.getActive() as Promise<string | null>,
    ]);
    tabOrderState = order;
    activeTabIdState = active;
    // Load state for each existing tab
    const stateResults = await Promise.all(
      order.map(id => window.zio.tabs.getState(id) as Promise<TabState | null>),
    );
    const newTabs: Record<string, TabRecord> = {};
    for (let i = 0; i < order.length; i++) {
      const id = order[i];
      const state = stateResults[i];
      if (id && state) {
        newTabs[id] = { ...state, id, url: state.url ?? '', title: state.title ?? 'New Tab', isLoading: state.isLoading ?? false };
      }
    }
    tabsState = newTabs;
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

  return {
    tabs: tabsState,
    tabOrder: tabOrderState,
    activeTabId: activeTabIdState,
    initTabs,
    createTab,
    closeTab,
    activateTab,
    navigate,
    goBack,
    goForward,
    reload,
    stop,
  };
}
