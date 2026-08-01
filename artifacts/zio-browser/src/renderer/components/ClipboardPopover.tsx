/**
 * ClipboardPopover — reads the clipboard, detects what's on it (web link,
 * email, phone number, plain text), and for web links offers one-click
 * short-URL creation with a custom alias. Plain text gets a one-click
 * "text page" link (the server hosts the text on a public page with a copy
 * button). Creation is AJAX-only (no tab navigation) and the new short URL
 * is auto-copied back to the clipboard.
 */
import { useState, useEffect, useCallback, useRef, forwardRef } from 'react';
import { useAuthStore } from '../store/auth-store';
import { ApiClient } from '../../shared/api-client';
import type { ApiDomain, ApiLink, AliasCheckResult } from '../../shared/api-client';
import { suggestAlias } from '../../shared/link-tools';
import { detectClipboardContent, clipboardKindLabel } from '../../shared/clipboard-content';
import type { ClipboardContent } from '../../shared/clipboard-content';

interface Props {
  baseUrl: string;
  onClose: () => void;
  onOpenAuth: () => void;
  /** Open a URL in a new tab (secondary action for detected links). */
  onOpenInNewTab?: (url: string) => void;
}

export function ClipboardPopover({ baseUrl, onClose, onOpenAuth, onOpenInNewTab }: Props) {
  const { token } = useAuthStore();
  const popoverRef = useRef<HTMLDivElement>(null);

  const [content, setContent] = useState<ClipboardContent | null>(null);
  const [alias, setAlias] = useState('');
  const [domains, setDomains] = useState<ApiDomain[]>([]);
  const [selectedDomainId, setSelectedDomainId] = useState<number | null>(null);
  const [aliasCheck, setAliasCheck] = useState<AliasCheckResult | null>(null);
  const [checkingAlias, setCheckingAlias] = useState(false);
  const [createdLink, setCreatedLink] = useState<ApiLink | null>(null);
  const [creating, setCreating] = useState(false);
  const [autoCopied, setAutoCopied] = useState(false);
  const [copied, setCopied] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const getClient = useCallback((): ApiClient | null => {
    if (!token) return null;
    return new ApiClient({ baseUrl, token });
  }, [token, baseUrl]);

  // Read the clipboard once on open and classify it.
  useEffect(() => {
    let cancelled = false;
    void window.zio.clipboard.read().then((text) => {
      if (cancelled) return;
      const detected = detectClipboardContent(text ?? '');
      setContent(detected);
      if (detected.kind === 'url' && detected.url) {
        try {
          const u = new URL(detected.url);
          setAlias(suggestAlias(u.hostname.replace(/^www\./, '')));
        } catch { /* keep empty alias */ }
      }
    }).catch(() => {
      if (!cancelled) setContent({ kind: 'empty', text: '' });
    });
    return () => { cancelled = true; };
  }, []);

  // Load available domains once (only useful for the URL flow).
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

  // Debounced alias availability check.
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

  // Close on outside click.
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (popoverRef.current && !popoverRef.current.contains(e.target as Node)) {
        onClose();
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [onClose]);

  const handleCreate = useCallback(async () => {
    if (!content || content.kind !== 'url' || !content.url) return;
    if (!token) { onOpenAuth(); return; }
    const client = getClient();
    if (!client) return;
    setCreating(true);
    setError(null);
    try {
      const res = await client.createLink({
        type: 'short',
        long_url: content.url,
        alias: alias || undefined,
        domain_id: selectedDomainId || undefined,
      });
      setCreatedLink(res.link);
      // Auto-copy the short URL back to the clipboard — that's the point of
      // the whole flow: copy a long link, click, paste the short one.
      if (res.link.short_url) {
        await window.zio.clipboard.write(res.link.short_url);
        setAutoCopied(true);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create short link');
    } finally {
      setCreating(false);
    }
  }, [content, token, getClient, alias, selectedDomainId, onOpenAuth]);

  // Plain text → shareable text-page link via the quick-shorten endpoint
  // (the server stores the text and hosts it on a public page with a copy
  // button). Same auto-copy behaviour as the URL flow.
  const [textPageUrl, setTextPageUrl] = useState<string | null>(null);
  const handleCreateTextPage = useCallback(async () => {
    if (!content || content.kind !== 'text' || !content.text) return;
    if (!token) { onOpenAuth(); return; }
    const client = getClient();
    if (!client) return;
    setCreating(true);
    setError(null);
    try {
      const res = await client.quickShorten(content.text);
      setTextPageUrl(res.short_url);
      await window.zio.clipboard.write(res.short_url);
      setAutoCopied(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create text page link');
    } finally {
      setCreating(false);
    }
  }, [content, token, getClient, onOpenAuth]);

  const handleCopy = useCallback(async (text: string) => {
    await window.zio.clipboard.write(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }, []);

  return (
    <Shell ref={popoverRef}>
      <div style={{ padding: '10px 14px', borderBottom: '1px solid var(--color-border)', display: 'flex', alignItems: 'center', gap: 8 }}>
        <span style={{ fontSize: 14 }}>📋</span>
        <span style={{ fontSize: 12, fontWeight: 600 }}>From your clipboard</span>
        {content && content.kind !== 'empty' && (
          <span style={{
            marginLeft: 'auto',
            fontSize: 10,
            fontWeight: 600,
            padding: '2px 8px',
            borderRadius: 999,
            background: 'var(--color-bg-elevated)',
            border: '1px solid var(--color-border)',
            color: 'var(--color-text-muted)',
            whiteSpace: 'nowrap',
          }}>{clipboardKindLabel(content.kind)}</span>
        )}
      </div>

      {/* Loading */}
      {!content && (
        <div style={{ padding: 20, textAlign: 'center', fontSize: 12, color: 'var(--color-text-muted)' }}>
          Reading clipboard…
        </div>
      )}

      {/* Empty clipboard */}
      {content?.kind === 'empty' && (
        <div style={{ padding: 20, textAlign: 'center', fontSize: 12, color: 'var(--color-text-muted)' }}>
          Your clipboard is empty. Copy a link first, then click this button to shorten it.
        </div>
      )}

      {/* Email / phone — show what was detected, no shorten action */}
      {content && (content.kind === 'email' || content.kind === 'phone') && (
        <div style={{ padding: 14 }}>
          <ClipboardPreview text={content.text} />
          <p style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 10 }}>
            {content.kind === 'email' && 'This looks like an email address. Copy a web link to create a short URL.'}
            {content.kind === 'phone' && 'This looks like a phone number. Copy a web link to create a short URL.'}
          </p>
        </div>
      )}

      {/* Plain text — one-click shareable text-page link */}
      {content?.kind === 'text' && (
        <div style={{ padding: 14 }}>
          {textPageUrl ? (
            <div>
              <p style={{ fontSize: 11, color: 'var(--color-success, #22c55e)', marginBottom: 6, fontWeight: 600 }}>
                ✓ Text page link created{autoCopied ? ' — copied to clipboard!' : '!'}
              </p>
              <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                <input
                  readOnly
                  value={textPageUrl}
                  style={{ ...inputStyle, flex: 1, background: 'var(--color-bg-elevated)', cursor: 'text' }}
                />
                <button
                  onClick={() => void handleCopy(textPageUrl)}
                  style={primaryBtn}
                >{copied ? '✓' : 'Copy'}</button>
              </div>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              <ClipboardPreview text={content.text} />
              <p style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>
                This is plain text. Turn it into a shareable page — visitors see the full text with a copy button.
              </p>
              {error && (
                <p style={{ fontSize: 11, color: 'var(--color-danger, #ef4444)' }}>{error}</p>
              )}
              {!token ? (
                <button onClick={() => { onClose(); onOpenAuth(); }} style={{ ...primaryBtn, width: '100%' }}>
                  Sign in to create a text page link
                </button>
              ) : (
                <button
                  onClick={() => void handleCreateTextPage()}
                  disabled={creating}
                  style={{ ...primaryBtn, width: '100%', opacity: creating ? 0.5 : 1 }}
                >{creating ? 'Creating…' : '📝 Create text page link & copy'}</button>
              )}
            </div>
          )}
        </div>
      )}

      {/* URL content — the shorten flow */}
      {content?.kind === 'url' && content.url && (
        <div style={{ padding: 14 }}>
          {createdLink ? (
            <div>
              <p style={{ fontSize: 11, color: 'var(--color-success, #22c55e)', marginBottom: 6, fontWeight: 600 }}>
                ✓ Short link created{autoCopied ? ' — copied to clipboard!' : '!'}
              </p>
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
              <p style={{ fontSize: 10, color: 'var(--color-text-muted)', marginTop: 8, wordBreak: 'break-all' }}>
                → {content.url}
              </p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              <ClipboardPreview text={content.url} />

              {!token ? (
                <button onClick={() => { onClose(); onOpenAuth(); }} style={{ ...primaryBtn, width: '100%' }}>
                  Sign in to shorten this link
                </button>
              ) : (
                <>
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

                  {error && (
                    <p style={{ fontSize: 11, color: 'var(--color-danger, #ef4444)' }}>{error}</p>
                  )}

                  <button
                    onClick={() => void handleCreate()}
                    disabled={creating || (!!alias && aliasCheck?.available === false)}
                    style={{ ...primaryBtn, width: '100%', opacity: creating ? 0.5 : 1 }}
                  >{creating ? 'Creating…' : '🔗 Create short URL & copy'}</button>

                  {onOpenInNewTab && (
                    <button
                      onClick={() => { onOpenInNewTab(content.url!); onClose(); }}
                      style={{ ...secondaryBtn, width: '100%' }}
                    >Open in new tab</button>
                  )}
                </>
              )}
            </div>
          )}
        </div>
      )}
    </Shell>
  );
}

/** Truncated single-box preview of the clipboard text. */
function ClipboardPreview({ text }: { text: string }) {
  const shown = text.length > 220 ? `${text.slice(0, 220)}…` : text;
  return (
    <div style={{
      fontSize: 11,
      lineHeight: 1.5,
      padding: '8px 10px',
      borderRadius: 8,
      background: 'var(--color-bg-elevated)',
      border: '1px solid var(--color-border)',
      color: 'var(--color-text)',
      wordBreak: 'break-all',
      whiteSpace: 'pre-wrap',
      maxHeight: 96,
      overflow: 'hidden',
    }}>{shown}</div>
  );
}

const Shell = forwardRef<HTMLDivElement, { children: React.ReactNode }>(
  ({ children }, ref) => (
    <div
      ref={ref}
      style={{
        position: 'absolute',
        top: 'calc(var(--chrome-height) + 4px)',
        left: '50%',
        transform: 'translateX(-50%)',
        width: 340,
        maxWidth: 'calc(100vw - 16px)',
        maxHeight: 'calc(100vh - var(--chrome-height) - 12px)',
        overflowY: 'auto',
        background: 'var(--color-bg-surface)',
        border: '1px solid var(--color-border)',
        borderRadius: 12,
        boxShadow: '0 8px 32px rgba(0,0,0,0.28)',
        zIndex: 1000,
        overflowX: 'hidden',
      }}
    >
      {children}
    </div>
  )
);
Shell.displayName = 'ClipboardPopoverShell';

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
  background: 'var(--gradient-primary)',
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
