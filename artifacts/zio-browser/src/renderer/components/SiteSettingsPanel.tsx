/**
 * SiteSettingsPanel — side panel for reviewing and managing per-site permissions
 * and the global tracker-blocking toggle.
 */
import { useState, useEffect, useCallback } from 'react';

interface SitePermissionRow {
  origin: string;
  permission: string;
  decision: 'allow' | 'block';
  updated_at: string;
}

interface OriginGroup {
  origin: string;
  permissions: Array<{ permission: string; decision: 'allow' | 'block' }>;
}

interface Props {
  currentOrigin: string | null;
  onClose: () => void;
}

const PERMISSION_ICONS: Record<string, string> = {
  camera: '📷',
  microphone: '🎤',
  notifications: '🔔',
  geolocation: '📍',
  midi: '🎵',
  pointerLock: '🖱️',
  'clipboard-read': '📋',
  'clipboard-sanitized-write': '📋',
};

function permLabel(p: string) {
  const map: Record<string, string> = {
    camera: 'Camera', microphone: 'Microphone', notifications: 'Notifications',
    geolocation: 'Location', midi: 'MIDI', pointerLock: 'Pointer Lock',
    'clipboard-read': 'Clipboard Read', 'clipboard-sanitized-write': 'Clipboard Write',
  };
  return map[p] ?? p;
}

function formatHost(origin: string) {
  try { return new URL(origin).hostname; } catch { return origin; }
}

export function SiteSettingsPanel({ currentOrigin, onClose }: Props) {
  const [groups, setGroups] = useState<OriginGroup[]>([]);
  const [trackerEnabled, setTrackerEnabled] = useState(false);
  const [activeTab, setActiveTab] = useState<'site' | 'all'>('site');

  const load = useCallback(async () => {
    const [rows, enabled] = await Promise.all([
      window.zio.permissions.getAll() as Promise<SitePermissionRow[]>,
      window.zio.tracker.isEnabled() as Promise<boolean>,
    ]);
    setTrackerEnabled(enabled);

    const map = new Map<string, Array<{ permission: string; decision: 'allow' | 'block' }>>();
    for (const row of rows) {
      if (!map.has(row.origin)) map.set(row.origin, []);
      map.get(row.origin)!.push({ permission: row.permission, decision: row.decision });
    }
    const sorted = [...map.entries()]
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([origin, permissions]) => ({ origin, permissions }));
    setGroups(sorted);
  }, []);

  useEffect(() => { void load(); }, [load]);

  const handleRevoke = useCallback(async (origin: string, permission: string) => {
    await window.zio.permissions.revoke(origin, permission);
    await load();
  }, [load]);

  const handleRevokeAll = useCallback(async (origin: string) => {
    const group = groups.find(g => g.origin === origin);
    if (!group) return;
    await Promise.all(group.permissions.map(p => window.zio.permissions.revoke(origin, p.permission)));
    await load();
  }, [groups, load]);

  const handleClearAll = useCallback(async () => {
    await window.zio.permissions.clearAll();
    await load();
  }, [load]);

  const handleToggleTracker = useCallback(async () => {
    const next = !trackerEnabled;
    await window.zio.tracker.setEnabled(next);
    setTrackerEnabled(next);
  }, [trackerEnabled]);

  const currentGroup = currentOrigin
    ? groups.find(g => g.origin === currentOrigin) ?? (currentOrigin ? { origin: currentOrigin, permissions: [] } : null)
    : null;

  const displayGroups = activeTab === 'site' && currentGroup ? [currentGroup] : groups;

  return (
    <div style={{
      position: 'fixed',
      inset: 0,
      background: 'rgba(0,0,0,0.5)',
      display: 'flex',
      alignItems: 'flex-start',
      justifyContent: 'flex-end',
      zIndex: 8000,
    }} onClick={onClose}>
      <div
        style={{
          width: 380,
          height: '100%',
          background: 'var(--color-bg-surface)',
          borderLeft: '1px solid var(--color-border)',
          display: 'flex',
          flexDirection: 'column',
          overflow: 'hidden',
        }}
        onClick={e => e.stopPropagation()}
      >
        {/* Header */}
        <div style={{
          padding: '18px 20px 14px',
          borderBottom: '1px solid var(--color-border)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          flexShrink: 0,
        }}>
          <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--color-text)' }}>
            🛡️ Site Settings
          </div>
          <button
            onClick={onClose}
            style={{ fontSize: 18, color: 'var(--color-text-muted)', padding: '2px 6px' }}
          >✕</button>
        </div>

        {/* Tracker toggle */}
        <div style={{
          padding: '14px 20px',
          borderBottom: '1px solid var(--color-border)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          flexShrink: 0,
        }}>
          <div>
            <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--color-text)' }}>
              🚫 Tracker &amp; Ad Blocking
            </div>
            <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 3 }}>
              {trackerEnabled ? 'On — blocking known trackers' : 'Off — trackers are not blocked'}
            </div>
          </div>
          <button
            onClick={handleToggleTracker}
            style={{
              width: 44,
              height: 24,
              borderRadius: 12,
              background: trackerEnabled ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
              border: `1px solid ${trackerEnabled ? 'var(--color-primary)' : 'var(--color-border)'}`,
              position: 'relative',
              transition: 'all 0.2s',
              flexShrink: 0,
              cursor: 'pointer',
            }}
            title={trackerEnabled ? 'Disable tracker blocking' : 'Enable tracker blocking'}
          >
            <div style={{
              position: 'absolute',
              top: 2,
              left: trackerEnabled ? 22 : 2,
              width: 18,
              height: 18,
              borderRadius: '50%',
              background: '#fff',
              transition: 'left 0.2s',
              boxShadow: '0 1px 4px rgba(0,0,0,0.25)',
            }} />
          </button>
        </div>

        {/* Tabs — only show if there's a current origin */}
        {currentOrigin && (
          <div style={{
            display: 'flex',
            borderBottom: '1px solid var(--color-border)',
            flexShrink: 0,
          }}>
            {(['site', 'all'] as const).map(tab => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                style={{
                  flex: 1,
                  padding: '10px',
                  fontSize: 12,
                  fontWeight: activeTab === tab ? 600 : 400,
                  color: activeTab === tab ? 'var(--color-primary)' : 'var(--color-text-muted)',
                  borderBottom: activeTab === tab ? '2px solid var(--color-primary)' : '2px solid transparent',
                  transition: 'all 0.15s',
                }}
              >
                {tab === 'site' ? `This site (${formatHost(currentOrigin)})` : 'All sites'}
              </button>
            ))}
          </div>
        )}

        {/* Permission list */}
        <div style={{ flex: 1, overflowY: 'auto', padding: '12px 0' }}>
          {displayGroups.length === 0 ? (
            <div style={{
              padding: '32px 20px',
              textAlign: 'center',
              color: 'var(--color-text-muted)',
              fontSize: 13,
            }}>
              No permission decisions stored yet.
              <br />
              <span style={{ fontSize: 11, marginTop: 6, display: 'block' }}>
                When a site asks for camera, microphone, or other permissions,
                your choices will appear here.
              </span>
            </div>
          ) : (
            displayGroups.map(group => (
              <div key={group.origin} style={{ marginBottom: 8 }}>
                {/* Origin header */}
                <div style={{
                  padding: '8px 20px',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                }}>
                  <div>
                    <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--color-text)' }}>
                      {formatHost(group.origin)}
                    </div>
                    <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 1 }}>
                      {group.origin}
                    </div>
                  </div>
                  {group.permissions.length > 0 && (
                    <button
                      onClick={() => void handleRevokeAll(group.origin)}
                      style={{
                        fontSize: 11,
                        color: 'var(--color-danger)',
                        padding: '3px 8px',
                        borderRadius: 6,
                        border: '1px solid var(--color-danger)',
                        opacity: 0.75,
                      }}
                    >Remove all</button>
                  )}
                </div>

                {group.permissions.length === 0 ? (
                  <div style={{
                    padding: '6px 20px',
                    fontSize: 12,
                    color: 'var(--color-text-muted)',
                    fontStyle: 'italic',
                  }}>
                    No permissions granted or blocked
                  </div>
                ) : (
                  group.permissions.map(perm => (
                    <div
                      key={perm.permission}
                      style={{
                        padding: '8px 20px',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        borderTop: '1px solid var(--color-border)',
                      }}
                    >
                      <span style={{ fontSize: 16, flexShrink: 0 }}>
                        {PERMISSION_ICONS[perm.permission] ?? '🔒'}
                      </span>
                      <div style={{ flex: 1 }}>
                        <div style={{ fontSize: 12, color: 'var(--color-text)' }}>
                          {permLabel(perm.permission)}
                        </div>
                        <div style={{
                          fontSize: 11,
                          color: perm.decision === 'allow' ? 'var(--color-success)' : 'var(--color-danger)',
                          fontWeight: 600,
                          marginTop: 1,
                        }}>
                          {perm.decision === 'allow' ? '✓ Allowed' : '✕ Blocked'}
                        </div>
                      </div>
                      <button
                        onClick={() => void handleRevoke(group.origin, perm.permission)}
                        style={{
                          fontSize: 11,
                          color: 'var(--color-text-muted)',
                          padding: '3px 8px',
                          borderRadius: 6,
                          border: '1px solid var(--color-border)',
                        }}
                        title="Remove this permission decision (will ask again next time)"
                      >Reset</button>
                    </div>
                  ))
                )}
              </div>
            ))
          )}
        </div>

        {/* Footer */}
        {groups.length > 0 && (
          <div style={{
            padding: '12px 20px',
            borderTop: '1px solid var(--color-border)',
            flexShrink: 0,
          }}>
            <button
              onClick={handleClearAll}
              style={{
                width: '100%',
                padding: '8px',
                borderRadius: 8,
                border: '1px solid var(--color-danger)',
                background: 'transparent',
                color: 'var(--color-danger)',
                fontSize: 12,
                fontWeight: 500,
                opacity: 0.8,
              }}
            >Clear all permission decisions</button>
          </div>
        )}
      </div>
    </div>
  );
}
