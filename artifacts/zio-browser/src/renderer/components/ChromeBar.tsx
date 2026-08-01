/**
 * ChromeBar — the browser chrome (tab strip + address bar + controls).
 * Runs in the renderer (app chrome window); actual web content is in WebContentsView.
 * Used in both Browser mode (full-width) and the right pane of Split mode.
 */
import { useState, useRef, useCallback, useEffect } from 'react';
import { useTabStore } from '../store/tab-store';
import { useAuthStore } from '../store/auth-store';
import { ShortenPopover } from './ShortenPopover';
import { SharePopover } from './SharePopover';
import { TabOverview, type TabOverviewHandle } from './TabOverview';
import { ClipboardPopover } from './ClipboardPopover';
import { SiteSettingsPopover } from './SiteSettingsPopover';
import { AdBlockShieldPopover } from './AdBlockShieldPopover';
import { CreateLinkPopover } from './CreateLinkPopover';
import { TabModeSwitcher } from './TabModeSwitcher';
import { normalizeTabMode } from '../../shared/window-mode';
import { ProfileSwitcher } from './ProfileSwitcher';
import { useChromeOverlay } from '../hooks/use-chrome-overlay';
import { AccountButton } from './AccountButton';
import zioIcon from '../assets/zio-icon.png';
import zioMascot from '../assets/zio-mascot.png';
import type { RecentlyClosedEntry } from '../../main/tab-manager';
import { resolveFavicon } from '../../shared/favicon';
import { FaviconImg } from './FaviconImg';
import type { SyncQueueProfileCount } from '../../main/db';
import { ApiClient } from '../../shared/api-client';
import { profileToAutofillCard } from '../../shared/form-autofill';
import { MAX_PINNED_TOOLS } from '../../shared/toolbar-pins';
import type { PinnableTool } from '../../shared/toolbar-pins';
import { usePinnedTools } from '../hooks/use-pinned-tools';
import { useSiteResolve } from '../hooks/use-site-resolve';
import { useOmniboxUrlSync } from '../hooks/use-omnibox-url-sync';
import { checkSayzioExists, extractSayzioSuggestQuery } from '../../shared/sayzio-suggest';
import type { SayzioExistsResult } from '../../shared/sayzio-suggest';

interface Props {
  zioPanelOpen: boolean;
  onToggleZio: () => void;
  onOpenAuth: () => void;
  onOpenTabSearch: () => void;
  downloadsPanelOpen?: boolean;
  onToggleDownloads?: () => void;
  activeDownloadCount?: number;
  /** True when this window is an incognito/private window. */
  isPrivate?: boolean;
  /** Called when the user clicks the Device Lab button. */
  onOpenDeviceLab?: () => void;
  /** Called when the user triggers a screenshot; fullPage indicates full-page vs. viewport. */
  onScreenshot?: (fullPage: boolean) => void;
  /** While a screenshot is being captured, show a busy state on the camera button. */
  screenshotCapturing?: boolean;
  /** Callback to open the site settings / privacy panel. */
  onOpenSiteSettings?: () => void;
  readingListOpen: boolean;
  onToggleReadingList: () => void;
  /** Whether the account-notes panel is open (button highlight state). */
  notesPanelOpen?: boolean;
  /** Callback to toggle the account-notes panel; 'page' opens the per-page scope. */
  onToggleNotes?: (scope?: 'page' | 'all') => void;
  /** Whether the Dialer pane is open (button highlight state). */
  dialerPanelOpen?: boolean;
  /** Callback to toggle the Dialer pane (call handoff to the phone). */
  onToggleDialer?: () => void;
  /** Callback to open the browser settings panel. */
  onOpenSettings?: () => void;
  settingsOpen?: boolean;
  /** Virtual keyboard — toolbar toggle (shown only when the feature is enabled). */
  vkEnabled?: boolean;
  vkOpen?: boolean;
  onToggleVk?: () => void;
}

const BASE_URL = 'https://sayzio.app';

/** Icon button style for the Safari-style far-right toolbar cluster. */
function clusterBtnStyle(active: boolean): React.CSSProperties {
  return {
    position: 'relative',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: 28,
    height: 28,
    borderRadius: 8,
    background: active ? 'var(--color-primary)' : 'transparent',
    color: active ? '#fff' : 'var(--color-text-muted)',
    border: '1px solid transparent',
    cursor: 'pointer',
    flexShrink: 0,
    transition: 'all 0.12s',
  };
}

/**
 * Context-menu "Fill form with my Sayzio card": fetch the signed-in profile
 * and run the autofill script on the target tab. Fire-and-forget — result
 * feedback lives in the Zio panel's Contacts tab.
 */
async function runContextAutofill(tabId: string, token: string | null): Promise<void> {
  if (!token) return;
  try {
    const client = new ApiClient({ baseUrl: BASE_URL, token });
    const { user } = await client.getProfile();
    const card = profileToAutofillCard(user);
    await window.zio.tabs.autofillForm(tabId, card as Record<string, string | undefined>);
  } catch {
    // Silent — never break browsing over an autofill convenience action.
  }
}

// ── Address bar suggestions ───────────────────────────────────────────────────

interface OmniSuggestion {
  url: string;
  title: string;
  kind: 'history' | 'bookmark' | 'search' | 'sayzio-link' | 'sayzio-profile';
  /** Secondary description text (used by Sayzio jump rows). */
  subtitle?: string;
}

// Session cache of Sayzio existence lookups (keyed by lowercased query) so
// backspacing/retyping the same handle doesn't re-hit the API.
const sayzioExistsCache = new Map<string, SayzioExistsResult>();

/**
 * Live-check Sayzio for an exact link alias and creator handle match.
 * Pure logic lives in shared/sayzio-suggest (tested); this wrapper binds the
 * session cache and a token-authed ApiClient. Requires a signed-in token
 * because the alias check is an authed endpoint (and the privacy gate only
 * allows remote lookups for signed-in users anyway).
 */
function checkSayzioExistsWithToken(q: string, token: string): Promise<SayzioExistsResult> {
  const client = new ApiClient({ baseUrl: BASE_URL, token });
  return checkSayzioExists(q, client, sayzioExistsCache);
}

interface HistoryRow { url: string; title: string | null }
interface BookmarkRow { url: string; title: string | null }

// ── Tiny inline context menu ──────────────────────────────────────────────────

interface ContextMenuState {
  tabId: string;
  x: number;
  y: number;
}

interface TabContextMenuProps {
  state: ContextMenuState;
  isPinned: boolean;
  isMuted: boolean;
  isAudible: boolean;
  tabCount: number;
  tabIndex: number;
  onClose: () => void;
  onPin: () => void;
  onMute: () => void;
  onDuplicate: () => void;
  onCloseTab: () => void;
  onCloseOthers: () => void;
  onCloseToRight: () => void;
}

function TabContextMenu({
  state, isPinned, isMuted, isAudible,
  tabCount, tabIndex,
  onClose, onPin, onMute, onDuplicate,
  onCloseTab, onCloseOthers, onCloseToRight,
}: TabContextMenuProps) {
  const menuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
        onClose();
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [onClose]);

  const menuStyle: React.CSSProperties = {
    position: 'fixed',
    left: state.x,
    top: state.y,
    background: 'var(--color-bg-surface)',
    border: '1px solid var(--color-border)',
    borderRadius: 8,
    boxShadow: '0 4px 20px rgba(0,0,0,0.3)',
    zIndex: 9999,
    minWidth: 180,
    padding: '4px 0',
    fontSize: 13,
    color: 'var(--color-text)',
  };

  const itemStyle: React.CSSProperties = {
    padding: '6px 14px',
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    gap: 8,
    whiteSpace: 'nowrap',
  };

  const sepStyle: React.CSSProperties = {
    borderTop: '1px solid var(--color-border)',
    margin: '4px 0',
  };

  const action = (fn: () => void) => () => { fn(); onClose(); };

  return (
    <div ref={menuRef} style={menuStyle}>
      <div
        style={itemStyle}
        onMouseDown={action(onPin)}
        onMouseEnter={e => (e.currentTarget.style.background = 'var(--color-bg-elevated)')}
        onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
      >
        <span>📌</span>
        {isPinned ? 'Unpin tab' : 'Pin tab'}
      </div>

      {(isAudible || isMuted) && (
        <div
          style={itemStyle}
          onMouseDown={action(onMute)}
          onMouseEnter={e => (e.currentTarget.style.background = 'var(--color-bg-elevated)')}
          onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
        >
          <span>{isMuted ? '🔊' : '🔇'}</span>
          {isMuted ? 'Unmute tab' : 'Mute tab'}
        </div>
      )}

      <div
        style={itemStyle}
        onMouseDown={action(onDuplicate)}
        onMouseEnter={e => (e.currentTarget.style.background = 'var(--color-bg-elevated)')}
        onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
      >
        <span>⧉</span>
        Duplicate tab
      </div>

      <div style={sepStyle} />

      {tabCount > 1 && (
        <div
          style={itemStyle}
          onMouseDown={action(onCloseOthers)}
          onMouseEnter={e => (e.currentTarget.style.background = 'var(--color-bg-elevated)')}
          onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
        >
          <span>✕</span>
          Close other tabs
        </div>
      )}

      {tabIndex < tabCount - 1 && (
        <div
          style={itemStyle}
          onMouseDown={action(onCloseToRight)}
          onMouseEnter={e => (e.currentTarget.style.background = 'var(--color-bg-elevated)')}
          onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
        >
          <span>→✕</span>
          Close tabs to the right
        </div>
      )}

      <div style={sepStyle} />

      <div
        style={{ ...itemStyle, color: 'var(--color-danger, #e55)' }}
        onMouseDown={action(onCloseTab)}
        onMouseEnter={e => (e.currentTarget.style.background = 'var(--color-bg-elevated)')}
        onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
      >
        <span>✕</span>
        Close tab
      </div>
    </div>
  );
}

// ── Recently-closed / tab-strip menu ─────────────────────────────────────────

interface StripMenuProps {
  anchorRef: React.RefObject<HTMLButtonElement | null>;
  recentlyClosed: RecentlyClosedEntry[];
  onClose: () => void;
  onReopenEntry: (url: string) => void;
  onMuteAll: (muted: boolean) => void;
  onOpenSearch: () => void;
}

function StripMenu({ anchorRef, recentlyClosed, onClose, onReopenEntry, onMuteAll, onOpenSearch }: StripMenuProps) {
  const menuRef = useRef<HTMLDivElement>(null);
  // Global "mute all tabs" policy state — the menu item toggles it.
  const [muteAllActive, setMuteAllActive] = useState(false);

  useEffect(() => {
    let cancelled = false;
    void window.zio.audio.getMuteAll().then((v) => { if (!cancelled) setMuteAllActive(v); });
    return () => { cancelled = true; };
  }, []);

  const rect = anchorRef.current?.getBoundingClientRect();
  const left = rect ? rect.right - 200 : 80;
  const top = rect ? rect.bottom + 4 : 40;

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(e.target as Node) &&
          !anchorRef.current?.contains(e.target as Node)) {
        onClose();
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [onClose, anchorRef]);

  const menuStyle: React.CSSProperties = {
    position: 'fixed',
    left,
    top,
    background: 'var(--color-bg-surface)',
    border: '1px solid var(--color-border)',
    borderRadius: 8,
    boxShadow: '0 4px 20px rgba(0,0,0,0.3)',
    zIndex: 9999,
    minWidth: 220,
    padding: '4px 0',
    fontSize: 13,
    color: 'var(--color-text)',
    maxHeight: 360,
    overflowY: 'auto',
  };

  const itemStyle: React.CSSProperties = {
    padding: '6px 14px',
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    gap: 8,
    whiteSpace: 'nowrap',
  };

  const sepStyle: React.CSSProperties = {
    borderTop: '1px solid var(--color-border)',
    margin: '4px 0',
  };

  const action = (fn: () => void) => () => { fn(); onClose(); };

  return (
    <div ref={menuRef} style={menuStyle}>
      <div
        style={{ ...itemStyle, fontSize: 11, color: 'var(--color-text-muted)', cursor: 'default' }}
      >
        RECENTLY CLOSED
      </div>

      {recentlyClosed.length === 0 ? (
        <div style={{ ...itemStyle, color: 'var(--color-text-muted)', fontSize: 12 }}>
          No recently closed tabs
        </div>
      ) : (
        recentlyClosed.map((entry, idx) => (
          <div
            key={`${entry.url}-${idx}`}
            style={itemStyle}
            onMouseDown={action(() => onReopenEntry(entry.url))}
            onMouseEnter={e => (e.currentTarget.style.background = 'var(--color-bg-elevated)')}
            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
          >
            <FaviconImg
              src={resolveFavicon(entry.favicon, entry.url)}
              size={12}
              fallback={<div style={{ width: 12, height: 12, borderRadius: 2, background: 'var(--color-border)', flexShrink: 0 }} />}
            />
            <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: 160 }}>
              {entry.title || entry.url}
            </span>
          </div>
        ))
      )}

      <div style={sepStyle} />

      <div
        style={itemStyle}
        onMouseDown={action(onOpenSearch)}
        onMouseEnter={e => (e.currentTarget.style.background = 'var(--color-bg-elevated)')}
        onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
      >
        <span>🔍</span>
        Search tabs
        <span style={{ marginLeft: 'auto', fontSize: 10, opacity: 0.6 }}>⌘⇧A</span>
      </div>

      <div
        style={itemStyle}
        onMouseDown={action(() => onMuteAll(!muteAllActive))}
        onMouseEnter={e => (e.currentTarget.style.background = 'var(--color-bg-elevated)')}
        onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
      >
        <span>{muteAllActive ? '🔊' : '🔇'}</span>
        {muteAllActive ? 'Unmute all tabs' : 'Mute all tabs'}
      </div>
    </div>
  );
}

// ── ChromeBar ─────────────────────────────────────────────────────────────────

export function ChromeBar({
  zioPanelOpen,
  onToggleZio,
  onOpenAuth,
  onOpenTabSearch,
  downloadsPanelOpen = false,
  onToggleDownloads,
  activeDownloadCount = 0,
  isPrivate = false,
  onOpenDeviceLab,
  onScreenshot,
  screenshotCapturing = false,
  onOpenSiteSettings,
  readingListOpen,
  onToggleReadingList,
  notesPanelOpen = false,
  onToggleNotes,
  dialerPanelOpen = false,
  onToggleDialer,
  onOpenSettings,
  settingsOpen = false,
  vkEnabled = false,
  vkOpen = false,
  onToggleVk,
}: Props) {
  const {
    tabs, tabOrder, activeTabId, recentlyClosed,
    createTab, closeTab, activateTab, navigate, goBack, goForward, reload, stop,
    pinTab, duplicateTab, closeOtherTabs, closeTabsToRight, muteAllTabs, setTabMode, reopenFromRecent,
  } = useTabStore();
  const { user, token } = useAuthStore();
  const activeTab = activeTabId ? tabs[activeTabId] : null;
  const [omniboxValue, setOmniboxValue] = useState('');
  const [omniboxFocused, setOmniboxFocused] = useState(false);
  const [omniboxEdited, setOmniboxEdited] = useState(false);
  const [shortenOpen, setShortenOpen] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [clipboardOpen, setClipboardOpen] = useState(false);
  // Context-menu link tools: override target URL/title and preselected type.
  const [linkToolTarget, setLinkToolTarget] = useState<{ url: string; title: string } | null>(null);
  const [createInitialType, setCreateInitialType] = useState<string | null>(null);
  // The popovers extend below the chrome bar into the web-content area, where
  // native WebContentsViews sit ABOVE the renderer DOM and would occlude them.
  // Hold the chrome overlay (detaches native views) while either is open.
  const [overflowOpen, setOverflowOpen] = useState(false);
  // Safari-style "Settings for this website" popover (per-site settings).
  const [sitePopoverOpen, setSitePopoverOpen] = useState(false);
  const [adblockPopoverOpen, setAdblockPopoverOpen] = useState(false);
  const [adblockActive, setAdblockActive] = useState(false);
  const [adblockLocked, setAdblockLocked] = useState(false);
  const overflowBtnRef = useRef<HTMLButtonElement>(null);
  // Safari-style far-right cluster: Share popover + full-window Tab Overview.
  const [shareOpen, setShareOpen] = useState(false);
  const shareBtnRef = useRef<HTMLButtonElement>(null);
  const [tabOverviewOpen, setTabOverviewOpen] = useState(false);
  const tabOverviewRef = useRef<TabOverviewHandle>(null);
  // Toggle helper: closing goes through the overview's animated dismiss so
  // the toolbar button / keyboard shortcut never hard-unmounts it.
  const toggleTabOverview = useCallback(() => {
    setTabOverviewOpen((open) => {
      if (open) {
        tabOverviewRef.current?.dismiss();
        return open; // dismiss() calls onClose after the exit animation
      }
      return true;
    });
  }, []);
  useChromeOverlay(shortenOpen || createOpen || clipboardOpen || overflowOpen || sitePopoverOpen || adblockPopoverOpen || shareOpen || tabOverviewOpen);
  const [pendingSyncCount, setPendingSyncCount] = useState(0);
  const [pendingSyncByProfile, setPendingSyncByProfile] = useState<SyncQueueProfileCount[]>([]);
  const [contextMenu, setContextMenu] = useState<ContextMenuState | null>(null);
  const [stripMenuOpen, setStripMenuOpen] = useState(false);
  const [blockedCount, setBlockedCount] = useState(0);
  const [trackerEnabled, setTrackerEnabled] = useState(false);
  const [dropTargetId, setDropTargetId] = useState<string | null>(null);
  const dragTabIdRef = useRef<string | null>(null);
  const [savedInReadingList, setSavedInReadingList] = useState(false);
  const [unreadCount, setUnreadCount] = useState(0);
  const [noteCount, setNoteCount] = useState(0);
  const [isBookmarked, setIsBookmarked] = useState(false);
  const omniboxRef = useRef<HTMLInputElement>(null);
  const stripMenuBtnRef = useRef<HTMLButtonElement>(null);

  // ── Pinned toolbar tools (promoted from the "⋯" overflow menu) ──────────
  // Shared hook keeps this surface in sync with the Settings panel via the
  // zio:pinned-tools-changed window event and enforces the pin cap.
  const { pinned: pinnedTools, togglePin: handleTogglePin, reorderPin } = usePinnedTools();

  // Drag-to-reorder for pinned tool buttons. The dragged tool id lives in a
  // ref (dataTransfer is unreadable during dragover in Chromium) and the
  // current hover target drives a subtle highlight.
  const dragPinnedToolRef = useRef<PinnableTool | null>(null);
  const [pinDropTarget, setPinDropTarget] = useState<PinnableTool | null>(null);
  const handlePinnedToolDrop = useCallback((target: PinnableTool) => {
    const dragged = dragPinnedToolRef.current;
    dragPinnedToolRef.current = null;
    setPinDropTarget(null);
    if (!dragged || dragged === target) return;
    reorderPin(dragged, target);
  }, [reorderPin]);
  const pinnedToolDragProps = useCallback((tool: PinnableTool) => ({
    draggable: true,
    onDragStart: (e: React.DragEvent) => {
      dragPinnedToolRef.current = tool;
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', tool);
    },
    onDragOver: (e: React.DragEvent) => {
      if (!dragPinnedToolRef.current || dragPinnedToolRef.current === tool) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      setPinDropTarget(tool);
    },
    onDragLeave: () => {
      setPinDropTarget(prev => (prev === tool ? null : prev));
    },
    onDrop: (e: React.DragEvent) => {
      e.preventDefault();
      handlePinnedToolDrop(tool);
    },
    onDragEnd: () => {
      dragPinnedToolRef.current = null;
      setPinDropTarget(null);
    },
  }), [handlePinnedToolDrop]);
  const pinDropHighlight = useCallback((tool: PinnableTool): React.CSSProperties => (
    pinDropTarget === tool
      ? { outline: '2px solid var(--color-primary)', outlineOffset: -2 }
      : {}
  ), [pinDropTarget]);

  // ── Address bar suggestions ─────────────────────────────────────────────
  const [suggestions, setSuggestions] = useState<OmniSuggestion[]>([]);
  const [suggestionIndex, setSuggestionIndex] = useState(-1);
  const suggestionsOpen = omniboxFocused && suggestions.length > 0;
  const suggestQueryRef = useRef('');

  // Keep the native web view from covering the dropdown while it is open.
  // The overlay is ref-counted in main, so acquire/release must be balanced
  // exactly 1:1 — the old `setChromeOverlay(suggestionsOpen)` form released
  // once in the cleanup AND once in the next effect body (double release),
  // which stole the overlay from other open menus.
  const suggestionsHeldOverlay = useRef(false);
  useEffect(() => {
    if (suggestionsOpen && !suggestionsHeldOverlay.current) {
      suggestionsHeldOverlay.current = true;
      void window.zio.window.setChromeOverlay(true);
    } else if (!suggestionsOpen && suggestionsHeldOverlay.current) {
      suggestionsHeldOverlay.current = false;
      void window.zio.window.setChromeOverlay(false);
    }
  }, [suggestionsOpen]);
  useEffect(() => () => {
    if (suggestionsHeldOverlay.current) {
      suggestionsHeldOverlay.current = false;
      void window.zio.window.setChromeOverlay(false);
    }
  }, []);

  // Debounced history + bookmarks lookup while typing
  useEffect(() => {
    if (!omniboxFocused) { setSuggestions([]); setSuggestionIndex(-1); return; }
    const q = omniboxValue.trim();
    suggestQueryRef.current = q;
    if (q.length < 2 || q === activeTab?.url) {
      setSuggestions([]); setSuggestionIndex(-1);
      return;
    }
    // Sayzio jump rows: bare handles AND full sayzio.app handle URLs, and
    // mirror the site-resolve privacy gate — never in private windows, only
    // when signed in.
    const sayzioQuery = !isPrivate && token ? extractSayzioSuggestQuery(q) : null;
    const timer = setTimeout(() => {
      void Promise.all([
        window.zio.history.search(q).catch(() => []),
        window.zio.bookmarks.search(q).catch(() => []),
        sayzioQuery && token
          ? checkSayzioExistsWithToken(sayzioQuery.handle, token).catch(() => ({ link: false, profile: false }))
          : Promise.resolve({ link: false, profile: false }),
      ]).then(([hist, bms, sayzio]) => {
        // Ignore stale responses
        if (suggestQueryRef.current !== q) return;
        const seen = new Set<string>();
        const merged: OmniSuggestion[] = [];
        const handle = sayzioQuery?.handle ?? q;
        // A typed sayzio.app/@handle URL only offers the profile row (and
        // vice-versa for a plain link URL); a bare handle offers both.
        if (sayzio.link && sayzioQuery?.form !== 'profile') {
          merged.push({
            url: `https://sayzio.app/${handle}`,
            title: `sayzio.app/${handle}`,
            subtitle: 'Open link on Sayzio',
            kind: 'sayzio-link',
          });
        }
        if (sayzio.profile && sayzioQuery?.form !== 'link') {
          merged.push({
            url: `https://sayzio.app/@${handle}`,
            title: `sayzio.app/@${handle}`,
            subtitle: 'Creator profile',
            kind: 'sayzio-profile',
          });
        }
        for (const b of (bms as BookmarkRow[])) {
          if (!b?.url || seen.has(b.url)) continue;
          seen.add(b.url);
          merged.push({ url: b.url, title: b.title || b.url, kind: 'bookmark' });
          if (merged.length >= 4) break;
        }
        for (const h of (hist as HistoryRow[])) {
          if (!h?.url || seen.has(h.url)) continue;
          seen.add(h.url);
          merged.push({ url: h.url, title: h.title || h.url, kind: 'history' });
          if (merged.length >= 7) break;
        }
        // Always offer a "search the web" fallback row
        merged.push({ url: q, title: `Search for "${q}"`, kind: 'search' });
        setSuggestions(merged);
        setSuggestionIndex(-1);
      });
    }, 120);
    return () => clearTimeout(timer);
  }, [omniboxValue, omniboxFocused, activeTab?.url, isPrivate, token]);

  const acceptSuggestion = useCallback((s: OmniSuggestion) => {
    if (!activeTabId) return;
    void navigate(activeTabId, s.url);
    setSuggestions([]);
    setSuggestionIndex(-1);
    setOmniboxEdited(false);
    discardedTypedTextRef.current = null;
    omniboxRef.current?.blur();
  }, [activeTabId, navigate]);

  const handleOmniboxKeyDown = useCallback((e: React.KeyboardEvent) => {
    // Ctrl/Cmd+Z restores typed text that an automatic navigation discarded,
    // but only when there are no fresh uncommitted edits (native undo applies then).
    if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && e.key.toLowerCase() === 'z'
        && !omniboxEdited && discardedTypedTextRef.current !== null) {
      e.preventDefault();
      setOmniboxValue(discardedTypedTextRef.current);
      setOmniboxEdited(true);
      discardedTypedTextRef.current = null;
      return;
    }
    if (!suggestionsOpen) {
      if (e.key === 'Escape') {
        // Like Chrome, Escape-cleared text goes into the Ctrl/Cmd+Z recovery
        // stash so the reset doesn't strand recoverable text.
        if (omniboxEdited && omniboxValue.trim() !== '') {
          discardedTypedTextRef.current = omniboxValue;
        }
        setOmniboxEdited(false);
        setOmniboxValue(activeTab?.url ?? '');
        omniboxRef.current?.blur();
      }
      return;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSuggestionIndex(prev => (prev + 1) % suggestions.length);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSuggestionIndex(prev => (prev <= 0 ? suggestions.length - 1 : prev - 1));
    } else if (e.key === 'Enter') {
      if (suggestionIndex >= 0 && suggestions[suggestionIndex]) {
        e.preventDefault();
        acceptSuggestion(suggestions[suggestionIndex]);
      }
      // else fall through to the form submit
    } else if (e.key === 'Escape') {
      setSuggestions([]);
      setSuggestionIndex(-1);
    }
  }, [suggestionsOpen, suggestions, suggestionIndex, acceptSuggestion, activeTab?.url, omniboxEdited, omniboxValue]);

  // Track reading list state for the active page
  useEffect(() => {
    const url = activeTab?.url;
    if (!url || url === 'about:newtab' || url === '') {
      setSavedInReadingList(false);
      return;
    }
    let cancelled = false;
    void window.zio.readingList.isSaved(url).then((saved: boolean) => {
      if (!cancelled) setSavedInReadingList(saved);
    }).catch(() => { /* main not ready */ });
    return () => { cancelled = true; };
  }, [activeTab?.url]);

  // Load unread count on mount and when reading list panel closes
  useEffect(() => {
    let cancelled = false;
    void window.zio.readingList.unreadCount().then((n: number) => {
      if (!cancelled) setUnreadCount(n);
    }).catch(() => { /* main not ready */ });
    return () => { cancelled = true; };
  }, [readingListOpen]);

  // Per-site note count badge for the notes tool — cheap cache-only read on
  // tab switch/navigation; refreshed when the notes panel closes (edits) and
  // live via the main-process 'notes:changed' event (save/delete/offline sync).
  useEffect(() => {
    const url = activeTab?.url;
    let host: string | null = null;
    if (url && url !== 'about:newtab' && /^https?:/i.test(url)) {
      try {
        host = new URL(url).hostname.toLowerCase().replace(/^www\./, '') || null;
      } catch { /* invalid URL */ }
    }
    if (!host || isPrivate) {
      setNoteCount(0);
      return;
    }
    let cancelled = false;
    const refresh = () => {
      void window.zio.notes.countForHost(host).then((n: number) => {
        if (!cancelled) setNoteCount(n);
      }).catch(() => { /* main not ready */ });
    };
    refresh();
    window.zio.on('notes:changed', refresh);
    return () => {
      cancelled = true;
      window.zio.off('notes:changed', refresh);
    };
  }, [activeTab?.url, isPrivate, notesPanelOpen]);

  // Track bookmark state for the active page
  useEffect(() => {
    const url = activeTab?.url;
    if (!url || url === 'about:newtab' || url === '') {
      setIsBookmarked(false);
      return;
    }
    let cancelled = false;
    const check = () => {
      void window.zio.bookmarks.isBookmarked(url).then((saved: boolean) => {
        if (!cancelled) setIsBookmarked(saved);
      }).catch(() => { /* main not ready */ });
    };
    check();
    // Re-check when the main process changes bookmarks (menu "Bookmark This Page").
    window.zio.on('bookmarks:changed', check);
    return () => {
      cancelled = true;
      window.zio.off('bookmarks:changed', check);
    };
  }, [activeTab?.url]);

  const handleToggleBookmark = useCallback(async () => {
    const url = activeTab?.url;
    if (!url || url === 'about:newtab') return;
    try {
      if (isBookmarked) {
        await window.zio.bookmarks.remove(url);
        setIsBookmarked(false);
      } else {
        await window.zio.bookmarks.add(url, activeTab?.title ?? url);
        setIsBookmarked(true);
      }
    } catch { /* non-fatal */ }
  }, [activeTab?.url, activeTab?.title, isBookmarked]);

  const handleSaveToReadingList = useCallback(async () => {
    if (!activeTab?.url || activeTab.url === 'about:newtab') return;
    if (savedInReadingList) {
      onToggleReadingList();
      return;
    }
    try {
      await window.zio.readingList.add(activeTab.url, activeTab.title ?? activeTab.url, activeTab.favicon ?? undefined);
      setSavedInReadingList(true);
      setUnreadCount(prev => prev + 1);
    } catch { /* non-fatal */ }
  }, [activeTab, savedInReadingList, onToggleReadingList]);

  // Track queued (offline / failed) sync pushes for the pending indicator
  useEffect(() => {
    let cancelled = false;
    void window.zio.sync.pendingCount().then((n: number) => {
      if (!cancelled) setPendingSyncCount(n);
    }).catch(() => { /* main not ready yet — event listener will update */ });
    void window.zio.sync.pendingByProfile().then((rows: SyncQueueProfileCount[]) => {
      if (!cancelled) setPendingSyncByProfile(rows);
    }).catch(() => { /* main not ready yet — event listener will update */ });

    const listener = (...args: unknown[]) => {
      const n = args[0];
      if (typeof n === 'number') setPendingSyncCount(n);
      const byProfile = args[1];
      if (Array.isArray(byProfile)) setPendingSyncByProfile(byProfile as SyncQueueProfileCount[]);
    };
    window.zio.on('sync:queue-changed', listener);
    return () => {
      cancelled = true;
      window.zio.off('sync:queue-changed', listener);
    };
  }, []);

  // Read initial tracker state
  useEffect(() => {
    void window.zio.tracker.isEnabled().then((v: boolean) => setTrackerEnabled(v)).catch(() => {});
  }, []);

  // Listen for per-tab blocked-count updates
  useEffect(() => {
    const listener = (...args: unknown[]) => {
      const tabId = args[0] as string;
      const count = args[1] as number;
      if (tabId === activeTabId) setBlockedCount(count);
    };
    window.zio.on('tracker:blocked-count', listener);
    return () => window.zio.off('tracker:blocked-count', listener);
  }, [activeTabId]);

  // Reset blocked count when active tab changes or navigates
  useEffect(() => {
    if (!activeTabId) { setBlockedCount(0); return; }
    void window.zio.tracker.getCount(activeTabId).then((n: number) => setBlockedCount(n)).catch(() => setBlockedCount(0));
  }, [activeTabId]);

  // Ad-block shield icon state — effective policy for the active tab.
  useEffect(() => {
    let cancelled = false;
    const refresh = () => {
      if (!activeTabId) { setAdblockActive(false); setAdblockLocked(false); return; }
      // Optional-chained: unit-test harnesses mock window.zio partially.
      void window.zio.adblock?.getState?.(activeTabId)?.then(s => {
        if (cancelled) return;
        setAdblockActive(s.active);
        setAdblockLocked(s.adminLocked);
      }).catch(() => {});
    };
    refresh();
    window.zio.on('adblock:state-changed', refresh);
    return () => {
      cancelled = true;
      window.zio.off('adblock:state-changed', refresh);
    };
  }, [activeTabId, activeTab?.url]);

  // Sync omnibox with active tab URL (unless the user has uncommitted edits);
  // discard-on-navigation + tab-switch reset live in the shared hook so the
  // behavior is unit-testable (tests/omnibox-url-sync.test.tsx). The hook also
  // stashes text discarded by an automatic navigation so the user can recover
  // it with Ctrl/Cmd+Z in the omnibox (like Chrome); the buffer is kept per
  // tab across tab switches (returning to a tab restores its stash) and
  // clears whenever the user commits a navigation themselves.
  const { discardedTypedTextRef } = useOmniboxUrlSync({
    activeTabId,
    activeTabUrl: activeTab?.url ?? '',
    omniboxFocused,
    omniboxEdited,
    omniboxValue,
    setOmniboxValue,
    setOmniboxEdited,
  });

  // Close popovers when the active tab changes
  useEffect(() => {
    setShortenOpen(false);
    setCreateOpen(false);
    setLinkToolTarget(null);
    setCreateInitialType(null);
  }, [activeTabId]);

  // Context-menu triggers from the main process: "Shorten…" / "QR code…" /
  // "Fill form with my Sayzio card".
  useEffect(() => {
    const onShorten = (...args: unknown[]) => {
      const [url, title] = args as [string, string];
      if (!url) return;
      setLinkToolTarget({ url, title: title ?? '' });
      setCreateOpen(false);
      setCreateInitialType(null);
      setShortenOpen(true);
    };
    const onQr = (...args: unknown[]) => {
      const [url, title] = args as [string, string];
      if (!url) return;
      setLinkToolTarget({ url, title: title ?? '' });
      setShortenOpen(false);
      setCreateInitialType('qr');
      setCreateOpen(true);
    };
    const onAutofill = (...args: unknown[]) => {
      const [tabId] = args as [string];
      if (tabId) void runContextAutofill(tabId, token);
    };
    window.zio.on('link:shorten-page', onShorten);
    window.zio.on('link:create-qr', onQr);
    window.zio.on('autofill:page', onAutofill);
    return () => {
      window.zio.off('link:shorten-page', onShorten);
      window.zio.off('link:create-qr', onQr);
      window.zio.off('autofill:page', onAutofill);
    };
  }, [token]);

  const canShorten = !!(activeTab?.url && activeTab.url !== 'about:newtab' && activeTab.url !== '');

  // Keyboard shortcut: Cmd/Ctrl+Shift+\ toggles the Tab Overview (Safari's
  // Show All Tabs shortcut).
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.shiftKey && (e.key === '\\' || e.key === '|')) {
        e.preventDefault();
        toggleTabOverview();
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [toggleTabOverview]);

  // "On Sayzio" site detection — debounced, per-host cached public lookup.
  // The hook clears the badge the moment the window flips private or the
  // user signs out, and cancels any pending debounced lookup.
  const siteResolve = useSiteResolve({
    url: activeTab?.url,
    isPrivate: !!isPrivate,
    token,
  });

  // Listen for custom events dispatched by the command palette
  useEffect(() => {
    const onShortenOpen = () => {
      if (canShorten) setShortenOpen(true);
    };
    const onFocusAddressBar = () => {
      omniboxRef.current?.focus();
      omniboxRef.current?.select();
    };
    document.addEventListener('zio:shorten-open', onShortenOpen);
    document.addEventListener('zio:focus-address-bar', onFocusAddressBar);
    return () => {
      document.removeEventListener('zio:shorten-open', onShortenOpen);
      document.removeEventListener('zio:focus-address-bar', onFocusAddressBar);
    };
  }, [canShorten]);

  const handleOmniboxSubmit = useCallback((e: React.FormEvent) => {
    e.preventDefault();
    if (!activeTabId || !omniboxValue.trim()) return;
    void navigate(activeTabId, omniboxValue.trim());
    setOmniboxEdited(false);
    discardedTypedTextRef.current = null;
    omniboxRef.current?.blur();
  }, [activeTabId, navigate, omniboxValue]);

  const handleNewTab = useCallback(() => {
    void createTab();
  }, [createTab]);

  const openContextMenu = useCallback((e: React.MouseEvent, tabId: string) => {
    e.preventDefault();
    e.stopPropagation();
    setContextMenu({ tabId, x: e.clientX, y: e.clientY });
  }, []);

  const closeContextMenu = useCallback(() => setContextMenu(null), []);

  // ── Drag-to-reorder tabs ─────────────────────────────────────────────────
  const handleTabDragStart = useCallback((e: React.DragEvent, tabId: string) => {
    dragTabIdRef.current = tabId;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', tabId);
  }, []);

  const handleTabDragOver = useCallback((e: React.DragEvent, tabId: string) => {
    if (!dragTabIdRef.current || dragTabIdRef.current === tabId) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    setDropTargetId(tabId);
  }, []);

  const handleTabDrop = useCallback((e: React.DragEvent, targetId: string) => {
    e.preventDefault();
    const dragId = dragTabIdRef.current;
    dragTabIdRef.current = null;
    setDropTargetId(null);
    if (!dragId || dragId === targetId) return;
    const toIndex = tabOrder.indexOf(targetId);
    if (toIndex === -1) return;
    // Main clamps the index to the pinned/normal section of the dragged tab.
    void window.zio.tabs.move(dragId, toIndex);
  }, [tabOrder]);

  const handleTabDragEnd = useCallback(() => {
    dragTabIdRef.current = null;
    setDropTargetId(null);
  }, []);

  const activeTabState = activeTab;

  // Pinned tabs always first in tabOrder (maintained by tab-manager); split for rendering
  const pinnedTabIds = tabOrder.filter(id => tabs[id]?.pinned);
  const normalTabIds = tabOrder.filter(id => !tabs[id]?.pinned);

  const ctxTab = contextMenu ? tabs[contextMenu.tabId] : null;
  const ctxTabIndex = contextMenu ? tabOrder.indexOf(contextMenu.tabId) : -1;

  return (
    <div style={{
      height: 'var(--chrome-height)',
      background: 'var(--color-bg-surface)',
      borderBottom: '1px solid var(--color-border)',
      display: 'flex',
      flexDirection: 'column',
      WebkitAppRegion: 'drag',
      position: 'relative',
    } as React.CSSProperties}>

      {/* Tab Strip */}
      <div style={{
        height: 'var(--tab-height)',
        display: 'flex',
        alignItems: 'center',
        paddingLeft: window.zio.platform === 'darwin' ? 80 : 8,
        paddingRight: 4,
        gap: 2,
        overflowX: 'auto',
        overflowY: 'hidden',
      }}>
        {/* Private mode badge — always visible at the left of the tab strip */}
        {isPrivate && (
          <div style={{
            display: 'flex',
            alignItems: 'center',
            gap: 4,
            padding: '2px 10px',
            borderRadius: 12,
            background: 'rgba(37,99,235,0.18)',
            border: '1px solid rgba(59,130,246,0.45)',
            color: '#93c5fd',
            fontSize: 11,
            fontWeight: 600,
            whiteSpace: 'nowrap',
            flexShrink: 0,
            letterSpacing: 0.2,
          }}>
            🔒 Private
          </div>
        )}
        {/* Pinned tabs — icon-only, compact */}
        {pinnedTabIds.map(id => {
          const tab = tabs[id];
          const isActive = id === activeTabId;
          return (
            <div
              key={id}
              draggable
              onDragStart={(e) => handleTabDragStart(e, id)}
              onDragOver={(e) => handleTabDragOver(e, id)}
              onDragLeave={() => setDropTargetId(prev => (prev === id ? null : prev))}
              onDrop={(e) => handleTabDrop(e, id)}
              onDragEnd={handleTabDragEnd}
              onClick={() => void activateTab(id)}
              onContextMenu={(e) => openContextMenu(e, id)}
              title={tab?.title || 'New Tab'}
              style={{
                boxShadow: dropTargetId === id ? 'inset 2px 0 0 var(--color-primary)' : 'none',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                gap: 4,
                padding: '0 6px',
                height: 28,
                width: 36,
                borderRadius: 6,
                background: isActive ? 'var(--color-bg-elevated)' : 'transparent',
                border: isActive ? '1px solid var(--color-border)' : '1px solid transparent',
                cursor: 'pointer',
                WebkitAppRegion: 'no-drag',
                flexShrink: 0,
                position: 'relative',
              } as React.CSSProperties}
            >
              {tab?.isLoading ? (
                <img src={zioIcon} width={14} height={14} className="zio-loading-icon" alt="" />
              ) : tab?.favicon ? (
                <img src={tab.favicon} width={14} height={14} style={{ borderRadius: 2, filter: isActive ? 'none' : 'grayscale(1)', opacity: isActive ? 1 : 0.75 }} alt="" />
              ) : (
                <div style={{ width: 14, height: 14, borderRadius: 2, background: 'var(--color-border)' }} />
              )}
              {/* Pin indicator dot */}
              <div style={{
                position: 'absolute',
                top: 2,
                right: 2,
                width: 5,
                height: 5,
                borderRadius: '50%',
                background: 'var(--gradient-primary)',
              }} />
              {/* Audio indicator */}
              {tab?.isAudible && !tab.isMuted && (
                <div
                  style={{
                    position: 'absolute',
                    bottom: 1,
                    right: 1,
                    fontSize: 8,
                    lineHeight: 1,
                  }}
                  onClick={(e) => {
                    e.stopPropagation();
                    void window.zio.tabs.mute(id, true);
                  }}
                  title="Mute tab"
                >🔊</div>
              )}
              {tab?.isMuted && (
                <div
                  style={{ position: 'absolute', bottom: 1, right: 1, fontSize: 8, lineHeight: 1 }}
                  onClick={(e) => {
                    e.stopPropagation();
                    void window.zio.tabs.mute(id, false);
                  }}
                  title="Unmute tab"
                >🔇</div>
              )}
            </div>
          );
        })}

        {/* Divider between pinned and normal tabs */}
        {pinnedTabIds.length > 0 && normalTabIds.length > 0 && (
          <div style={{
            width: 1,
            height: 20,
            background: 'var(--color-border)',
            flexShrink: 0,
            margin: '0 2px',
          }} />
        )}

        {/* Normal tabs */}
        {normalTabIds.map(id => {
          const tab = tabs[id];
          const isActive = id === activeTabId;
          return (
            <div
              key={id}
              draggable
              onDragStart={(e) => handleTabDragStart(e, id)}
              onDragOver={(e) => handleTabDragOver(e, id)}
              onDragLeave={() => setDropTargetId(prev => (prev === id ? null : prev))}
              onDrop={(e) => handleTabDrop(e, id)}
              onDragEnd={handleTabDragEnd}
              onClick={() => void activateTab(id)}
              onContextMenu={(e) => openContextMenu(e, id)}
              style={{
                boxShadow: dropTargetId === id ? 'inset 2px 0 0 var(--color-primary)' : 'none',
                display: 'flex',
                alignItems: 'center',
                gap: 6,
                padding: '0 8px 0 10px',
                height: 28,
                minWidth: 120,
                maxWidth: 200,
                borderRadius: 6,
                background: isActive ? 'var(--color-bg-elevated)' : 'transparent',
                border: isActive ? '1px solid var(--color-border)' : '1px solid transparent',
                cursor: 'pointer',
                WebkitAppRegion: 'no-drag',
                flexShrink: 0,
                position: 'relative',
              } as React.CSSProperties}
            >
              {tab?.isLoading ? (
                <img src={zioIcon} width={14} height={14} className="zio-loading-icon" style={{ flexShrink: 0 }} alt="" />
              ) : tab?.favicon ? (
                <img src={tab.favicon} width={14} height={14} style={{ borderRadius: 2, flexShrink: 0, filter: isActive ? 'none' : 'grayscale(1)', opacity: isActive ? 1 : 0.75 }} alt="" />
              ) : (
                <div style={{ width: 14, height: 14, borderRadius: 2, background: 'var(--color-border)', flexShrink: 0 }} />
              )}

              <span style={{
                flex: 1,
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                whiteSpace: 'nowrap',
                fontSize: 12,
                color: isActive ? 'var(--color-text)' : 'var(--color-text-muted)',
              }}>
                {tab?.title || 'New Tab'}
              </span>

              {/* Audio indicator — click to mute */}
              {tab?.isAudible && !tab.isMuted && (
                <button
                  onClick={(e) => { e.stopPropagation(); void window.zio.tabs.mute(id, true); }}
                  title="Mute tab"
                  style={{
                    width: 16, height: 16, borderRadius: 4,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontSize: 10, flexShrink: 0,
                    WebkitAppRegion: 'no-drag',
                  } as React.CSSProperties}
                >🔊</button>
              )}
              {tab?.isMuted && (
                <button
                  onClick={(e) => { e.stopPropagation(); void window.zio.tabs.mute(id, false); }}
                  title="Unmute tab"
                  style={{
                    width: 16, height: 16, borderRadius: 4,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontSize: 10, flexShrink: 0, opacity: 0.7,
                    WebkitAppRegion: 'no-drag',
                  } as React.CSSProperties}
                >🔇</button>
              )}

              {/* Close button — hidden for pinned tabs */}
              <button
                onClick={(e) => { e.stopPropagation(); void closeTab(id); }}
                style={{
                  width: 16, height: 16, borderRadius: 4,
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  fontSize: 10, opacity: 0.6, flexShrink: 0,
                  WebkitAppRegion: 'no-drag',
                } as React.CSSProperties}
              >✕</button>
            </div>
          );
        })}

        {/* New Tab Button */}
        <button
          onClick={handleNewTab}
          style={{
            width: 28, height: 28, borderRadius: 6,
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: 16, color: 'var(--color-text-muted)',
            WebkitAppRegion: 'no-drag',
            flexShrink: 0,
          } as React.CSSProperties}
          title="New tab (Ctrl+T)"
        >+</button>

        {/* Tab strip menu — recently closed + tab actions */}
        <button
          ref={stripMenuBtnRef}
          onClick={() => setStripMenuOpen(v => !v)}
          style={{
            width: 24, height: 24, borderRadius: 5,
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: 13, color: 'var(--color-text-muted)',
            WebkitAppRegion: 'no-drag',
            background: stripMenuOpen ? 'var(--color-bg-elevated)' : 'transparent',
            flexShrink: 0,
          } as React.CSSProperties}
          title="Recently closed tabs & tab actions"
        >⋮</button>

        {/* Account — avatar menu at the right edge of the tab row.
            Rendered signed-out too so a "Sign in" affordance appears after logout. */}
        <div style={{ marginLeft: 'auto', flexShrink: 0, display: 'flex', alignItems: 'center' }}>
          <AccountButton onOpenAuth={onOpenAuth} compact />
        </div>
      </div>

      {/* Address Bar Row */}
      <div style={{
        height: 36,
        display: 'flex',
        alignItems: 'center',
        gap: 8,
        padding: '0 12px',
        WebkitAppRegion: 'no-drag',
      } as React.CSSProperties}>

        {/* Nav buttons */}
        <button
          onClick={() => activeTabId && void goBack(activeTabId)}
          disabled={!activeTabState?.canGoBack}
          style={{ opacity: activeTabState?.canGoBack ? 1 : 0.3, fontSize: 14, padding: '2px 6px' }}
          title="Back"
        >←</button>
        <button
          onClick={() => activeTabId && void goForward(activeTabId)}
          disabled={!activeTabState?.canGoForward}
          style={{ opacity: activeTabState?.canGoForward ? 1 : 0.3, fontSize: 14, padding: '2px 6px' }}
          title="Forward"
        >→</button>
        <button
          onClick={() => activeTabId && (activeTabState?.isLoading ? void stop(activeTabId) : void reload(activeTabId))}
          style={{ fontSize: 14, padding: '2px 6px' }}
          title={activeTabState?.isLoading ? 'Stop' : 'Reload'}
        >{activeTabState?.isLoading ? '✕' : '↻'}</button>
        <button
          onClick={() => activeTabId && void navigate(activeTabId, BASE_URL)}
          style={{ fontSize: 14, padding: '2px 6px' }}
          title="Home"
        >⌂</button>

        {/* "On Sayzio" chip — shown when the current site is a verified Sayzio custom domain */}
        {siteResolve?.on_sayzio && (
          <button
            onClick={() => {
              const handle = siteResolve.owner?.handle;
              if (activeTabId && handle) void navigate(activeTabId, `${BASE_URL}/@${handle}`);
            }}
            style={{
              display: 'flex', alignItems: 'center', gap: 4,
              fontSize: 11, fontWeight: 600,
              padding: '2px 8px', borderRadius: 10,
              color: '#fff', background: 'var(--color-primary)',
              flexShrink: 0, whiteSpace: 'nowrap',
            }}
            title={siteResolve.owner?.name
              ? `This site is on Sayzio — run by ${siteResolve.owner.name}. Click to view their profile.`
              : 'This site is on Sayzio'}
          >⚡ On Sayzio</button>
        )}

        {/* Omnibox */}
        {(() => {
          // In the Website+Website split the single address bar drives the
          // focused pane; surface WHICH pane that is with a small badge so
          // similar-looking sites can't be confused. Clicking it toggles the
          // controlled pane.
          const splitPane = activeTab?.mode === 'browser+browser'
            ? (activeTab.focusedPane ?? 'primary')
            : null;
          return (
        <form onSubmit={handleOmniboxSubmit} style={{ flex: 1, position: 'relative' }}>
          <input
            ref={omniboxRef}
            value={omniboxFocused || omniboxEdited ? omniboxValue : (activeTab?.url ?? '')}
            onChange={e => { setOmniboxValue(e.target.value); setOmniboxEdited(true); }}
            onFocus={(e) => { setOmniboxFocused(true); setOmniboxValue(e.target.value); e.target.select(); }}
            onBlur={() => setOmniboxFocused(false)}
            onKeyDown={handleOmniboxKeyDown}
            placeholder="Search or enter URL"
            style={{
              width: '100%',
              height: 28,
              borderRadius: 14,
              border: '1px solid var(--color-border)',
              background: omniboxFocused ? 'var(--color-bg-elevated)' : 'var(--color-bg)',
              color: 'var(--color-text)',
              padding: '0 14px',
              fontSize: 13,
              outline: omniboxFocused ? '2px solid var(--color-primary)' : 'none',
              outlineOffset: 0,
              transition: 'all 0.15s',
              ...(splitPane ? { paddingRight: 122 } : {}),
            }}
          />

          {/* Split-pane controls — swap panes + which pane the address bar controls */}
          {splitPane && (
            <div
              style={{
                position: 'absolute',
                right: 5,
                top: '50%',
                transform: 'translateY(-50%)',
                display: 'inline-flex',
                alignItems: 'center',
                gap: 4,
              }}
            >
              {/* Swap the two panes' contents (URL/history) in one click */}
              <button
                type="button"
                // mousedown (with preventDefault) keeps omnibox focus intact
                onMouseDown={(e) => {
                  e.preventDefault();
                  if (activeTabId) {
                    void window.zio.tabs.swapPanes(activeTabId);
                  }
                }}
                title="Swap panes — move the left site to the right and the right site to the left (Ctrl/Cmd+Shift+S)"
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  width: 22,
                  height: 20,
                  padding: 0,
                  borderRadius: 10,
                  border: '1px solid var(--color-border)',
                  fontSize: 12,
                  color: 'var(--color-text)',
                  background: 'var(--color-bg)',
                  cursor: 'pointer',
                  lineHeight: 1,
                }}
              >
                <span aria-hidden>⇄</span>
              </button>
              <button
                type="button"
                // mousedown (with preventDefault) keeps omnibox focus intact
                onMouseDown={(e) => {
                  e.preventDefault();
                  if (activeTabId) {
                    void window.zio.tabs.focusPane(
                      activeTabId,
                      splitPane === 'primary' ? 'second' : 'primary',
                    );
                  }
                }}
                title={`The address bar controls the ${splitPane === 'primary' ? 'left' : 'right'} pane. Click to switch to the ${splitPane === 'primary' ? 'right' : 'left'} pane.`}
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: 4,
                  height: 20,
                  padding: '0 8px',
                  borderRadius: 10,
                  border: 'none',
                  fontSize: 10,
                  fontWeight: 700,
                  letterSpacing: 0.3,
                  textTransform: 'uppercase',
                  whiteSpace: 'nowrap',
                  color: '#fff',
                  background: 'var(--color-primary)',
                  cursor: 'pointer',
                  lineHeight: 1,
                }}
              >
                <span aria-hidden style={{ fontSize: 11 }}>{splitPane === 'primary' ? '◧' : '◨'}</span>
                {splitPane === 'primary' ? 'Left pane' : 'Right pane'}
              </button>
            </div>
          )}

          {/* Suggestions dropdown */}
          {suggestionsOpen && (
            <div style={{
              position: 'absolute',
              top: 'calc(100% + 4px)',
              left: 0,
              right: 0,
              background: 'var(--color-bg-surface)',
              border: '1px solid var(--color-border)',
              borderRadius: 10,
              boxShadow: '0 10px 32px rgba(0,0,0,0.35)',
              zIndex: 3000,
              overflow: 'hidden',
            }}>
              {suggestions.map((s, i) => (
                <div
                  key={`${s.kind}:${s.url}`}
                  // mousedown fires before the input's blur, so the click always lands
                  onMouseDown={(e) => { e.preventDefault(); acceptSuggestion(s); }}
                  onMouseEnter={() => setSuggestionIndex(i)}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 8,
                    padding: '7px 12px',
                    cursor: 'pointer',
                    background: i === suggestionIndex ? 'var(--color-bg-elevated)' : 'transparent',
                    transition: 'background 0.08s',
                  }}
                >
                  {(s.kind === 'sayzio-link' || s.kind === 'sayzio-profile') ? (
                    <span style={{
                      fontSize: 9,
                      fontWeight: 800,
                      flexShrink: 0,
                      width: 16,
                      height: 16,
                      borderRadius: 5,
                      display: 'inline-flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      background: 'var(--gradient-primary, var(--color-primary))',
                      color: '#fff',
                      lineHeight: 1,
                    }}>S</span>
                  ) : (
                    <span style={{ fontSize: 12, flexShrink: 0, opacity: 0.8 }}>
                      {s.kind === 'bookmark' ? '★' : s.kind === 'history' ? '🕘' : '🔍'}
                    </span>
                  )}
                  <span style={{
                    fontSize: 12,
                    color: 'var(--color-text)',
                    whiteSpace: 'nowrap',
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    flexShrink: 0,
                    maxWidth: '45%',
                  }}>{s.title}</span>
                  {s.subtitle ? (
                    <span style={{
                      fontSize: 11,
                      color: 'var(--color-text-muted)',
                      whiteSpace: 'nowrap',
                      overflow: 'hidden',
                      textOverflow: 'ellipsis',
                    }}>{s.subtitle}</span>
                  ) : s.kind !== 'search' && (
                    <span style={{
                      fontSize: 11,
                      color: 'var(--color-text-muted)',
                      whiteSpace: 'nowrap',
                      overflow: 'hidden',
                      textOverflow: 'ellipsis',
                    }}>{s.url}</span>
                  )}
                </div>
              ))}
            </div>
          )}
        </form>
          );
        })()}

        {/* ── Link tool buttons ─────────────────────────────────────────────── */}

        {/* Create link popover trigger */}
        <button
          onClick={() => {
            if (!token) { onOpenAuth(); return; }
            setShortenOpen(false);
            setCreateOpen(prev => !prev);
          }}
          title="Create — shorten this page, QR code, biolink, event, vCard, WiFi, and more"
          style={{
            fontSize: 12,
            padding: '3px 10px',
            borderRadius: 8,
            background: createOpen ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
            color: createOpen ? '#fff' : 'var(--color-text)',
            border: '1px solid var(--color-border)',
            fontWeight: 600,
            whiteSpace: 'nowrap',
            transition: 'all 0.12s',
            cursor: 'pointer',
          }}
        >+ Create</button>

        {/* Clipboard tool — read clipboard, detect content, shorten links */}
        {!isPrivate && (
          <button
            onClick={() => {
              setShortenOpen(false);
              setCreateOpen(false);
              setClipboardOpen(prev => !prev);
            }}
            title="Clipboard — detect what you copied and create a short URL from it"
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              width: 28,
              height: 28,
              borderRadius: 8,
              background: clipboardOpen ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
              border: '1px solid var(--color-border)',
              fontSize: 14,
              flexShrink: 0,
              cursor: 'pointer',
              transition: 'all 0.12s',
            }}
          >📋</button>
        )}

        {/* Sync pending indicator */}
        {pendingSyncCount > 0 && (
          <div
            title={
              pendingSyncByProfile.length > 0
                ? `Waiting to sync — will retry automatically: ${pendingSyncByProfile
                    .map(p => `${p.count} pending for ${p.profileName}`)
                    .join(', ')}`
                : `${pendingSyncCount} change${pendingSyncCount === 1 ? '' : 's'} waiting to sync — will retry automatically`
            }
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: 4,
              padding: '2px 8px',
              borderRadius: 10,
              background: 'var(--color-bg-elevated)',
              border: '1px solid var(--color-border)',
              fontSize: 11,
              color: 'var(--color-text-muted)',
              whiteSpace: 'nowrap',
              flexShrink: 0,
            }}
          >
            <span style={{
              width: 6,
              height: 6,
              borderRadius: '50%',
              background: '#f0a020',
              flexShrink: 0,
            }} />
            Sync pending
          </div>
        )}

        {/* "Settings for this website" popover button (per-site settings). */}
        {!isPrivate && (() => {
          let siteOrigin: string | null = null;
          try {
            const u = new URL(activeTab?.url ?? '');
            if (u.origin.startsWith('http')) siteOrigin = u.origin;
          } catch { /* not a web page */ }
          if (!siteOrigin) return null;
          return (
            <>
              <button
                onClick={() => setSitePopoverOpen(o => !o)}
                title="Settings for this website"
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  width: 28,
                  height: 28,
                  borderRadius: 8,
                  background: sitePopoverOpen ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
                  color: sitePopoverOpen ? '#fff' : 'var(--color-text-muted)',
                  border: '1px solid var(--color-border)',
                  fontSize: 14,
                  flexShrink: 0,
                  cursor: 'pointer',
                  transition: 'all 0.12s',
                }}
              >
                ⚙️
              </button>
              {sitePopoverOpen && (
                <SiteSettingsPopover
                  origin={siteOrigin}
                  onClose={() => setSitePopoverOpen(false)}
                />
              )}
            </>
          );
        })()}

        {/* Shield button — ad-block quick controls popover, with tracker badge */}
        <button
          onClick={() => setAdblockPopoverOpen(o => !o)}
          title={adblockLocked
            ? 'Ad blocking — Managed by Sayzio'
            : adblockActive
              ? `Ad blocking on — ${blockedCount} blocked on this page`
              : 'Ad blocking off or paused'}
          style={{
            position: 'relative',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            width: 28,
            height: 28,
            borderRadius: 8,
            background: adblockPopoverOpen ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
            border: `1px solid ${blockedCount > 0 ? 'var(--color-success)' : 'var(--color-border)'}`,
            fontSize: 14,
            opacity: adblockActive || trackerEnabled || blockedCount > 0 ? 1 : 0.65,
            transition: 'all 0.15s',
            flexShrink: 0,
          } as React.CSSProperties}
        >
          {adblockLocked ? '🔒' : adblockActive ? '🛡️' : '🛡'}
          {trackerEnabled && blockedCount > 0 && (
            <span style={{
              position: 'absolute',
              top: -5,
              right: -5,
              minWidth: 14,
              height: 14,
              borderRadius: 7,
              background: 'var(--color-success)',
              color: '#fff',
              fontSize: 9,
              fontWeight: 700,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              padding: '0 3px',
              lineHeight: 1,
            }}>
              {blockedCount > 99 ? '99+' : blockedCount}
            </span>
          )}
        </button>
        {adblockPopoverOpen && activeTabId && (
          <AdBlockShieldPopover
            tabId={activeTabId}
            host={(() => { try { return new URL(activeTab?.url ?? '').hostname; } catch { return ''; } })()}
            blockedCount={blockedCount}
            onClose={() => setAdblockPopoverOpen(false)}
          />
        )}

        {/* Bookmark button (hidden in private windows — bookmarks are not saved there) */}
        {!isPrivate && (
        <button
          onClick={() => void handleToggleBookmark()}
          title={isBookmarked ? 'Remove bookmark' : 'Bookmark this page'}
          style={{
            fontSize: 15,
            padding: '2px 7px',
            borderRadius: 8,
            background: 'var(--color-bg-elevated)',
            color: isBookmarked ? 'var(--color-primary)' : 'var(--color-text-muted)',
            border: '1px solid var(--color-border)',
            transition: 'all 0.12s',
            flexShrink: 0,
          }}
        >
          {isBookmarked ? '★' : '☆'}
        </button>
        )}

        {/* Zio AI button — hidden / disabled in private mode */}
        {!isPrivate ? (
          <button
            onClick={onToggleZio}
            style={{
              padding: '3px 10px',
              borderRadius: 8,
              background: zioPanelOpen ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
              color: zioPanelOpen ? '#fff' : 'var(--color-text)',
              border: '1px solid var(--color-border)',
              fontSize: 12,
              fontWeight: 600,
              whiteSpace: 'nowrap',
              flexShrink: 0,
              cursor: 'pointer',
              transition: 'all 0.12s',
            }}
            title="Open Zio AI Panel"
          ><img src={zioMascot} alt="" aria-hidden="true" style={{ width: 16, height: 16, objectFit: 'contain', verticalAlign: 'text-bottom', marginRight: 4 }} />Zio</button>
        ) : (
          <div
            title="Zio AI is not available in private windows"
            style={{
              padding: '3px 10px',
              borderRadius: 8,
              background: 'rgba(37,99,235,0.08)',
              color: 'rgba(147,197,253,0.35)',
              border: '1px solid rgba(59,130,246,0.2)',
              fontSize: 12,
              fontWeight: 600,
              whiteSpace: 'nowrap',
              cursor: 'default',
              userSelect: 'none',
            }}
          ><img src={zioMascot} alt="" aria-hidden="true" style={{ width: 16, height: 16, objectFit: 'contain', verticalAlign: 'text-bottom', marginRight: 4, opacity: 0.5 }} />Zio</div>
        )}

        {/* Per-tab view mode switcher — not available in private windows */}
        {!isPrivate && activeTabId && (
          <TabModeSwitcher
            currentMode={normalizeTabMode(activeTab?.mode) ?? 'browser'}
            onSetMode={(m) => void setTabMode(activeTabId, m)}
          />
        )}

        {/* Profile switcher — shows workspace profiles when signed in */}
        <ProfileSwitcher
          isAuthenticated={!!user}
          onOpenAuth={onOpenAuth}
        />

        {/* Pinned tools — overflow items the user promoted onto the toolbar */}
        {pinnedTools.map((tool) => {
          if (tool === 'reading_list') {
            return (
              <button
                key={tool}
                onClick={() => {
                  if (!activeTab?.url || activeTab.url === 'about:newtab' || activeTab.url === '') {
                    onToggleReadingList();
                  } else {
                    void handleSaveToReadingList();
                  }
                }}
                title={savedInReadingList ? 'Saved — open reading list' : 'Save to reading list'}
                {...pinnedToolDragProps(tool)}
                style={{ ...pinnedToolBtnStyle(readingListOpen), ...pinDropHighlight(tool) }}
              >
                {savedInReadingList ? '🔖' : '📖'}
                {unreadCount > 0 && (
                  <span style={pinnedToolBadgeStyle}>{unreadCount > 99 ? '99+' : unreadCount}</span>
                )}
              </button>
            );
          }
          if (tool === 'notes') {
            if (isPrivate || !onToggleNotes) return null;
            const hasPage = !!activeTab?.url && activeTab.url !== 'about:newtab' && /^https?:/i.test(activeTab.url);
            return (
              <button
                key={tool}
                onClick={() => onToggleNotes(hasPage ? 'page' : 'all')}
                title={hasPage ? 'Notes for this page' : 'Notes'}
                {...pinnedToolDragProps(tool)}
                style={{ ...pinnedToolBtnStyle(notesPanelOpen), ...pinDropHighlight(tool) }}
              >
                📝
                {noteCount > 0 && (
                  <span style={pinnedToolBadgeStyle} data-testid="notes-count-badge">{noteCount > 99 ? '99+' : noteCount}</span>
                )}
              </button>
            );
          }
          if (tool === 'dialer') {
            if (isPrivate || !onToggleDialer) return null;
            return (
              <button
                key={tool}
                onClick={onToggleDialer}
                title="Dialer — search & call on your phone"
                {...pinnedToolDragProps(tool)}
                style={{ ...pinnedToolBtnStyle(dialerPanelOpen), ...pinDropHighlight(tool) }}
              >📞</button>
            );
          }
          if (tool === 'device_lab') {
            if (!onOpenDeviceLab) return null;
            return (
              <button
                key={tool}
                onClick={onOpenDeviceLab}
                title="Device Lab — phone / tablet / desktop preview"
                {...pinnedToolDragProps(tool)}
                style={{ ...pinnedToolBtnStyle(false), ...pinDropHighlight(tool) }}
              >🔬</button>
            );
          }
          if (tool === 'screenshot') {
            if (!canShorten || isPrivate || !onScreenshot) return null;
            return (
              <button
                key={tool}
                onClick={() => onScreenshot(false)}
                disabled={screenshotCapturing}
                title="Screenshot — visible area"
                {...pinnedToolDragProps(tool)}
                style={{ ...pinnedToolBtnStyle(false), opacity: screenshotCapturing ? 0.5 : 1, ...pinDropHighlight(tool) }}
              >{screenshotCapturing ? '⏳' : '📷'}</button>
            );
          }
          return null;
        })}

        {/* Virtual keyboard toggle */}
        {vkEnabled && onToggleVk && (
          <button
            onClick={onToggleVk}
            title={vkOpen ? 'Hide virtual keyboard' : 'Show virtual keyboard'}
            data-testid="vk-toggle"
            style={{
              fontSize: 15,
              padding: '2px 7px',
              borderRadius: 8,
              background: vkOpen ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
              color: vkOpen ? '#fff' : 'var(--color-text-muted)',
              border: '1px solid var(--color-border)',
              transition: 'all 0.12s',
              flexShrink: 0,
              cursor: 'pointer',
            }}
          >
            ⌨️
          </button>
        )}

        {/* Settings button */}
        {onOpenSettings && (
          <button
            onClick={onOpenSettings}
            title="Settings"
            style={{
              fontSize: 15,
              padding: '2px 7px',
              borderRadius: 8,
              background: settingsOpen ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
              color: settingsOpen ? '#fff' : 'var(--color-text-muted)',
              border: '1px solid var(--color-border)',
              transition: 'all 0.12s',
              flexShrink: 0,
            }}
          >⚙️</button>
        )}

        {/* Overflow "⋯" menu — low-frequency utilities */}
        <div style={{ position: 'relative', flexShrink: 0 }}>
          <button
            ref={overflowBtnRef}
            onClick={() => setOverflowOpen(v => !v)}
            title="More tools"
            style={{
              fontSize: 15,
              padding: '2px 7px',
              borderRadius: 8,
              background: overflowOpen ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
              color: overflowOpen ? '#fff' : 'var(--color-text-muted)',
              border: '1px solid var(--color-border)',
              transition: 'all 0.12s',
            }}
          >⋯</button>
          {unreadCount > 0 && (
            <span style={{
              position: 'absolute',
              top: -4,
              right: -4,
              minWidth: 14,
              height: 14,
              borderRadius: 7,
              background: 'var(--gradient-primary)',
              color: '#fff',
              fontSize: 9,
              fontWeight: 700,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              padding: '0 2px',
              pointerEvents: 'none',
            }}>
              {unreadCount > 99 ? '99+' : unreadCount}
            </span>
          )}
        </div>

        {/* ── Safari-style far-right cluster: Downloads · Share · New Tab · Tab Overview ── */}
        <div style={{
          display: 'flex',
          alignItems: 'center',
          gap: 2,
          marginLeft: 6,
          paddingLeft: 8,
          borderLeft: '1px solid var(--color-border)',
          flexShrink: 0,
        }}>
          {/* Downloads (relocated — keeps its badge) */}
          {onToggleDownloads && (
            <button
              onClick={onToggleDownloads}
              title="Downloads"
              data-testid="cluster-downloads"
              style={clusterBtnStyle(downloadsPanelOpen)}
            >
              <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
                <path d="M8 2v8m0 0l-3-3m3 3l3-3M3 13h10" />
              </svg>
              {activeDownloadCount > 0 && (
                <span style={{
                  position: 'absolute',
                  top: -5,
                  right: -5,
                  minWidth: 16,
                  height: 16,
                  borderRadius: 8,
                  background: 'var(--gradient-primary)',
                  color: '#fff',
                  fontSize: 9,
                  fontWeight: 700,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  padding: '0 3px',
                  border: '1.5px solid var(--color-bg-surface)',
                  lineHeight: 1,
                }}>
                  {activeDownloadCount}
                </span>
              )}
            </button>
          )}

          {/* Share */}
          <button
            ref={shareBtnRef}
            onClick={() => setShareOpen(o => !o)}
            title="Share this page"
            data-testid="cluster-share"
            disabled={!activeTab?.url || activeTab.url === 'about:newtab'}
            style={{
              ...clusterBtnStyle(shareOpen),
              opacity: !activeTab?.url || activeTab.url === 'about:newtab' ? 0.4 : 1,
            }}
          >
            <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
              <path d="M8 1.5v8m0-8L5.5 4M8 1.5L10.5 4M4.5 7H3.5v7h9V7h-1" />
            </svg>
          </button>

          {/* New tab */}
          <button
            onClick={() => void createTab()}
            title="New tab (Cmd/Ctrl+T)"
            data-testid="cluster-new-tab"
            style={clusterBtnStyle(false)}
          >
            <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round">
              <path d="M8 3v10M3 8h10" />
            </svg>
          </button>

          {/* Tab Overview — Safari-style overlapping squares */}
          <button
            onClick={toggleTabOverview}
            title="Tab Overview (Cmd/Ctrl+Shift+\)"
            data-testid="cluster-tab-overview"
            style={clusterBtnStyle(tabOverviewOpen)}
          >
            <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round">
              <rect x="2" y="5" width="9" height="9" rx="1.5" />
              <path d="M5.5 3H12a2 2 0 0 1 2 2v6.5" />
            </svg>
          </button>
        </div>
      </div>

      {/* Overflow menu dropdown */}
      {overflowOpen && (
        <OverflowMenu
          anchorRef={overflowBtnRef}
          onClose={() => setOverflowOpen(false)}
          isPrivate={isPrivate}
          canScreenshot={!!(canShorten && !isPrivate && onScreenshot)}
          screenshotCapturing={screenshotCapturing}
          onScreenshot={onScreenshot}
          onOpenDeviceLab={onOpenDeviceLab}
          dialerAvailable={!isPrivate && !!onToggleDialer}
          dialerPanelOpen={dialerPanelOpen}
          onToggleDialer={onToggleDialer}
          notesAvailable={!isPrivate && !!onToggleNotes}
          notesPanelOpen={notesPanelOpen}
          noteCount={noteCount}
          onToggleNotes={onToggleNotes
            ? () => {
                const hasPage = !!activeTab?.url && activeTab.url !== 'about:newtab' && /^https?:/i.test(activeTab.url);
                onToggleNotes(hasPage ? 'page' : 'all');
              }
            : undefined}
          pinnedTools={pinnedTools}
          onTogglePin={handleTogglePin}
          savedInReadingList={savedInReadingList}
          unreadCount={unreadCount}
          onReadingList={() => {
            if (!activeTab?.url || activeTab.url === 'about:newtab' || activeTab.url === '') {
              onToggleReadingList();
            } else {
              void handleSaveToReadingList();
            }
          }}
        />
      )}

      {/* Create link popover */}
      {createOpen && (
        <CreateLinkPopover
          pageUrl={linkToolTarget?.url ?? activeTab?.url ?? ''}
          pageTitle={linkToolTarget?.title ?? activeTab?.title ?? ''}
          baseUrl={BASE_URL}
          initialType={createInitialType ?? undefined}
          onShortenPage={canShorten ? () => { setCreateOpen(false); setCreateInitialType(null); setShortenOpen(true); } : undefined}
          onClose={() => { setCreateOpen(false); setLinkToolTarget(null); setCreateInitialType(null); }}
          onOpenAuth={() => { setCreateOpen(false); onOpenAuth(); }}
          onNavigate={(url) => {
            if (activeTabId) {
              void window.zio.tabs.navigate(activeTabId, url);
            }
          }}
        />
      )}

      {/* Share popover (far-right cluster) */}
      {shareOpen && activeTab && (
        <SharePopover
          anchorRef={shareBtnRef}
          pageUrl={activeTab.url}
          pageTitle={activeTab.title ?? ''}
          canShorten={canShorten}
          onClose={() => setShareOpen(false)}
          onShorten={() => setShortenOpen(true)}
        />
      )}

      {/* Tab Overview — full-window exposé grid (far-right cluster) */}
      {tabOverviewOpen && (
        <TabOverview
          ref={tabOverviewRef}
          tabs={tabs}
          tabOrder={tabOrder}
          activeTabId={activeTabId}
          isPrivate={isPrivate}
          onClose={() => setTabOverviewOpen(false)}
          onActivate={(id) => void activateTab(id)}
          onCloseTab={(id) => void closeTab(id)}
          onNewTab={() => void createTab()}
        />
      )}

      {/* Shorten / QR popover */}
      {shortenOpen && activeTab && (
        <ShortenPopover
          pageUrl={linkToolTarget?.url ?? activeTab.url}
          pageTitle={linkToolTarget?.title ?? activeTab.title ?? ''}
          baseUrl={BASE_URL}
          onClose={() => { setShortenOpen(false); setLinkToolTarget(null); }}
          onOpenAuth={() => { setShortenOpen(false); onOpenAuth(); }}
          onNavigate={(url) => {
            if (activeTabId) {
              void window.zio.tabs.navigate(activeTabId, url);
            }
          }}
        />
      )}

      {/* Clipboard popover */}
      {clipboardOpen && (
        <ClipboardPopover
          baseUrl={BASE_URL}
          onClose={() => setClipboardOpen(false)}
          onOpenAuth={() => { setClipboardOpen(false); onOpenAuth(); }}
          onOpenInNewTab={(url) => void createTab(url)}
        />
      )}

      {/* Tab context menu */}
      {contextMenu && ctxTab && (
        <TabContextMenu
          state={contextMenu}
          isPinned={ctxTab.pinned ?? false}
          isMuted={ctxTab.isMuted ?? false}
          isAudible={ctxTab.isAudible ?? false}
          tabCount={tabOrder.length}
          tabIndex={ctxTabIndex}
          onClose={closeContextMenu}
          onPin={() => void pinTab(contextMenu.tabId, !(ctxTab.pinned ?? false))}
          onMute={() => void window.zio.tabs.mute(contextMenu.tabId, !(ctxTab.isMuted ?? false))}
          onDuplicate={() => void duplicateTab(contextMenu.tabId)}
          onCloseTab={() => void closeTab(contextMenu.tabId)}
          onCloseOthers={() => void closeOtherTabs(contextMenu.tabId)}
          onCloseToRight={() => void closeTabsToRight(contextMenu.tabId)}
        />
      )}

      {/* Tab strip menu */}
      {stripMenuOpen && (
        <StripMenu
          anchorRef={stripMenuBtnRef}
          recentlyClosed={recentlyClosed}
          onClose={() => setStripMenuOpen(false)}
          onReopenEntry={(url) => void reopenFromRecent(url)}
          onMuteAll={(muted) => void muteAllTabs(muted)}
          onOpenSearch={() => { setStripMenuOpen(false); onOpenTabSearch(); }}
        />
      )}
    </div>
  );
}

// ── Overflow "⋯" menu ─────────────────────────────────────────────────────────
// Low-frequency utilities relocated off the toolbar: Device Lab, Private
// Window, Screenshot, Dialer, and Reading List.

interface OverflowMenuProps {
  anchorRef: React.RefObject<HTMLButtonElement | null>;
  onClose: () => void;
  isPrivate: boolean;
  canScreenshot: boolean;
  screenshotCapturing: boolean;
  onScreenshot?: (fullPage: boolean) => void;
  onOpenDeviceLab?: () => void;
  dialerAvailable: boolean;
  dialerPanelOpen: boolean;
  onToggleDialer?: () => void;
  notesAvailable: boolean;
  notesPanelOpen: boolean;
  /** Count of notes attached to the active tab's host (badge). */
  noteCount: number;
  onToggleNotes?: () => void;
  savedInReadingList: boolean;
  unreadCount: number;
  onReadingList: () => void;
  /** Tools currently pinned onto the toolbar. */
  pinnedTools: PinnableTool[];
  /** Toggle a tool's pinned state (capped at MAX_PINNED_TOOLS). */
  onTogglePin: (tool: PinnableTool) => void;
}

function OverflowMenu({
  anchorRef, onClose, isPrivate,
  canScreenshot, screenshotCapturing, onScreenshot,
  onOpenDeviceLab,
  dialerAvailable, dialerPanelOpen, onToggleDialer,
  notesAvailable, notesPanelOpen, noteCount, onToggleNotes,
  savedInReadingList, unreadCount, onReadingList,
  pinnedTools, onTogglePin,
}: OverflowMenuProps) {
  const menuRef = useRef<HTMLDivElement>(null);
  const pinCapReached = pinnedTools.length >= MAX_PINNED_TOOLS;

  /** Pin/unpin toggle rendered as a sibling of each row's action button. */
  const pinToggle = (tool: PinnableTool) => {
    const pinned = pinnedTools.includes(tool);
    const disabled = !pinned && pinCapReached;
    return (
      <button
        onClick={(e) => { e.stopPropagation(); if (!disabled) onTogglePin(tool); }}
        title={pinned
          ? 'Unpin from toolbar'
          : disabled
            ? `Toolbar is full — unpin another tool first (max ${MAX_PINNED_TOOLS})`
            : 'Pin to toolbar'}
        style={{
          flexShrink: 0,
          padding: '4px 8px',
          fontSize: 12,
          borderRadius: 6,
          cursor: disabled ? 'default' : 'pointer',
          opacity: pinned ? 1 : disabled ? 0.25 : 0.55,
          color: pinned ? 'var(--color-primary)' : 'var(--color-text-muted)',
          transition: 'opacity 0.1s',
        }}
      >📌</button>
    );
  };

  const rect = anchorRef.current?.getBoundingClientRect();
  const left = rect ? Math.max(8, rect.right - 230) : undefined;
  const top = rect ? rect.bottom + 6 : undefined;

  // Close on outside click
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(e.target as Node) &&
          !anchorRef.current?.contains(e.target as Node)) {
        onClose();
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [onClose, anchorRef]);

  const action = (fn: () => void) => () => { fn(); onClose(); };

  return (
    <div
      ref={menuRef}
      style={{
        position: 'fixed',
        left,
        top,
        right: rect ? undefined : 12,
        background: 'var(--color-bg-surface)',
        border: '1px solid var(--color-border)',
        borderRadius: 10,
        boxShadow: '0 8px 28px rgba(0,0,0,0.3)',
        minWidth: 230,
        zIndex: 9999,
        padding: '4px 0',
        overflow: 'hidden',
      }}
    >
      {/* Reading list */}
      <div style={menuRowStyle}>
        <button onClick={action(onReadingList)} style={{ ...menuItemStyle, flex: 1 }}>
          <span>{savedInReadingList ? '🔖' : '📖'}</span>
          <span>{savedInReadingList ? 'Saved — open reading list' : 'Save to reading list'}</span>
          {unreadCount > 0 && (
            <span style={{
              marginLeft: 'auto',
              minWidth: 16,
              height: 16,
              borderRadius: 8,
              background: 'var(--gradient-primary)',
              color: '#fff',
              fontSize: 9,
              fontWeight: 700,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              padding: '0 4px',
            }}>
              {unreadCount > 99 ? '99+' : unreadCount}
            </span>
          )}
        </button>
        {pinToggle('reading_list')}
      </div>

      {/* Notes */}
      {notesAvailable && onToggleNotes && (
        <div style={menuRowStyle}>
          <button onClick={action(onToggleNotes)} style={{
            ...menuItemStyle,
            flex: 1,
            color: notesPanelOpen ? 'var(--color-primary)' : menuItemStyle.color,
          }}>
            <span>📝</span>
            <span>Notes — synced with your account</span>
            {noteCount > 0 && (
              <span style={{
                marginLeft: 'auto',
                minWidth: 16,
                height: 16,
                borderRadius: 8,
                background: 'var(--color-primary)',
                color: '#fff',
                fontSize: 10,
                fontWeight: 700,
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: '0 4px',
              }}>
                {noteCount > 99 ? '99+' : noteCount}
              </span>
            )}
          </button>
          {pinToggle('notes')}
        </div>
      )}

      {/* Dialer */}
      {dialerAvailable && onToggleDialer && (
        <div style={menuRowStyle}>
          <button onClick={action(onToggleDialer)} style={{
            ...menuItemStyle,
            flex: 1,
            color: dialerPanelOpen ? 'var(--color-primary)' : menuItemStyle.color,
          }}>
            <span>📞</span>
            <span>Dialer — search &amp; call on your phone</span>
          </button>
          {pinToggle('dialer')}
        </div>
      )}

      {/* Device Lab */}
      {onOpenDeviceLab && (
        <div style={menuRowStyle}>
          <button onClick={action(onOpenDeviceLab)} style={{ ...menuItemStyle, flex: 1 }}>
            <span>🔬</span>
            <span>Device Lab — phone / tablet / desktop preview</span>
          </button>
          {pinToggle('device_lab')}
        </div>
      )}

      {/* Screenshot */}
      {canScreenshot && onScreenshot && (
        <>
          <div style={menuRowStyle}>
            <button
              onClick={action(() => onScreenshot(false))}
              disabled={screenshotCapturing}
              style={{ ...menuItemStyle, flex: 1, opacity: screenshotCapturing ? 0.5 : 1 }}
            >
              <span>{screenshotCapturing ? '⏳' : '📷'}</span>
              <span>Screenshot — visible area</span>
            </button>
            {pinToggle('screenshot')}
          </div>
          <button
            onClick={action(() => onScreenshot(true))}
            disabled={screenshotCapturing}
            style={{ ...menuItemStyle, opacity: screenshotCapturing ? 0.5 : 1 }}
          >
            <span>{screenshotCapturing ? '⏳' : '📄'}</span>
            <span>Screenshot — full page</span>
          </button>
        </>
      )}

      <div style={{ borderTop: '1px solid var(--color-border)', margin: '4px 0' }} />

      {/* New Private Window */}
      <button
        onClick={action(() => { void window.zio.window.openPrivate(); })}
        style={menuItemStyle}
      >
        <span>🕶️</span>
        <span>New Private Window</span>
        <span style={{ marginLeft: 'auto', fontSize: 10, opacity: 0.6 }}>⌘⇧N</span>
      </button>
      {isPrivate && (
        <div style={{ padding: '2px 14px 6px', fontSize: 10, color: 'var(--color-text-muted)' }}>
          You're already in a private window
        </div>
      )}
    </div>
  );
}

/** Compact icon-button style shared by pinned toolbar tools. */
function pinnedToolBtnStyle(active: boolean): React.CSSProperties {
  return {
    position: 'relative',
    fontSize: 15,
    padding: '2px 7px',
    borderRadius: 8,
    background: active ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
    color: active ? '#fff' : 'var(--color-text-muted)',
    border: '1px solid var(--color-border)',
    transition: 'all 0.12s',
    flexShrink: 0,
  };
}

const pinnedToolBadgeStyle: React.CSSProperties = {
  position: 'absolute',
  top: -5,
  right: -5,
  minWidth: 14,
  height: 14,
  borderRadius: 7,
  background: 'var(--gradient-primary)',
  color: '#fff',
  fontSize: 9,
  fontWeight: 700,
  display: 'flex',
  alignItems: 'center',
  justifyContent: 'center',
  padding: '0 2px',
  pointerEvents: 'none',
};

/** Row wrapper for overflow items that carry a pin toggle next to the action. */
const menuRowStyle: React.CSSProperties = {
  display: 'flex',
  alignItems: 'center',
  width: '100%',
};

const menuItemStyle: React.CSSProperties = {
  display: 'flex',
  alignItems: 'center',
  gap: 8,
  width: '100%',
  padding: '9px 14px',
  fontSize: 12,
  color: 'var(--color-text)',
  textAlign: 'left',
  cursor: 'pointer',
  transition: 'background 0.1s',
};
