/**
 * ProfileSettingsModal — edit the signed-in user's Sayzio profile from the
 * browser (name, handle, bio, phone). Loads the live profile via
 * GET /profile, saves via PATCH /profile, then refreshes the cached auth
 * identity so the avatar/name in the header updates immediately.
 *
 * Avatar changes are out of scope here — the picture is managed on the
 * Sayzio dashboard, so we show it read-only with a hint.
 */
import { useState, useEffect, useCallback } from 'react';
import { ApiClient, ApiClientError } from '../../shared/api-client';
import type { ApiUserProfile, UpdateProfilePayload } from '../../shared/api-client';
import { useAuthStore } from '../store/auth-store';

const BASE_URL = 'https://sayzio.app';

interface Props {
  onClose: () => void;
}

export function ProfileSettingsModal({ onClose }: Props) {
  const { token, refreshUser } = useAuthStore();
  const [profile, setProfile] = useState<ApiUserProfile | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);

  const [name, setName] = useState('');
  const [handle, setHandle] = useState('');
  const [bio, setBio] = useState('');
  const [phone, setPhone] = useState('');

  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  // Load the live profile on open.
  useEffect(() => {
    if (!token) return;
    let cancelled = false;
    const client = new ApiClient({ baseUrl: BASE_URL, token });
    client.getProfile()
      .then(({ user }) => {
        if (cancelled) return;
        setProfile(user);
        setName(user.name ?? '');
        setHandle(user.handle ?? '');
        setBio(user.bio ?? '');
        setPhone(user.phone ?? '');
      })
      .catch((err) => {
        if (!cancelled) setLoadError(err instanceof Error ? err.message : 'Could not load your profile');
      });
    return () => { cancelled = true; };
  }, [token]);

  const handleSave = useCallback(async () => {
    if (!token || !profile) return;
    setSaving(true);
    setSaveError(null);
    setSaved(false);
    try {
      const client = new ApiClient({ baseUrl: BASE_URL, token });
      // Only send fields the user actually changed — avoids tripping
      // server-side rules on untouched fields (e.g. a locked name).
      const payload: UpdateProfilePayload = {};
      if (name.trim() !== (profile.name ?? '')) payload.name = name.trim();
      if (handle.trim() !== (profile.handle ?? '')) payload.handle = handle.trim() || null;
      if (bio !== (profile.bio ?? '')) payload.bio = bio || null;
      if (phone.trim() !== (profile.phone ?? '')) payload.phone = phone.trim() || null;
      if (Object.keys(payload).length === 0) {
        setSaved(true);
        setSaving(false);
        return;
      }
      const { user } = await client.updateProfile(payload);
      setProfile(user);
      setName(user.name ?? '');
      setHandle(user.handle ?? '');
      setBio(user.bio ?? '');
      setPhone(user.phone ?? '');
      setSaved(true);
      // Refresh the cached identity (header avatar/name) from the server.
      await refreshUser();
    } catch (err) {
      if (err instanceof ApiClientError && err.status === 422) {
        setSaveError(err.message || 'Some fields are invalid — please check and try again.');
      } else {
        setSaveError(err instanceof Error ? err.message : 'Could not save your profile');
      }
    } finally {
      setSaving(false);
    }
  }, [token, profile, name, handle, bio, phone, refreshUser]);

  const avatarUrl = profile?.avatar && /^https?:\/\//.test(profile.avatar) ? profile.avatar : null;
  const initial = (profile?.name ?? profile?.email ?? 'U').charAt(0).toUpperCase();

  return (
    <div style={{
      position: 'fixed',
      inset: 0,
      background: 'rgba(0,0,0,0.7)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      zIndex: 1000,
    }}>
      <div style={{
        background: 'var(--color-bg-surface)',
        border: '1px solid var(--color-border)',
        borderRadius: 16,
        padding: 28,
        width: 400,
        maxWidth: '92vw',
        maxHeight: '86vh',
        overflowY: 'auto',
      }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 20 }}>
          <h2 style={{ fontSize: 18, fontWeight: 700 }}>Profile settings</h2>
          <button onClick={onClose} style={{ opacity: 0.6, fontSize: 18, cursor: 'pointer' }}>✕</button>
        </div>

        {loadError && (
          <div style={errorBoxStyle}>{loadError}</div>
        )}

        {!profile && !loadError && (
          <div style={{ fontSize: 13, color: 'var(--color-text-muted)', padding: '20px 0' }}>Loading your profile…</div>
        )}

        {profile && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            {/* Avatar (read-only) */}
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
              <div style={{
                width: 56, height: 56, borderRadius: '50%',
                background: 'var(--color-primary)', color: '#fff',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontSize: 22, fontWeight: 700, overflow: 'hidden', flexShrink: 0,
              }}>
                {avatarUrl
                  ? <img src={avatarUrl} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover', display: 'block' }} />
                  : initial}
              </div>
              <div style={{ fontSize: 11.5, color: 'var(--color-text-muted)', lineHeight: 1.45 }}>
                Your profile picture is managed on the Sayzio dashboard
                (Settings → Profile).
              </div>
            </div>

            <label style={labelStyle}>
              Name
              <input value={name} onChange={e => setName(e.target.value)} style={inputStyle} placeholder="Your name" />
            </label>

            <label style={labelStyle}>
              Handle
              <input
                value={handle}
                onChange={e => setHandle(e.target.value.replace(/\s+/g, ''))}
                style={inputStyle}
                placeholder="your-handle"
              />
            </label>

            <label style={labelStyle}>
              Bio
              <textarea
                value={bio}
                onChange={e => setBio(e.target.value)}
                rows={3}
                style={{ ...inputStyle, height: 'auto', padding: '10px 14px', resize: 'vertical', fontFamily: 'inherit' }}
                placeholder="A short bio"
              />
            </label>

            <label style={labelStyle}>
              Phone
              <input value={phone} onChange={e => setPhone(e.target.value)} style={inputStyle} placeholder="+1 555 123 4567" />
            </label>

            {saveError && <div style={errorBoxStyle}>{saveError}</div>}
            {saved && !saveError && (
              <div style={{
                padding: '8px 12px', borderRadius: 8, fontSize: 13,
                background: 'rgba(60,160,90,0.12)', border: '1px solid rgba(60,160,90,0.3)',
                color: 'var(--color-text)',
              }}>Profile saved.</div>
            )}

            <div style={{ display: 'flex', gap: 10, marginTop: 4 }}>
              <button
                onClick={() => void handleSave()}
                disabled={saving || !name.trim()}
                style={{
                  flex: 1, height: 42, borderRadius: 10, border: 'none',
                  background: 'var(--gradient-primary)', color: '#fff',
                  fontSize: 14, fontWeight: 600,
                  cursor: saving ? 'default' : 'pointer',
                  opacity: saving || !name.trim() ? 0.6 : 1,
                }}
              >{saving ? 'Saving…' : 'Save changes'}</button>
              <button
                onClick={onClose}
                style={{
                  height: 42, padding: '0 18px', borderRadius: 10,
                  background: 'var(--color-bg-elevated)', color: 'var(--color-text)',
                  fontSize: 14, border: '1px solid var(--color-border)', cursor: 'pointer',
                }}
              >Close</button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

const labelStyle: React.CSSProperties = {
  display: 'flex',
  flexDirection: 'column',
  gap: 6,
  fontSize: 12,
  fontWeight: 600,
  color: 'var(--color-text-muted)',
};

const inputStyle: React.CSSProperties = {
  width: '100%',
  boxSizing: 'border-box',
  height: 40,
  borderRadius: 10,
  border: '1px solid var(--color-border)',
  background: 'var(--color-bg)',
  color: 'var(--color-text)',
  padding: '0 14px',
  fontSize: 13.5,
  outline: 'none',
  fontFamily: 'inherit',
  fontWeight: 400,
};

const errorBoxStyle: React.CSSProperties = {
  padding: '8px 12px',
  borderRadius: 8,
  background: 'rgba(239,68,68,0.1)',
  border: '1px solid rgba(239,68,68,0.3)',
  color: 'var(--color-danger)',
  fontSize: 13,
};
