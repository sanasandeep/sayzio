/**
 * AddToBiolinkModal — let the user pick one of their biolinks and add
 * the current page as a link block.
 *
 * This component is shown both from the context-menu IPC event
 * ('biolink:add-page') and as a quick action in the Zio panel.
 */
import { useState, useEffect, useCallback, useRef } from 'react';
import { ApiClient } from '../../shared/api-client';
import type { ApiLink } from '../../shared/api-client';

interface Props {
  pageUrl: string;
  pageTitle: string;
  baseUrl: string;
  token: string;
  onClose: () => void;
}

type Status = 'idle' | 'loading' | 'success' | 'error';

export function AddToBiolinkModal({ pageUrl, pageTitle, baseUrl, token, onClose }: Props) {
  const backdropRef = useRef<HTMLDivElement>(null);
  const [biolinks, setBiolinks] = useState<ApiLink[]>([]);
  const [loadingBiolinks, setLoadingBiolinks] = useState(true);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [label, setLabel] = useState(pageTitle || pageUrl);
  const [status, setStatus] = useState<Status>('idle');
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [addedLink, setAddedLink] = useState<ApiLink | null>(null);

  const client = new ApiClient({ baseUrl, token });

  useEffect(() => {
    void client.listBiolinks().then(res => {
      setBiolinks(res.items);
      if (res.items[0]) setSelectedId(res.items[0].id);
      setLoadingBiolinks(false);
    }).catch(() => setLoadingBiolinks(false));
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleAdd = useCallback(async () => {
    if (!selectedId) return;
    setStatus('loading');
    setErrorMsg(null);
    try {
      await client.addBiolinkBlock(selectedId, {
        type: 'link',
        settings: {
          url: pageUrl,
          title: label,
          label,
        },
      });
      const biolink = biolinks.find(b => b.id === selectedId) ?? null;
      setAddedLink(biolink);
      setStatus('success');
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to add block';
      setErrorMsg(msg);
      setStatus('error');
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedId, label, pageUrl, biolinks]);

  const handleOpenEditor = useCallback(() => {
    if (!addedLink) return;
    const editorUrl = `${baseUrl}/user/biolinks/${addedLink.id}/edit`;
    void window.zio.shell.openExternal(editorUrl);
    onClose();
  }, [addedLink, baseUrl, onClose]);

  return (
    <div
      ref={backdropRef}
      onClick={e => { if (e.target === backdropRef.current) onClose(); }}
      style={{
        position: 'fixed',
        inset: 0,
        background: 'rgba(0,0,0,0.5)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 2000,
      }}
    >
      <div style={{
        width: 380,
        background: 'var(--color-bg-surface)',
        border: '1px solid var(--color-border)',
        borderRadius: 14,
        boxShadow: '0 12px 48px rgba(0,0,0,0.35)',
        overflow: 'hidden',
      }}>
        {/* Header */}
        <div style={{
          padding: '14px 16px',
          borderBottom: '1px solid var(--color-border)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
        }}>
          <span style={{ fontWeight: 700, fontSize: 14 }}>Add to my biolink</span>
          <button onClick={onClose} style={{ opacity: 0.6, fontSize: 16 }}>✕</button>
        </div>

        <div style={{ padding: 16 }}>
          {status === 'success' ? (
            <div style={{ textAlign: 'center' }}>
              <div style={{ fontSize: 32, marginBottom: 10 }}>✅</div>
              <p style={{ fontWeight: 600, marginBottom: 6 }}>Added to your biolink!</p>
              <p style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 16 }}>
                "{label}" has been added to{' '}
                <strong>{addedLink?.title ?? addedLink?.alias ?? 'your biolink'}</strong>.
              </p>
              <div style={{ display: 'flex', gap: 8 }}>
                <button onClick={onClose} style={{ ...secondaryBtn, flex: 1 }}>Done</button>
                <button onClick={handleOpenEditor} style={{ ...primaryBtn, flex: 1 }}>
                  Open editor →
                </button>
              </div>
            </div>
          ) : (
            <>
              {loadingBiolinks ? (
                <p style={{ color: 'var(--color-text-muted)', fontSize: 13, textAlign: 'center' }}>
                  Loading your biolinks…
                </p>
              ) : biolinks.length === 0 ? (
                <div style={{ textAlign: 'center' }}>
                  <p style={{ fontSize: 13, color: 'var(--color-text-muted)', marginBottom: 12 }}>
                    You don't have any biolinks yet.
                  </p>
                  <button
                    onClick={() => void window.zio.shell.openExternal(`${baseUrl}/user/links/new?type=biolink`)}
                    style={primaryBtn}
                  >Create a biolink</button>
                </div>
              ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                  {/* Destination URL */}
                  <div>
                    <label style={labelStyle}>Page URL</label>
                    <input
                      readOnly
                      value={pageUrl}
                      style={{ ...inputStyle, opacity: 0.7 }}
                    />
                  </div>

                  {/* Label */}
                  <div>
                    <label style={labelStyle}>Link label</label>
                    <input
                      value={label}
                      onChange={e => setLabel(e.target.value)}
                      placeholder="Enter a label for this link"
                      style={inputStyle}
                    />
                  </div>

                  {/* Biolink picker */}
                  <div>
                    <label style={labelStyle}>Add to biolink</label>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 6, maxHeight: 180, overflowY: 'auto' }}>
                      {biolinks.map(b => (
                        <button
                          key={b.id}
                          onClick={() => setSelectedId(b.id)}
                          style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            padding: '8px 10px',
                            borderRadius: 8,
                            border: '1px solid',
                            borderColor: selectedId === b.id ? 'var(--color-primary)' : 'var(--color-border)',
                            background: selectedId === b.id ? 'color-mix(in srgb, var(--color-primary) 10%, transparent)' : 'var(--color-bg-elevated)',
                            textAlign: 'left',
                            cursor: 'pointer',
                          }}
                        >
                          <span style={{ fontSize: 14 }}>🔗</span>
                          <div style={{ flex: 1, minWidth: 0 }}>
                            <div style={{ fontSize: 13, fontWeight: 500, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                              {b.title ?? b.alias}
                            </div>
                            <div style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>
                              /{b.alias}
                            </div>
                          </div>
                          {selectedId === b.id && <span style={{ color: 'var(--color-primary)', fontSize: 14 }}>✓</span>}
                        </button>
                      ))}
                    </div>
                  </div>

                  {errorMsg && (
                    <p style={{ fontSize: 11, color: 'var(--color-danger, #ef4444)' }}>{errorMsg}</p>
                  )}

                  <button
                    onClick={() => void handleAdd()}
                    disabled={!selectedId || status === 'loading' || !label.trim()}
                    style={{
                      ...primaryBtn,
                      width: '100%',
                      opacity: (!selectedId || status === 'loading' || !label.trim()) ? 0.5 : 1,
                    }}
                  >{status === 'loading' ? 'Adding…' : '+ Add to biolink'}</button>
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}

// ── Micro styles ──────────────────────────────────────────────────────────────

const inputStyle: React.CSSProperties = {
  width: '100%',
  height: 32,
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
  padding: '8px 14px',
  borderRadius: 8,
  background: 'var(--gradient-primary)',
  color: '#fff',
  fontSize: 13,
  fontWeight: 600,
  cursor: 'pointer',
};

const secondaryBtn: React.CSSProperties = {
  padding: '8px 14px',
  borderRadius: 8,
  background: 'var(--color-bg-elevated)',
  color: 'var(--color-text)',
  border: '1px solid var(--color-border)',
  fontSize: 13,
  cursor: 'pointer',
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
