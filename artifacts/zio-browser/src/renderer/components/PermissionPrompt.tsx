/**
 * PermissionPrompt — modal that appears when a site requests a sensitive permission.
 * Rendered in the app-chrome window; the user allows or blocks, with an optional
 * "remember this decision" checkbox.
 */
import { useState, useEffect, useCallback } from 'react';

export interface PendingPermission {
  requestId: string;
  origin: string;
  permission: string;
  requestingUrl: string;
}

interface Props {
  request: PendingPermission;
  onDismiss: () => void;
}

const PERMISSION_META: Record<string, { icon: string; label: string; description: string }> = {
  camera:          { icon: '📷', label: 'Camera',        description: 'access your camera' },
  microphone:      { icon: '🎤', label: 'Microphone',    description: 'access your microphone' },
  notifications:   { icon: '🔔', label: 'Notifications', description: 'send you notifications' },
  geolocation:     { icon: '📍', label: 'Location',      description: 'access your location' },
  midi:            { icon: '🎵', label: 'MIDI',          description: 'access MIDI devices' },
  pointerLock:     { icon: '🖱️',  label: 'Pointer Lock', description: 'lock your mouse pointer' },
  'clipboard-read':             { icon: '📋', label: 'Clipboard Read',  description: 'read your clipboard' },
  'clipboard-sanitized-write':  { icon: '📋', label: 'Clipboard Write', description: 'write to your clipboard' },
};

function getMeta(permission: string) {
  return PERMISSION_META[permission] ?? { icon: '🔒', label: permission, description: `use ${permission}` };
}

function formatOrigin(origin: string): string {
  try { return new URL(origin).hostname; } catch { return origin; }
}

export function PermissionPrompt({ request, onDismiss }: Props) {
  const [remember, setRemember] = useState(true);
  const meta = getMeta(request.permission);
  const host = formatOrigin(request.origin);

  const respond = useCallback((decision: 'allow' | 'block') => {
    void window.zio.permissions.respond(
      request.requestId, decision, remember, request.origin, request.permission,
    );
    onDismiss();
  }, [request, remember, onDismiss]);

  // Keyboard shortcuts: Enter = allow, Escape = block
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') respond('block');
      if (e.key === 'Enter') respond('allow');
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [respond]);

  return (
    <div style={{
      position: 'fixed',
      inset: 0,
      background: 'rgba(0,0,0,0.55)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      zIndex: 9999,
    }}>
      <div style={{
        background: 'var(--color-bg-surface)',
        border: '1px solid var(--color-border)',
        borderRadius: 14,
        padding: '24px 28px',
        minWidth: 340,
        maxWidth: 420,
        boxShadow: '0 20px 60px rgba(0,0,0,0.5)',
        display: 'flex',
        flexDirection: 'column',
        gap: 16,
      }}>
        {/* Icon + title */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
          <div style={{
            width: 48, height: 48, borderRadius: 12,
            background: 'var(--color-bg-elevated)',
            border: '1px solid var(--color-border)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: 22, flexShrink: 0,
          }}>{meta.icon}</div>
          <div>
            <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--color-text)' }}>
              {meta.label} Request
            </div>
            <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginTop: 3 }}>
              {host}
            </div>
          </div>
        </div>

        {/* Body */}
        <div style={{ fontSize: 13, color: 'var(--color-text-muted)', lineHeight: 1.6 }}>
          <strong style={{ color: 'var(--color-text)' }}>{host}</strong> wants to {meta.description}.
        </div>

        {/* Remember checkbox */}
        <label style={{
          display: 'flex', alignItems: 'center', gap: 8,
          fontSize: 12, color: 'var(--color-text-muted)', cursor: 'pointer',
        }}>
          <input
            type="checkbox"
            checked={remember}
            onChange={e => setRemember(e.target.checked)}
            style={{ cursor: 'pointer' }}
          />
          Remember this decision for {host}
        </label>

        {/* Buttons */}
        <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end' }}>
          <button
            onClick={() => respond('block')}
            style={{
              padding: '8px 18px',
              borderRadius: 8,
              border: '1px solid var(--color-border)',
              background: 'var(--color-bg-elevated)',
              color: 'var(--color-text)',
              fontSize: 13,
              fontWeight: 500,
            }}
          >Block</button>
          <button
            onClick={() => respond('allow')}
            style={{
              padding: '8px 18px',
              borderRadius: 8,
              border: 'none',
              background: 'var(--color-primary)',
              color: '#fff',
              fontSize: 13,
              fontWeight: 600,
            }}
          >Allow</button>
        </div>
      </div>
    </div>
  );
}
