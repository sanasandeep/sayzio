/**
 * Window mode store for the renderer process.
 * Syncs current window mode, split ratio, and Zio panel state with the main process via IPC.
 */
import { useState, useCallback, useEffect, useRef } from 'react';
import type { WindowMode } from '../../shared/window-mode';
import {
  DEFAULT_SPLIT_RATIO,
  MIN_SPLIT_RATIO,
  MAX_SPLIT_RATIO,
  DEFAULT_ZIO_PANEL_WIDTH,
  MIN_ZIO_PANEL_WIDTH,
  MAX_ZIO_PANEL_WIDTH,
} from '../../shared/window-mode';

interface ModeStoreState {
  mode: WindowMode;
  splitRatio: number;
  zioPanelWidth: number;
  zioPanelDocked: boolean;
  isInitialized: boolean;
  setMode: (mode: WindowMode) => Promise<void>;
  setSplitRatio: (ratio: number) => Promise<void>;
  setZioPanelWidth: (width: number) => Promise<void>;
  setZioPanelDocked: (docked: boolean) => Promise<void>;
  init: () => Promise<void>;
  reloadDashboard: () => Promise<void>;
}

// Singleton state
let modeState: WindowMode = 'browser';
let splitRatioState: number = DEFAULT_SPLIT_RATIO;
let zioPanelWidthState: number = DEFAULT_ZIO_PANEL_WIDTH;
let zioPanelDockedState = false;
let isInitializedState = false;
const listeners = new Set<() => void>();

function notify(): void {
  listeners.forEach(l => l());
}

// Wire up IPC events once
let ipcWired = false;
function wireIpc(): void {
  if (ipcWired || typeof window === 'undefined' || !window.zio) return;
  ipcWired = true;

  window.zio.on('window:mode-changed', (newMode: unknown) => {
    modeState = newMode as WindowMode;
    notify();
  });
}

export function useModeStore(): ModeStoreState {
  const [, rerender] = useState(0);
  const registeredRef = useRef(false);

  useEffect(() => {
    if (!registeredRef.current) {
      registeredRef.current = true;
      const listener = () => rerender(n => n + 1);
      listeners.add(listener);
      return () => { listeners.delete(listener); };
    }
    return undefined;
  }, []);

  const init = useCallback(async () => {
    wireIpc();
    const [mode, ratio, panelWidth, panelDocked] = await Promise.all([
      window.zio.window.getMode() as Promise<WindowMode>,
      window.zio.window.getSplitRatio() as Promise<number>,
      window.zio.window.getZioPanelWidth() as Promise<number>,
      window.zio.window.getZioPanelDocked() as Promise<boolean>,
    ]);
    modeState = mode;
    splitRatioState = Math.max(MIN_SPLIT_RATIO, Math.min(MAX_SPLIT_RATIO, ratio));
    zioPanelWidthState = Math.max(MIN_ZIO_PANEL_WIDTH, Math.min(MAX_ZIO_PANEL_WIDTH, panelWidth));
    zioPanelDockedState = panelDocked;
    isInitializedState = true;
    notify();
  }, []);

  const setMode = useCallback(async (mode: WindowMode) => {
    modeState = mode;
    notify();
    await window.zio.window.setMode(mode);
  }, []);

  const setSplitRatio = useCallback(async (ratio: number) => {
    const clamped = Math.max(MIN_SPLIT_RATIO, Math.min(MAX_SPLIT_RATIO, ratio));
    splitRatioState = clamped;
    notify();
    await window.zio.window.setSplitRatio(clamped);
  }, []);

  const setZioPanelWidth = useCallback(async (width: number) => {
    const clamped = Math.max(MIN_ZIO_PANEL_WIDTH, Math.min(MAX_ZIO_PANEL_WIDTH, width));
    zioPanelWidthState = clamped;
    notify();
    await window.zio.window.setZioPanelWidth(clamped);
  }, []);

  const setZioPanelDocked = useCallback(async (docked: boolean) => {
    zioPanelDockedState = docked;
    notify();
    await window.zio.window.setZioPanelDocked(docked);
  }, []);

  const reloadDashboard = useCallback(async () => {
    await window.zio.window.reloadDashboard();
  }, []);

  return {
    mode: modeState,
    splitRatio: splitRatioState,
    zioPanelWidth: zioPanelWidthState,
    zioPanelDocked: zioPanelDockedState,
    isInitialized: isInitializedState,
    setMode,
    setSplitRatio,
    setZioPanelWidth,
    setZioPanelDocked,
    init,
    reloadDashboard,
  };
}
