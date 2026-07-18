/**
 * ChromeBar — the browser chrome (tab strip + address bar + controls).
 * Runs in the renderer (app chrome window); actual web content is in WebContentsView.
 */
import { useState, useRef, useCallback, useEffect } from 'react';
import { useTabStore } from '../store/tab-store';
import { useAuthStore } from '../store/auth-store';

interface Props {
  zioPanelOpen: boolean;
  onToggleZio: () => void;
  onOpenAuth: () => void;
}

export function ChromeBar({ zioPanelOpen, onToggleZio, onOpenAuth }: Props) {
  const { tabs, tabOrder, activeTabId, createTab, closeTab, activateTab, navigate, goBack, goForward, reload, stop } = useTabStore();
  const { user } = useAuthStore();
  const [omniboxValue, setOmniboxValue] = useState('');
  const [omniboxFocused, setOmniboxFocused] = useState(false);
  const omniboxRef = useRef<HTMLInputElement>(null);

  const activeTab = activeTabId ? tabs[activeTabId] : null;

  // Sync omnibox with active tab URL
  useEffect(() => {
    if (!omniboxFocused) {
      setOmniboxValue(activeTab?.url ?? '');
    }
  }, [activeTab?.url, omniboxFocused]);

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

  return (
    <div style={{
      height: 'var(--chrome-height)',
      background: 'var(--color-bg-surface)',
      borderBottom: '1px solid var(--color-border)',
      display: 'flex',
      flexDirection: 'column',
      WebkitAppRegion: 'drag',
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

        {/* Bookmark button */}
        <button style={{ fontSize: 16, padding: '2px 6px', opacity: 0.7 }} title="Bookmark">☆</button>

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
    </div>
  );
}
