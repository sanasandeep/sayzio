/**
 * Command Palette — Ctrl/Cmd+K overlay for the Zio Browser.
 *
 * Sources:
 *  - Browser commands (new tab, shorten, modes, etc.)
 *  - Open tabs (switch to tab)
 *  - User's Sayzio links (open short URL in tab)
 *  - Bookmarks (open URL)
 *  - Recent history (open URL)
 *
 * Cross-component actions (shorten popover, address bar focus) are dispatched
 * as custom DOM events so this component stays decoupled from ChromeBar.
 */
import { useState, useEffect, useRef, useCallback } from 'react';
import type { CachedSayzioLink } from '../../shared/db-schema';
import {
  COMMAND_REGISTRY,
  KEYBOARD_SHORTCUTS,
  type PaletteItem,
  fuzzyScore,
  scoreItem,
} from '../../shared/command-palette';
import type { TabRecord } from '../store/tab-store';
import type { WindowMode } from '../../shared/window-mode';
import { useFindStore } from '../store/find-store';
import { resolveFavicon } from '../../shared/favicon';

interface Props {
  onClose: () => void;
  tabs: Record<string, TabRecord>;
  tabOrder: string[];
  activeTabId: string | null;
  user: { name: string } | null;
  mode: WindowMode;
  isPrivate: boolean;
  onSetMode: (m: WindowMode) => void;
}

type ViewState = 'search' | 'shortcuts';

const MAX_PER_GROUP = 5;

// ── Styles ────────────────────────────────────────────────────────────────────

const overlayStyle: React.CSSProperties = {
  position: 'fixed',
  inset: 0,
  zIndex: 9000,
  display: 'flex',
  alignItems: 'flex-start',
  justifyContent: 'center',
  paddingTop: 80,
  background: 'rgba(0,0,0,0.55)',
  backdropFilter: 'blur(2px)',
};

const paletteStyle: React.CSSProperties = {
  width: 620,
  maxWidth: 'calc(100vw - 40px)',
  maxHeight: 'calc(100vh - 140px)',
  borderRadius: 12,
  background: 'var(--color-bg-surface)',
  border: '1px solid var(--color-border)',
  boxShadow: '0 24px 64px rgba(0,0,0,0.35)',
  display: 'flex',
  flexDirection: 'column',
  overflow: 'hidden',
};

function kindIcon(kind: PaletteItem['kind']): string {
  switch (kind) {
    case 'tab': return '🗂';
    case 'bookmark': return '☆';
    case 'history': return '🕐';
    case 'sayzio-link': return '🔗';
    case 'command': return '⚡';
  }
}

function kindLabel(kind: PaletteItem['kind']): string {
  switch (kind) {
    case 'tab': return 'Open Tabs';
    case 'bookmark': return 'Bookmarks';
    case 'history': return 'History';
    case 'sayzio-link': return 'Your Sayzio Links';
    case 'command': return 'Commands';
  }
}

const KIND_ORDER: PaletteItem['kind'][] = ['command', 'tab', 'sayzio-link', 'bookmark', 'history'];

export function CommandPalette({
  onClose,
  tabs,
  tabOrder,
  activeTabId,
  user,
  isPrivate,
  onSetMode,
}: Props) {
  const [query, setQuery] = useState('');
  const [view, setView] = useState<ViewState>('search');
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [bookmarks, setBookmarks] = useState<Array<{ url: string; title: string; favicon_url?: string | null }>>([]);
  const [history, setHistory] = useState<Array<{ url: string; title: string | null; favicon_url?: string | null }>>([]);
  const [sayzioLinks, setSayzioLinks] = useState<CachedSayzioLink[]>([]);

  const inputRef = useRef<HTMLInputElement>(null);
  const listRef = useRef<HTMLDivElement>(null);
  const { openFind } = useFindStore();

  // Load data sources on mount
  useEffect(() => {
    void window.zio.bookmarks.all().then((bms: unknown) => {
      if (Array.isArray(bms)) {
        setBookmarks(bms as Array<{ url: string; title: string; favicon_url?: string | null }>);
      }
    }).catch(() => { /* ignore */ });

    void window.zio.history.recent().then((hist: unknown) => {
      if (Array.isArray(hist)) {
        setHistory(hist as Array<{ url: string; title: string | null; favicon_url?: string | null }>);
      }
    }).catch(() => { /* ignore */ });

    // Sayzio links: read the local cache first so they appear instantly,
    // even offline or signed out (main clears the cache on sign-out).
    let cancelled = false;
    void window.zio.sayzioLinks.cached().then((links: unknown) => {
      if (!cancelled && Array.isArray(links)) {
        setSayzioLinks(links as CachedSayzioLink[]);
      }
    }).catch(() => { /* ignore */ });

    // Then refresh in the background when logged in — the main process
    // no-ops without a token or when the cache is still fresh.
    if (user) {
      void window.zio.sayzioLinks.refresh().then((links: unknown) => {
        if (!cancelled && Array.isArray(links)) {
          setSayzioLinks(links as CachedSayzioLink[]);
        }
      }).catch(() => { /* network error — keep showing the cache */ });
    }
    return () => { cancelled = true; };
  }, [user]);

  // Focus input on mount
  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  const activeTab = activeTabId ? tabs[activeTabId] : null;
  const hasPage = !!(activeTab?.url && activeTab.url !== '' && activeTab.url !== 'about:newtab');

  // ── Build item lists ──────────────────────────────────────────────────────

  const tabItems: PaletteItem[] = tabOrder
    .filter(id => id !== activeTabId)
    .map(id => {
      const t = tabs[id];
      return {
        id: `tab-${id}`,
        kind: 'tab' as const,
        title: t?.title || 'New Tab',
        subtitle: t?.url || '',
        icon: '🗂',
        favicon: resolveFavicon(t?.favicon, t?.url),
        tabId: id,
        url: t?.url,
        score: 0,
      };
    });

  const bookmarkItems: PaletteItem[] = bookmarks.map(b => ({
    id: `bm-${b.url}`,
    kind: 'bookmark' as const,
    title: b.title || b.url,
    subtitle: b.url,
    url: b.url,
    favicon: resolveFavicon(b.favicon_url, b.url),
    score: 0,
  }));

  const historyItems: PaletteItem[] = history
    .filter(h => !bookmarks.some(b => b.url === h.url))
    .map(h => ({
      id: `hist-${h.url}`,
      kind: 'history' as const,
      title: h.title || h.url,
      subtitle: h.url,
      url: h.url,
      favicon: resolveFavicon(h.favicon_url, h.url),
      score: 0,
    }));

  const sayzioItems: PaletteItem[] = sayzioLinks.map(link => ({
    id: `sl-${link.id}`,
    kind: 'sayzio-link' as const,
    title: link.title || link.alias,
    subtitle: link.short_url,
    url: link.short_url,
    icon: '🔗',
    score: 0,
  }));

  // Commands — filter based on context
  const commandItems: PaletteItem[] = COMMAND_REGISTRY.filter(cmd => {
    if (cmd.action === 'shorten-page' || cmd.action === 'qr-page') return hasPage && !!user;
    if (cmd.action === 'add-to-biolink') return hasPage && !!user;
    if (cmd.action === 'close-tab') return tabOrder.length > 0;
    if (cmd.action === 'reload-tab') return hasPage;
    if (cmd.action === 'find-on-page') return hasPage;
    if (cmd.action === 'new-private-window') return !isPrivate;
    if (cmd.action === 'restore-session') return !isPrivate;
    if (cmd.action === 'mode-browser' || cmd.action === 'mode-split' || cmd.action === 'mode-dashboard') {
      return !isPrivate;
    }
    return true;
  }).map(cmd => ({
    id: `cmd-${cmd.id}`,
    kind: 'command' as const,
    title: cmd.title,
    subtitle: cmd.subtitle,
    icon: cmd.icon,
    action: cmd.action,
    // Store keywords in the item so scoreItem can use them
    score: 0,
    _keywords: cmd.keywords,
  } as PaletteItem & { _keywords?: string[] }));

  // ── Search / filter ───────────────────────────────────────────────────────

  function scoreAndFilter(items: Array<PaletteItem & { _keywords?: string[] }>, q: string, limit: number): PaletteItem[] {
    if (!q.trim()) {
      return items.slice(0, limit).map(i => ({ ...i, score: 50 }));
    }
    const scored = items.flatMap(item => {
      const s = scoreItem(
        { title: item.title, subtitle: item.subtitle, keywords: item._keywords },
        q,
      );
      if (s < 0) return [];
      const urlScore = item.url ? fuzzyScore(item.url, q) * 0.4 : -1;
      return [{ ...item, score: Math.max(s, urlScore) }];
    });
    return scored.sort((a, b) => b.score - a.score).slice(0, limit);
  }

  const filteredCommands = scoreAndFilter(commandItems as Array<PaletteItem & { _keywords?: string[] }>, query, commandItems.length);
  const filteredTabs = scoreAndFilter(tabItems as Array<PaletteItem & { _keywords?: string[] }>, query, MAX_PER_GROUP);
  const filteredSayzio = scoreAndFilter(sayzioItems as Array<PaletteItem & { _keywords?: string[] }>, query, MAX_PER_GROUP);
  const filteredBookmarks = scoreAndFilter(bookmarkItems as Array<PaletteItem & { _keywords?: string[] }>, query, MAX_PER_GROUP);
  const filteredHistory = scoreAndFilter(historyItems as Array<PaletteItem & { _keywords?: string[] }>, query, MAX_PER_GROUP);

  type GroupEntry = { kind: PaletteItem['kind']; items: PaletteItem[] };
  const groups: GroupEntry[] = KIND_ORDER.flatMap(kind => {
    const map: Record<PaletteItem['kind'], PaletteItem[]> = {
      command: filteredCommands,
      tab: filteredTabs,
      'sayzio-link': filteredSayzio,
      bookmark: filteredBookmarks,
      history: filteredHistory,
    };
    const its = map[kind];
    if (!its.length) return [];
    return [{ kind, items: its }];
  });

  const flatItems: PaletteItem[] = groups.flatMap(g => g.items);

  // ── Execute action ────────────────────────────────────────────────────────

  const executeItem = useCallback((item: PaletteItem) => {
    onClose();

    if (item.kind === 'tab' && item.tabId) {
      void window.zio.tabs.activate(item.tabId);
      return;
    }

    if (item.url && item.kind !== 'command') {
      if (activeTabId) {
        void window.zio.tabs.navigate(activeTabId, item.url);
      } else {
        void window.zio.tabs.create(item.url);
      }
      return;
    }

    if (item.kind === 'command' && item.action) {
      switch (item.action) {
        case 'new-tab':
          void window.zio.tabs.create();
          break;
        case 'close-tab':
          if (activeTabId) void window.zio.tabs.close(activeTabId);
          break;
        case 'shorten-page':
        case 'qr-page':
          // Dispatch a custom DOM event — ChromeBar listens and opens the popover
          document.dispatchEvent(new CustomEvent('zio:shorten-open'));
          break;
        case 'add-to-biolink':
          if (activeTab?.url && activeTab?.title) {
            document.dispatchEvent(new CustomEvent('zio:add-to-biolink', {
              detail: { url: activeTab.url, title: activeTab.title },
            }));
          }
          break;
        case 'new-window':
          void window.zio.window.openNew();
          break;
        case 'new-private-window':
          void window.zio.window.openPrivate();
          break;
        case 'focus-address-bar':
          document.dispatchEvent(new CustomEvent('zio:focus-address-bar'));
          break;
        case 'mode-browser':
          void onSetMode('browser');
          break;
        case 'mode-split':
          void onSetMode('split');
          break;
        case 'mode-dashboard':
          void onSetMode('dashboard');
          break;
        case 'reload-tab':
          if (activeTabId) void window.zio.tabs.reload(activeTabId);
          break;
        case 'find-on-page':
          if (activeTabId) openFind();
          break;
        case 'restore-session':
          void window.zio.tabs.restoreSession();
          break;
        case 'shortcuts':
          // Re-open palette in shortcuts view via a micro-timeout so the close
          // animation completes first.
          setTimeout(() => {
            document.dispatchEvent(new CustomEvent('zio:shortcuts-open'));
          }, 50);
          break;
        default:
          break;
      }
    }
  }, [onClose, activeTabId, activeTab, onSetMode, openFind]);

  // ── Keyboard navigation ───────────────────────────────────────────────────

  useEffect(() => {
    setSelectedIndex(0);
  }, [query]);

  useEffect(() => {
    if (!listRef.current) return;
    const el = listRef.current.querySelector('[data-selected="true"]');
    el?.scrollIntoView({ block: 'nearest' });
  }, [selectedIndex]);

  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (view === 'shortcuts') {
      if (e.key === 'Escape') onClose();
      return;
    }
    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setSelectedIndex(i => Math.min(i + 1, flatItems.length - 1));
        break;
      case 'ArrowUp':
        e.preventDefault();
        setSelectedIndex(i => Math.max(i - 1, 0));
        break;
      case 'Enter': {
        e.preventDefault();
        const item = flatItems[selectedIndex];
        if (item) executeItem(item);
        break;
      }
      case 'Escape':
        onClose();
        break;
      default:
        break;
    }
  }, [view, flatItems, selectedIndex, executeItem, onClose]);

  // ── Render ────────────────────────────────────────────────────────────────

  return (
    <div style={overlayStyle} onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div style={paletteStyle} onKeyDown={handleKeyDown}>

        {/* Header */}
        <div style={{
          display: 'flex',
          alignItems: 'center',
          gap: 10,
          padding: '12px 16px',
          borderBottom: '1px solid var(--color-border)',
        }}>
          {view === 'shortcuts' ? (
            <>
              <button
                onClick={() => setView('search')}
                style={{ fontSize: 14, opacity: 0.6, padding: '2px 6px', borderRadius: 6 }}
                title="Back to search"
              >←</button>
              <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--color-text)', flex: 1 }}>
                Keyboard Shortcuts
              </span>
              <button
                onClick={onClose}
                style={{ fontSize: 13, opacity: 0.5, padding: '2px 8px', borderRadius: 6 }}
              >✕</button>
            </>
          ) : (
            <>
              <span style={{ fontSize: 16, opacity: 0.5 }}>🔍</span>
              <input
                ref={inputRef}
                value={query}
                onChange={e => setQuery(e.target.value)}
                placeholder="Search tabs, bookmarks, history, commands…"
                style={{
                  flex: 1,
                  border: 'none',
                  outline: 'none',
                  background: 'transparent',
                  color: 'var(--color-text)',
                  fontSize: 15,
                }}
              />
              {query && (
                <button
                  onClick={() => setQuery('')}
                  style={{ fontSize: 12, opacity: 0.4, padding: '2px 6px', borderRadius: 6 }}
                >✕</button>
              )}
              <kbd style={kbdStyle}>Esc</kbd>
            </>
          )}
        </div>

        {/* Body */}
        <div ref={listRef} style={{ overflowY: 'auto', flex: 1, padding: '8px 0' }}>
          {view === 'shortcuts' ? (
            <ShortcutsView />
          ) : flatItems.length === 0 ? (
            <div style={{
              padding: '32px 20px',
              textAlign: 'center',
              color: 'var(--color-text-muted)',
              fontSize: 14,
            }}>
              {query ? `No results for "${query}"` : 'Start typing to search…'}
            </div>
          ) : (
            groups.map(group => {
              let groupStartIndex = 0;
              for (const g of groups) {
                if (g.kind === group.kind) break;
                groupStartIndex += g.items.length;
              }
              return (
                <div key={group.kind}>
                  <div style={{
                    padding: '6px 16px 2px',
                    fontSize: 10,
                    fontWeight: 700,
                    letterSpacing: 0.8,
                    textTransform: 'uppercase',
                    color: 'var(--color-text-muted)',
                    opacity: 0.7,
                  }}>
                    {kindLabel(group.kind)}
                  </div>
                  {group.items.map((item, i) => {
                    const globalIdx = groupStartIndex + i;
                    const isSelected = globalIdx === selectedIndex;
                    return (
                      <div
                        key={item.id}
                        data-selected={isSelected}
                        onMouseEnter={() => setSelectedIndex(globalIdx)}
                        onMouseDown={(e) => { e.preventDefault(); executeItem(item); }}
                        style={{
                          display: 'flex',
                          alignItems: 'center',
                          gap: 10,
                          padding: '7px 16px',
                          cursor: 'default',
                          background: isSelected ? 'var(--color-primary)' : 'transparent',
                        }}
                      >
                        <span style={{
                          fontSize: 15,
                          flexShrink: 0,
                          width: 22,
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          opacity: 0.85,
                        }}>
                          {item.favicon ? (
                            <img
                              src={item.favicon}
                              width={16} height={16}
                              style={{ borderRadius: 3 }}
                              alt=""
                              onError={e => { (e.currentTarget as HTMLImageElement).style.visibility = 'hidden'; }}
                            />
                          ) : (
                            item.icon ?? kindIcon(item.kind)
                          )}
                        </span>
                        <div style={{ flex: 1, minWidth: 0 }}>
                          <div style={{
                            fontSize: 13,
                            fontWeight: 500,
                            color: isSelected ? '#fff' : 'var(--color-text)',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace: 'nowrap',
                          }}>
                            {item.title}
                          </div>
                          {item.subtitle && (
                            <div style={{
                              fontSize: 11,
                              color: isSelected ? 'rgba(255,255,255,0.7)' : 'var(--color-text-muted)',
                              overflow: 'hidden',
                              textOverflow: 'ellipsis',
                              whiteSpace: 'nowrap',
                              marginTop: 1,
                            }}>
                              {item.subtitle}
                            </div>
                          )}
                        </div>
                        {isSelected && (
                          <kbd style={{
                            fontSize: 11,
                            padding: '2px 6px',
                            borderRadius: 4,
                            background: 'rgba(255,255,255,0.2)',
                            color: '#fff',
                            flexShrink: 0,
                          }}>↵</kbd>
                        )}
                      </div>
                    );
                  })}
                </div>
              );
            })
          )}
        </div>

        {/* Footer */}
        {view === 'search' && (
          <div style={{
            padding: '8px 16px',
            borderTop: '1px solid var(--color-border)',
            display: 'flex',
            alignItems: 'center',
            gap: 16,
            fontSize: 11,
            color: 'var(--color-text-muted)',
          }}>
            <span><kbd style={kbdStyle}>↑↓</kbd> navigate</span>
            <span><kbd style={kbdStyle}>↵</kbd> open</span>
            <span><kbd style={kbdStyle}>Esc</kbd> close</span>
            <button
              onClick={() => setView('shortcuts')}
              style={{
                marginLeft: 'auto',
                fontSize: 11,
                color: 'var(--color-text-muted)',
                cursor: 'pointer',
                textDecoration: 'underline',
                background: 'none',
                border: 'none',
                padding: 0,
              }}
            >
              ⌨️ Keyboard shortcuts
            </button>
          </div>
        )}
      </div>
    </div>
  );
}

const kbdStyle: React.CSSProperties = {
  display: 'inline-block',
  padding: '1px 5px',
  borderRadius: 4,
  background: 'var(--color-bg-elevated)',
  border: '1px solid var(--color-border)',
  fontSize: 10,
  fontFamily: 'monospace',
  color: 'var(--color-text-muted)',
  marginRight: 4,
};

// ── Shortcuts cheat-sheet ─────────────────────────────────────────────────────

function ShortcutsView() {
  const categories = [...new Set(KEYBOARD_SHORTCUTS.map(s => s.category))];
  return (
    <div style={{ padding: '8px 16px 16px' }}>
      {categories.map(cat => (
        <div key={cat} style={{ marginBottom: 20 }}>
          <div style={{
            fontSize: 10,
            fontWeight: 700,
            letterSpacing: 0.8,
            textTransform: 'uppercase',
            color: 'var(--color-text-muted)',
            opacity: 0.7,
            marginBottom: 6,
            paddingBottom: 4,
            borderBottom: '1px solid var(--color-border)',
          }}>
            {cat}
          </div>
          {KEYBOARD_SHORTCUTS.filter(s => s.category === cat).map(shortcut => (
            <div key={shortcut.label} style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              padding: '5px 0',
              gap: 12,
            }}>
              <span style={{ fontSize: 13, color: 'var(--color-text)' }}>
                {shortcut.label}
              </span>
              <div style={{ display: 'flex', gap: 4, flexShrink: 0 }}>
                {shortcut.keys.map((key, i) => (
                  <kbd key={i} style={{
                    fontSize: 11,
                    padding: '2px 7px',
                    borderRadius: 5,
                    background: 'var(--color-bg-elevated)',
                    border: '1px solid var(--color-border)',
                    color: 'var(--color-text)',
                    fontFamily: 'monospace',
                  }}>
                    {key}
                  </kbd>
                ))}
              </div>
            </div>
          ))}
        </div>
      ))}
    </div>
  );
}
