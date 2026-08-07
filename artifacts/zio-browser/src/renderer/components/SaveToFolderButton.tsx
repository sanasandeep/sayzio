/**
 * Toolbar "Save to folder" button + popover.
 *
 * One-click save of the current page into a chosen folder. When the user is
 * signed in to Sayzio, folders are the ACCOUNT folders (the same "projects"
 * the web dashboard's folders desk shows) and saving files the page as a link
 * in that folder — so browser and web stay in sync. Signed out, it falls back
 * to the browser's local collections.
 * Hidden in private windows (folders are normal-profile data; the main
 * process additionally rejects collection IPC from private senders).
 */
import { useState, useEffect, useRef, useCallback } from 'react';
import type { Collection } from '../../shared/collection-store';
import { ApiClient } from '../../shared/api-client';
import type { ApiProject } from '../../shared/api-client';
import { useAuthStore } from '../store/auth-store';
import { useChromeOverlay } from '../hooks/use-chrome-overlay';

const API_BASE_URL = 'https://sayzio.app';

interface Props {
  url: string | null | undefined;
  title: string | null | undefined;
}

interface FolderOption {
  id: string;          // local collection id or `p:<projectId>`
  name: string;
  count: number;
}

export function SaveToFolderButton({ url, title }: Props) {
  const [open, setOpen] = useState(false);
  const [folders, setFolders] = useState<FolderOption[]>([]);
  const [savedToId, setSavedToId] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);
  const [newName, setNewName] = useState('');
  const rootRef = useRef<HTMLDivElement>(null);
  const { token } = useAuthStore();

  // Native WebContentsViews sit above renderer DOM — hold the ref-counted
  // chrome overlay while the popover is open so it isn't occluded/unclickable.
  useChromeOverlay(open);

  const canSave = !!url && url !== 'about:newtab' && !url.startsWith('about:');

  const getClient = useCallback((): ApiClient | null => {
    if (!token) return null;
    return new ApiClient({ baseUrl: API_BASE_URL, token });
  }, [token]);

  // Request-generation guard: bumped per load AND on token changes so a slow
  // response from the previous account can never repopulate the list after a
  // login/logout/account switch while the popover is open.
  const loadSeqRef = useRef(0);
  useEffect(() => {
    loadSeqRef.current += 1;
    setFolders([]);
    if (open) setOpen(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token]);

  const load = useCallback(async () => {
    const seq = ++loadSeqRef.current;
    const client = getClient();
    if (client) {
      try {
        const res = await client.listProjects();
        if (loadSeqRef.current !== seq) return;
        setFolders(res.items.map(p => ({ id: `p:${p.id}`, name: p.name, count: p.links_count ?? 0 })));
        return;
      } catch { /* fall through to local */ }
    }
    try {
      const all = await window.zio.collections.all() as Collection[];
      if (loadSeqRef.current !== seq) return;
      setFolders(all.map(c => ({ id: c.id, name: c.name, count: c.item_count ?? 0 })));
    } catch { if (loadSeqRef.current === seq) setFolders([]); }
  }, [getClient]);

  useEffect(() => {
    if (!open) return;
    void load();
    const onDown = (e: MouseEvent) => {
      if (rootRef.current && !rootRef.current.contains(e.target as Node)) setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') setOpen(false); };
    document.addEventListener('mousedown', onDown);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDown);
      document.removeEventListener('keydown', onKey);
    };
  }, [open, load]);

  const saveTo = useCallback(async (folderId: string) => {
    if (!url) return;
    try {
      if (folderId.startsWith('p:')) {
        const client = getClient();
        if (!client) return;
        // Saving a page into an account folder = creating a short link filed
        // under that folder, so it shows up on the web dashboard too.
        await client.createLink({
          type: 'short',
          long_url: url,
          title: (title || url).slice(0, 200),
          project_id: Number(folderId.slice(2)),
        });
      } else {
        await window.zio.collections.saveLink(folderId, url, title || url);
      }
      setSavedToId(folderId);
      await load();
      setTimeout(() => { setSavedToId(null); setOpen(false); }, 900);
    } catch { /* non-fatal */ }
  }, [url, title, load, getClient]);

  const createAndSave = useCallback(async () => {
    const name = newName.trim();
    if (!name) return;
    try {
      const client = getClient();
      if (client) {
        const created = await client.createProject({ name: name.slice(0, 120) });
        setNewName('');
        setCreating(false);
        if (created?.project?.id) await saveTo(`p:${created.project.id}`);
        else void load();
        return;
      }
      const created = await window.zio.collections.create(name) as Collection | null;
      setNewName('');
      setCreating(false);
      if (created?.id) await saveTo(created.id);
      else void load();
    } catch { /* non-fatal */ }
  }, [newName, saveTo, load, getClient]);

  if (!canSave) return null;

  return (
    <div ref={rootRef} style={{ position: 'relative', flexShrink: 0 }}>
      <button
        onClick={() => { setOpen(o => !o); setCreating(false); }}
        title="Save this page to a folder"
        style={{
          fontSize: 13,
          padding: '3px 7px',
          borderRadius: 8,
          background: open ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
          color: open ? '#fff' : 'var(--color-text-muted)',
          border: '1px solid var(--color-border)',
          transition: 'all 0.12s',
          cursor: 'pointer',
        }}
      >
        📁
      </button>

      {open && (
        <div style={{
          position: 'absolute',
          top: 'calc(100% + 6px)',
          right: 0,
          width: 240,
          maxHeight: 320,
          overflowY: 'auto',
          borderRadius: 12,
          background: 'var(--color-bg-surface)',
          border: '1px solid var(--color-border)',
          boxShadow: '0 12px 32px rgba(0,0,0,0.35)',
          padding: 8,
          zIndex: 1000,
        }}>
          <div style={{
            fontSize: 10.5, fontWeight: 700, textTransform: 'uppercase', letterSpacing: 0.8,
            color: 'var(--color-text-muted)', padding: '2px 6px 6px',
          }}>
            Save to folder
          </div>

          {folders.length === 0 && !creating && (
            <div style={{ fontSize: 12, color: 'var(--color-text-muted)', padding: '2px 6px 8px' }}>
              No folders yet — create your first one below.
            </div>
          )}

          {folders.map(c => (
            <button
              key={c.id}
              onClick={() => void saveTo(c.id)}
              style={{
                display: 'flex', alignItems: 'center', gap: 8, width: '100%',
                padding: '7px 8px', borderRadius: 8, border: 'none',
                background: 'transparent', cursor: 'pointer', textAlign: 'left',
              }}
              onMouseEnter={e => { (e.currentTarget as HTMLButtonElement).style.background = 'var(--color-bg-elevated)'; }}
              onMouseLeave={e => { (e.currentTarget as HTMLButtonElement).style.background = 'transparent'; }}
            >
              <span aria-hidden="true" style={{ fontSize: 14 }}>{savedToId === c.id ? '✅' : '📁'}</span>
              <span style={{
                flex: 1, fontSize: 12.5, fontWeight: 600, color: 'var(--color-text)',
                overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
              }}>
                {savedToId === c.id ? 'Saved!' : c.name}
              </span>
              {savedToId !== c.id && (
                <span style={{ fontSize: 10.5, color: 'var(--color-text-muted)', flexShrink: 0 }}>
                  {c.count}
                </span>
              )}
            </button>
          ))}

          <div style={{ borderTop: '1px solid var(--color-border)', margin: '6px 0' }} />

          {creating ? (
            <div style={{ display: 'flex', gap: 6, padding: '2px 4px' }}>
              <input
                value={newName}
                onChange={e => setNewName(e.target.value)}
                onKeyDown={e => {
                  if (e.key === 'Enter') void createAndSave();
                  if (e.key === 'Escape') { setCreating(false); setNewName(''); }
                }}
                placeholder="Folder name"
                autoFocus
                style={{
                  flex: 1, minWidth: 0, fontSize: 12, padding: '5px 8px', borderRadius: 8,
                  border: '1px solid var(--color-border)', background: 'var(--color-bg)',
                  color: 'var(--color-text)', outline: 'none',
                }}
              />
              <button
                onClick={() => void createAndSave()}
                style={{
                  fontSize: 11, fontWeight: 700, color: '#fff', border: 'none', cursor: 'pointer',
                  padding: '5px 10px', borderRadius: 8, background: 'var(--color-primary)', flexShrink: 0,
                }}
              >
                Save
              </button>
            </div>
          ) : (
            <button
              onClick={() => setCreating(true)}
              style={{
                display: 'flex', alignItems: 'center', gap: 8, width: '100%',
                padding: '7px 8px', borderRadius: 8, border: 'none',
                background: 'transparent', cursor: 'pointer', textAlign: 'left',
                fontSize: 12.5, fontWeight: 600, color: 'var(--color-primary)',
              }}
              onMouseEnter={e => { (e.currentTarget as HTMLButtonElement).style.background = 'var(--color-bg-elevated)'; }}
              onMouseLeave={e => { (e.currentTarget as HTMLButtonElement).style.background = 'transparent'; }}
            >
              <span aria-hidden="true">＋</span> New folder…
            </button>
          )}
        </div>
      )}
    </div>
  );
}
