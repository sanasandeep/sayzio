/**
 * ScreenshotSheet — Preview sheet shown after a page screenshot is taken.
 *
 * Actions available:
 *  - Save to disk (native save dialog via main process)
 *  - Copy to clipboard (native image via main process)
 *  - Upload to Sayzio Files (multipart upload via ApiClient)
 *  - Create share link + QR (feeds the uploaded file URL into the existing
 *    ShortenPopover flow once the file URL is known)
 *
 * The sheet is a centered modal overlay.  It handles its own loading/error
 * states and requires no parent state beyond `dataUrl`, `pageTitle`, and the
 * two close/auth callbacks.
 */
import { useState, useCallback, useEffect, useRef } from 'react';
import { useAuthStore } from '../store/auth-store';
import { ApiClient } from '../../shared/api-client';
import type { ApiFile } from '../../shared/api-client';
import { quickQrImageUrl, suggestAlias } from '../../shared/link-tools';

const BASE_URL = 'https://1in.me';

export interface ScreenshotSheetProps {
  dataUrl: string;
  pageTitle: string;
  pageUrl: string;
  onClose: () => void;
  onOpenAuth: () => void;
}

type UploadState = 'idle' | 'uploading' | 'done' | 'error';
type LinkState = 'idle' | 'creating' | 'done' | 'error';

export function ScreenshotSheet({ dataUrl, pageTitle, pageUrl, onClose, onOpenAuth }: ScreenshotSheetProps) {
  const { token } = useAuthStore();
  const overlayRef = useRef<HTMLDivElement>(null);

  const [saveState, setSaveState] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
  const [copyState, setCopyState] = useState<'idle' | 'copied' | 'error'>('idle');
  const [uploadState, setUploadState] = useState<UploadState>('idle');
  const [uploadError, setUploadError] = useState<string | null>(null);
  const [uploadedFile, setUploadedFile] = useState<ApiFile | null>(null);
  const [linkState, setLinkState] = useState<LinkState>('idle');
  const [linkError, setLinkError] = useState<string | null>(null);
  const [shortUrl, setShortUrl] = useState<string | null>(null);
  const [linkCopied, setLinkCopied] = useState(false);

  // Close on Escape key
  useEffect(() => {
    const handler = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [onClose]);

  const suggestedFilename = `screenshot-${slugify(pageTitle || 'page')}-${Date.now()}.png`;

  const handleSaveToDisk = useCallback(async () => {
    setSaveState('saving');
    try {
      const filePath = await window.zio.screenshot.saveToDisk(dataUrl, suggestedFilename);
      setSaveState(filePath ? 'saved' : 'idle');
    } catch {
      setSaveState('error');
    }
  }, [dataUrl, suggestedFilename]);

  const handleCopyToClipboard = useCallback(async () => {
    const ok = await window.zio.screenshot.copyToClipboard(dataUrl);
    setCopyState(ok ? 'copied' : 'error');
    if (ok) setTimeout(() => setCopyState('idle'), 2200);
  }, [dataUrl]);

  const handleUpload = useCallback(async () => {
    if (!token) { onOpenAuth(); return; }
    setUploadState('uploading');
    setUploadError(null);
    try {
      const client = new ApiClient({ baseUrl: BASE_URL, token });
      const file = await client.uploadScreenshot(dataUrl, suggestedFilename);
      setUploadedFile(file);
      setUploadState('done');
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Upload failed';
      setUploadError(msg);
      setUploadState('error');
    }
  }, [token, dataUrl, suggestedFilename, onOpenAuth]);

  const handleCreateShareLink = useCallback(async () => {
    if (!uploadedFile || !token) return;
    setLinkState('creating');
    setLinkError(null);
    try {
      const client = new ApiClient({ baseUrl: BASE_URL, token });
      const alias = suggestAlias(pageTitle || 'screenshot');
      const res = await client.createLink({
        type: 'short',
        long_url: uploadedFile.url,
        title: pageTitle ? `Screenshot: ${pageTitle}` : 'Screenshot',
        alias: alias || undefined,
      });
      setShortUrl(res.link.short_url);
      setLinkState('done');
    } catch (err) {
      setLinkError(err instanceof Error ? err.message : 'Failed to create link');
      setLinkState('error');
    }
  }, [uploadedFile, token, pageTitle]);

  const handleCopyLink = useCallback(async () => {
    if (!shortUrl) return;
    await window.zio.clipboard.write(shortUrl);
    setLinkCopied(true);
    setTimeout(() => setLinkCopied(false), 2000);
  }, [shortUrl]);

  return (
    <div
      ref={overlayRef}
      onClick={(e) => { if (e.target === overlayRef.current) onClose(); }}
      style={{
        position: 'fixed',
        inset: 0,
        background: 'rgba(0,0,0,0.65)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 3000,
      }}
    >
      <div style={{
        width: 520,
        maxWidth: 'calc(100vw - 40px)',
        background: 'var(--color-bg-surface)',
        border: '1px solid var(--color-border)',
        borderRadius: 14,
        boxShadow: '0 20px 60px rgba(0,0,0,0.5)',
        overflow: 'hidden',
        display: 'flex',
        flexDirection: 'column',
      }}>
        {/* Header */}
        <div style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          padding: '12px 16px',
          borderBottom: '1px solid var(--color-border)',
        }}>
          <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--color-text)' }}>
            📷 Screenshot
          </span>
          <button
            onClick={onClose}
            style={{
              width: 24,
              height: 24,
              borderRadius: 6,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontSize: 13,
              color: 'var(--color-text-muted)',
              background: 'transparent',
            }}
            title="Close"
          >✕</button>
        </div>

        {/* Preview */}
        <div style={{
          background: 'repeating-conic-gradient(#888 0% 25%, transparent 0% 50%) 0 0 / 12px 12px',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          padding: 12,
          maxHeight: 280,
          overflow: 'hidden',
        }}>
          <img
            src={dataUrl}
            alt="Screenshot preview"
            style={{
              maxWidth: '100%',
              maxHeight: 260,
              objectFit: 'contain',
              borderRadius: 6,
              boxShadow: '0 4px 20px rgba(0,0,0,0.4)',
            }}
          />
        </div>

        {/* Source URL */}
        {pageUrl && pageUrl !== 'about:newtab' && (
          <div style={{ padding: '6px 16px', borderBottom: '1px solid var(--color-border)' }}>
            <p style={{
              fontSize: 11,
              color: 'var(--color-text-muted)',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
              whiteSpace: 'nowrap',
              margin: 0,
            }} title={pageUrl}>{pageUrl}</p>
          </div>
        )}

        {/* Action buttons */}
        <div style={{ padding: 16, display: 'flex', flexDirection: 'column', gap: 10 }}>

          {/* Row 1: local actions */}
          <div style={{ display: 'flex', gap: 8 }}>
            <ActionButton
              onClick={() => void handleSaveToDisk()}
              disabled={saveState === 'saving'}
              icon="💾"
              label={saveState === 'saving' ? 'Saving…' : saveState === 'saved' ? 'Saved ✓' : saveState === 'error' ? 'Save failed' : 'Save to disk'}
              variant="secondary"
              flex={1}
            />
            <ActionButton
              onClick={() => void handleCopyToClipboard()}
              icon="📋"
              label={copyState === 'copied' ? 'Copied ✓' : copyState === 'error' ? 'Copy failed' : 'Copy image'}
              variant="secondary"
              flex={1}
            />
          </div>

          {/* Row 2: upload to Sayzio */}
          {uploadState !== 'done' ? (
            <ActionButton
              onClick={() => void handleUpload()}
              disabled={uploadState === 'uploading'}
              icon="☁️"
              label={
                uploadState === 'uploading' ? 'Uploading…' :
                uploadState === 'error' ? (uploadError ?? 'Upload failed — retry') :
                !token ? 'Sign in to upload to Sayzio Files' :
                'Upload to Sayzio Files'
              }
              variant={uploadState === 'error' ? 'danger' : 'primary'}
              flex={1}
            />
          ) : (
            // Upload done — show share link section
            <div style={{
              background: 'var(--color-bg-elevated)',
              border: '1px solid var(--color-border)',
              borderRadius: 10,
              padding: 12,
              display: 'flex',
              flexDirection: 'column',
              gap: 8,
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'var(--color-text)' }}>
                <span style={{ color: '#22c55e', fontWeight: 700 }}>✓</span>
                Uploaded to Sayzio Files
              </div>

              {linkState === 'idle' && (
                <ActionButton
                  onClick={() => void handleCreateShareLink()}
                  icon="🔗"
                  label="Create share link + QR"
                  variant="primary"
                  flex={1}
                />
              )}
              {linkState === 'creating' && (
                <p style={{ fontSize: 12, color: 'var(--color-text-muted)', margin: 0 }}>Creating link…</p>
              )}
              {linkState === 'error' && (
                <p style={{ fontSize: 12, color: 'var(--color-danger, #ef4444)', margin: 0 }}>{linkError}</p>
              )}
              {linkState === 'done' && shortUrl && (
                <ShareLinkResult
                  shortUrl={shortUrl}
                  copied={linkCopied}
                  onCopy={() => void handleCopyLink()}
                />
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

// ── Share link result block ──────────────────────────────────────────────────

function ShareLinkResult({ shortUrl, copied, onCopy }: {
  shortUrl: string;
  copied: boolean;
  onCopy: () => void;
}) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
      <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
        <input
          readOnly
          value={shortUrl}
          style={{
            flex: 1,
            height: 30,
            borderRadius: 8,
            border: '1px solid var(--color-border)',
            background: 'var(--color-bg)',
            color: 'var(--color-text)',
            padding: '0 10px',
            fontSize: 12,
            outline: 'none',
            boxSizing: 'border-box',
          }}
        />
        <button
          onClick={onCopy}
          style={{
            padding: '5px 12px',
            borderRadius: 8,
            background: 'var(--color-primary)',
            color: '#fff',
            fontSize: 12,
            fontWeight: 600,
            whiteSpace: 'nowrap',
            cursor: 'pointer',
          }}
        >{copied ? '✓ Copied' : 'Copy'}</button>
      </div>
      {/* QR preview */}
      <div style={{ textAlign: 'center' }}>
        <img
          src={quickQrImageUrl(shortUrl, 140)}
          alt="QR code"
          width={140}
          height={140}
          style={{ borderRadius: 6 }}
        />
      </div>
    </div>
  );
}

// ── Action button ─────────────────────────────────────────────────────────────

interface ActionButtonProps {
  onClick: () => void;
  icon: string;
  label: string;
  variant: 'primary' | 'secondary' | 'danger';
  flex?: number;
  disabled?: boolean;
}

function ActionButton({ onClick, icon, label, variant, flex, disabled }: ActionButtonProps) {
  const bg = variant === 'primary'
    ? 'var(--color-primary)'
    : variant === 'danger'
      ? 'var(--color-danger, #ef4444)'
      : 'var(--color-bg-elevated)';
  const color = variant === 'secondary' ? 'var(--color-text)' : '#fff';

  return (
    <button
      onClick={onClick}
      disabled={disabled}
      style={{
        flex,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        gap: 6,
        padding: '8px 14px',
        borderRadius: 9,
        background: bg,
        color,
        border: variant === 'secondary' ? '1px solid var(--color-border)' : 'none',
        fontSize: 12,
        fontWeight: 600,
        cursor: disabled ? 'default' : 'pointer',
        opacity: disabled ? 0.6 : 1,
        whiteSpace: 'nowrap',
        transition: 'opacity 0.12s',
      }}
    >
      <span>{icon}</span>
      <span>{label}</span>
    </button>
  );
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function slugify(s: string): string {
  return s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40) || 'page';
}
