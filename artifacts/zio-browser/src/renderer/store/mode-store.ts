/**
 * Window mode store for the renderer process.
 * Syncs current window mode and split ratio with the main process via IPC.
 */
import { useState, useCallback, useEffect, useRef } from 'react';
import type { WindowMode } from '../../shared/window-mode';
import { DEFAULT_SPLIT_RATIO, MIN_SPLIT_RATIO, MAX_SPLIT_RATIO } from '../../shared/window-mode';

interface ModeStoreState {
  mode: WindowMode;
  splitRatio: number;
  isInitialized: boolean;
  setMode: (mode: WindowMode) => Promise<void>;
  setSplitRatio: (ratio: number) => Promise<void>;
  init: () => Promise<void>;
  reloadDashboard: () => Promise<void>;
}

// Singleton state
let modeState: WindowMode = 'browser';
let splitRatioState: number = DEFAULT_SPLIT_RATIO;
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
    const [mode, ratio] = await Promise.all([
      window.zio.window.getMode() as Promise<WindowMode>,
      window.zio.window.getSplitRatio() as Promise<number>,
    ]);
    modeState = mode;
    splitRatioState = Math.max(MIN_SPLIT_RATIO, Math.min(MAX_SPLIT_RATIO, ratio));
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

  const reloadDashboard = useCallback(async () => {
    await window.zio.window.reloadDashboard();
  }, []);

  return {
    mode: modeState,
    splitRatio: splitRatioState,
    isInitialized: isInitializedState,
    setMode,
    setSplitRatio,
    init,
    reloadDashboard,
  };
}
