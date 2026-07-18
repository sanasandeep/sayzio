/**
 * ChromeBar — the browser chrome (tab strip + address bar + controls).
 * Runs in the renderer (app chrome window); actual web content is in WebContentsView.
 * Used in both Browser mode (full-width) and the right pane of Split mode.
 */
import { useState, useRef, useCallback, useEffect } from 'react';
import { useTabStore } from '../store/tab-store';
import { useAuthStore } from '../store/auth-store';
import { ShortenPopover } from './ShortenPopover';
import { ModeSwitcher } from './ModeSwitcher';
import { useModeStore } from '../store/mode-store';

interface Props {
  zioPanelOpen: boolean;
  onToggleZio: () => void;
  onOpenAuth: () => void;
  /** If false, hides the mode switcher (used in split mode right pane). */
  showModeSwitcher?: boolean;
  downloadsPanelOpen?: boolean;
  onToggleDownloads?: () => void;
  activeDownloadCount?: number;
}

const BASE_URL = 'https://1in.me';

export function ChromeBar({
  zioPanelOpen,
  onToggleZio,
  onOpenAuth,
  showModeSwitcher = true,
  downloadsPanelOpen = false,
  onToggleDownloads,
  activeDownloadCount = 0,
}: Props) {
  const { tabs, tabOrder, activeTabId, createTab, closeTab, activateTab, navigate, goBack, goForward, reload, stop } = useTabStore();
  const { user } = useAuthStore();
  const { mode, setMode } = useModeStore();
  const [omniboxValue, setOmniboxValue] = useState('');
  const [omniboxFocused, setOmniboxFocused] = useState(false);
  const [shortenOpen, setShortenOpen] = useState(false);
  const [pendingSyncCount, setPendingSyncCount] = useState(0);
  const omniboxRef = useRef<HTMLInputElement>(null);

  // Track queued (offline / failed) sync pushes for the pending indicator
  useEffect(() => {
    let cancelled = false;
    void window.zio.sync.pendingCount().then((n: number) => {
      if (!cancelled) setPendingSyncCount(n);
    }).catch(() => { /* main not ready yet — event listener will update */ });

    const listener = (...args: unknown[]) => {
      const n = args[0];
      if (typeof n === 'number') setPendingSyncCount(n);
    };
    window.zio.on('sync:queue-changed', listener);
    return () => {
      cancelled = true;
      window.zio.off('sync:queue-changed', listener);
    };
  }, []);

  const activeTab = activeTabId ? tabs[activeTabId] : null;

  // Sync omnibox with active tab URL
  useEffect(() => {
    if (!omniboxFocused) {
      setOmniboxValue(activeTab?.url ?? '');
    }
  }, [activeTab?.url, omniboxFocused]);

  // Close the shorten popover when the active tab changes
  useEffect(() => {
    setShortenOpen(false);
  }, [activeTabId]);

  const handleOmniboxSubmit = useCallback((e: React.FormEvent) => {
    e.preventDefault();
    if (!activeTabId || !omniboxValue.trim()) return;
    void navigate(activeTabId, omniboxValue.trim());
    omniboxRef.current?.blur();
  }, [activeTabId, navigate, omniboxValue]);

  const handleNewTab = useCallback(() => {
    void createTab();
  }, [createTab]);

  const activeTabState = activeTab;
  const canShorten = !!(activeTab?.url && activeTab.url !== 'about:newtab' && activeTab.url !== '');

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
        paddingLeft: process.platform === 'darwin' ? 80 : 8,
        paddingRight: 8,
        gap: 2,
        overflowX: 'auto',
        overflowY: 'hidden',
      }}>
        {tabOrder.map(id => {
          const tab = tabs[id];
          const isActive = id === activeTabId;
          return (
            <div
              key={id}
              onClick={() => void activateTab(id)}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 6,
                padding: '0 10px',
                height: 28,
                minWidth: 120,
                maxWidth: 200,
                borderRadius: 6,
                background: isActive ? 'var(--color-bg-elevated)' : 'transparent',
                border: isActive ? '1px solid var(--color-border)' : '1px solid transparent',
                cursor: 'pointer',
                WebkitAppRegion: 'no-drag',
                flexShrink: 0,
              } as React.CSSProperties}
            >
              {tab?.favicon ? (
                <img src={tab.favicon} width={14} height={14} style={{ borderRadius: 2 }} alt="" />
              ) : (
                <div style={{ width: 14, height: 14, borderRadius: 2, background: 'var(--color-border)' }} />
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
              <button
                onClick={(e) => { e.stopPropagation(); void closeTab(id); }}
                style={{
                  width: 16,
                  height: 16,
                  borderRadius: 4,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  fontSize: 10,
                  opacity: 0.6,
                  flexShrink: 0,
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
            width: 28,
            height: 28,
            borderRadius: 6,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontSize: 16,
            color: 'var(--color-text-muted)',
            WebkitAppRegion: 'no-drag',
          } as React.CSSProperties}
        >+</button>
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

        {/* Omnibox */}
        <form onSubmit={handleOmniboxSubmit} style={{ flex: 1 }}>
          <input
            ref={omniboxRef}
            value={omniboxFocused ? omniboxValue : (activeTab?.url ?? '')}
            onChange={e => setOmniboxValue(e.target.value)}
            onFocus={(e) => { setOmniboxFocused(true); setOmniboxValue(e.target.value); e.target.select(); }}
            onBlur={() => setOmniboxFocused(false)}
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
            }}
          />
        </form>

        {/* ── Link tool buttons ─────────────────────────────────────────────── */}

        {/* Shorten + QR popover trigger */}
        <button
          onClick={() => {
            if (!canShorten) return;
            setShortenOpen(prev => !prev);
          }}
          disabled={!canShorten}
          title="Shorten this page / generate QR code"
          style={{
            fontSize: 13,
            padding: '3px 8px',
            borderRadius: 8,
            background: shortenOpen ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
            color: shortenOpen ? '#fff' : 'var(--color-text)',
            border: '1px solid var(--color-border)',
            opacity: canShorten ? 1 : 0.35,
            whiteSpace: 'nowrap',
            transition: 'all 0.12s',
          }}
        >🔗</button>

        {/* Sync pending indicator */}
        {pendingSyncCount > 0 && (
          <div
            title={`${pendingSyncCount} change${pendingSyncCount === 1 ? '' : 's'} waiting to sync — will retry automatically`}
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

        {/* Bookmark button */}
        <button style={{ fontSize: 16, padding: '2px 6px', opacity: 0.7 }} title="Bookmark">☆</button>

        {/* Downloads button */}
        {onToggleDownloads && (
          <button
            onClick={onToggleDownloads}
            title="Downloads"
            style={{
              position: 'relative',
              fontSize: 15,
              padding: '2px 7px',
              borderRadius: 8,
              background: downloadsPanelOpen ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
              color: downloadsPanelOpen ? '#fff' : 'var(--color-text-muted)',
              border: '1px solid var(--color-border)',
              transition: 'all 0.12s',
            }}
          >
            ⬇
            {activeDownloadCount > 0 && (
              <span style={{
                position: 'absolute',
                top: -5,
                right: -5,
                minWidth: 16,
                height: 16,
                borderRadius: 8,
                background: 'var(--color-primary)',
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

        {/* Zio AI button */}
        <button
          onClick={onToggleZio}
          style={{
            padding: '4px 12px',
            borderRadius: 14,
            background: zioPanelOpen ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
            color: zioPanelOpen ? '#fff' : 'var(--color-text)',
            border: '1px solid var(--color-primary)',
            fontSize: 12,
            fontWeight: 600,
            transition: 'all 0.15s',
          }}
          title="Open Zio AI Panel"
        >⚡ Zio</button>

        {/* Mode switcher — shown in browser mode, hidden in split right-pane */}
        {showModeSwitcher && (
          <ModeSwitcher currentMode={mode} onSetMode={setMode} />
        )}

        {/* User avatar / sign in */}
        {user ? (
          <div style={{
            width: 28,
            height: 28,
            borderRadius: '50%',
            background: 'var(--color-primary)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontSize: 12,
            fontWeight: 700,
            color: '#fff',
            cursor: 'pointer',
            flexShrink: 0,
          }}
            title={user.name}
          >
            {(user.name ?? 'U').charAt(0).toUpperCase()}
          </div>
        ) : (
          <button
            onClick={onOpenAuth}
            style={{
              padding: '4px 10px',
              borderRadius: 12,
              background: 'var(--color-bg-elevated)',
              border: '1px solid var(--color-border)',
              fontSize: 12,
              whiteSpace: 'nowrap',
            }}
          >Sign in</button>
        )}
      </div>

      {/* Shorten / QR popover */}
      {shortenOpen && activeTab && (
        <ShortenPopover
          pageUrl={activeTab.url}
          pageTitle={activeTab.title ?? ''}
          baseUrl={BASE_URL}
          onClose={() => setShortenOpen(false)}
          onOpenAuth={() => { setShortenOpen(false); onOpenAuth(); }}
        />
      )}
    </div>
  );
}
