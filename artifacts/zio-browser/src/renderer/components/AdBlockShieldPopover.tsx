import React, { useCallback, useEffect, useState } from 'react';

/**
 * Shield popover — ad-block quick controls for the current page.
 *
 * Shows the effective ad-block state for the active tab (layered policy:
 * admin-mandated → timed pause → page pause → per-site setting → user lists
 * → strength/global), with quick toggles for pausing, per-site allow/block
 * list entries, and blocking strength. Admin-mandated sites render a locked
 * "Managed by Sayzio" view with no user overrides.
 */

interface AdBlockState {
  active: boolean;
  reason: string;
  adminLocked: boolean;
  strength: 'strict' | 'balanced';
  globalEnabled: boolean;
  timedPauseUntil: number | null;
  pausedUntilRestart: boolean;
}

interface Props {
  tabId: string;
  host: string;
  blockedCount: number;
  onClose: () => void;
}

const rowStyle: React.CSSProperties = {
  display: 'flex',
  alignItems: 'center',
  justifyContent: 'space-between',
  padding: '6px 0',
  gap: 8,
};

const btnStyle: React.CSSProperties = {
  fontSize: 12,
  padding: '5px 10px',
  borderRadius: 8,
  border: '1px solid var(--color-border)',
  background: 'var(--color-bg-elevated)',
  color: 'var(--color-text)',
  cursor: 'pointer',
};

function reasonLabel(state: AdBlockState, host: string): string {
  switch (state.reason) {
    case 'admin-block': return `Ad blocking is required on ${host}`;
    case 'admin-allow': return `Ads are allowed on ${host} by policy`;
    case 'timed-pause': return state.pausedUntilRestart
      ? 'Paused until restart'
      : 'Paused temporarily';
    case 'page-pause': return 'Paused on this page until you navigate';
    case 'site-setting': return state.active ? `Blocking ads on ${host} (site setting)` : `Ads allowed on ${host} (site setting)`;
    case 'user-block': return `${host} is on your block list`;
    case 'user-allow': return `${host} is on your allow list`;
    case 'global': return state.active ? `Blocking ads on ${host}` : 'Ad blocking is turned off';
    default: return state.active ? `Blocking ads on ${host}` : `Ads allowed on ${host}`;
  }
}

export function AdBlockShieldPopover({ tabId, host, blockedCount, onClose }: Props) {
  const [state, setState] = useState<AdBlockState | null>(null);
  const [lists, setLists] = useState<{ allow: string[]; block: string[] }>({ allow: [], block: [] });

  const refresh = useCallback(() => {
    void window.zio.adblock.getState(tabId).then(setState).catch(() => {});
    void window.zio.adblock.getLists().then(setLists).catch(() => {});
  }, [tabId]);

  useEffect(() => {
    refresh();
    const listener = () => refresh();
    window.zio.on('adblock:state-changed', listener);
    return () => window.zio.off('adblock:state-changed', listener);
  }, [refresh]);

  // Close on Escape.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  const onAllowList = state ? lists.allow.some(d => host === d || host.endsWith('.' + d)) : false;
  const onBlockList = state ? lists.block.some(d => host === d || host.endsWith('.' + d)) : false;
  const paused = state ? (state.reason === 'timed-pause' || state.reason === 'page-pause') : false;

  return (
    <>
      <div style={{ position: 'fixed', inset: 0, zIndex: 8500 }} onClick={onClose} />
      <div
        role="dialog"
        aria-label={`Ad blocking on ${host}`}
        style={{
          position: 'fixed',
          top: 'max(8px, min(86px, calc(100vh - 140px)))',
          right: 'max(8px, min(96px, calc(100vw - 316px)))',
          width: 300,
          maxWidth: 'calc(100vw - 16px)',
          maxHeight: 'min(calc(100vh - 16px), max(132px, calc(100vh - 94px)))',
          overflowY: 'auto',
          background: 'var(--color-bg-surface)',
          border: '1px solid var(--color-border)',
          borderRadius: 14,
          boxShadow: '0 12px 40px rgba(0,0,0,0.35)',
          padding: '14px 16px 16px',
          zIndex: 8501,
        }}
      >
        <div style={{
          display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 6,
        }}>
          <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--color-text)' }}>
            {state?.active ? '🛡️ Ad blocking on' : '🛡️ Ad blocking off'}
          </span>
          <button onClick={onClose} title="Close"
            style={{ fontSize: 15, color: 'var(--color-text-muted)', padding: '0 4px' }}>✕</button>
        </div>

        {!state ? (
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', padding: '8px 0' }}>Loading…</div>
        ) : (
          <>
            <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 8 }}>
              {reasonLabel(state, host)}
            </div>
            {state.active && blockedCount > 0 && (
              <div style={{ fontSize: 12, color: 'var(--color-success)', marginBottom: 8 }}>
                {blockedCount} request{blockedCount === 1 ? '' : 's'} blocked on this page
              </div>
            )}

            {state.adminLocked ? (
              <div
                data-testid="adblock-managed"
                style={{
                  fontSize: 12,
                  color: 'var(--color-text-muted)',
                  border: '1px dashed var(--color-border)',
                  borderRadius: 10,
                  padding: '10px 12px',
                }}
              >
                🔒 Managed by Sayzio — this site's ad-blocking setting is set by
                policy and can't be changed.
              </div>
            ) : (
              <>
                {/* Pause quick-toggles */}
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginBottom: 10 }}>
                  {paused ? (
                    <button style={btnStyle} onClick={() => { void window.zio.adblock.resume().then(refresh); }}>
                      ▶ Resume blocking
                    </button>
                  ) : (
                    <>
                      <button style={btnStyle} onClick={() => { void window.zio.adblock.pausePage(tabId).then(refresh); }}>
                        Pause on this page
                      </button>
                      <button style={btnStyle} onClick={() => { void window.zio.adblock.pauseTimed(15).then(refresh); }}>
                        Pause 15 min
                      </button>
                      <button style={btnStyle} onClick={() => { void window.zio.adblock.pauseTimed(60).then(refresh); }}>
                        Pause 1 hour
                      </button>
                      <button style={btnStyle} onClick={() => { void window.zio.adblock.pauseTimed(null).then(refresh); }}>
                        Until restart
                      </button>
                    </>
                  )}
                </div>

                {/* Per-site list quick actions */}
                <div style={rowStyle}>
                  <span style={{ fontSize: 12, color: 'var(--color-text)' }}>This site</span>
                  <div style={{ display: 'flex', gap: 6 }}>
                    <button
                      style={{ ...btnStyle, ...(onAllowList ? { borderColor: 'var(--color-primary)', color: 'var(--color-primary)' } : {}) }}
                      onClick={() => {
                        const p = onAllowList
                          ? window.zio.adblock.removeListDomain('allow', host)
                          : window.zio.adblock.addListDomain('allow', host);
                        void Promise.resolve(p).then(refresh);
                      }}
                    >
                      {onAllowList ? '✓ Allowed' : 'Always allow'}
                    </button>
                    <button
                      style={{ ...btnStyle, ...(onBlockList ? { borderColor: 'var(--color-primary)', color: 'var(--color-primary)' } : {}) }}
                      onClick={() => {
                        const p = onBlockList
                          ? window.zio.adblock.removeListDomain('block', host)
                          : window.zio.adblock.addListDomain('block', host);
                        void Promise.resolve(p).then(refresh);
                      }}
                    >
                      {onBlockList ? '✓ Blocked' : 'Always block'}
                    </button>
                  </div>
                </div>

                {/* Strength */}
                <div style={rowStyle}>
                  <span style={{ fontSize: 12, color: 'var(--color-text)' }}>Strength</span>
                  <select
                    style={{
                      fontSize: 12, padding: '4px 6px', borderRadius: 7,
                      border: '1px solid var(--color-border)',
                      background: 'var(--color-bg-elevated)', color: 'var(--color-text)',
                    }}
                    value={state.globalEnabled ? state.strength : 'off'}
                    onChange={e => {
                      const v = e.target.value;
                      if (v === 'off') {
                        void window.zio.adblock.setEnabled(false).then(refresh);
                      } else {
                        void window.zio.adblock.setEnabled(true)
                          .then(() => window.zio.adblock.setStrength(v === 'strict' ? 'strict' : 'balanced'))
                          .then(refresh);
                      }
                    }}
                  >
                    <option value="strict">Strict (ads + cosmetics)</option>
                    <option value="balanced">Balanced (recommended)</option>
                    <option value="off">Off</option>
                  </select>
                </div>
              </>
            )}
          </>
        )}
      </div>
    </>
  );
}
