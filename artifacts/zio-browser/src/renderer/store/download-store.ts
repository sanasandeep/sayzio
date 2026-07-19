/**
 * Download store — module-level singleton tracking active download count and
 * downloads-panel visibility, shared across all window modes (browser, split,
 * dashboard) so the chrome badge stays accurate everywhere.
 */
import { useState, useCallback, useEffect, useRef } from 'react';

interface DownloadState {
  activeDownloadCount: number;
  panelOpen: boolean;
}

export interface DownloadStoreAPI extends DownloadState {
  togglePanel: () => void;
  openPanel: () => void;
  closePanel: () => void;
}

// Module-level singleton — shared across all useDownloadStore hook instances
let downloadState: DownloadState = {
  activeDownloadCount: 0,
  panelOpen: false,
};

const storeListeners = new Set<() => void>();

function patchState(updates: Partial<DownloadState>): void {
  downloadState = { ...downloadState, ...updates };
  storeListeners.forEach(l => l());
}

// Wire IPC events once at module load time
let ipcWired = false;

function wireIpc(): void {
  if (ipcWired || typeof window === 'undefined' || !window.zio) return;
  ipcWired = true;

  window.zio.on('download:started', () => {
    // The DownloadToast surfaces new downloads; the panel opens on demand.
    patchState({
      activeDownloadCount: downloadState.activeDownloadCount + 1,
    });
  });

  window.zio.on('download:done', () => {
    patchState({
      activeDownloadCount: Math.max(0, downloadState.activeDownloadCount - 1),
    });
  });
}

export function useDownloadStore(): DownloadStoreAPI {
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

  const togglePanel = useCallback(() => {
    patchState({ panelOpen: !downloadState.panelOpen });
  }, []);

  const openPanel = useCallback(() => {
    patchState({ panelOpen: true });
  }, []);

  const closePanel = useCallback(() => {
    patchState({ panelOpen: false });
  }, []);

  return {
    ...downloadState,
    togglePanel,
    openPanel,
    closePanel,
  };
}
