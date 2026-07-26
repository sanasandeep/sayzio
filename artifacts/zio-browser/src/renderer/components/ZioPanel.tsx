/**
 * ZioPanel — the AI assistant panel for Zio Browser.
 * Shows contextual AI responses, contact extraction, collections,
 * browser management tools, and (when on a Sayzio link) live click stats.
 *
 * Browser mode extensions:
 *  - presentation: 'overlay' | 'docked' — floating card vs push-layout side panel
 *  - panelWidth: explicit pixel width (when docked, driven by drag-resize in App.tsx)
 *  - Browser tab with History, Cookies, Passwords, Downloads sections
 *  - Chat assistant intent detection for browser-management commands (handled locally)
 *  - Password offer banner when a login form is submitted on an active tab
 */
import { useState, useCallback, useRef, useEffect } from 'react';
import { useAuthStore } from '../store/auth-store';
import { ApiClient, ApiClientError } from '../../shared/api-client';
import type { LinkAnalytics, AssistantPage, ApiContact, ApiUserProfile, ApiLink, UpdateLinkPayload, ApiFile } from '../../shared/api-client';
import { trimPageContext } from '../../shared/context-extractor';
import type { PageContext, TrimmedContext } from '../../shared/context-extractor';
import { detectSayzioLink } from '../../shared/link-tools';
import { AddToBiolinkModal } from './AddToBiolinkModal';
import type { AutofillCard, AutofillResult } from '../../shared/form-autofill';
import { BrowserToolsView } from './BrowserToolsView';
import { detectBrowserIntent, describeIntent } from '../../shared/browser-intents';
import type { BrowserIntent } from '../../shared/browser-intents';
import { ProfileBadge } from './ProfileBadge';

const BASE_URL = 'https://1in.me';

interface Props {
  pageContext: { url: string; title: string } | null;
  onClose: () => void;
  /**
   * When 'embedded', renders full-area (no fixed width) for the split-mode left pane.
   * When 'overlay', floats over the page content as an absolute-positioned card.
   * When 'docked', is a side panel with explicit width (set by drag-resize in App.tsx).
   * Defaults to 'embedded' for backwards compatibility.
   */
  presentation?: 'embedded' | 'overlay' | 'docked';
  /** Explicit pixel width (only used when presentation is 'overlay' or 'docked'). */
  panelWidth?: number;
  /** Called when the user toggles the docked/overlay mode switch in the panel header. */
  onSetDocked?: (docked: boolean) => void;
}

interface Message {
  role: 'user' | 'assistant';
  content: string;
  timestamp: number;
  /** True while tokens are still streaming into this message */
  streaming?: boolean;
  /** When set, this message is a local browser action response (not from backend). */
  isLocalAction?: boolean;
}

interface PendingCredential {
  origin: string;
  username: string;
  password: string;
}

const VISITOR_TOKEN_KEY = 'zio.assistant.visitor_token';

function loadVisitorToken(): string | null {
  try { return window.localStorage.getItem(VISITOR_TOKEN_KEY); } catch { return null; }
}

function storeVisitorToken(token: string): void {
  try { window.localStorage.setItem(VISITOR_TOKEN_KEY, token); } catch { /* ignore */ }
}

type PanelTab = 'chat' | 'contacts' | 'collections' | 'stats' | 'browser';

// ── Tab labels / icons ─────────────────────────────────────────────────────────

const TAB_LABELS: Record<PanelTab, string> = {
  chat: 'Chat',
  contacts: 'Contacts',
  collections: 'Collections',
  stats: 'Stats',
  browser: 'Browser',
};

// ── ZioPanel component ────────────────────────────────────────────────────────

export function ZioPanel({ pageContext, onClose, presentation = 'embedded', panelWidth, onSetDocked }: Props) {
  const { token } = useAuthStore();
  const [messages, setMessages] = useState<Message[]>([]);
  const [input, setInput] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [greeting, setGreeting] = useState<string | null>(null);
  const [trimmedCtx, setTrimmedCtx] = useState<TrimmedContext | null>(null);
  const [activeTab, setActiveTab] = useState<PanelTab>('chat');
  const [error, setError] = useState<string | null>(null);
  const [addToBiolinkOpen, setAddToBiolinkOpen] = useState(false);
  const [addToBiolinkPayload, setAddToBiolinkPayload] = useState<{ url: string; title: string } | null>(null);

  // Browser management state
  const [browserFocusSection, setBrowserFocusSection] = useState<'history' | 'cookies' | 'passwords' | 'downloads' | null>(null);
  const [pendingCredential, setPendingCredential] = useState<PendingCredential | null>(null);
  const [savingPassword, setSavingPassword] = useState(false);

  const messagesEndRef = useRef<HTMLDivElement>(null);
  const visitorTokenRef = useRef<string | null>(loadVisitorToken());
  const sessionOpenedRef = useRef(false);

  // Drag & drop file uploads → Sayzio Files
  const [isDragging, setIsDragging] = useState(false);
  const [isUploading, setIsUploading] = useState(false);
  const dragDepthRef = useRef(0);
  /** Files uploaded via drag & drop, referenced in the next chat message's context. */
  const lastUploadsRef = useRef<ApiFile[]>([]);

  const getClient = useCallback((): ApiClient | null => {
    if (!token) return null;
    return new ApiClient({ baseUrl: BASE_URL, token });
  }, [token]);

  // Detect whether the current page is a Sayzio link
  const sayzioLink = pageContext?.url ? detectSayzioLink(pageContext.url) : null;

  // Auto-switch tabs based on context
  useEffect(() => {
    if (sayzioLink && token && activeTab === 'chat') {
      setActiveTab('stats');
    }
    if (!sayzioLink && activeTab === 'stats') {
      setActiveTab('chat');
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sayzioLink?.alias, token]);

  const buildPage = useCallback((): AssistantPage | undefined => {
    if (!pageContext) return undefined;
    let path: string | undefined;
    try { path = new URL(pageContext.url).pathname.slice(0, 240); } catch { path = undefined; }
    const page: AssistantPage = {
      url: pageContext.url.slice(0, 500),
      title: pageContext.title.slice(0, 240),
    };
    if (path) page.path = path;
    return page;
  }, [pageContext]);

  // Extract page context from the active tab
  useEffect(() => {
    if (!pageContext) return;

    void (async () => {
      try {
        const active = await window.zio.tabs.getActive() as string | null;
        if (!active) return;
        const ctx = await window.zio.tabs.extractContext(active) as PageContext | null;
        if (ctx) {
          setTrimmedCtx(trimPageContext(ctx));
        }
      } catch {
        // ignore
      }
    })();
  }, [pageContext?.url]);

  // Open (or resume) the assistant session once we have a signed-in token.
  useEffect(() => {
    if (!token || sessionOpenedRef.current) return;
    const client = getClient();
    if (!client) return;
    sessionOpenedRef.current = true;

    void (async () => {
      try {
        const res = await client.assistantSession(visitorTokenRef.current, buildPage());
        if (res.visitor_token) {
          visitorTokenRef.current = res.visitor_token;
          storeVisitorToken(res.visitor_token);
        }
        if (!res.ok) {
          if (res.error) setError(res.error);
          return;
        }
        if (res.greeting) setGreeting(res.greeting);
        if (Array.isArray(res.messages) && res.messages.length > 0) {
          setMessages(res.messages.map(m => ({
            role: m.role,
            content: m.content,
            timestamp: m.created_at ? Date.parse(m.created_at) || Date.now() : Date.now(),
          })));
        }
      } catch (err) {
        sessionOpenedRef.current = false;
        const msg = err instanceof ApiClientError && err.code === 'auth_required'
          ? 'Sign in to use Zio AI'
          : (err instanceof Error ? err.message : 'Could not reach Zio');
        setError(msg);
      }
    })();
  }, [token, getClient, buildPage]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  // Listen for "Add to my biolink" IPC event
  useEffect(() => {
    const handler = (url: unknown, title: unknown) => {
      setAddToBiolinkPayload({
        url: typeof url === 'string' ? url : pageContext?.url ?? '',
        title: typeof title === 'string' ? title : pageContext?.title ?? '',
      });
      setAddToBiolinkOpen(true);
    };
    window.zio.on('biolink:add-page', handler);
    return () => window.zio.off('biolink:add-page', handler);
  }, [pageContext]);

  // Listen for detected password credentials from the main process
  useEffect(() => {
    const handler = (cred: unknown) => {
      if (cred && typeof cred === 'object' && 'origin' in cred) {
        setPendingCredential(cred as PendingCredential);
      }
    };
    window.zio.on('password:detected', handler);
    return () => window.zio.off('password:detected', handler);
  }, []);

  // Inject password detector into the active tab when it loads
  useEffect(() => {
    if (!pageContext?.url) return;
    void (async () => {
      const activeId = await window.zio.tabs.getActive() as string | null;
      if (activeId) {
        await window.zio.tabs.injectPasswordDetector(activeId);
      }
    })();
  }, [pageContext?.url]);

  // ── Browser intent handling ─────────────────────────────────────────────────

  /**
   * Execute a browser management intent locally.
   * Returns a local response string to show in the chat bubble.
   * For destructive intents, returns null (caller shows confirm dialog in BrowserToolsView).
   */
  const handleBrowserIntent = useCallback(async (intent: BrowserIntent): Promise<string | null> => {
    switch (intent.action) {
      case 'show_history':
        setActiveTab('browser');
        setBrowserFocusSection('history');
        return 'Opening your browsing history.';

      case 'clear_history': {
        // Inject a confirm-required message; BrowserToolsView will handle the confirm UI
        setActiveTab('browser');
        setBrowserFocusSection('history');
        return 'I\'ve opened your history. Use the "Clear all" button to confirm deletion.';
      }

      case 'show_cookies':
        setActiveTab('browser');
        setBrowserFocusSection('cookies');
        return 'Opening cookies for this site.';

      case 'clear_cookies_for_site': {
        setActiveTab('browser');
        setBrowserFocusSection('cookies');
        return 'I\'ve opened your cookies panel. Use "Clear site" to confirm.';
      }

      case 'clear_cookies_all': {
        setActiveTab('browser');
        setBrowserFocusSection('cookies');
        return 'I\'ve opened the cookies panel. Use "Clear all" to confirm (you\'ll be signed out of all sites).';
      }

      case 'show_passwords':
        setActiveTab('browser');
        setBrowserFocusSection('passwords');
        return 'Opening your saved passwords.';

      case 'delete_password_for': {
        setActiveTab('browser');
        setBrowserFocusSection('passwords');
        return `I've opened your passwords. Find the entry for "${intent.query}" and click the delete button to remove it.`;
      }

      case 'show_downloads':
        setActiveTab('browser');
        setBrowserFocusSection('downloads');
        return 'Opening your recent downloads.';

      case 'clear_browsing_data': {
        setActiveTab('browser');
        setBrowserFocusSection('history');
        return 'I\'ve opened the browser tools. Use the "Clear all browsing data" button at the bottom to confirm.';
      }
    }
  }, []);

  // ── Drag & drop file uploads ────────────────────────────────────────────────

  const formatSize = (bytes: number): string => {
    if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${bytes} B`;
  };

  const handleDroppedFiles = useCallback(async (files: File[]) => {
    if (files.length === 0 || isUploading) return;

    const client = getClient();
    if (!client) {
      setError('Sign in to upload files');
      return;
    }

    setActiveTab('chat');
    setIsUploading(true);
    setError(null);

    const names = files.map(f => f.name).join(', ');
    setMessages(prev => [...prev, {
      role: 'user',
      content: `📎 Uploading ${files.length === 1 ? names : `${files.length} files (${names})`} to my Files…`,
      timestamp: Date.now(),
      isLocalAction: true,
    }]);

    const uploaded: ApiFile[] = [];
    const failures: string[] = [];
    let quotaHit = false;

    try {
    for (const file of files) {
      try {
        const rec = await client.uploadFile(file, file.name);
        uploaded.push(rec);
      } catch (err) {
        if (err instanceof ApiClientError && (err.code === 'quota_exceeded' || err.status === 413)) {
          quotaHit = true;
          failures.push(`${file.name} — storage limit reached`);
        } else {
          failures.push(`${file.name} — ${err instanceof Error ? err.message : 'upload failed'}`);
        }
      }
    }

    if (uploaded.length > 0) {
      lastUploadsRef.current = uploaded;
    }

    const lines: string[] = [];
    if (uploaded.length > 0) {
      lines.push(uploaded.length === 1
        ? `Done! I saved **${uploaded[0]!.original_name || uploaded[0]!.filename}** (${formatSize(uploaded[0]!.size)}) to your Sayzio Files.`
        : `Done! I saved ${uploaded.length} files to your Sayzio Files:`);
      if (uploaded.length > 1) {
        for (const f of uploaded) lines.push(`• ${f.original_name || f.filename} (${formatSize(f.size)})`);
      }
    }
    if (failures.length > 0) {
      lines.push(uploaded.length > 0 ? `\nSome files couldn't be uploaded:` : `I couldn't upload your file${files.length > 1 ? 's' : ''}:`);
      for (const f of failures) lines.push(`• ${f}`);
      if (quotaHit) {
        lines.push(`\nYour file storage is full. You can free up space in your Files page, or upgrade your plan for more storage.`);
      }
    }
    if (uploaded.length > 0) {
      lines.push(`\nHow would you like to use ${uploaded.length === 1 ? 'it' : 'them'}? For example, I can add ${uploaded.length === 1 ? 'it' : 'one'} to your biolink, create a share link, or just keep ${uploaded.length === 1 ? 'it' : 'them'} in your Files.`);
    }

    setMessages(prev => [...prev, {
      role: 'assistant',
      content: lines.join('\n'),
      timestamp: Date.now(),
      isLocalAction: true,
    }]);
    } finally {
      setIsUploading(false);
    }
  }, [getClient, isUploading]);

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
      const files = Array.from(e.dataTransfer.files);
      void handleDroppedFiles(files);
    },
  };

  // ── Chat send ───────────────────────────────────────────────────────────────

  const sendMessage = useCallback(async () => {
    if (!input.trim() || isLoading) return;

    const text = input.trim();
    setInput('');

    // Check for browser management intent before sending to backend
    const intent = detectBrowserIntent(text);
    if (intent) {
      const userMsg: Message = { role: 'user', content: text, timestamp: Date.now() };
      setMessages(prev => [...prev, userMsg]);
      const response = await handleBrowserIntent(intent);
      if (response) {
        const assistantMsg: Message = {
          role: 'assistant',
          content: response,
          timestamp: Date.now(),
          isLocalAction: true,
        };
        setMessages(prev => [...prev, assistantMsg]);
      }
      return;
    }

    const client = getClient();
    if (!client) { setError('Sign in to use Zio AI'); return; }

    const excerpt = trimmedCtx?.excerpt?.trim();
    let outgoing = trimmedCtx && excerpt
      ? `${text}\n\n[Current page: ${trimmedCtx.title} (${trimmedCtx.url})]\n[Page content excerpt]:\n${excerpt.slice(0, 2500)}`
      : text;

    // Give the assistant context about files the user just dropped into the
    // panel so it can act on "add it to my biolink" style follow-ups.
    if (lastUploadsRef.current.length > 0) {
      const filesCtx = lastUploadsRef.current
        .map(f => `${f.original_name || f.filename} — ${f.url}`)
        .join('\n');
      outgoing += `\n\n[Recently uploaded files (already saved in the user's Sayzio Files)]:\n${filesCtx}`;
      lastUploadsRef.current = [];
    }

    const userMsg: Message = { role: 'user', content: text, timestamp: Date.now() };
    setMessages(prev => [...prev, userMsg]);
    setIsLoading(true);
    setError(null);

    const runStream = async (): Promise<void> => {
      let vt = visitorTokenRef.current;
      if (!vt) {
        const res = await client.assistantSession(null, buildPage());
        vt = res.visitor_token;
        visitorTokenRef.current = vt;
        storeVisitorToken(vt);
      }

      let streamingStarted = false;
      let rotatedRetry: string | null = null;
      let streamError: string | null = null;

      const appendDelta = (delta: string): void => {
        if (!delta) return;
        setMessages(prev => {
          const next = [...prev];
          const last = next[next.length - 1];
          if (last && last.role === 'assistant' && last.streaming) {
            next[next.length - 1] = { ...last, content: last.content + delta };
          } else {
            next.push({ role: 'assistant', content: delta, timestamp: Date.now(), streaming: true });
          }
          return next;
        });
      };

      await client.assistantStream(vt, outgoing, buildPage(), {
        onToken: (delta) => { streamingStarted = true; appendDelta(delta); },
        onDone: (payload) => {
          const finalText = payload.assistant_message?.content;
          setMessages(prev => {
            const next = [...prev];
            const last = next[next.length - 1];
            if (last && last.role === 'assistant' && last.streaming) {
              next[next.length - 1] = {
                ...last,
                content: finalText && finalText.trim() !== '' ? finalText : last.content,
                streaming: false,
              };
            } else if (finalText) {
              next.push({ role: 'assistant', content: finalText, timestamp: Date.now() });
            }
            return next;
          });
        },
        onError: (payload) => {
          if (payload.rotated && payload.visitor_token && !streamingStarted) {
            rotatedRetry = payload.visitor_token;
          } else {
            streamError = payload.error ?? 'The assistant could not respond right now.';
          }
        },
      });

      if (rotatedRetry) {
        visitorTokenRef.current = rotatedRetry;
        storeVisitorToken(rotatedRetry);
        rotatedRetry = null;
        await client.assistantStream(visitorTokenRef.current, outgoing, buildPage(), {
          onToken: appendDelta,
          onDone: (payload) => {
            setMessages(prev => {
              const next = [...prev];
              const last = next[next.length - 1];
              const finalText = payload.assistant_message?.content;
              if (last && last.role === 'assistant' && last.streaming) {
                next[next.length - 1] = { ...last, content: finalText || last.content, streaming: false };
              } else if (finalText) {
                next.push({ role: 'assistant', content: finalText, timestamp: Date.now() });
              }
              return next;
            });
          },
          onError: (payload) => { streamError = payload.error ?? 'The assistant could not respond right now.'; },
        });
      }

      if (streamError) setError(streamError);
    };

    try {
      await runStream();
    } catch (err) {
      const msg = err instanceof ApiClientError && err.code === 'auth_required'
        ? 'Sign in to use Zio AI'
        : (err instanceof Error ? err.message : 'Failed to send message');
      setError(msg);
    } finally {
      setMessages(prev => prev.map(m => (m.streaming ? { ...m, streaming: false } : m)));
      setIsLoading(false);
    }
  }, [input, isLoading, getClient, trimmedCtx, buildPage, handleBrowserIntent]);

  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      void sendMessage();
    }
  }, [sendMessage]);

  const saveCurrentPageToCollection = useCallback(async () => {
    if (!pageContext) return;
    const collections = await window.zio.collections.all() as Array<{ id: string; name: string }>;
    if (collections.length === 0) {
      await window.zio.collections.create('Saved Pages');
      const updated = await window.zio.collections.all() as Array<{ id: string; name: string }>;
      const col = updated[0];
      if (col) await window.zio.collections.saveLink(col.id, pageContext.url, pageContext.title);
    } else {
      const col = collections[0];
      if (col) await window.zio.collections.saveLink(col.id, pageContext.url, pageContext.title);
    }
  }, [pageContext]);

  // ── Password offer ──────────────────────────────────────────────────────────

  const handleSavePassword = useCallback(async () => {
    if (!pendingCredential) return;
    setSavingPassword(true);
    try {
      await window.zio.passwords.save(
        pendingCredential.origin,
        pendingCredential.username,
        pendingCredential.password,
      );
      setPendingCredential(null);
    } finally {
      setSavingPassword(false);
    }
  }, [pendingCredential]);

  // ── Tab visibility ──────────────────────────────────────────────────────────

  const isEmbedded = presentation === 'embedded';
  const isBrowserMode = presentation === 'overlay' || presentation === 'docked';
  const isDocked = presentation === 'docked';

  const visibleTabs: PanelTab[] = ['chat', 'contacts', 'collections'];
  if (sayzioLink) visibleTabs.push('stats');
  // Browser tab always shown when in browser mode (overlay or docked)
  if (isBrowserMode) visibleTabs.push('browser');

  // ── Root container style ────────────────────────────────────────────────────

  const containerStyle: React.CSSProperties = (() => {
    if (isEmbedded) {
      return {
        width: '100%',
        height: '100%',
        background: 'var(--color-bg-surface)',
        display: 'flex',
        flexDirection: 'column',
        flexShrink: 1,
      };
    }

    const w = panelWidth ? `${panelWidth}px` : 'var(--sidebar-width, 360px)';

    if (isDocked) {
      // With an explicit panelWidth we hold that width; without one (tab in
      // full "Ask Zio" mode) we grow to fill the whole content area.
      return {
        width: panelWidth ? w : undefined,
        flex: panelWidth ? undefined : 1,
        minWidth: 0,
        height: '100%',
        background: 'var(--color-bg-surface)',
        display: 'flex',
        flexDirection: 'column',
        flexShrink: 0,
        borderLeft: 'none',
      };
    }

    // Overlay: floating card over the page
    return {
      position: 'absolute',
      right: 0,
      top: 0,
      bottom: 0,
      width: w,
      background: 'var(--color-bg-surface)',
      display: 'flex',
      flexDirection: 'column',
      boxShadow: '-4px 0 24px rgba(0,0,0,0.18)',
      borderLeft: '1px solid var(--color-border)',
      zIndex: 50,
    };
  })();

  return (
    <div
      style={{ position: 'relative', ...containerStyle }}
      {...dragHandlers}
    >
      {/* ── Drag & drop overlay ────────────────────────────────────────────── */}
      {isDragging && (
        <div style={{
          position: 'absolute',
          inset: 0,
          zIndex: 100,
          background: 'rgba(99, 102, 241, 0.12)',
          border: '2px dashed var(--color-primary, #6366f1)',
          borderRadius: 8,
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          gap: 8,
          pointerEvents: 'none',
        }}>
          <span style={{ fontSize: 32 }}>📎</span>
          <span style={{ fontWeight: 600, fontSize: 14 }}>Drop files to upload to your Files</span>
        </div>
      )}
      {isUploading && (
        <div style={{
          position: 'absolute',
          top: 8,
          right: 8,
          zIndex: 100,
          background: 'var(--color-bg-surface)',
          border: '1px solid var(--color-border)',
          borderRadius: 6,
          padding: '4px 10px',
          fontSize: 12,
          color: 'var(--color-text-muted)',
        }}>Uploading…</div>
      )}
      {/* ── Header ─────────────────────────────────────────────────────────── */}
      <div style={{
        padding: '12px 16px',
        borderBottom: '1px solid var(--color-border)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: 8,
        flexShrink: 0,
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, minWidth: 0, flex: 1 }}>
          <span style={{ fontSize: 18, flexShrink: 0 }}>⚡</span>
          <span style={{ fontWeight: 700, fontSize: 15, flexShrink: 0 }}>Zio</span>
          <ProfileBadge variant="pill" style={{ flexShrink: 0 }} />
          {pageContext && (
            <span style={{ fontSize: 11, color: 'var(--color-text-muted)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
              — {pageContext.title}
            </span>
          )}
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 6, flexShrink: 0 }}>
          {/* Add to biolink quick action */}
          {token && pageContext?.url && (
            <button
              onClick={() => {
                setAddToBiolinkPayload({ url: pageContext.url, title: pageContext.title });
                setAddToBiolinkOpen(true);
              }}
              title="Add this page to my biolink"
              style={headerSmallBtn}
            >+ Biolink</button>
          )}
          {/* Overlay / docked toggle (browser mode only) */}
          {isBrowserMode && onSetDocked && (
            <button
              onClick={() => onSetDocked(!isDocked)}
              title={isDocked ? 'Switch to overlay mode' : 'Dock the panel'}
              style={headerSmallBtn}
            >{isDocked ? '🪟 Overlay' : '⊡ Dock'}</button>
          )}
          <button onClick={onClose} style={{ opacity: 0.6, fontSize: 16, padding: 2, flexShrink: 0 }}>✕</button>
        </div>
      </div>

      {/* ── Password offer banner ────────────────────────────────────────── */}
      {pendingCredential && (
        <PasswordOfferBanner
          credential={pendingCredential}
          saving={savingPassword}
          onSave={() => void handleSavePassword()}
          onDismiss={() => setPendingCredential(null)}
        />
      )}

      {/* ── Tab nav ──────────────────────────────────────────────────────── */}
      <div style={{
        display: 'flex',
        borderBottom: '1px solid var(--color-border)',
        padding: '0 12px',
        flexShrink: 0,
        overflowX: 'auto',
      }}>
        {visibleTabs.map(tab => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            style={{
              padding: '8px 10px',
              fontSize: 12,
              fontWeight: activeTab === tab ? 600 : 400,
              color: activeTab === tab ? 'var(--color-primary)' : 'var(--color-text-muted)',
              borderBottom: activeTab === tab ? '2px solid var(--color-primary)' : '2px solid transparent',
              marginBottom: -1,
              whiteSpace: 'nowrap',
              flexShrink: 0,
            }}
          >{TAB_LABELS[tab]}</button>
        ))}
      </div>

      {/* ── Content ──────────────────────────────────────────────────────── */}
      <div style={{ flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
        {activeTab === 'chat' && (
          <ChatView
            messages={messages}
            input={input}
            isLoading={isLoading}
            error={error}
            greeting={greeting}
            trimmedCtx={trimmedCtx}
            messagesEndRef={messagesEndRef}
            onInputChange={setInput}
            onKeyDown={handleKeyDown}
            onSend={() => void sendMessage()}
          />
        )}

        {activeTab === 'contacts' && pageContext && (
          <ContactExtractorView url={pageContext.url} title={pageContext.title} trimmedCtx={trimmedCtx} />
        )}

        {activeTab === 'collections' && (
          <CollectionsView onSaveCurrent={saveCurrentPageToCollection} currentUrl={pageContext?.url} />
        )}

        {activeTab === 'stats' && sayzioLink && (
          <StatsView alias={sayzioLink.alias} baseUrl={BASE_URL} token={token} />
        )}

        {activeTab === 'browser' && (
          <BrowserToolsView
            currentUrl={pageContext?.url ?? null}
            focusSection={browserFocusSection}
            onFocusSectionConsumed={() => setBrowserFocusSection(null)}
          />
        )}
      </div>

      {/* Add-to-biolink modal */}
      {addToBiolinkOpen && token && addToBiolinkPayload && (
        <AddToBiolinkModal
          pageUrl={addToBiolinkPayload.url}
          pageTitle={addToBiolinkPayload.title}
          baseUrl={BASE_URL}
          token={token}
          onClose={() => setAddToBiolinkOpen(false)}
        />
      )}
    </div>
  );
}

// ── Password offer banner ─────────────────────────────────────────────────────

function PasswordOfferBanner({
  credential,
  saving,
  onSave,
  onDismiss,
}: {
  credential: { origin: string; username: string };
  saving: boolean;
  onSave: () => void;
  onDismiss: () => void;
}) {
  return (
    <div style={{
      margin: '8px 12px 0',
      padding: '10px 12px',
      borderRadius: 10,
      background: 'color-mix(in srgb, var(--color-primary) 8%, var(--color-bg-elevated))',
      border: '1px solid color-mix(in srgb, var(--color-primary) 25%, transparent)',
      flexShrink: 0,
    }}>
      <p style={{ fontSize: 12, fontWeight: 600, marginBottom: 2, color: 'var(--color-text)' }}>
        🔑 Save password for {credential.origin}?
      </p>
      <p style={{ fontSize: 11, color: 'var(--color-text-muted)', marginBottom: 8 }}>
        Username: {credential.username}
      </p>
      <div style={{ display: 'flex', gap: 8 }}>
        <button
          onClick={onSave}
          disabled={saving}
          style={{
            flex: 1,
            padding: '5px 10px',
            borderRadius: 8,
            background: 'var(--gradient-primary)',
            color: '#fff',
            fontSize: 11,
            fontWeight: 600,
            opacity: saving ? 0.6 : 1,
          }}
        >{saving ? 'Saving…' : 'Save'}</button>
        <button
          onClick={onDismiss}
          style={{
            flex: 1,
            padding: '5px 10px',
            borderRadius: 8,
            background: 'var(--color-bg)',
            border: '1px solid var(--color-border)',
            color: 'var(--color-text-muted)',
            fontSize: 11,
          }}
        >Not now</button>
      </div>
    </div>
  );
}

// ── Chat view ─────────────────────────────────────────────────────────────────

function ChatView({
  messages,
  input,
  isLoading,
  error,
  greeting,
  trimmedCtx,
  messagesEndRef,
  onInputChange,
  onKeyDown,
  onSend,
}: {
  messages: Message[];
  input: string;
  isLoading: boolean;
  error: string | null;
  greeting: string | null;
  trimmedCtx: TrimmedContext | null;
  messagesEndRef: React.RefObject<HTMLDivElement | null>;
  onInputChange: (v: string) => void;
  onKeyDown: (e: React.KeyboardEvent) => void;
  onSend: () => void;
}) {
  return (
    <>
      <div style={{ flex: 1, overflowY: 'auto', padding: 16, display: 'flex', flexDirection: 'column', gap: 12 }}>
        {messages.length === 0 && (
          <div style={{ textAlign: 'center', color: 'var(--color-text-muted)', marginTop: 40 }}>
            <div style={{ fontSize: 32, marginBottom: 12 }}>⚡</div>
            <p style={{ fontSize: 14 }}>{greeting ?? 'Ask Zio anything about this page'}</p>
            {trimmedCtx && (
              <p style={{ fontSize: 11, marginTop: 8, opacity: 0.6 }}>
                Context loaded: {trimmedCtx.title}
              </p>
            )}
            <p style={{ fontSize: 11, marginTop: 12, opacity: 0.5 }}>
              Try: "show history", "clear cookies", "saved passwords"
            </p>
          </div>
        )}
        {messages.map((msg, i) => (
          <div key={i} style={{ display: 'flex', justifyContent: msg.role === 'user' ? 'flex-end' : 'flex-start' }}>
            <div style={{
              maxWidth: '85%',
              padding: '8px 12px',
              borderRadius: msg.role === 'user' ? '12px 12px 4px 12px' : '12px 12px 12px 4px',
              background: msg.role === 'user' ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
              color: msg.role === 'user' ? '#fff' : 'var(--color-text)',
              fontSize: 13,
              lineHeight: 1.5,
              whiteSpace: 'pre-wrap',
              borderLeft: msg.isLocalAction ? '3px solid var(--color-primary)' : undefined,
            }}>
              {msg.content}
            </div>
          </div>
        ))}
        {isLoading && !messages[messages.length - 1]?.streaming && (
          <div style={{ display: 'flex', justifyContent: 'flex-start' }}>
            <div style={{
              padding: '8px 12px',
              borderRadius: '12px 12px 12px 4px',
              background: 'var(--color-bg-elevated)',
              fontSize: 13,
              color: 'var(--color-text-muted)',
            }}>Zio is thinking…</div>
          </div>
        )}
        {error && (
          <div style={{ color: 'var(--color-danger)', fontSize: 12, textAlign: 'center' }}>{error}</div>
        )}
        <div ref={messagesEndRef} />
      </div>
      <div style={{ padding: 12, borderTop: '1px solid var(--color-border)', flexShrink: 0 }}>
        <div style={{ display: 'flex', gap: 8, alignItems: 'flex-end' }}>
          <textarea
            value={input}
            onChange={e => onInputChange(e.target.value)}
            onKeyDown={onKeyDown}
            placeholder='Ask about this page… or "show history"'
            style={{
              flex: 1,
              minHeight: 36,
              maxHeight: 120,
              resize: 'none',
              borderRadius: 10,
              border: '1px solid var(--color-border)',
              background: 'var(--color-bg)',
              color: 'var(--color-text)',
              padding: '8px 12px',
              fontSize: 13,
              outline: 'none',
              fontFamily: 'inherit',
            }}
          />
          <button
            onClick={onSend}
            disabled={!input.trim() || isLoading}
            style={{
              padding: '8px 14px',
              borderRadius: 10,
              background: 'var(--gradient-primary)',
              color: '#fff',
              fontSize: 13,
              fontWeight: 600,
              opacity: !input.trim() || isLoading ? 0.5 : 1,
              flexShrink: 0,
            }}
          >Send</button>
        </div>
      </div>
    </>
  );
}

// ── Stats view ────────────────────────────────────────────────────────────────

function StatsView({ alias, baseUrl, token }: { alias: string; baseUrl: string; token: string | null }) {
  const [analytics, setAnalytics] = useState<LinkAnalytics | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [link, setLink] = useState<ApiLink | null>(null);
  const [subTab, setSubTab] = useState<'stats' | 'edit'>('stats');
  const [currentAlias, setCurrentAlias] = useState(alias);

  useEffect(() => {
    if (!token) return;
    const client = new ApiClient({ baseUrl, token });
    setLoading(true);
    setError(null);
    setAnalytics(null);
    setLink(null);
    setSubTab('stats');
    setCurrentAlias(alias);

    void (async () => {
      try {
        const page = await client.listLinks({ q: alias, per_page: 20 });
        const match = page.items.find(l => l.alias === alias);
        if (!match) {
          setError('This link was not found in your account.');
          setLoading(false);
          return;
        }
        setLink(match);
        const stats = await client.getLinkAnalytics(match.id);
        setAnalytics(stats);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load stats');
      } finally {
        setLoading(false);
      }
    })();
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [alias, token]);

  const linkId = link?.id ?? null;

  const handleOpenDashboard = useCallback(() => {
    if (!linkId) return;
    void window.zio.shell.openExternal(`${baseUrl}/user/links/${linkId}/analytics`);
  }, [linkId, baseUrl]);

  if (!token) {
    return (
      <div style={{ padding: 24, textAlign: 'center', color: 'var(--color-text-muted)' }}>
        <p style={{ fontSize: 14 }}>Sign in to see link stats</p>
      </div>
    );
  }

  if (loading) {
    return (
      <div style={{ padding: 24, textAlign: 'center', color: 'var(--color-text-muted)' }}>
        <p style={{ fontSize: 14 }}>Loading stats…</p>
      </div>
    );
  }

  if (error) {
    return (
      <div style={{ padding: 24, textAlign: 'center' }}>
        <p style={{ fontSize: 13, color: 'var(--color-text-muted)', marginBottom: 4 }}>/{alias}</p>
        <p style={{ fontSize: 12, color: 'var(--color-danger, #ef4444)' }}>{error}</p>
      </div>
    );
  }

  if (!analytics || !link) return null;

  const header = (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <span style={{
          fontSize: 12,
          fontWeight: 600,
          color: 'var(--color-primary)',
          background: 'color-mix(in srgb, var(--color-primary) 12%, transparent)',
          padding: '3px 10px',
          borderRadius: 20,
        }}>/{currentAlias}</span>
        {/* Stats / Edit sub-tab switch (only shown for owned links) */}
        <div style={{ display: 'flex', gap: 2, background: 'var(--color-bg-elevated)', borderRadius: 8, padding: 2 }}>
          {(['stats', 'edit'] as const).map(st => (
            <button
              key={st}
              onClick={() => setSubTab(st)}
              style={{
                fontSize: 11,
                fontWeight: subTab === st ? 600 : 400,
                padding: '3px 10px',
                borderRadius: 6,
                background: subTab === st ? 'var(--color-bg-surface)' : 'transparent',
                color: subTab === st ? 'var(--color-text)' : 'var(--color-text-muted)',
                boxShadow: subTab === st ? '0 1px 2px rgba(0,0,0,0.12)' : 'none',
              }}
            >{st === 'stats' ? 'Stats' : 'Edit'}</button>
          ))}
        </div>
      </div>
      {linkId && subTab === 'stats' && (
        <button
          onClick={handleOpenDashboard}
          style={{ fontSize: 11, color: 'var(--color-text-muted)', textDecoration: 'underline', cursor: 'pointer' }}
        >Full dashboard ↗</button>
      )}
    </div>
  );

  if (subTab === 'edit') {
    return (
      <div style={{ padding: 16, overflowY: 'auto', flex: 1, display: 'flex', flexDirection: 'column', gap: 16 }}>
        {header}
        <LinkEditForm
          link={link}
          baseUrl={baseUrl}
          token={token}
          onSaved={updated => {
            setLink(updated);
            setCurrentAlias(updated.alias);
          }}
        />
      </div>
    );
  }

  return (
    <div style={{ padding: 16, overflowY: 'auto', flex: 1, display: 'flex', flexDirection: 'column', gap: 16 }}>
      {header}

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
        <StatCard label="Total clicks" value={analytics.total_clicks.toLocaleString()} />
        <StatCard label="Unique clicks" value={analytics.unique_clicks.toLocaleString()} />
      </div>

      {analytics.by_country.length > 0 && (
        <section>
          <SectionTitle>Top countries</SectionTitle>
          {analytics.by_country.slice(0, 5).map(r => (
            <BarRow key={r.country} label={r.country || 'Unknown'} value={r.clicks} max={analytics.by_country[0]!.clicks} />
          ))}
        </section>
      )}

      {analytics.by_device.length > 0 && (
        <section>
          <SectionTitle>Devices</SectionTitle>
          {analytics.by_device.slice(0, 4).map(r => (
            <BarRow key={r.device_type} label={r.device_type || 'Unknown'} value={r.clicks} max={analytics.by_device[0]!.clicks} />
          ))}
        </section>
      )}

      {analytics.by_day.length > 0 && (
        <section>
          <SectionTitle>Last 30 days</SectionTitle>
          <Sparkline data={analytics.by_day} />
        </section>
      )}

      <p style={{ fontSize: 10, color: 'var(--color-text-muted)', marginTop: 'auto' }}>
        30-day window · {new Date(analytics.window.from).toLocaleDateString()} – {new Date(analytics.window.to).toLocaleDateString()}
      </p>
    </div>
  );
}

// ── Inline link edit form (Edit sub-tab of the Stats view) ───────────────────

const VISIBILITY_OPTIONS: Array<{ value: 'public' | 'registered' | 'followers' | 'subscribers'; label: string }> = [
  { value: 'public', label: 'Public' },
  { value: 'registered', label: 'Signed-in users' },
  { value: 'followers', label: 'Followers' },
  { value: 'subscribers', label: 'Subscribers' },
];

/** Pull the first field-level validation message out of a 422 error payload. */
function firstValidationMessage(details: unknown): string | null {
  if (!details || typeof details !== 'object') return null;
  for (const value of Object.values(details as Record<string, unknown>)) {
    if (Array.isArray(value) && typeof value[0] === 'string') return value[0];
    if (typeof value === 'string') return value;
  }
  return null;
}

function LinkEditForm({
  link,
  baseUrl,
  token,
  onSaved,
}: {
  link: ApiLink;
  baseUrl: string;
  token: string;
  onSaved: (updated: ApiLink) => void;
}) {
  const [title, setTitle] = useState(link.title ?? '');
  const [aliasValue, setAliasValue] = useState(link.alias);
  const [visibility, setVisibility] = useState(link.visibility);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [savedAt, setSavedAt] = useState<number | null>(null);

  const dirty =
    title !== (link.title ?? '') ||
    aliasValue !== link.alias ||
    visibility !== link.visibility;

  const handleSave = useCallback(async () => {
    setSaving(true);
    setSaveError(null);
    setSavedAt(null);
    try {
      const payload: UpdateLinkPayload = {};
      if (title !== (link.title ?? '')) payload.title = title.trim() === '' ? null : title.trim();
      if (aliasValue !== link.alias) payload.alias = aliasValue.trim();
      if (visibility !== link.visibility) payload.visibility = visibility as UpdateLinkPayload['visibility'];
      if (Object.keys(payload).length === 0) { setSaving(false); return; }

      const client = new ApiClient({ baseUrl, token });
      const res = await client.updateLink(link.id, payload);
      onSaved(res.link);
      setSavedAt(Date.now());
    } catch (err) {
      if (err instanceof ApiClientError) {
        setSaveError(firstValidationMessage(err.details) ?? err.message);
      } else {
        setSaveError(err instanceof Error ? err.message : 'Failed to save changes');
      }
    } finally {
      setSaving(false);
    }
  }, [title, aliasValue, visibility, link, baseUrl, token, onSaved]);

  const handleOpenFullEditor = useCallback(() => {
    // /user/links/{id}/edit type-routes to the right editor server-side
    // (biolink → appearance settings, vcf → vCard builder, etc).
    void window.zio.shell.openExternal(`${baseUrl}/user/links/${link.id}/edit`);
  }, [baseUrl, link.id]);

  const fieldLabel: React.CSSProperties = {
    fontSize: 11,
    fontWeight: 600,
    color: 'var(--color-text-muted)',
    textTransform: 'uppercase',
    letterSpacing: '0.06em',
    marginBottom: 4,
    display: 'block',
  };
  const fieldInput: React.CSSProperties = {
    width: '100%',
    borderRadius: 8,
    border: '1px solid var(--color-border)',
    background: 'var(--color-bg)',
    color: 'var(--color-text)',
    padding: '8px 10px',
    fontSize: 13,
    outline: 'none',
    fontFamily: 'inherit',
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      <div>
        <label style={fieldLabel}>Title</label>
        <input
          value={title}
          onChange={e => setTitle(e.target.value)}
          maxLength={200}
          placeholder="Untitled link"
          style={fieldInput}
        />
      </div>

      <div>
        <label style={fieldLabel}>Alias</label>
        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
          <span style={{ fontSize: 13, color: 'var(--color-text-muted)', flexShrink: 0 }}>/</span>
          <input
            value={aliasValue}
            onChange={e => setAliasValue(e.target.value)}
            maxLength={100}
            spellCheck={false}
            style={{ ...fieldInput, fontFamily: 'ui-monospace, monospace' }}
          />
        </div>
      </div>

      <div>
        <label style={fieldLabel}>Visibility</label>
        <select
          value={visibility}
          onChange={e => setVisibility(e.target.value)}
          style={{ ...fieldInput, appearance: 'auto', cursor: 'pointer' }}
        >
          {VISIBILITY_OPTIONS.map(opt => (
            <option key={opt.value} value={opt.value}>{opt.label}</option>
          ))}
        </select>
      </div>

      {saveError && (
        <p style={{ fontSize: 12, color: 'var(--color-danger, #ef4444)' }}>{saveError}</p>
      )}
      {savedAt !== null && !dirty && !saveError && (
        <p style={{ fontSize: 12, color: 'var(--color-success, #22c55e)' }}>Changes saved ✓</p>
      )}

      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
        <button
          onClick={() => void handleSave()}
          disabled={!dirty || saving}
          style={{
            padding: '8px 16px',
            borderRadius: 10,
            background: 'var(--gradient-primary)',
            color: '#fff',
            fontSize: 13,
            fontWeight: 600,
            opacity: !dirty || saving ? 0.5 : 1,
            cursor: !dirty || saving ? 'default' : 'pointer',
          }}
        >{saving ? 'Saving…' : 'Save changes'}</button>
        <button
          onClick={handleOpenFullEditor}
          style={{ fontSize: 12, color: 'var(--color-primary)', textDecoration: 'underline', cursor: 'pointer' }}
        >Full editor →</button>
      </div>

      <p style={{ fontSize: 10, color: 'var(--color-text-muted)' }}>
        Blocks, appearance, and advanced settings live in the full editor.
      </p>
    </div>
  );
}

function StatCard({ label, value }: { label: string; value: string }) {
  return (
    <div style={{
      padding: '12px 14px',
      borderRadius: 10,
      background: 'var(--color-bg-elevated)',
      border: '1px solid var(--color-border)',
    }}>
      <div style={{ fontSize: 22, fontWeight: 700, lineHeight: 1 }}>{value}</div>
      <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 4 }}>{label}</div>
    </div>
  );
}

function SectionTitle({ children }: { children: React.ReactNode }) {
  return (
    <p style={{ fontSize: 11, fontWeight: 600, color: 'var(--color-text-muted)', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: 8 }}>
      {children}
    </p>
  );
}

function BarRow({ label, value, max }: { label: string; value: number; max: number }) {
  const pct = max > 0 ? Math.round((value / max) * 100) : 0;
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6 }}>
      <span style={{ fontSize: 12, width: 90, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', flexShrink: 0 }}>
        {label}
      </span>
      <div style={{ flex: 1, height: 6, borderRadius: 4, background: 'var(--color-border)', overflow: 'hidden' }}>
        <div style={{ width: `${pct}%`, height: '100%', background: 'var(--gradient-primary)', borderRadius: 4 }} />
      </div>
      <span style={{ fontSize: 11, color: 'var(--color-text-muted)', width: 32, textAlign: 'right', flexShrink: 0 }}>
        {value.toLocaleString()}
      </span>
    </div>
  );
}

function Sparkline({ data }: { data: Array<{ date: string; clicks: number }> }) {
  const maxVal = Math.max(...data.map(d => d.clicks), 1);
  const recent = data.slice(-30);
  return (
    <div style={{ display: 'flex', alignItems: 'flex-end', gap: 2, height: 48 }}>
      {recent.map((d, i) => {
        const h = Math.max(2, Math.round((d.clicks / maxVal) * 44));
        return (
          <div
            key={i}
            title={`${d.date}: ${d.clicks}`}
            style={{
              flex: 1,
              height: h,
              borderRadius: 2,
              background: d.clicks > 0 ? 'var(--color-primary)' : 'var(--color-border)',
              opacity: 0.75,
              transition: 'height 0.3s',
            }}
          />
        );
      })}
    </div>
  );
}

// ── Contact extractor ─────────────────────────────────────────────────────────

type ContactSaveState =
  | { kind: 'idle' }
  | { kind: 'saving' }
  | { kind: 'saved'; contactId: number; name: string }
  | { kind: 'duplicate'; existingId: number; existingName: string; existingEmails: string[]; existingPhones: string[] }
  | { kind: 'updating' }
  | { kind: 'updated' }
  | { kind: 'skipped' }
  | { kind: 'limit_reached' }
  | { kind: 'error'; message: string };

interface ContactEntry {
  emails: string[];
  phones: string[];
  key: string;
}

function profileToAutofillCard(profile: ApiUserProfile): AutofillCard {
  const nameParts = (profile.name ?? '').trim().split(/\s+/);
  return {
    full_name: profile.name ?? undefined,
    given_name: profile.given_name ?? (nameParts[0] ?? undefined),
    family_name: profile.family_name ?? (nameParts.length > 1 ? nameParts.slice(1).join(' ') : undefined),
    email: profile.email ?? undefined,
    phone: profile.phone ?? undefined,
    organization: profile.organization ?? undefined,
    job_title: profile.job_title ?? undefined,
    website: profile.website ?? undefined,
  };
}

function ContactExtractorView({ url, trimmedCtx }: { url: string; title: string; trimmedCtx: TrimmedContext | null }) {
  const { token } = useAuthStore();
  const [saveStates, setSaveStates] = useState<Record<string, ContactSaveState>>({});
  const [profile, setProfile] = useState<ApiUserProfile | null>(null);
  const [autofillResult, setAutofillResult] = useState<AutofillResult | null>(null);
  const [isAutofilling, setIsAutofilling] = useState(false);

  useEffect(() => {
    if (!token) { setProfile(null); return; }
    const client = new ApiClient({ baseUrl: BASE_URL, token });
    void (async () => {
      try {
        const res = await client.getProfile();
        setProfile(res.user);
      } catch { /* Non-critical */ }
    })();
  }, [token]);

  const contacts: ContactEntry[] = (() => {
    if (!trimmedCtx) return [];
    const emails = trimmedCtx.emails;
    const phones = trimmedCtx.phones;
    if (emails.length === 0 && phones.length === 0) return [];
    if (emails.length <= 3 && phones.length <= 3) {
      return [{ emails, phones, key: [...emails, ...phones].join('|') }];
    }
    const entries: ContactEntry[] = [];
    for (const e of emails.slice(0, 10)) entries.push({ emails: [e], phones: [], key: `email:${e}` });
    for (const p of phones.slice(0, 10)) entries.push({ emails: [], phones: [p], key: `phone:${p}` });
    return entries;
  })();

  const setSaveState = (key: string, state: ContactSaveState) =>
    setSaveStates(prev => ({ ...prev, [key]: state }));

  const handleSave = useCallback(async (entry: ContactEntry) => {
    if (!token) return;
    setSaveState(entry.key, { kind: 'saving' });
    const client = new ApiClient({ baseUrl: BASE_URL, token });
    try {
      const res = await client.createContact({
        emails: entry.emails.map(e => ({ value: e })),
        phones: entry.phones.map(p => ({ value: p })),
        source_url: url,
      });
      setSaveState(entry.key, { kind: 'saved', contactId: res.contact.id, name: res.contact.display_name });
    } catch (err) {
      if (err instanceof ApiClientError) {
        if (err.status === 409) {
          const details = err.details as { duplicate_of?: number } | undefined;
          const dupId = details?.duplicate_of;
          if (dupId) {
            try {
              const existing = await client.getContact(dupId);
              setSaveState(entry.key, {
                kind: 'duplicate',
                existingId: existing.contact.id,
                existingName: existing.contact.display_name,
                existingEmails: existing.contact.emails.map(e => e.value),
                existingPhones: existing.contact.phones.map(p => p.value),
              });
            } catch {
              setSaveState(entry.key, { kind: 'duplicate', existingId: dupId, existingName: 'Existing contact', existingEmails: [], existingPhones: [] });
            }
          } else {
            setSaveState(entry.key, { kind: 'error', message: err.message });
          }
        } else if (err.status === 402) {
          setSaveState(entry.key, { kind: 'limit_reached' });
        } else {
          setSaveState(entry.key, { kind: 'error', message: err.message });
        }
      } else {
        setSaveState(entry.key, { kind: 'error', message: 'Failed to save contact' });
      }
    }
  }, [token, url]);

  const handleUpdate = useCallback(async (entry: ContactEntry, existingId: number) => {
    if (!token) return;
    setSaveState(entry.key, { kind: 'updating' });
    const client = new ApiClient({ baseUrl: BASE_URL, token });
    try {
      await client.updateContact(existingId, {
        emails: entry.emails.map(e => ({ value: e })),
        phones: entry.phones.map(p => ({ value: p })),
        source_url: url,
      });
      setSaveState(entry.key, { kind: 'updated' });
    } catch (err) {
      setSaveState(entry.key, { kind: 'error', message: err instanceof Error ? err.message : 'Update failed' });
    }
  }, [token, url]);

  const handleAutofill = useCallback(async () => {
    if (!token || !profile) return;
    setIsAutofilling(true);
    setAutofillResult(null);
    try {
      const activeTabId = await window.zio.tabs.getActive() as string | null;
      if (!activeTabId) return;
      const card = profileToAutofillCard(profile);
      const result = await window.zio.tabs.autofillForm(activeTabId, card as Record<string, string | undefined>) as AutofillResult;
      setAutofillResult(result);
    } catch {
      setAutofillResult({ filled: 0, filled_fields: [] });
    } finally {
      setIsAutofilling(false);
    }
  }, [token, profile]);

  if (!token) {
    return (
      <div style={{ padding: 24, textAlign: 'center', color: 'var(--color-text-muted)' }}>
        <div style={{ fontSize: 28, marginBottom: 10 }}>🔒</div>
        <p style={{ fontSize: 14, marginBottom: 6 }}>Sign in to save contacts and autofill forms</p>
        <p style={{ fontSize: 12, opacity: 0.7 }}>Your Sayzio account is required for these features.</p>
      </div>
    );
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', flex: 1, overflow: 'hidden' }}>
      <div style={{ padding: '12px 16px', borderBottom: '1px solid var(--color-border)', background: 'var(--color-bg-elevated)' }}>
        <p style={{ fontSize: 11, fontWeight: 600, color: 'var(--color-text-muted)', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: 8 }}>
          Form autofill
        </p>
        {profile ? (
          <div>
            <p style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 8 }}>
              Fill this page's forms using your digital card ({profile.name}{profile.email ? ` · ${profile.email}` : ''}).
            </p>
            <button
              onClick={() => void handleAutofill()}
              disabled={isAutofilling}
              style={{
                width: '100%',
                padding: '8px 12px',
                borderRadius: 8,
                background: isAutofilling ? 'var(--color-bg)' : 'var(--color-primary)',
                color: isAutofilling ? 'var(--color-text-muted)' : '#fff',
                border: '1px solid var(--color-border)',
                fontSize: 12,
                fontWeight: 600,
                cursor: isAutofilling ? 'default' : 'pointer',
              }}
            >{isAutofilling ? 'Filling…' : '⌨ Fill this form'}</button>
            {autofillResult && (
              <p style={{ fontSize: 11, marginTop: 6, color: autofillResult.filled > 0 ? 'var(--color-success, #22c55e)' : 'var(--color-text-muted)' }}>
                {autofillResult.filled > 0
                  ? `✓ Filled ${autofillResult.filled} field${autofillResult.filled === 1 ? '' : 's'} (${autofillResult.filled_fields.map(f => f.replace('_', ' ')).join(', ')})`
                  : 'No fillable form fields found on this page.'}
              </p>
            )}
          </div>
        ) : (
          <p style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>Loading your card…</p>
        )}
      </div>

      <div style={{ flex: 1, overflowY: 'auto', padding: 16 }}>
        {contacts.length === 0 ? (
          <div style={{ textAlign: 'center', color: 'var(--color-text-muted)', marginTop: 24 }}>
            <p style={{ fontSize: 14 }}>No contacts detected on this page</p>
            <p style={{ fontSize: 11, marginTop: 6, opacity: 0.7 }}>Emails and phone numbers found on the page will appear here.</p>
          </div>
        ) : (
          <>
            <p style={{ fontSize: 11, fontWeight: 600, color: 'var(--color-text-muted)', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: 12 }}>
              Found on page
            </p>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              {contacts.map(entry => (
                <ContactEntryCard
                  key={entry.key}
                  entry={entry}
                  state={saveStates[entry.key] ?? { kind: 'idle' }}
                  onSave={() => void handleSave(entry)}
                  onUpdate={(existingId) => void handleUpdate(entry, existingId)}
                  onSkip={() => setSaveState(entry.key, { kind: 'skipped' })}
                />
              ))}
            </div>
          </>
        )}
      </div>
    </div>
  );
}

function ContactEntryCard({
  entry,
  state,
  onSave,
  onUpdate,
  onSkip,
}: {
  entry: ContactEntry;
  state: ContactSaveState;
  onSave: () => void;
  onUpdate: (existingId: number) => void;
  onSkip: () => void;
}) {
  return (
    <div style={{ borderRadius: 10, border: '1px solid var(--color-border)', background: 'var(--color-bg-elevated)', padding: '10px 12px' }}>
      {entry.emails.map(e => (
        <div key={e} style={{ fontSize: 12, color: 'var(--color-text)', marginBottom: 3, display: 'flex', alignItems: 'center', gap: 6 }}>
          <span style={{ opacity: 0.5, fontSize: 11 }}>✉</span> {e}
        </div>
      ))}
      {entry.phones.map(p => (
        <div key={p} style={{ fontSize: 12, color: 'var(--color-text)', marginBottom: 3, display: 'flex', alignItems: 'center', gap: 6 }}>
          <span style={{ opacity: 0.5, fontSize: 11 }}>📞</span> {p}
        </div>
      ))}
      <div style={{ marginTop: 8 }}>
        {state.kind === 'idle' && (
          <button onClick={onSave} style={{ padding: '5px 14px', borderRadius: 6, background: 'var(--gradient-primary)', color: '#fff', fontSize: 11, fontWeight: 600 }}>
            Save to Contacts
          </button>
        )}
        {state.kind === 'saving' && <span style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>Saving…</span>}
        {state.kind === 'saved' && <span style={{ fontSize: 11, color: 'var(--color-success, #22c55e)' }}>✓ Saved as "{state.name}"</span>}
        {state.kind === 'duplicate' && (
          <div>
            <div style={{ padding: '6px 8px', borderRadius: 6, background: 'color-mix(in srgb, var(--color-primary) 8%, transparent)', border: '1px solid color-mix(in srgb, var(--color-primary) 20%, transparent)', marginBottom: 6, fontSize: 11, color: 'var(--color-text)' }}>
              <span style={{ fontWeight: 600 }}>Match found: </span>{state.existingName}
              {state.existingEmails.length > 0 && <span style={{ color: 'var(--color-text-muted)', display: 'block', marginTop: 2 }}>{state.existingEmails.slice(0, 2).join(', ')}</span>}
            </div>
            <div style={{ display: 'flex', gap: 6 }}>
              <button onClick={() => onUpdate(state.existingId)} style={{ flex: 1, padding: '5px 8px', borderRadius: 6, background: 'var(--gradient-primary)', color: '#fff', fontSize: 11, fontWeight: 600 }}>Update</button>
              <button onClick={onSkip} style={{ flex: 1, padding: '5px 8px', borderRadius: 6, background: 'var(--color-bg)', border: '1px solid var(--color-border)', color: 'var(--color-text-muted)', fontSize: 11 }}>Skip</button>
            </div>
          </div>
        )}
        {state.kind === 'updating' && <span style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>Updating…</span>}
        {state.kind === 'updated' && <span style={{ fontSize: 11, color: 'var(--color-success, #22c55e)' }}>✓ Contact updated</span>}
        {state.kind === 'skipped' && <span style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>Skipped</span>}
        {state.kind === 'limit_reached' && (
          <div>
            <span style={{ fontSize: 11, color: 'var(--color-danger, #ef4444)' }}>Contact limit reached on your plan. </span>
            <a href="#" onClick={e => { e.preventDefault(); void window.zio.shell.openExternal(`${BASE_URL}/user/upgrade`); }} style={{ fontSize: 11, color: 'var(--color-primary)', textDecoration: 'underline' }}>Upgrade</a>
          </div>
        )}
        {state.kind === 'error' && (
          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <span style={{ fontSize: 11, color: 'var(--color-danger, #ef4444)' }}>{state.message}</span>
            <button onClick={onSave} style={{ fontSize: 10, color: 'var(--color-primary)', textDecoration: 'underline' }}>Retry</button>
          </div>
        )}
      </div>
    </div>
  );
}

// ── Collections view ──────────────────────────────────────────────────────────

function CollectionsView({ onSaveCurrent, currentUrl }: { onSaveCurrent: () => Promise<void>; currentUrl?: string }) {
  const [collections, setCollections] = useState<Array<{ id: string; name: string; item_count?: number }>>([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [newName, setNewName] = useState('');
  const [creating, setCreating] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const cols = await window.zio.collections.all() as Array<{ id: string; name: string; item_count?: number }>;
      setCollections(cols);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const handleSave = async () => {
    setSaving(true);
    setSaved(false);
    try {
      await onSaveCurrent();
      setSaved(true);
      await load();
    } finally {
      setSaving(false);
    }
  };

  const handleCreate = async () => {
    if (!newName.trim()) return;
    setCreating(true);
    try {
      await window.zio.collections.create(newName.trim());
      setNewName('');
      await load();
    } finally {
      setCreating(false);
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', flex: 1, overflow: 'hidden' }}>
      {/* Active profile ribbon — collections are scoped to this profile */}
      <div style={{ padding: '10px 16px 0' }}>
        <ProfileBadge variant="ribbon" />
      </div>

      {currentUrl && (
        <div style={{ padding: '12px 16px', borderBottom: '1px solid var(--color-border)', background: 'var(--color-bg-elevated)' }}>
          <button
            onClick={() => void handleSave()}
            disabled={saving}
            style={{ width: '100%', padding: '8px 12px', borderRadius: 8, background: 'var(--gradient-primary)', color: '#fff', fontSize: 12, fontWeight: 600, opacity: saving ? 0.6 : 1 }}
          >{saving ? 'Saving…' : saved ? '✓ Saved to collection' : '+ Save this page'}</button>
        </div>
      )}

      <div style={{ flex: 1, overflowY: 'auto', padding: 16 }}>
        <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
          <input
            value={newName}
            onChange={e => setNewName(e.target.value)}
            onKeyDown={e => { if (e.key === 'Enter') void handleCreate(); }}
            placeholder="New collection name…"
            style={{ flex: 1, height: 32, borderRadius: 8, border: '1px solid var(--color-border)', background: 'var(--color-bg)', color: 'var(--color-text)', padding: '0 10px', fontSize: 12, outline: 'none' }}
          />
          <button
            onClick={() => void handleCreate()}
            disabled={!newName.trim() || creating}
            style={{ padding: '0 12px', height: 32, borderRadius: 8, background: 'var(--gradient-primary)', color: '#fff', fontSize: 12, fontWeight: 600, opacity: !newName.trim() || creating ? 0.5 : 1 }}
          >Create</button>
        </div>

        {loading && <p style={{ fontSize: 13, color: 'var(--color-text-muted)', textAlign: 'center' }}>Loading…</p>}
        {!loading && collections.length === 0 && (
          <p style={{ fontSize: 13, color: 'var(--color-text-muted)', textAlign: 'center' }}>No collections yet. Create one above.</p>
        )}
        {collections.map(c => (
          <div key={c.id} style={{ padding: '10px 12px', borderRadius: 10, border: '1px solid var(--color-border)', background: 'var(--color-bg-elevated)', marginBottom: 8 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--color-text)' }}>{c.name}</span>
              {c.item_count !== undefined && (
                <span style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>{c.item_count} saved</span>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

// ── Shared micro-styles ────────────────────────────────────────────────────────

const headerSmallBtn: React.CSSProperties = {
  fontSize: 11,
  padding: '3px 8px',
  borderRadius: 8,
  background: 'var(--color-bg-elevated)',
  border: '1px solid var(--color-border)',
  color: 'var(--color-text)',
  whiteSpace: 'nowrap' as const,
  cursor: 'pointer',
};
