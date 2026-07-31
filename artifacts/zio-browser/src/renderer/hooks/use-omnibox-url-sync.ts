/**
 * Keeps the omnibox (address bar) value in sync with the active tab's URL.
 *
 * Invariants (matching Chrome/Firefox behavior):
 *  - While the input is FOCUSED, background navigations never touch the
 *    user's typed text.
 *  - While UNFOCUSED with no uncommitted edits, the bar mirrors the tab URL.
 *  - While UNFOCUSED with uncommitted edits, a tab navigation (link click,
 *    redirect) DISCARDS the stale typed text so the bar stays truthful about
 *    where the tab actually is.
 *  - Switching tabs always resets the bar to the new tab's URL, but any
 *    recoverable text (an uncommitted draft, or text a navigation/Escape
 *    already discarded) is stashed PER TAB, so returning to the tab lets
 *    Ctrl/Cmd+Z restore the draft (Chrome-like).
 */
import { useEffect, useRef, type MutableRefObject } from 'react';

interface OmniboxUrlSyncOptions {
  activeTabId: string | null;
  activeTabUrl: string;
  omniboxFocused: boolean;
  omniboxEdited: boolean;
  /** Current typed value — stashed into the undo buffer when a navigation discards it. */
  omniboxValue?: string;
  setOmniboxValue: (value: string) => void;
  setOmniboxEdited: (edited: boolean) => void;
}

export interface OmniboxUrlSyncResult {
  /**
   * Typed text that an automatic navigation discarded, recoverable via
   * Ctrl/Cmd+Z in the omnibox (like Chrome). On tab switch the stash is
   * saved per tab and restored when that tab becomes active again; the
   * consumer should clear it whenever the user commits a navigation
   * themselves (submit / suggestion accept).
   */
  discardedTypedTextRef: MutableRefObject<string | null>;
}

export function useOmniboxUrlSync({
  activeTabId,
  activeTabUrl,
  omniboxFocused,
  omniboxEdited,
  omniboxValue,
  setOmniboxValue,
  setOmniboxEdited,
}: OmniboxUrlSyncOptions): OmniboxUrlSyncResult {
  // Sync omnibox with active tab URL (unless the user has uncommitted edits).
  // When the tab navigates on its own (link click, redirect) while the bar is
  // unfocused, discard any uncommitted typed text — like Chrome/Firefox do —
  // so the bar stays truthful about where the tab actually is. The discarded
  // text is stashed so the user can recover it with Ctrl/Cmd+Z.
  const discardedTypedTextRef = useRef<string | null>(null);
  const lastSyncedUrlRef = useRef<string>(activeTabUrl);
  useEffect(() => {
    const url = activeTabUrl;
    const urlChanged = url !== lastSyncedUrlRef.current;
    lastSyncedUrlRef.current = url;
    if (omniboxFocused) return;
    if (!omniboxEdited) {
      setOmniboxValue(url);
    } else if (urlChanged) {
      if (omniboxValue !== undefined && omniboxValue.trim() !== '') {
        discardedTypedTextRef.current = omniboxValue;
      }
      setOmniboxEdited(false);
      setOmniboxValue(url);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTabUrl, omniboxFocused, omniboxEdited, setOmniboxValue, setOmniboxEdited]);

  // Per-tab recovery stash: text a tab switch would otherwise strand, keyed
  // by tab id so returning to the tab lets Ctrl/Cmd+Z restore the draft.
  const stashByTabRef = useRef<Map<string, string>>(new Map());
  const prevTabIdRef = useRef<string | null>(activeTabId);

  // Switching tabs always resets the omnibox to that tab's URL, but first the
  // departing tab's recoverable text is saved: a fresh uncommitted draft wins
  // over an older discarded stash. The arriving tab's saved stash (if any) is
  // loaded so Ctrl/Cmd+Z brings the draft back.
  useEffect(() => {
    const prevTabId = prevTabIdRef.current;
    if (prevTabId !== activeTabId) {
      if (prevTabId !== null) {
        const draft =
          omniboxEdited && omniboxValue !== undefined && omniboxValue.trim() !== ''
            ? omniboxValue
            : discardedTypedTextRef.current;
        if (draft !== null && draft.trim() !== '') {
          stashByTabRef.current.set(prevTabId, draft);
        } else {
          stashByTabRef.current.delete(prevTabId);
        }
      }
      prevTabIdRef.current = activeTabId;
    }
    discardedTypedTextRef.current =
      (activeTabId !== null ? stashByTabRef.current.get(activeTabId) : undefined) ?? null;
    setOmniboxEdited(false);
    setOmniboxValue(activeTabUrl);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTabId]);

  return { discardedTypedTextRef };
}
