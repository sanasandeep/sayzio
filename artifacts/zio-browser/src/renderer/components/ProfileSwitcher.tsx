/**
 * ProfileSwitcher — dropdown that lists the user's Sayzio workspaces as
 * browser profiles. Switching a profile isolates web sessions, history,
 * bookmarks/collections, and cloud sync to that workspace.
 */
import { useState, useRef, useEffect, useCallback } from 'react';
import { useProfileStore } from '../store/profile-store';
import { useTabStore } from '../store/tab-store';
import { useChromeOverlay } from '../hooks/use-chrome-overlay';
import type { BrowserProfile } from '../../shared/profile-store';
import { computeMenuPos, useMenuReanchor, type MenuPos } from '../lib/menu-position';

const MENU_WIDTH = 200;

interface Props {
  /** Show sign-in prompt instead of workspace list when user is not authenticated. */
  isAuthenticated: boolean;
  onOpenAuth: () => void;
}

export function ProfileSwitcher({ isAuthenticated, onOpenAuth }: Props) {
  const { profiles, activeProfileId, switchProfile } = useProfileStore();
  const { createTab } = useTabStore();
  const [open, setOpen] = useState(false);
  const [switching, setSwitching] = useState(false);
  // Viewport coordinates for the fixed-position menu. The trigger chip can
  // live inside a scrollable tab strip (browser mode) whose overflow clips
  // absolutely-positioned children — `position: fixed` escapes that
  // (same pattern as AccountButton).
  const [menuPos, setMenuPos] = useState<MenuPos | null>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const close = useCallback(() => setOpen(false), []);

  // Keep the menu anchored (and on screen) if the window resizes while open.
  useMenuReanchor(open, triggerRef, MENU_WIDTH, setMenuPos, close);

  // The dropdown extends below the chrome bar into the region covered by
  // native WebContentsViews — hold the chrome overlay while open so the menu
  // is visible and clickable (same pattern as ModeSwitcher/AccountButton).
  useChromeOverlay(open);

  // Close on outside click
  useEffect(() => {
    if (!open) return;
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  const activeProfile: BrowserProfile | undefined =
    profiles.find(p => p.id === activeProfileId) ?? profiles[0];

  const handleSwitch = useCallback(async (profile: BrowserProfile) => {
    if (profile.id === activeProfileId) { setOpen(false); return; }
    setSwitching(true);
    try {
      await switchProfile(profile.id);
    } finally {
      setSwitching(false);
      setOpen(false);
    }
  }, [activeProfileId, switchProfile]);

  const initial = (activeProfile?.name ?? 'P').charAt(0).toUpperCase();

  return (
    <div ref={containerRef} style={{ position: 'relative', flexShrink: 0 }}>
      {/* Trigger button */}
      <button
        ref={triggerRef}
        onClick={(e) => {
          if (!isAuthenticated) { onOpenAuth(); return; }
          setMenuPos(computeMenuPos(e.currentTarget.getBoundingClientRect(), MENU_WIDTH));
          setOpen(prev => !prev);
        }}
        title={activeProfile ? `Profile: ${activeProfile.name}` : 'Switch profile'}
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 5,
          padding: '3px 8px',
          borderRadius: 10,
          background: open ? 'var(--color-bg-elevated)' : 'transparent',
          border: '1px solid var(--color-border)',
          fontSize: 12,
          color: 'var(--color-text)',
          cursor: 'pointer',
          transition: 'all 0.12s',
          WebkitAppRegion: 'no-drag',
        } as React.CSSProperties}
      >
        <span style={{
          width: 18,
          height: 18,
          borderRadius: '50%',
          background: profileColor(activeProfile?.id ?? 'default'),
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontSize: 10,
          fontWeight: 700,
          color: '#fff',
          flexShrink: 0,
        }}>{initial}</span>
        <span style={{
          maxWidth: 80,
          overflow: 'hidden',
          textOverflow: 'ellipsis',
          whiteSpace: 'nowrap',
        }}>
          {activeProfile?.name ?? 'Personal'}
        </span>
        <span style={{ fontSize: 8, opacity: 0.6 }}>▾</span>
      </button>

      {/* Dropdown */}
      {open && menuPos && (
        <div style={{
          position: 'fixed',
          top: menuPos.top,
          right: menuPos.right,
          minWidth: MENU_WIDTH,
          maxHeight: menuPos.maxHeight,
          overflowY: 'auto',
          background: 'var(--color-bg-surface)',
          border: '1px solid var(--color-border)',
          borderRadius: 10,
          boxShadow: '0 8px 24px rgba(0,0,0,0.18)',
          zIndex: 1000,
          overflow: 'hidden',
        }}>
          <div style={{
            padding: '8px 12px 6px',
            fontSize: 10,
            fontWeight: 600,
            color: 'var(--color-text-muted)',
            textTransform: 'uppercase',
            letterSpacing: 0.8,
            borderBottom: '1px solid var(--color-border)',
          }}>
            Browser Profile
          </div>

          {profiles.map(profile => {
            const isActive = profile.id === activeProfileId;
            return (
              <button
                key={profile.id}
                onClick={() => void handleSwitch(profile)}
                disabled={switching}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 10,
                  width: '100%',
                  padding: '9px 12px',
                  background: isActive ? 'var(--color-bg-elevated)' : 'transparent',
                  borderBottom: '1px solid var(--color-border)',
                  cursor: switching ? 'wait' : 'pointer',
                  textAlign: 'left',
                  transition: 'background 0.1s',
                }}
                onMouseEnter={e => {
                  if (!isActive) (e.currentTarget as HTMLButtonElement).style.background = 'var(--color-bg-elevated)';
                }}
                onMouseLeave={e => {
                  if (!isActive) (e.currentTarget as HTMLButtonElement).style.background = 'transparent';
                }}
              >
                {/* Avatar */}
                <span style={{
                  width: 28,
                  height: 28,
                  borderRadius: '50%',
                  background: profileColor(profile.id),
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  fontSize: 12,
                  fontWeight: 700,
                  color: '#fff',
                  flexShrink: 0,
                  opacity: switching && !isActive ? 0.5 : 1,
                }}>
                  {profile.name.charAt(0).toUpperCase()}
                </span>

                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{
                    fontSize: 13,
                    fontWeight: isActive ? 600 : 400,
                    color: 'var(--color-text)',
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    whiteSpace: 'nowrap',
                  }}>
                    {profile.name}
                  </div>
                  <div style={{ fontSize: 10, color: 'var(--color-text-muted)' }}>
                    {profile.isPersonal ? 'Personal profile' : 'Workspace profile'}
                  </div>
                </div>

                {isActive && (
                  <span style={{ fontSize: 14, color: 'var(--color-primary)', flexShrink: 0 }}>✓</span>
                )}
              </button>
            );
          })}

          {/* New Workspace — opens the Sayzio workspace creation page */}
          <button
            onClick={() => {
              setOpen(false);
              void createTab('https://sayzio.app/user/dashboard?create_workspace=1');
            }}
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: 10,
              width: '100%',
              padding: '9px 12px',
              background: 'transparent',
              borderBottom: '1px solid var(--color-border)',
              cursor: 'pointer',
              textAlign: 'left',
              transition: 'background 0.1s',
            }}
            onMouseEnter={e => { (e.currentTarget as HTMLButtonElement).style.background = 'var(--color-bg-elevated)'; }}
            onMouseLeave={e => { (e.currentTarget as HTMLButtonElement).style.background = 'transparent'; }}
          >
            <span style={{
              width: 28,
              height: 28,
              borderRadius: '50%',
              border: '1.5px dashed var(--color-primary)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontSize: 15,
              color: 'var(--color-primary)',
              flexShrink: 0,
            }}>+</span>
            <div style={{ fontSize: 13, color: 'var(--color-text)' }}>New Workspace</div>
          </button>

          <div style={{ padding: '6px 12px 8px', fontSize: 10, color: 'var(--color-text-muted)' }}>
            Each profile has separate history, bookmarks &amp; sessions.
          </div>
        </div>
      )}
    </div>
  );
}

/**
 * Generate a deterministic accent colour from a profile ID so each profile
 * has its own distinct avatar tint.
 */
export function profileColor(profileId: string): string {
  if (profileId === 'default') return '#3d6bff';
  const PALETTE = [
    '#e05c97', '#3b82f6', '#10b981', '#f59e0b',
    '#0ea5e9', '#ef4444', '#14b8a6', '#f97316',
  ];
  let h = 0;
  for (let i = 0; i < profileId.length; i++) {
    h = (h * 31 + profileId.charCodeAt(i)) >>> 0;
  }
  return PALETTE[h % PALETTE.length] ?? '#3d6bff';
}
