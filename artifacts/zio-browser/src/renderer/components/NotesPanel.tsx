/**
 * NotesPanel — account-notes sidebar (Dialer Notes, synced via the Sayzio API).
 *
 * Two scopes:
 *  - "This site": notes attached to the domain being viewed, with quick
 *    creation pre-attached to the current URL + page title.
 *  - "All notes": full dashboard (own + shared-with-me) with search,
 *    checklists, colors, reminders display, create/edit/delete.
 *
 * Works offline — the main process serves the local cache and queues writes.
 */
import { useState, useEffect, useCallback, useMemo } from 'react';
import type { ApiDialerNote, ApiNoteChecklistItem, DialerNoteInput } from '../../shared/api-client';

interface NotesListResult {
  notes: ApiDialerNote[];
  shared: ApiDialerNote[];
  offline: boolean;
  pending: number;
  authed: boolean;
}

const NOTE_COLORS = ['#f59e0b', '#22c55e', '#3b82f6', '#a855f7', '#ef4444', '#14b8a6'];

interface EditorState {
  id: number | null;
  kind: 'note' | 'checklist';
  title: string;
  body: string;
  checklist: ApiNoteChecklistItem[];
  color: string | null;
  attachedUrl: string | null;
  attachedTitle: string | null;
}

const EMPTY_EDITOR: EditorState = {
  id: null,
  kind: 'note',
  title: '',
  body: '',
  checklist: [],
  color: null,
  attachedUrl: null,
  attachedTitle: null,
};

function hostOf(url: string): string {
  try {
    return new URL(url).hostname.replace(/^www\./, '');
  } catch {
    return url;
  }
}

interface Props {
  onClose: () => void;
  onNavigate: (url: string) => void;
  /** URL + title of the active tab; enables the "This site" scope. */
  currentUrl?: string | null;
  currentTitle?: string | null;
  /** Open directly on the per-page scope. */
  initialScope?: 'page' | 'all';
}

export function NotesPanel({ onClose, onNavigate, currentUrl, currentTitle, initialScope }: Props) {
  const pageHost = currentUrl ? hostOf(currentUrl) : null;
  const canScopePage = !!pageHost && /^https?:/i.test(currentUrl ?? '');
  const [scope, setScope] = useState<'page' | 'all'>(
    initialScope === 'page' && canScopePage ? 'page' : 'all',
  );
  const [result, setResult] = useState<NotesListResult | null>(null);
  const [loading, setLoading] = useState(true);
  const [query, setQuery] = useState('');
  const [editor, setEditor] = useState<EditorState | null>(null);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const filter = scope === 'page' && pageHost ? { domain: pageHost } : undefined;
      const res = (await window.zio.notes.list(filter)) as NotesListResult;
      setResult(res);
    } catch {
      setResult({ notes: [], shared: [], offline: true, pending: 0, authed: false });
    } finally {
      setLoading(false);
    }
  }, [scope, pageHost]);

  useEffect(() => {
    setLoading(true);
    void load();
  }, [load]);

  const filtered = useMemo(() => {
    if (!result) return { notes: [] as ApiDialerNote[], shared: [] as ApiDialerNote[] };
    const q = query.trim().toLowerCase();
    if (!q) return { notes: result.notes, shared: result.shared };
    const match = (n: ApiDialerNote): boolean =>
      (n.title ?? '').toLowerCase().includes(q) ||
      (n.body ?? '').toLowerCase().includes(q) ||
      (n.attached_title ?? '').toLowerCase().includes(q) ||
      (n.attached_host ?? '').toLowerCase().includes(q) ||
      n.checklist.some(i => i.text.toLowerCase().includes(q));
    return { notes: result.notes.filter(match), shared: result.shared.filter(match) };
  }, [result, query]);

  const openCreate = useCallback(() => {
    setSaveError(null);
    setEditor({
      ...EMPTY_EDITOR,
      // Per-page scope pre-attaches the current page.
      attachedUrl: scope === 'page' && currentUrl ? currentUrl : null,
      attachedTitle: scope === 'page' && currentUrl ? (currentTitle ?? null) : null,
    });
  }, [scope, currentUrl, currentTitle]);

  const openEdit = useCallback((n: ApiDialerNote) => {
    if (!n.own) return;
    setSaveError(null);
    setEditor({
      id: n.id,
      kind: n.kind === 'checklist' ? 'checklist' : 'note',
      title: n.title ?? '',
      body: n.body ?? '',
      checklist: n.checklist.map(i => ({ text: i.text ?? '', done: !!i.done })),
      color: n.color ?? null,
      attachedUrl: n.attached_url ?? null,
      attachedTitle: n.attached_title ?? null,
    });
  }, []);

  const save = useCallback(async () => {
    if (!editor || saving) return;
    const checklist = editor.checklist.filter(i => i.text.trim() !== '');
    if (!editor.title.trim() && !editor.body.trim() && checklist.length === 0) {
      setSaveError('Add a title or some text first.');
      return;
    }
    const input: DialerNoteInput = {
      kind: editor.kind,
      title: editor.title.trim() || null,
      body: editor.kind === 'note' ? editor.body.trim() || null : null,
      checklist: editor.kind === 'checklist' ? checklist : null,
      color: editor.color,
      attached_url: editor.attachedUrl,
      attached_title: editor.attachedUrl ? editor.attachedTitle : null,
    };
    setSaving(true);
    setSaveError(null);
    try {
      await window.zio.notes.save(editor.id, input);
      setEditor(null);
      await load();
    } catch (e) {
      setSaveError(e instanceof Error && e.message ? e.message : "Couldn't save the note.");
    } finally {
      setSaving(false);
    }
  }, [editor, saving, load]);

  const remove = useCallback(async (n: ApiDialerNote) => {
    try {
      await window.zio.notes.remove(n.id);
      setEditor(prev => (prev?.id === n.id ? null : prev));
      await load();
    } catch { /* non-fatal */ }
  }, [load]);

  const toggleDone = useCallback(async (n: ApiDialerNote) => {
    try {
      await window.zio.notes.save(n.id, { done: !n.done });
      await load();
    } catch { /* non-fatal */ }
  }, [load]);

  const toggleChecklistItem = useCallback(async (n: ApiDialerNote, idx: number) => {
    const next = n.checklist.map((i, j) => (j === idx ? { ...i, done: !i.done } : i));
    try {
      await window.zio.notes.save(n.id, { checklist: next });
      await load();
    } catch { /* non-fatal */ }
  }, [load]);

  return (
    <div style={{
      width: 360,
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
          Notes
          {result && result.pending > 0 && (
            <span
              title={`${result.pending} change${result.pending === 1 ? '' : 's'} waiting to sync`}
              style={{
                marginLeft: 6, fontSize: 10, fontWeight: 700, padding: '1px 6px',
                borderRadius: 8, background: 'var(--color-bg-elevated)',
                border: '1px solid var(--color-border)', color: 'var(--color-text-muted)',
              }}
            >
              {result.pending} pending
            </span>
          )}
          {result?.offline && (
            <span style={{
              marginLeft: 6, fontSize: 10, fontWeight: 700, padding: '1px 6px',
              borderRadius: 8, background: 'var(--color-bg-elevated)',
              border: '1px solid var(--color-border)', color: 'var(--color-text-muted)',
            }}>
              offline
            </span>
          )}
        </span>
        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
          <button
            onClick={openCreate}
            title={scope === 'page' ? 'New note for this page' : 'New note'}
            style={{
              fontSize: 13, fontWeight: 600, padding: '2px 8px', borderRadius: 4,
              background: 'var(--gradient-primary)', color: '#fff',
            }}
          >＋</button>
          <button
            onClick={onClose}
            style={{ fontSize: 16, color: 'var(--color-text-muted)', padding: '2px 6px', borderRadius: 4 }}
            title="Close notes"
          >✕</button>
        </div>
      </div>

      {/* Scope tabs */}
      {canScopePage && (
        <div style={{ display: 'flex', gap: 6, padding: '8px 16px 0', flexShrink: 0 }}>
          {(['page', 'all'] as const).map(s => (
            <button
              key={s}
              onClick={() => setScope(s)}
              style={{
                fontSize: 12,
                fontWeight: 600,
                padding: '3px 10px',
                borderRadius: 999,
                border: '1px solid var(--color-border)',
                background: scope === s ? 'var(--color-bg-elevated)' : 'transparent',
                color: scope === s ? 'var(--color-text)' : 'var(--color-text-muted)',
              }}
            >
              {s === 'page' ? `This site (${pageHost})` : 'All notes'}
            </button>
          ))}
        </div>
      )}

      {/* Search */}
      <div style={{ padding: '8px 16px', flexShrink: 0 }}>
        <input
          value={query}
          onChange={e => setQuery(e.target.value)}
          placeholder="Search notes…"
          style={{
            width: '100%', fontSize: 13, padding: '6px 10px', borderRadius: 6,
            border: '1px solid var(--color-border)', background: 'var(--color-bg)',
            color: 'var(--color-text)',
          }}
        />
      </div>

      {/* Content */}
      <div style={{ flex: 1, overflowY: 'auto', padding: '0 0 12px' }}>
        {loading && (
          <div style={{ padding: '24px 16px', color: 'var(--color-text-muted)', fontSize: 13 }}>Loading…</div>
        )}

        {!loading && result && !result.authed && result.notes.length === 0 && result.shared.length === 0 && (
          <div style={{ padding: '32px 20px', textAlign: 'center', color: 'var(--color-text-muted)', fontSize: 13 }}>
            <div style={{ fontSize: 28, marginBottom: 10 }}>📝</div>
            <div style={{ fontWeight: 600, marginBottom: 4 }}>Sign in to use notes</div>
            <div style={{ lineHeight: 1.5 }}>
              Notes live on your Sayzio account, so they appear on the web and mobile apps too.
            </div>
          </div>
        )}

        {!loading && result && result.authed && filtered.notes.length === 0 && filtered.shared.length === 0 && (
          <div style={{ padding: '32px 20px', textAlign: 'center', color: 'var(--color-text-muted)', fontSize: 13 }}>
            <div style={{ fontSize: 28, marginBottom: 10 }}>📝</div>
            <div style={{ fontWeight: 600, marginBottom: 4 }}>
              {query ? 'No matching notes' : scope === 'page' ? 'No notes for this site yet' : 'No notes yet'}
            </div>
            {!query && (
              <div style={{ lineHeight: 1.5 }}>
                {scope === 'page'
                  ? 'Click ＋ to add a note attached to this page.'
                  : 'Click ＋ to write your first note.'}
              </div>
            )}
          </div>
        )}

        {!loading && filtered.notes.length > 0 && (
          <NoteSection
            title="My notes"
            notes={filtered.notes}
            onOpen={openEdit}
            onToggleDone={toggleDone}
            onToggleItem={toggleChecklistItem}
            onRemove={remove}
            onNavigate={onNavigate}
          />
        )}
        {!loading && filtered.shared.length > 0 && (
          <NoteSection
            title="Shared with me"
            notes={filtered.shared}
            onOpen={openEdit}
            onToggleDone={toggleDone}
            onToggleItem={toggleChecklistItem}
            onRemove={remove}
            onNavigate={onNavigate}
          />
        )}
      </div>

      {/* Editor */}
      {editor && (
        <div style={{
          borderTop: '1px solid var(--color-border)',
          padding: '10px 16px 12px',
          flexShrink: 0,
          maxHeight: '55%',
          overflowY: 'auto',
          background: 'var(--color-bg)',
        }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
            <div style={{ display: 'flex', gap: 6 }}>
              {(['note', 'checklist'] as const).map(k => (
                <button
                  key={k}
                  onClick={() => setEditor(e => e && ({
                    ...e,
                    kind: k,
                    checklist: k === 'checklist' && e.checklist.length === 0
                      ? [{ text: '', done: false }]
                      : e.checklist,
                  }))}
                  style={{
                    fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 999,
                    border: '1px solid var(--color-border)',
                    background: editor.kind === k ? 'var(--color-bg-elevated)' : 'transparent',
                    color: editor.kind === k ? 'var(--color-text)' : 'var(--color-text-muted)',
                  }}
                >
                  {k === 'note' ? 'Note' : 'To-do list'}
                </button>
              ))}
            </div>
            <button
              onClick={() => setEditor(null)}
              style={{ fontSize: 14, color: 'var(--color-text-muted)', padding: '0 4px' }}
              title="Close editor"
            >✕</button>
          </div>

          <input
            value={editor.title}
            onChange={e => setEditor(prev => prev && ({ ...prev, title: e.target.value }))}
            placeholder="Title (optional)"
            style={{
              width: '100%', fontSize: 13, padding: '6px 10px', borderRadius: 6,
              border: '1px solid var(--color-border)', background: 'var(--color-bg-surface)',
              color: 'var(--color-text)', marginBottom: 6,
            }}
          />

          {editor.kind === 'note' ? (
            <textarea
              value={editor.body}
              onChange={e => setEditor(prev => prev && ({ ...prev, body: e.target.value }))}
              placeholder="Write your note…"
              rows={4}
              style={{
                width: '100%', fontSize: 13, padding: '6px 10px', borderRadius: 6,
                border: '1px solid var(--color-border)', background: 'var(--color-bg-surface)',
                color: 'var(--color-text)', resize: 'vertical', marginBottom: 6,
              }}
            />
          ) : (
            <div style={{ marginBottom: 6 }}>
              {editor.checklist.map((item, idx) => (
                <div key={idx} style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 4 }}>
                  <input
                    type="checkbox"
                    checked={item.done}
                    onChange={() => setEditor(prev => prev && ({
                      ...prev,
                      checklist: prev.checklist.map((i, j) => j === idx ? { ...i, done: !i.done } : i),
                    }))}
                  />
                  <input
                    value={item.text}
                    onChange={e => setEditor(prev => prev && ({
                      ...prev,
                      checklist: prev.checklist.map((i, j) => j === idx ? { ...i, text: e.target.value } : i),
                    }))}
                    placeholder="To-do item…"
                    style={{
                      flex: 1, fontSize: 13, padding: '4px 8px', borderRadius: 6,
                      border: '1px solid var(--color-border)', background: 'var(--color-bg-surface)',
                      color: 'var(--color-text)',
                    }}
                  />
                  <button
                    onClick={() => setEditor(prev => prev && ({
                      ...prev,
                      checklist: prev.checklist.filter((_, j) => j !== idx),
                    }))}
                    style={{ fontSize: 12, color: 'var(--color-text-muted)' }}
                    title="Remove item"
                  >✕</button>
                </div>
              ))}
              <button
                onClick={() => setEditor(prev => prev && ({
                  ...prev,
                  checklist: [...prev.checklist, { text: '', done: false }],
                }))}
                style={{ fontSize: 12, fontWeight: 600, color: 'var(--color-text-muted)' }}
              >＋ Add item</button>
            </div>
          )}

          {/* Website attachment */}
          {editor.attachedUrl ? (
            <div style={{
              display: 'flex', alignItems: 'center', gap: 8, padding: '6px 10px',
              border: '1px solid var(--color-border)', borderRadius: 6, marginBottom: 6,
              background: 'var(--color-bg-surface)',
            }}>
              <span style={{ fontSize: 13 }}>🌐</span>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{
                  fontSize: 12, fontWeight: 600, color: 'var(--color-text)',
                  overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
                }}>
                  {editor.attachedTitle || hostOf(editor.attachedUrl)}
                </div>
                <div style={{
                  fontSize: 11, color: 'var(--color-text-muted)',
                  overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
                }}>
                  {editor.attachedUrl}
                </div>
              </div>
              <button
                onClick={() => setEditor(prev => prev && ({ ...prev, attachedUrl: null, attachedTitle: null }))}
                style={{ fontSize: 12, color: 'var(--color-text-muted)' }}
                title="Remove attached website"
              >✕</button>
            </div>
          ) : currentUrl && canScopePage ? (
            <button
              onClick={() => setEditor(prev => prev && ({
                ...prev,
                attachedUrl: currentUrl,
                attachedTitle: currentTitle ?? null,
              }))}
              style={{
                fontSize: 12, fontWeight: 600, color: 'var(--color-text-muted)',
                border: '1px dashed var(--color-border)', borderRadius: 6,
                padding: '4px 10px', marginBottom: 6,
              }}
            >
              🌐 Attach this page ({pageHost})
            </button>
          ) : null}

          {/* Color swatches */}
          <div style={{ display: 'flex', gap: 6, alignItems: 'center', marginBottom: 8 }}>
            <button
              onClick={() => setEditor(prev => prev && ({ ...prev, color: null }))}
              title="No color"
              style={{
                width: 18, height: 18, borderRadius: 9,
                border: editor.color === null ? '2px solid var(--color-text)' : '1px solid var(--color-border)',
                background: 'transparent',
              }}
            />
            {NOTE_COLORS.map(c => (
              <button
                key={c}
                onClick={() => setEditor(prev => prev && ({ ...prev, color: c }))}
                title={`Color ${c}`}
                style={{
                  width: 18, height: 18, borderRadius: 9, background: c,
                  border: editor.color === c ? '2px solid var(--color-text)' : '1px solid transparent',
                }}
              />
            ))}
          </div>

          {saveError && (
            <div style={{ fontSize: 12, color: '#ef4444', marginBottom: 6 }}>{saveError}</div>
          )}

          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
            <button
              onClick={() => setEditor(null)}
              style={{
                fontSize: 13, fontWeight: 600, padding: '4px 12px', borderRadius: 6,
                border: '1px solid var(--color-border)', color: 'var(--color-text)',
              }}
            >Cancel</button>
            <button
              onClick={() => void save()}
              disabled={saving}
              style={{
                fontSize: 13, fontWeight: 600, padding: '4px 14px', borderRadius: 6,
                background: 'var(--gradient-primary)', color: '#fff', opacity: saving ? 0.6 : 1,
              }}
            >{saving ? 'Saving…' : 'Save'}</button>
          </div>
        </div>
      )}
    </div>
  );
}

interface NoteSectionProps {
  title: string;
  notes: ApiDialerNote[];
  onOpen: (n: ApiDialerNote) => void;
  onToggleDone: (n: ApiDialerNote) => void;
  onToggleItem: (n: ApiDialerNote, idx: number) => void;
  onRemove: (n: ApiDialerNote) => void;
  onNavigate: (url: string) => void;
}

function NoteSection({ title, notes, onOpen, onToggleDone, onToggleItem, onRemove, onNavigate }: NoteSectionProps) {
  return (
    <div>
      <div style={{
        padding: '8px 16px 2px', fontSize: 11, fontWeight: 700,
        color: 'var(--color-text-muted)', letterSpacing: '0.05em', textTransform: 'uppercase',
      }}>
        {title}
      </div>
      {notes.map(n => (
        <NoteRow
          key={n.id}
          note={n}
          onOpen={onOpen}
          onToggleDone={onToggleDone}
          onToggleItem={onToggleItem}
          onRemove={onRemove}
          onNavigate={onNavigate}
        />
      ))}
    </div>
  );
}

interface NoteRowProps {
  note: ApiDialerNote;
  onOpen: (n: ApiDialerNote) => void;
  onToggleDone: (n: ApiDialerNote) => void;
  onToggleItem: (n: ApiDialerNote, idx: number) => void;
  onRemove: (n: ApiDialerNote) => void;
  onNavigate: (url: string) => void;
}

function NoteRow({ note: n, onOpen, onToggleDone, onToggleItem, onRemove, onNavigate }: NoteRowProps) {
  const [hovered, setHovered] = useState(false);
  const remindLabel = (() => {
    if (!n.remind_at) return null;
    const d = new Date(n.remind_at);
    if (Number.isNaN(d.getTime())) return null;
    return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  })();
  const overdue = !!n.remind_at && !n.done && new Date(n.remind_at).getTime() < Date.now();

  return (
    <div
      onClick={() => n.own && onOpen(n)}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
      style={{
        padding: '8px 12px 8px 10px',
        margin: '2px 8px',
        borderRadius: 8,
        borderLeft: `3px solid ${n.color ?? 'var(--color-border)'}`,
        background: hovered ? 'var(--color-bg-elevated)' : 'transparent',
        cursor: n.own ? 'pointer' : 'default',
        opacity: n.done ? 0.6 : 1,
        transition: 'background 0.1s',
      }}
    >
      <div style={{ display: 'flex', alignItems: 'flex-start', gap: 8 }}>
        {n.own ? (
          <button
            onClick={e => { e.stopPropagation(); onToggleDone(n); }}
            title={n.done ? 'Mark as not done' : 'Mark as done'}
            style={{ fontSize: 13, lineHeight: '18px', color: n.done ? 'var(--color-text)' : 'var(--color-text-muted)' }}
          >
            {n.done ? '☑' : '☐'}
          </button>
        ) : (
          <span title={`Shared by ${n.owner_name ?? 'someone'}`} style={{ fontSize: 12, lineHeight: '18px' }}>👥</span>
        )}
        <div style={{ flex: 1, minWidth: 0 }}>
          {(n.title || n.kind === 'checklist') && (
            <div style={{
              fontSize: 13, fontWeight: 600, color: 'var(--color-text)',
              textDecoration: n.done ? 'line-through' : 'none',
              overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
            }}>
              {n.title || 'To-do list'}
            </div>
          )}
          {n.kind === 'note' && n.body && (
            <div style={{
              fontSize: 12, color: 'var(--color-text-muted)', marginTop: 1,
              display: '-webkit-box', WebkitLineClamp: 3, WebkitBoxOrient: 'vertical', overflow: 'hidden',
            }}>
              {n.body}
            </div>
          )}
          {n.kind === 'checklist' && n.checklist.length > 0 && (
            <div style={{ marginTop: 3 }}>
              {n.checklist.map((item, idx) => (
                <div
                  key={idx}
                  onClick={e => { e.stopPropagation(); if (n.own) onToggleItem(n, idx); }}
                  style={{
                    display: 'flex', alignItems: 'center', gap: 5, fontSize: 12,
                    color: item.done ? 'var(--color-text-muted)' : 'var(--color-text)',
                    textDecoration: item.done ? 'line-through' : 'none',
                    cursor: n.own ? 'pointer' : 'default',
                    padding: '1px 0',
                  }}
                >
                  <span>{item.done ? '☑' : '☐'}</span>
                  <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{item.text}</span>
                </div>
              ))}
            </div>
          )}
          {n.attached_url && (
            <button
              onClick={e => { e.stopPropagation(); onNavigate(n.attached_url as string); }}
              title={`Open ${n.attached_url}`}
              style={{
                display: 'inline-flex', alignItems: 'center', gap: 5, marginTop: 4,
                fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 999,
                border: '1px solid var(--color-border)', color: 'var(--color-text)',
                maxWidth: '100%',
              }}
            >
              <span>🌐</span>
              <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                {n.attached_title || n.attached_host || hostOf(n.attached_url)}
              </span>
            </button>
          )}
          <div style={{ display: 'flex', gap: 10, marginTop: 3, fontSize: 11, color: 'var(--color-text-muted)' }}>
            {remindLabel && (
              <span style={{ color: overdue ? '#ef4444' : undefined, fontWeight: overdue ? 700 : 400 }}>
                ⏰ {remindLabel}
              </span>
            )}
            {n.number && <span>📞 {n.number}</span>}
            {!n.own && n.owner_name && <span>From {n.owner_name}</span>}
            {n.own && n.share_phones.length > 0 && <span>Shared with {n.share_phones.length}</span>}
          </div>
        </div>
        {n.own && hovered && (
          <button
            onClick={e => { e.stopPropagation(); onRemove(n); }}
            title="Delete note"
            style={{ fontSize: 12, color: 'var(--color-text-muted)' }}
          >🗑</button>
        )}
      </div>
    </div>
  );
}
