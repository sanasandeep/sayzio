/**
 * FilesPane — the "My Files" tab pane: lists the user's Sayzio Files vault
 * (name / size / type / date) with open, copy-share-link, delete and refresh
 * actions. The ENTIRE pane surface is a drag-and-drop target (Dropbox-style):
 * dropping files anywhere shows a full-surface overlay and uploads them to
 * Sayzio Files with per-file progress and quota messaging.
 *
 * Renderer-drawn like the Ask Zio pane — the main process attaches no native
 * view for the 'files' pane, so this component fills the reserved area.
 */
import { useState, useCallback, useRef, useEffect } from 'react';
import { useAuthStore } from '../store/auth-store';
import { ApiClient, ApiClientError } from '../../shared/api-client';
import type { ApiFile } from '../../shared/api-client';

const BASE_URL = 'https://sayzio.app';

interface Props {
  /** Prompt the user to sign in (opens the auth modal). */
  onOpenAuth: () => void;
}

type UploadStatus = 'uploading' | 'done' | 'error';

interface UploadEntry {
  key: string;
  name: string;
  size: number;
  status: UploadStatus;
  message?: string;
}

const TYPE_ICONS: Record<string, string> = {
  image: '🖼️',
  video: '🎬',
  audio: '🎵',
  document: '📄',
};

function fileIcon(f: ApiFile): string {
  return TYPE_ICONS[f.type ?? ''] ?? '📄';
}

function formatSize(bytes: number): string {
  if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${bytes} B`;
}

function formatDate(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export function FilesPane({ onOpenAuth }: Props) {
  const { token, user } = useAuthStore();
  const [files, setFiles] = useState<ApiFile[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [copiedId, setCopiedId] = useState<number | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [uploads, setUploads] = useState<UploadEntry[]>([]);
  const [quotaMessage, setQuotaMessage] = useState<string | null>(null);
  const [isDragging, setIsDragging] = useState(false);
  const dragDepthRef = useRef(0);
  const uploadingRef = useRef(false);
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;
    return () => { mountedRef.current = false; };
  }, []);

  const getClient = useCallback((): ApiClient | null => {
    if (!token) return null;
    return new ApiClient({ baseUrl: BASE_URL, token });
  }, [token]);

  const loadFiles = useCallback(async () => {
    const client = getClient();
    if (!client) return;
    setLoading(true);
    setError(null);
    try {
      const page = await client.listFiles({ per_page: 100 });
      if (mountedRef.current) setFiles(page.files);
    } catch (err) {
      if (mountedRef.current) {
        setError(err instanceof Error ? err.message : 'Could not load your files');
      }
    } finally {
      if (mountedRef.current) setLoading(false);
    }
  }, [getClient]);

  useEffect(() => {
    if (token) void loadFiles();
    else setFiles([]);
  }, [token, loadFiles]);

  // ── Per-file actions ────────────────────────────────────────────────────────

  const handleOpen = useCallback((f: ApiFile) => {
    if (f.url) void window.zio.tabs.create(f.url);
  }, []);

  const handleCopy = useCallback(async (f: ApiFile) => {
    if (!f.url) return;
    try {
      await navigator.clipboard.writeText(f.url);
      setCopiedId(f.id);
      window.setTimeout(() => {
        if (mountedRef.current) setCopiedId(prev => (prev === f.id ? null : prev));
      }, 1500);
    } catch { /* clipboard unavailable */ }
  }, []);

  const handleDelete = useCallback(async (f: ApiFile) => {
    const client = getClient();
    if (!client || deletingId !== null) return;
    if (!window.confirm(`Delete "${f.original_name}" from your Sayzio Files? This can't be undone.`)) return;
    setDeletingId(f.id);
    setError(null);
    try {
      await client.deleteFile(f.id);
      if (mountedRef.current) setFiles(prev => prev.filter(x => x.id !== f.id));
    } catch (err) {
      if (mountedRef.current) {
        setError(err instanceof Error ? err.message : 'Could not delete the file');
      }
    } finally {
      if (mountedRef.current) setDeletingId(null);
    }
  }, [getClient, deletingId]);

  // ── Drag & drop uploads ─────────────────────────────────────────────────────

  const handleDroppedFiles = useCallback(async (dropped: File[]) => {
    if (dropped.length === 0 || uploadingRef.current) return;
    const client = getClient();
    if (!client) {
      onOpenAuth();
      return;
    }

    uploadingRef.current = true;
    setQuotaMessage(null);
    const entries: UploadEntry[] = dropped.map((f, i) => ({
      key: `${Date.now()}-${i}-${f.name}`,
      name: f.name,
      size: f.size,
      status: 'uploading',
    }));
    setUploads(entries);

    let quotaHit = false;
    let anyUploaded = false;
    try {
      for (let i = 0; i < dropped.length; i++) {
        const file = dropped[i]!;
        const key = entries[i]!.key;
        try {
          await client.uploadFile(file, file.name);
          anyUploaded = true;
          if (mountedRef.current) {
            setUploads(prev => prev.map(u => u.key === key ? { ...u, status: 'done' } : u));
          }
        } catch (err) {
          let message = err instanceof Error ? err.message : 'Upload failed';
          if (err instanceof ApiClientError &&
            (err.code === 'quota_exceeded' || err.code === 'plan_limit_reached' || err.status === 413 || err.status === 402)) {
            quotaHit = true;
            message = 'Storage limit reached';
          }
          if (mountedRef.current) {
            setUploads(prev => prev.map(u => u.key === key ? { ...u, status: 'error', message } : u));
          }
        }
      }
    } finally {
      uploadingRef.current = false;
    }

    if (quotaHit && mountedRef.current) {
      setQuotaMessage('Your file storage is full. Free up space by deleting files, or upgrade your plan for more storage.');
    }
    if (anyUploaded) await loadFiles();
    // Keep the results visible briefly, then clear the finished list.
    window.setTimeout(() => {
      if (mountedRef.current && !uploadingRef.current) {
        setUploads(prev => prev.some(u => u.status === 'error') ? prev : []);
      }
    }, 4000);
  }, [getClient, onOpenAuth, loadFiles]);

  const dragHandlers = {
    onDragEnter: (e: React.DragEvent) => {
      if (!e.dataTransfer.types.includes('Files')) return;
      e.preventDefault();
      dragDepthRef.current += 1;
      setIsDragging(true);
    },
    onDragOver: (e: React.DragEvent) => {
      if (!e.dataTransfer.types.includes('Files')) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'copy';
    },
    onDragLeave: (e: React.DragEvent) => {
      if (!e.dataTransfer.types.includes('Files')) return;
      dragDepthRef.current = Math.max(0, dragDepthRef.current - 1);
      if (dragDepthRef.current === 0) setIsDragging(false);
    },
    onDrop: (e: React.DragEvent) => {
      if (!e.dataTransfer.types.includes('Files')) return;
      e.preventDefault();
      dragDepthRef.current = 0;
      setIsDragging(false);
      void handleDroppedFiles(Array.from(e.dataTransfer.files));
    },
  };

  // ── Render ──────────────────────────────────────────────────────────────────

  const actionButtonStyle: React.CSSProperties = {
    padding: '4px 8px',
    borderRadius: 6,
    border: '1px solid var(--color-border)',
    background: 'transparent',
    color: 'var(--color-text-muted)',
    fontSize: 11,
    cursor: 'pointer',
    whiteSpace: 'nowrap',
  };

  if (!token || !user) {
    return (
      <div
        onDragEnter={(e: React.DragEvent) => { if (e.dataTransfer.types.includes('Files')) e.preventDefault(); }}
        onDragOver={(e: React.DragEvent) => {
          if (!e.dataTransfer.types.includes('Files')) return;
          e.preventDefault();
          e.dataTransfer.dropEffect = 'none';
        }}
        onDrop={(e: React.DragEvent) => {
          if (!e.dataTransfer.types.includes('Files')) return;
          e.preventDefault();
          onOpenAuth();
        }}
        style={{
        flex: 1,
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: 12,
        background: 'var(--color-bg-base)',
        color: 'var(--color-text)',
        padding: 24,
        textAlign: 'center',
      }}>
        <span style={{ fontSize: 40 }}>📁</span>
        <div style={{ fontSize: 16, fontWeight: 600 }}>My Files</div>
        <div style={{ fontSize: 13, color: 'var(--color-text-muted)', maxWidth: 320 }}>
          Sign in to your Sayzio account to see your files and drop new ones here to upload.
        </div>
        <button
          onClick={onOpenAuth}
          style={{
            marginTop: 4,
            padding: '8px 20px',
            borderRadius: 8,
            border: 'none',
            background: 'var(--color-primary, #6366f1)',
            color: '#fff',
            fontSize: 13,
            fontWeight: 600,
            cursor: 'pointer',
          }}
        >
          Sign in
        </button>
      </div>
    );
  }

  return (
    <div
      {...dragHandlers}
      style={{
        flex: 1,
        display: 'flex',
        flexDirection: 'column',
        overflow: 'hidden',
        position: 'relative',
        background: 'var(--color-bg-base)',
        color: 'var(--color-text)',
        minWidth: 0,
      }}
    >
      {/* Header */}
      <div style={{
        display: 'flex',
        alignItems: 'center',
        gap: 8,
        padding: '10px 14px',
        borderBottom: '1px solid var(--color-border)',
        flexShrink: 0,
      }}>
        <span style={{ fontSize: 16 }}>📁</span>
        <span style={{ fontSize: 14, fontWeight: 600, flex: 1 }}>My Files</span>
        <span style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>
          {loading ? 'Loading…' : `${files.length} file${files.length === 1 ? '' : 's'}`}
        </span>
        <button
          onClick={() => void loadFiles()}
          disabled={loading}
          title="Refresh"
          style={{ ...actionButtonStyle, opacity: loading ? 0.5 : 1 }}
        >
          ⟳ Refresh
        </button>
      </div>

      {/* Upload progress strip */}
      {uploads.length > 0 && (
        <div style={{
          borderBottom: '1px solid var(--color-border)',
          padding: '8px 14px',
          display: 'flex',
          flexDirection: 'column',
          gap: 4,
          flexShrink: 0,
          maxHeight: 140,
          overflowY: 'auto',
        }}>
          {uploads.map(u => (
            <div key={u.key} style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12 }}>
              <span style={{ flexShrink: 0 }}>
                {u.status === 'uploading' ? '⏳' : u.status === 'done' ? '✅' : '⚠️'}
              </span>
              <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', flex: 1 }} title={u.name}>
                {u.name}
              </span>
              <span style={{ color: 'var(--color-text-muted)', flexShrink: 0 }}>
                {u.status === 'uploading' ? `uploading… (${formatSize(u.size)})`
                  : u.status === 'done' ? 'uploaded'
                    : (u.message ?? 'failed')}
              </span>
            </div>
          ))}
        </div>
      )}

      {quotaMessage && (
        <div style={{
          padding: '8px 14px',
          fontSize: 12,
          background: 'rgba(245, 158, 11, 0.12)',
          color: 'var(--color-text)',
          borderBottom: '1px solid var(--color-border)',
          flexShrink: 0,
        }}>
          {quotaMessage}
        </div>
      )}

      {error && (
        <div style={{
          padding: '8px 14px',
          fontSize: 12,
          background: 'rgba(239, 68, 68, 0.12)',
          color: 'var(--color-text)',
          borderBottom: '1px solid var(--color-border)',
          flexShrink: 0,
        }}>
          {error}
        </div>
      )}

      {/* File list */}
      <div style={{ flex: 1, overflowY: 'auto' }}>
        {loading && files.length === 0 ? (
          <div style={{ padding: 24, fontSize: 13, color: 'var(--color-text-muted)', textAlign: 'center' }}>
            Loading your files…
          </div>
        ) : files.length === 0 ? (
          <div style={{
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            gap: 8,
            padding: '48px 24px',
            textAlign: 'center',
            color: 'var(--color-text-muted)',
          }}>
            <span style={{ fontSize: 36 }}>📂</span>
            <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--color-text)' }}>No files yet</div>
            <div style={{ fontSize: 12, maxWidth: 300 }}>
              Drop files anywhere on this pane to upload them to your Sayzio Files.
            </div>
          </div>
        ) : (
          files.map(f => (
            <div
              key={f.id}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 10,
                padding: '9px 14px',
                borderBottom: '1px solid var(--color-border)',
                opacity: deletingId === f.id ? 0.5 : 1,
              }}
            >
              <span style={{ fontSize: 18, flexShrink: 0 }}>{fileIcon(f)}</span>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div
                  style={{ fontSize: 13, fontWeight: 500, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}
                  title={f.original_name}
                >
                  {f.original_name}
                </div>
                <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 1 }}>
                  {[f.size_human, f.type, formatDate(f.created_at)].filter(Boolean).join(' · ')}
                </div>
              </div>
              <div style={{ display: 'flex', gap: 6, flexShrink: 0 }}>
                <button onClick={() => handleOpen(f)} title="Open in a new tab" style={actionButtonStyle}>
                  Open
                </button>
                <button onClick={() => void handleCopy(f)} title="Copy share link" style={actionButtonStyle}>
                  {copiedId === f.id ? 'Copied ✓' : 'Copy link'}
                </button>
                <button
                  onClick={() => void handleDelete(f)}
                  disabled={deletingId !== null}
                  title="Delete file"
                  style={{ ...actionButtonStyle, color: '#ef4444', borderColor: 'rgba(239,68,68,0.4)' }}
                >
                  Delete
                </button>
              </div>
            </div>
          ))
        )}
      </div>

      {/* Full-surface drop overlay */}
      {isDragging && (
        <div style={{
          position: 'absolute',
          inset: 0,
          background: 'rgba(99, 102, 241, 0.12)',
          border: '2px dashed var(--color-primary, #6366f1)',
          borderRadius: 8,
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          gap: 8,
          zIndex: 20,
          pointerEvents: 'none',
        }}>
          <span style={{ fontSize: 36 }}>📥</span>
          <div style={{ fontSize: 15, fontWeight: 600 }}>Drop to upload</div>
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>
            Files will be saved to your Sayzio Files
          </div>
        </div>
      )}
    </div>
  );
}
