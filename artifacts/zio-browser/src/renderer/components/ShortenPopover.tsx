/**
 * ShortenPopover — Shorten the current page URL and/or generate a QR code.
 * Opens as a popover anchored to the Shorten button in the ChromeBar.
 */
import { useState, useEffect, useCallback, useRef } from 'react';
import { useAuthStore } from '../store/auth-store';
import { ApiClient } from '../../shared/api-client';
import type { ApiDomain, ApiLink, AliasCheckResult } from '../../shared/api-client';
import { suggestAlias, quickQrImageUrl } from '../../shared/link-tools';

interface Props {
  pageUrl: string;
  pageTitle: string;
  baseUrl: string;
  onClose: () => void;
  onOpenAuth: () => void;
  /** Called after successful creation with the new short URL — navigates the active browser tab. */
  onNavigate?: (url: string) => void;
}

type View = 'shorten' | 'qr';

export function ShortenPopover({ pageUrl, pageTitle, baseUrl, onClose, onOpenAuth, onNavigate }: Props) {
  const { token } = useAuthStore();
  const popoverRef = useRef<HTMLDivElement>(null);

  const [view, setView] = useState<View>('shorten');
  const [alias, setAlias] = useState(() => suggestAlias(pageTitle));
  const [domains, setDomains] = useState<ApiDomain[]>([]);
  const [selectedDomainId, setSelectedDomainId] = useState<number | null>(null);
  const [aliasCheck, setAliasCheck] = useState<AliasCheckResult | null>(null);
  const [checkingAlias, setCheckingAlias] = useState(false);
  const [createdLink, setCreatedLink] = useState<ApiLink | null>(null);
  const [creating, setCreating] = useState(false);
  const [copied, setCopied] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const getClient = useCallback((): ApiClient | null => {
    if (!token) return null;
    return new ApiClient({ baseUrl, token });
  }, [token, baseUrl]);

  // Load available domains once
  useEffect(() => {
    if (!token) return;
    const client = getClient();
    if (!client) return;
    void client.listAvailableDomains().then(res => {
      setDomains(res.items);
      const primary = res.items.find(d => d.is_primary) ?? res.items[0];
      if (primary) setSelectedDomainId(primary.id);
    }).catch(() => {/* silent */});
  }, [token, getClient]);

  // Debounced alias availability check
  useEffect(() => {
    if (!token || !alias) { setAliasCheck(null); return; }
    const client = getClient();
    if (!client) return;
    setCheckingAlias(true);
    const timer = setTimeout(() => {
      void client.checkAlias(alias).then(result => {
        setAliasCheck(result);
        setCheckingAlias(false);
      }).catch(() => { setCheckingAlias(false); });
    }, 400);
    return () => clearTimeout(timer);
  }, [alias, token, getClient]);

  // Close on outside click
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (popoverRef.current && !popoverRef.current.contains(e.target as Node)) {
        onClose();
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [onClose]);

  const handleShorten = useCallback(async () => {
    if (!token) { onOpenAuth(); return; }
    const client = getClient();
    if (!client) return;
    setCreating(true);
    setError(null);
    try {
      const payload = {
        type: 'short',
        long_url: pageUrl,
        title: pageTitle || undefined,
        alias: alias || undefined,
        domain_id: selectedDomainId || undefined,
      };
      const res = await client.createLink(payload);
      setCreatedLink(res.link);
      if (res.link.short_url && onNavigate) {
        onNavigate(res.link.short_url);
      }
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to create short link';
      setError(msg);
    } finally {
      setCreating(false);
    }
  }, [token, getClient, pageUrl, pageTitle, alias, selectedDomainId, onOpenAuth]);

  const handleCopy = useCallback(async (text: string) => {
    await window.zio.clipboard.write(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }, []);

  const shortUrl = createdLink?.short_url ?? '';
  const qrUrl = view === 'qr' ? quickQrImageUrl(createdLink?.short_url ?? pageUrl) : '';

  if (!token) {
    return (
      <PopoverShell ref={popoverRef} onClose={onClose}>
        <div style={{ padding: 20, textAlign: 'center' }}>
          <p style={{ fontSize: 13, marginBottom: 12 }}>Sign in to shorten links with Sayzio</p>
          <button
            onClick={() => { onClose(); onOpenAuth(); }}
            style={primaryBtn}
          >Sign in</button>
        </div>
      </PopoverShell>
    );
  }

  return (
    <PopoverShell ref={popoverRef} onClose={onClose}>
      {/* Tab strip */}
      <div style={{ display: 'flex', borderBottom: '1px solid var(--color-border)' }}>
        {(['shorten', 'qr'] as View[]).map(v => (
          <button
            key={v}
            onClick={() => setView(v)}
            style={{
              flex: 1,
              padding: '8px 0',
              fontSize: 12,
              fontWeight: view === v ? 600 : 400,
              color: view === v ? 'var(--color-primary)' : 'var(--color-text-muted)',
              borderBottom: view === v ? '2px solid var(--color-primary)' : '2px solid transparent',
              marginBottom: -1,
            }}
          >{v === 'shorten' ? '🔗 Shorten' : '⬛ QR Code'}</button>
        ))}
      </div>

      {view === 'shorten' && (
        <div style={{ padding: 14 }}>
          {createdLink ? (
            // Success state
            <div>
              <p style={{ fontSize: 11, color: 'var(--color-text-muted)', marginBottom: 6 }}>Short link created!</p>
              <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                <input
                  readOnly
                  value={createdLink.short_url}
                  style={{ ...inputStyle, flex: 1, background: 'var(--color-bg-elevated)', cursor: 'text' }}
                />
                <button
                  onClick={() => void handleCopy(createdLink.short_url)}
                  style={primaryBtn}
                >{copied ? '✓' : 'Copy'}</button>
              </div>
              <div style={{ marginTop: 10, display: 'flex', gap: 8 }}>
                <button
                  onClick={() => setView('qr')}
                  style={{ ...secondaryBtn, flex: 1 }}
                >⬛ QR Code</button>
                <button
                  onClick={() => { setCreatedLink(null); setAlias(suggestAlias(pageTitle)); }}
                  style={{ ...secondaryBtn, flex: 1 }}
                >+ New</button>
              </div>
            </div>
          ) : (
            // Create form
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              {/* Domain selector */}
              {domains.length > 0 && (
                <div>
                  <label style={labelStyle}>Domain</label>
                  <select
                    value={selectedDomainId ?? ''}
                    onChange={e => setSelectedDomainId(Number(e.target.value) || null)}
                    style={inputStyle}
                  >
                    {domains.map(d => (
                      <option key={d.id} value={d.id}>
                        {d.host}{d.is_primary ? ' (default)' : ''}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              {/* Alias */}
              <div>
                <label style={labelStyle}>Custom alias (optional)</label>
                <input
                  value={alias}
                  onChange={e => setAlias(e.target.value)}
                  placeholder="leave empty to auto-generate"
                  style={{
                    ...inputStyle,
                    borderColor: aliasCheck
                      ? aliasCheck.available ? 'var(--color-success, #22c55e)' : 'var(--color-danger, #ef4444)'
                      : 'var(--color-border)',
                  }}
                />
                {alias && (
                  <p style={{ fontSize: 10, marginTop: 3, color: aliasCheck?.available ? 'var(--color-success, #22c55e)' : 'var(--color-danger, #ef4444)' }}>
                    {checkingAlias ? 'Checking…' : (aliasCheck?.message ?? '')}
                  </p>
                )}
                {alias && !checkingAlias && aliasCheck?.available === false && (aliasCheck.suggestions?.length ?? 0) > 0 && (
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4, marginTop: 4 }}>
                    {aliasCheck.suggestions!.map(s => (
                      <button
                        key={s}
                        type="button"
                        onClick={() => { setAlias(s); setAliasCheck(null); }}
                        style={{ ...secondaryBtn, padding: '2px 8px', fontSize: 10, borderRadius: 999 }}
                      >{s}</button>
                    ))}
                  </div>
                )}
              </div>

              {/* Destination URL (read-only) */}
              <div>
                <label style={labelStyle}>Destination</label>
                <input
                  readOnly
                  value={pageUrl}
                  style={{ ...inputStyle, opacity: 0.7, cursor: 'default' }}
                />
              </div>

              {error && (
                <p style={{ fontSize: 11, color: 'var(--color-danger, #ef4444)' }}>{error}</p>
              )}

              <button
                onClick={() => void handleShorten()}
                disabled={creating || (!!alias && aliasCheck?.available === false)}
                style={{ ...primaryBtn, width: '100%', opacity: creating ? 0.5 : 1 }}
              >{creating ? 'Creating…' : '🔗 Shorten this page'}</button>
            </div>
          )}
        </div>
      )}

      {view === 'qr' && (
        <QrView
          url={createdLink?.short_url ?? pageUrl}
          title={pageTitle}
          baseUrl={baseUrl}
          token={token}
          existingLinkId={createdLink?.id}
          onOpenAuth={onOpenAuth}
        />
      )}
    </PopoverShell>
  );
}

function QrView({ url, title, baseUrl, token, existingLinkId, onOpenAuth }: {
  url: string;
  title: string;
  baseUrl: string;
  token: string | null;
  existingLinkId?: number;
  onOpenAuth: () => void;
}) {
  const [saved, setSaved] = useState(false);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  /** Real Sayzio-styled QR preview URL returned after saving to Sayzio. */
  const [sayzioQrUrl, setSayzioQrUrl] = useState<string | null>(null);
  const fallbackImgSrc = quickQrImageUrl(url, 220);
  const imgSrc = sayzioQrUrl ?? fallbackImgSrc;

  const handleSaveToSayzio = useCallback(async () => {
    if (!token) { onOpenAuth(); return; }
    const client = new ApiClient({ baseUrl, token });
    setSaving(true);
    setSaveError(null);
    try {
      const res = await client.createQrCode({
        name: title || url,
        type: 'url',
        link_id: existingLinkId ?? null,
        payload: existingLinkId ? {} : { url },
      });
      setSaved(true);
      // Use the real Sayzio-rendered QR image (styled, branded) instead of the
      // generic third-party fallback used for preview.
      if (res.qr_code.preview_url) {
        setSayzioQrUrl(res.qr_code.preview_url);
      }
    } catch (err) {
      setSaveError(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  }, [token, baseUrl, title, url, existingLinkId, onOpenAuth]);

  return (
    <div style={{ padding: 14, textAlign: 'center' }}>
      <img
        src={imgSrc}
        alt="QR code"
        width={220}
        height={220}
        style={{ borderRadius: 8, display: 'block', margin: '0 auto 12px' }}
      />
      {saved && sayzioQrUrl && (
        <p style={{ fontSize: 10, color: 'var(--color-text-muted)', marginBottom: 4 }}>
          ✓ Sayzio QR — styled with your branding
        </p>
      )}
      <p style={{ fontSize: 11, color: 'var(--color-text-muted)', marginBottom: 10, wordBreak: 'break-all' }}>
        {url}
      </p>
      <div style={{ display: 'flex', gap: 8 }}>
        <a
          href={imgSrc}
          download={`qr-${Date.now()}.${sayzioQrUrl ? 'png' : 'png'}`}
          style={{ ...secondaryBtn, flex: 1, textDecoration: 'none', textAlign: 'center' }}
        >⬇ Download</a>
        {token ? (
          <button
            onClick={() => void handleSaveToSayzio()}
            disabled={saving || saved}
            style={{ ...primaryBtn, flex: 1, opacity: saving || saved ? 0.7 : 1 }}
          >{saved ? '✓ Saved' : saving ? 'Saving…' : '💾 Save to Sayzio'}</button>
        ) : (
          <button onClick={onOpenAuth} style={{ ...secondaryBtn, flex: 1 }}>Sign in to save</button>
        )}
      </div>
      {saveError && (
        <p style={{ fontSize: 11, color: 'var(--color-danger, #ef4444)', marginTop: 6 }}>{saveError}</p>
      )}
    </div>
  );
}

// ── Popover shell ─────────────────────────────────────────────────────────────

import { forwardRef } from 'react';

const PopoverShell = forwardRef<HTMLDivElement, { children: React.ReactNode; onClose: () => void }>(
  ({ children }, ref) => (
    <div
      ref={ref}
      style={{
        position: 'absolute',
        top: 'calc(var(--chrome-height) + 4px)',
        left: '50%',
        transform: 'translateX(-50%)',
        width: 340,
        background: 'var(--color-bg-surface)',
        border: '1px solid var(--color-border)',
        borderRadius: 12,
        boxShadow: '0 8px 32px rgba(0,0,0,0.28)',
        zIndex: 1000,
        overflow: 'hidden',
      }}
    >
      {children}
    </div>
  )
);
PopoverShell.displayName = 'PopoverShell';

// ── Micro styles ──────────────────────────────────────────────────────────────

const inputStyle: React.CSSProperties = {
  width: '100%',
  height: 30,
  borderRadius: 8,
  border: '1px solid var(--color-border)',
  background: 'var(--color-bg)',
  color: 'var(--color-text)',
  padding: '0 10px',
  fontSize: 12,
  outline: 'none',
  boxSizing: 'border-box',
};

const primaryBtn: React.CSSProperties = {
  padding: '6px 14px',
  borderRadius: 8,
  background: 'var(--color-primary)',
  color: '#fff',
  fontSize: 12,
  fontWeight: 600,
  cursor: 'pointer',
  whiteSpace: 'nowrap',
};

const secondaryBtn: React.CSSProperties = {
  padding: '6px 14px',
  borderRadius: 8,
  background: 'var(--color-bg-elevated)',
  color: 'var(--color-text)',
  border: '1px solid var(--color-border)',
  fontSize: 12,
  cursor: 'pointer',
  whiteSpace: 'nowrap',
};

const labelStyle: React.CSSProperties = {
  display: 'block',
  fontSize: 10,
  fontWeight: 600,
  color: 'var(--color-text-muted)',
  textTransform: 'uppercase',
  letterSpacing: '0.05em',
  marginBottom: 4,
};
