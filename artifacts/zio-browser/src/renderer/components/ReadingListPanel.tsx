/**
 * ReadingListPanel — sidebar panel showing saved reading-list entries.
 * Supports unread / read sections, mark-read toggle, open, and delete.
 */
import { useState, useEffect, useCallback } from 'react';

interface ReadingListEntry {
  id: string;
  url: string;
  title: string;
  favicon_url: string | null;
  is_read: boolean;
  saved_at: string;
}

interface Props {
  onClose: () => void;
  onNavigate: (url: string) => void;
}

export function ReadingListPanel({ onClose, onNavigate }: Props) {
  const [entries, setEntries] = useState<ReadingListEntry[]>([]);
  const [loading, setLoading] = useState(true);

  const loadEntries = useCallback(async () => {
    try {
      const all = (await window.zio.readingList.all()) as ReadingListEntry[];
      setEntries(all);
    } catch {
      setEntries([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadEntries();
  }, [loadEntries]);

  const handleOpen = useCallback(async (entry: ReadingListEntry) => {
    if (!entry.is_read) {
      try {
        await window.zio.readingList.markRead(entry.id, true);
        setEntries(prev => prev.map(e => e.id === entry.id ? { ...e, is_read: true } : e));
      } catch { /* non-fatal */ }
    }
    onNavigate(entry.url);
    onClose();
  }, [onClose, onNavigate]);

  const handleToggleRead = useCallback(async (e: React.MouseEvent, entry: ReadingListEntry) => {
    e.stopPropagation();
    const newIsRead = !entry.is_read;
    try {
      await window.zio.readingList.markRead(entry.id, newIsRead);
      setEntries(prev => prev.map(item => item.id === entry.id ? { ...item, is_read: newIsRead } : item));
    } catch { /* non-fatal */ }
  }, []);

  const handleRemove = useCallback(async (e: React.MouseEvent, id: string) => {
    e.stopPropagation();
    try {
      await window.zio.readingList.remove(id);
      setEntries(prev => prev.filter(item => item.id !== id));
    } catch { /* non-fatal */ }
  }, []);

  const unread = entries.filter(e => !e.is_read);
  const read = entries.filter(e => e.is_read);

  return (
    <div style={{
      width: 340,
      height: '100%',
      background: 'var(--color-bg-surface)',
      borderLeft: '1px solid var(--color-border)',
      display: 'flex',
      flexDirection: 'column',
      flexShrink: 0,
    }}>
      {/* Header */}
      <div style={{
        height: 44,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: '0 16px',
        borderBottom: '1px solid var(--color-border)',
        flexShrink: 0,
      }}>
        <span style={{ fontWeight: 600, fontSize: 14 }}>
          Reading List
          {unread.length > 0 && (
            <span style={{
              marginLeft: 6,
              display: 'inline-flex',
              alignItems: 'center',
              justifyContent: 'center',
              minWidth: 18,
              height: 18,
              borderRadius: 9,
              background: 'var(--color-primary)',
              color: '#fff',
              fontSize: 11,
              fontWeight: 700,
              padding: '0 4px',
            }}>
              {unread.length}
            </span>
          )}
        </span>
        <button
          onClick={onClose}
          style={{
            fontSize: 16,
            color: 'var(--color-text-muted)',
            padding: '2px 6px',
            borderRadius: 4,
          }}
          title="Close reading list"
        >✕</button>
      </div>

      {/* Content */}
      <div style={{ flex: 1, overflowY: 'auto', padding: '8px 0' }}>
        {loading && (
          <div style={{ padding: '24px 16px', color: 'var(--color-text-muted)', fontSize: 13 }}>
            Loading…
          </div>
        )}

        {!loading && entries.length === 0 && (
          <div style={{
            padding: '32px 20px',
            textAlign: 'center',
            color: 'var(--color-text-muted)',
            fontSize: 13,
          }}>
            <div style={{ fontSize: 28, marginBottom: 10 }}>📖</div>
            <div style={{ fontWeight: 600, marginBottom: 4 }}>No saved pages</div>
            <div style={{ lineHeight: 1.5 }}>
              Click the bookmark icon in the address bar to save the current page for later.
            </div>
          </div>
        )}

        {!loading && unread.length > 0 && (
          <Section
            title="Unread"
            entries={unread}
            onOpen={handleOpen}
            onToggleRead={handleToggleRead}
            onRemove={handleRemove}
          />
        )}

        {!loading && read.length > 0 && (
          <Section
            title="Read"
            entries={read}
            onOpen={handleOpen}
            onToggleRead={handleToggleRead}
            onRemove={handleRemove}
            dimmed
          />
        )}
      </div>
    </div>
  );
}

interface SectionProps {
  title: string;
  entries: ReadingListEntry[];
  onOpen: (entry: ReadingListEntry) => void;
  onToggleRead: (e: React.MouseEvent, entry: ReadingListEntry) => void;
  onRemove: (e: React.MouseEvent, id: string) => void;
  dimmed?: boolean;
}

function Section({ title, entries, onOpen, onToggleRead, onRemove, dimmed }: SectionProps) {
  return (
    <div>
      <div style={{
        padding: '6px 16px 2px',
        fontSize: 11,
        fontWeight: 700,
        color: 'var(--color-text-muted)',
        letterSpacing: '0.05em',
        textTransform: 'uppercase',
      }}>
        {title}
      </div>
      {entries.map(entry => (
        <EntryRow
          key={entry.id}
          entry={entry}
          onOpen={onOpen}
          onToggleRead={onToggleRead}
          onRemove={onRemove}
          dimmed={dimmed}
        />
      ))}
    </div>
  );
}

interface EntryRowProps {
  entry: ReadingListEntry;
  onOpen: (entry: ReadingListEntry) => void;
  onToggleRead: (e: React.MouseEvent, entry: ReadingListEntry) => void;
  onRemove: (e: React.MouseEvent, id: string) => void;
  dimmed?: boolean;
}

function EntryRow({ entry, onOpen, onToggleRead, onRemove, dimmed }: EntryRowProps) {
  const [hovered, setHovered] = useState(false);

  const savedLabel = (() => {
    const d = new Date(entry.saved_at);
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  })();

  return (
    <div
      onClick={() => onOpen(entry)}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 10,
        padding: '8px 12px',
        cursor: 'pointer',
        background: hovered ? 'var(--color-bg-elevated)' : 'transparent',
        opacity: dimmed ? 0.65 : 1,
        transition: 'background 0.1s',
      }}
    >
      {/* Favicon */}
      {entry.favicon_url ? (
        <img
          src={entry.favicon_url}
          width={16}
          height={16}
          style={{ borderRadius: 3, flexShrink: 0 }}
          alt=""
          onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
        />
      ) : (
        <div style={{
          width: 16, height: 16, borderRadius: 3, background: 'var(--color-border)',
          flexShrink: 0,
        }} />
      )}

      {/* Title + URL */}
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{
          fontSize: 13,
          fontWeight: entry.is_read ? 400 : 600,
          color: 'var(--color-text)',
          overflow: 'hidden',
          textOverflow: 'ellipsis',
          whiteSpace: 'nowrap',
        }}>
          {entry.title || entry.url}
        </div>
        <div style={{
          fontSize: 11,
          color: 'var(--color-text-muted)',
          overflow: 'hidden',
          textOverflow: 'ellipsis',
          whiteSpace: 'nowrap',
          marginTop: 1,
        }}>
          {entry.url}
        </div>
      </div>

      {/* Date + actions */}
      <div style={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'flex-end',
        gap: 4,
        flexShrink: 0,
      }}>
        <span style={{ fontSize: 10, color: 'var(--color-text-muted)' }}>{savedLabel}</span>
        {hovered && (
          <div style={{ display: 'flex', gap: 4 }}>
            <button
              onClick={(e) => onToggleRead(e, entry)}
              title={entry.is_read ? 'Mark as unread' : 'Mark as read'}
              style={{
                fontSize: 12,
                padding: '1px 5px',
                borderRadius: 4,
                background: 'var(--color-bg)',
                border: '1px solid var(--color-border)',
                color: 'var(--color-text-muted)',
              }}
            >
              {entry.is_read ? '◎' : '✓'}
            </button>
            <button
              onClick={(e) => onRemove(e, entry.id)}
              title="Remove from reading list"
              style={{
                fontSize: 12,
                padding: '1px 5px',
                borderRadius: 4,
                background: 'var(--color-bg)',
                border: '1px solid var(--color-border)',
                color: 'var(--color-text-muted)',
              }}
            >✕</button>
          </div>
        )}
      </div>
    </div>
  );
}
