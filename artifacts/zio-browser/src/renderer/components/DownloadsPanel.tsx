/**
 * DownloadsPanel — full downloads manager UI.
 * Shows active + completed downloads with progress, speed, and controls.
 */
import { useState, useEffect, useCallback, useRef } from 'react';
import type { Download } from '../../main/db';

// ── Types extended for live state ────────────────────────────────────────────

interface LiveProgress {
  receivedBytes: number;
  totalBytes: number | null;
  speedBps: number;
  isPaused: boolean;
}

interface DownloadEntry extends Download {
  liveProgress?: LiveProgress;
}

interface StartedPayload {
  id: string;
  url: string;
  filename: string;
  savePath: string;
  totalBytes: number | null;
  mimeType: string | null;
  isPrivate: boolean;
}

interface ProgressPayload {
  id: string;
  receivedBytes: number;
  totalBytes: number | null;
  speedBps: number;
  state: string;
  isPaused: boolean;
}

interface DonePayload {
  id: string;
  state: 'completed' | 'interrupted' | 'cancelled';
  savePath: string;
  filename: string;
  isPrivate: boolean;
}

interface Props {
  onClose: () => void;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function formatBytes(bytes: number | null): string {
  if (bytes === null || bytes < 0) return '—';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
}

function formatSpeed(bps: number): string {
  if (bps <= 0) return '';
  if (bps < 1024) return `${bps} B/s`;
  if (bps < 1024 * 1024) return `${(bps / 1024).toFixed(0)} KB/s`;
  return `${(bps / (1024 * 1024)).toFixed(1)} MB/s`;
}

function formatProgress(received: number, total: number | null): string {
  if (total && total > 0) {
    const pct = Math.round((received / total) * 100);
    return `${formatBytes(received)} / ${formatBytes(total)} (${pct}%)`;
  }
  return formatBytes(received);
}

function hostFromUrl(url: string): string {
  try {
    return new URL(url).hostname;
  } catch {
    return url.slice(0, 40);
  }
}

function stateLabel(entry: DownloadEntry): string {
  if (entry.liveProgress) {
    if (entry.liveProgress.isPaused) return 'Paused';
    return 'Downloading…';
  }
  switch (entry.state) {
    case 'completed': return 'Complete';
    case 'interrupted': return 'Failed';
    case 'cancelled': return 'Cancelled';
    case 'progressing': return 'Downloading…';
    default: return 'Pending';
  }
}

function stateColor(entry: DownloadEntry): string {
  if (entry.liveProgress?.isPaused) return '#f0a020';
  switch (entry.state) {
    case 'completed': return '#22c55e';
    case 'interrupted': return '#ef4444';
    case 'cancelled': return '#94a3b8';
    default: return 'var(--color-primary)';
  }
}

function progressPercent(entry: DownloadEntry): number {
  const received = entry.liveProgress?.receivedBytes ?? entry.received_bytes;
  const total = entry.liveProgress?.totalBytes ?? entry.total_bytes;
  if (!total || total <= 0) return 0;
  return Math.min(100, Math.round((received / total) * 100));
}

function isActive(entry: DownloadEntry): boolean {
  return !!entry.liveProgress && !['completed', 'cancelled', 'interrupted'].includes(entry.state);
}

// ── Main component ────────────────────────────────────────────────────────────

export function DownloadsPanel({ onClose }: Props) {
  const [entries, setEntries] = useState<DownloadEntry[]>([]);
  const [search, setSearch] = useState('');
  const [searching, setSearching] = useState(false);
  const searchTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Load persisted history on mount
  useEffect(() => {
    void window.zio.downloads.recent().then((rows: Download[]) => {
      setEntries(rows.map(r => ({ ...r })));
    });
  }, []);

  // Live IPC event listeners
  useEffect(() => {
    const onStarted = (...args: unknown[]) => {
      const payload = args[0] as StartedPayload;
      if (payload.isPrivate) return;
      const now = new Date().toISOString();
      const newEntry: DownloadEntry = {
        id: payload.id,
        url: payload.url,
        filename: payload.filename,
        save_path: payload.savePath,
        mime_type: payload.mimeType,
        total_bytes: payload.totalBytes,
        received_bytes: 0,
        state: 'progressing',
        created_at: now,
        completed_at: null,
        liveProgress: {
          receivedBytes: 0,
          totalBytes: payload.totalBytes,
          speedBps: 0,
          isPaused: false,
        },
      };
      setEntries(prev => [newEntry, ...prev]);
    };

    const onProgress = (...args: unknown[]) => {
      const payload = args[0] as ProgressPayload;
      setEntries(prev => prev.map(e => {
        if (e.id !== payload.id) return e;
        return {
          ...e,
          liveProgress: {
            receivedBytes: payload.receivedBytes,
            totalBytes: payload.totalBytes,
            speedBps: payload.speedBps,
            isPaused: payload.isPaused,
          },
        };
      }));
    };

    const onDone = (...args: unknown[]) => {
      const payload = args[0] as DonePayload;
      setEntries(prev => prev.map(e => {
        if (e.id !== payload.id) return e;
        return {
          ...e,
          liveProgress: undefined,
          state: payload.state,
          save_path: payload.savePath,
          completed_at: payload.state === 'completed' ? new Date().toISOString() : null,
        };
      }));
    };

    window.zio.on('download:started', onStarted);
    window.zio.on('download:progress', onProgress);
    window.zio.on('download:done', onDone);
    return () => {
      window.zio.off('download:started', onStarted);
      window.zio.off('download:progress', onProgress);
      window.zio.off('download:done', onDone);
    };
  }, []);

  // Debounced search
  useEffect(() => {
    if (searchTimeout.current) clearTimeout(searchTimeout.current);
    if (!search.trim()) {
      setSearching(false);
      void window.zio.downloads.recent().then((rows: Download[]) => {
        setEntries(prev => {
          const activeMap = new Map(prev.filter(e => e.liveProgress).map(e => [e.id, e]));
          return rows.map(r => {
            const active = activeMap.get(r.id);
            return active ?? { ...r };
          });
        });
      });
      return;
    }
    setSearching(true);
    searchTimeout.current = setTimeout(() => {
      void window.zio.downloads.search(search.trim()).then((rows: Download[]) => {
        setSearching(false);
        setEntries(prev => {
          const activeMap = new Map(prev.filter(e => e.liveProgress).map(e => [e.id, e]));
          return rows.map(r => {
            const active = activeMap.get(r.id);
            return active ?? { ...r };
          });
        });
      });
    }, 250);
    return () => {
      if (searchTimeout.current) clearTimeout(searchTimeout.current);
    };
  }, [search]);

  const handleClearAll = useCallback(async () => {
    await window.zio.downloads.clear();
    setEntries(prev => prev.filter(e => isActive(e)));
  }, []);

  const handleRemove = useCallback(async (id: string) => {
    await window.zio.downloads.remove(id);
    setEntries(prev => prev.filter(e => e.id !== id));
  }, []);

  const handlePause = useCallback((id: string) => {
    void window.zio.downloads.pause(id);
    setEntries(prev => prev.map(e => {
      if (e.id !== id || !e.liveProgress) return e;
      return { ...e, liveProgress: { ...e.liveProgress, isPaused: true } };
    }));
  }, []);

  const handleResume = useCallback((id: string) => {
    void window.zio.downloads.resume(id);
    setEntries(prev => prev.map(e => {
      if (e.id !== id || !e.liveProgress) return e;
      return { ...e, liveProgress: { ...e.liveProgress, isPaused: false } };
    }));
  }, []);

  const handleCancel = useCallback((id: string) => {
    void window.zio.downloads.cancel(id);
  }, []);

  const handleRetry = useCallback((url: string) => {
    void window.zio.downloads.retry(url);
  }, []);

  const handleOpen = useCallback(async (savePath: string) => {
    const result = await window.zio.downloads.open(savePath) as { ok: boolean; error?: string };
    if (!result.ok) {
      // file missing or cannot be opened — nothing else to do for now
    }
  }, []);

  const handleReveal = useCallback((savePath: string) => {
    void window.zio.downloads.show(savePath);
  }, []);

  const activeDownloads = entries.filter(e => isActive(e));
  const historyEntries = entries.filter(e => !isActive(e));
  const hasHistory = historyEntries.length > 0;

  const panelStyle: React.CSSProperties = {
    width: 380,
    maxHeight: 'calc(100vh - 80px)',
    background: 'var(--color-bg-surface)',
    border: '1px solid var(--color-border)',
    borderRadius: 12,
    boxShadow: '0 8px 32px rgba(0,0,0,0.24)',
    display: 'flex',
    flexDirection: 'column',
    overflow: 'hidden',
  };

  const headerStyle: React.CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: '12px 14px 10px',
    borderBottom: '1px solid var(--color-border)',
    flexShrink: 0,
  };

  const sectionLabelStyle: React.CSSProperties = {
    fontSize: 11,
    fontWeight: 600,
    color: 'var(--color-text-muted)',
    textTransform: 'uppercase',
    letterSpacing: '0.06em',
    padding: '8px 14px 4px',
  };

  const entryStyle: React.CSSProperties = {
    padding: '10px 14px',
    borderBottom: '1px solid var(--color-border)',
    display: 'flex',
    flexDirection: 'column',
    gap: 5,
  };

  const iconBtnStyle: React.CSSProperties = {
    fontSize: 13,
    padding: '2px 6px',
    borderRadius: 6,
    background: 'var(--color-bg-elevated)',
    border: '1px solid var(--color-border)',
    color: 'var(--color-text-muted)',
    cursor: 'pointer',
    lineHeight: 1,
  };

  return (
    <div style={panelStyle}>
      {/* Header */}
      <div style={headerStyle}>
        <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--color-text)' }}>
          Downloads
          {activeDownloads.length > 0 && (
            <span style={{
              marginLeft: 8,
              background: 'var(--color-primary)',
              color: '#fff',
              borderRadius: 10,
              padding: '1px 7px',
              fontSize: 11,
              fontWeight: 700,
            }}>
              {activeDownloads.length}
            </span>
          )}
        </span>
        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
          {hasHistory && (
            <button
              onClick={() => void handleClearAll()}
              style={{ ...iconBtnStyle, fontSize: 11 }}
              title="Clear completed downloads"
            >
              Clear history
            </button>
          )}
          <button onClick={onClose} style={{ ...iconBtnStyle, fontSize: 15 }} title="Close">
            ✕
          </button>
        </div>
      </div>

      {/* Search bar */}
      <div style={{ padding: '8px 14px', flexShrink: 0, borderBottom: '1px solid var(--color-border)' }}>
        <input
          value={search}
          onChange={e => setSearch(e.target.value)}
          placeholder="Search downloads…"
          style={{
            width: '100%',
            height: 28,
            borderRadius: 8,
            border: '1px solid var(--color-border)',
            background: 'var(--color-bg)',
            color: 'var(--color-text)',
            padding: '0 10px',
            fontSize: 12,
            boxSizing: 'border-box',
            outline: 'none',
          }}
        />
      </div>

      {/* Scrollable content */}
      <div style={{ flex: 1, overflowY: 'auto' }}>

        {/* Active downloads */}
        {activeDownloads.length > 0 && (
          <>
            <div style={sectionLabelStyle}>In progress</div>
            {activeDownloads.map(entry => {
              const pct = progressPercent(entry);
              const live = entry.liveProgress!;
              return (
                <div key={entry.id} style={entryStyle}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 6 }}>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{
                        fontSize: 13,
                        fontWeight: 500,
                        color: 'var(--color-text)',
                        whiteSpace: 'nowrap',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                      }}
                        title={entry.filename}
                      >
                        {entry.filename}
                      </div>
                      <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 1 }}>
                        {hostFromUrl(entry.url)}
                      </div>
                    </div>
                    <div style={{ display: 'flex', gap: 4, flexShrink: 0 }}>
                      {live.isPaused ? (
                        <button onClick={() => handleResume(entry.id)} style={iconBtnStyle} title="Resume">▶</button>
                      ) : (
                        <button onClick={() => handlePause(entry.id)} style={iconBtnStyle} title="Pause">⏸</button>
                      )}
                      <button onClick={() => handleCancel(entry.id)} style={{ ...iconBtnStyle, color: '#ef4444' }} title="Cancel">✕</button>
                    </div>
                  </div>

                  {/* Progress bar */}
                  <div style={{
                    height: 4,
                    borderRadius: 2,
                    background: 'var(--color-border)',
                    overflow: 'hidden',
                  }}>
                    <div style={{
                      height: '100%',
                      width: `${pct}%`,
                      background: live.isPaused ? '#f0a020' : 'var(--color-primary)',
                      borderRadius: 2,
                      transition: 'width 0.3s ease',
                    }} />
                  </div>

                  {/* Progress numbers + speed */}
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 11, color: 'var(--color-text-muted)' }}>
                    <span>{formatProgress(live.receivedBytes, live.totalBytes)}</span>
                    <span style={{ color: live.isPaused ? '#f0a020' : 'var(--color-text-muted)' }}>
                      {live.isPaused ? 'Paused' : (live.speedBps > 0 ? formatSpeed(live.speedBps) : '…')}
                    </span>
                  </div>
                </div>
              );
            })}
          </>
        )}

        {/* History */}
        {!searching && historyEntries.length > 0 && (
          <>
            {activeDownloads.length > 0 && <div style={sectionLabelStyle}>History</div>}
            {historyEntries.map(entry => {
              const isComplete = entry.state === 'completed';
              const isFailed = entry.state === 'interrupted';
              const isCancelled = entry.state === 'cancelled';
              return (
                <div key={entry.id} style={entryStyle}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 6 }}>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{
                        fontSize: 13,
                        fontWeight: 500,
                        color: 'var(--color-text)',
                        whiteSpace: 'nowrap',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                      }}
                        title={entry.filename}
                      >
                        {entry.filename}
                      </div>
                      <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 1 }}>
                        {hostFromUrl(entry.url)}
                      </div>
                    </div>
                    <button
                      onClick={() => void handleRemove(entry.id)}
                      style={{ ...iconBtnStyle, flexShrink: 0 }}
                      title="Remove from list"
                    >✕</button>
                  </div>

                  {/* Completed: full progress bar */}
                  {isComplete && (
                    <div style={{ height: 3, borderRadius: 2, background: '#22c55e', opacity: 0.5 }} />
                  )}
                  {/* Failed: partial + red */}
                  {isFailed && entry.total_bytes && entry.total_bytes > 0 && (
                    <div style={{ height: 3, borderRadius: 2, background: 'var(--color-border)', overflow: 'hidden' }}>
                      <div style={{
                        height: '100%',
                        width: `${progressPercent(entry)}%`,
                        background: '#ef4444',
                        borderRadius: 2,
                      }} />
                    </div>
                  )}

                  {/* Status + size + controls row */}
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: 11 }}>
                    <span style={{ color: stateColor(entry) }}>{stateLabel(entry)}</span>
                    <div style={{ display: 'flex', gap: 4, alignItems: 'center' }}>
                      {isComplete && (
                        <span style={{ color: 'var(--color-text-muted)', marginRight: 4 }}>
                          {formatBytes(entry.total_bytes)}
                        </span>
                      )}
                      {(isFailed || isCancelled) && (
                        <button
                          onClick={() => handleRetry(entry.url)}
                          style={{ ...iconBtnStyle, fontSize: 11 }}
                          title="Retry download"
                        >↺ Retry</button>
                      )}
                      {isComplete && entry.save_path && (
                        <>
                          <button
                            onClick={() => void handleOpen(entry.save_path!)}
                            style={iconBtnStyle}
                            title="Open file"
                          >Open</button>
                          <button
                            onClick={() => handleReveal(entry.save_path!)}
                            style={iconBtnStyle}
                            title="Show in folder"
                          >📂</button>
                        </>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </>
        )}

        {/* Empty / searching states */}
        {searching && (
          <div style={{ padding: '24px 14px', textAlign: 'center', color: 'var(--color-text-muted)', fontSize: 13 }}>
            Searching…
          </div>
        )}
        {!searching && entries.length === 0 && !search && (
          <div style={{ padding: '32px 14px', textAlign: 'center', color: 'var(--color-text-muted)', fontSize: 13 }}>
            <div style={{ fontSize: 28, marginBottom: 8, opacity: 0.4 }}>⬇</div>
            No downloads yet
          </div>
        )}
        {!searching && entries.length === 0 && search && (
          <div style={{ padding: '24px 14px', textAlign: 'center', color: 'var(--color-text-muted)', fontSize: 13 }}>
            No downloads match &ldquo;{search}&rdquo;
          </div>
        )}
      </div>
    </div>
  );
}
