/**
 * Shared pinned-toolbar-tools state used by every surface that manages pins
 * (the ChromeBar "⋯" overflow menu and the Settings → General "Toolbar"
 * block). Loads the `pinned_toolbar_tools` preference once, stays in sync
 * across surfaces via the `zio:pinned-tools-changed` window event, and
 * enforces the MAX_PINNED_TOOLS cap on every toggle.
 */
import { useState, useEffect, useCallback } from 'react';
import {
  parsePinnedTools,
  serializePinnedTools,
  togglePinnedTool,
  reorderPinnedTools,
  movePinnedTool,
  isPinnableTool,
  MAX_PINNED_TOOLS,
  PINNED_TOOLS_PREF_KEY,
  PINNED_TOOLS_CHANGED_EVENT,
} from '../../shared/toolbar-pins';
import type { PinnableTool } from '../../shared/toolbar-pins';

export interface PinnedToolsState {
  /** Tools currently pinned onto the toolbar (capped at MAX_PINNED_TOOLS). */
  pinned: PinnableTool[];
  /** True when no more tools may be pinned (unpinning still works). */
  capReached: boolean;
  /** Toggle a tool's pinned state; pinning past the cap is a no-op. */
  togglePin: (tool: PinnableTool) => void;
  /** Move a pinned tool to another pinned tool's position (drag-to-reorder). */
  reorderPin: (dragged: PinnableTool, target: PinnableTool) => void;
  /** Move a pinned tool one step up (-1) or down (+1) in the pin order. */
  movePin: (tool: PinnableTool, direction: -1 | 1) => void;
}

export function usePinnedTools(): PinnedToolsState {
  const [pinned, setPinned] = useState<PinnableTool[]>([]);

  // Initial load from the preferences store.
  useEffect(() => {
    let cancelled = false;
    void window.zio.prefs.get(PINNED_TOOLS_PREF_KEY).then((raw: string | null) => {
      if (!cancelled) setPinned(parsePinnedTools(raw));
    }).catch(() => { /* main not ready — default to none pinned */ });
    return () => { cancelled = true; };
  }, []);

  // Stay in sync when pins are toggled from another surface.
  useEffect(() => {
    const onChanged = (e: Event) => {
      const detail = (e as CustomEvent).detail;
      if (Array.isArray(detail)) {
        setPinned(detail.filter(isPinnableTool).slice(0, MAX_PINNED_TOOLS));
      }
    };
    window.addEventListener(PINNED_TOOLS_CHANGED_EVENT, onChanged);
    return () => window.removeEventListener(PINNED_TOOLS_CHANGED_EVENT, onChanged);
  }, []);

  const togglePin = useCallback((tool: PinnableTool) => {
    setPinned(prev => {
      const next = togglePinnedTool(prev, tool);
      if (next !== prev) {
        void window.zio.prefs.set(PINNED_TOOLS_PREF_KEY, serializePinnedTools(next)).catch(() => {});
        // Notify other surfaces after this handler returns.
        setTimeout(() => {
          window.dispatchEvent(new CustomEvent(PINNED_TOOLS_CHANGED_EVENT, { detail: next }));
        }, 0);
      }
      return next;
    });
  }, []);

  const reorderPin = useCallback((dragged: PinnableTool, target: PinnableTool) => {
    setPinned(prev => {
      const next = reorderPinnedTools(prev, dragged, prev.indexOf(target));
      if (next !== prev) {
        void window.zio.prefs.set(PINNED_TOOLS_PREF_KEY, serializePinnedTools(next)).catch(() => {});
        // Notify other surfaces after this handler returns.
        setTimeout(() => {
          window.dispatchEvent(new CustomEvent(PINNED_TOOLS_CHANGED_EVENT, { detail: next }));
        }, 0);
      }
      return next;
    });
  }, []);

  const movePin = useCallback((tool: PinnableTool, direction: -1 | 1) => {
    setPinned(prev => {
      const next = movePinnedTool(prev, tool, direction);
      if (next !== prev) {
        void window.zio.prefs.set(PINNED_TOOLS_PREF_KEY, serializePinnedTools(next)).catch(() => {});
        // Notify other surfaces after this handler returns.
        setTimeout(() => {
          window.dispatchEvent(new CustomEvent(PINNED_TOOLS_CHANGED_EVENT, { detail: next }));
        }, 0);
      }
      return next;
    });
  }, []);

  return { pinned, capReached: pinned.length >= MAX_PINNED_TOOLS, togglePin, reorderPin, movePin };
}
