/**
 * ZioPanel — the split-screen AI assistant panel.
 * Shows contextual AI responses, contact extraction, and CRM actions.
 */
import { useState, useCallback, useRef, useEffect } from 'react';
import { useAuthStore } from '../store/auth-store';
import { ApiClient } from '../../shared/api-client';
import { trimPageContext } from '../../shared/context-extractor';
import type { PageContext, TrimmedContext } from '../../shared/context-extractor';

interface Props {
  pageContext: { url: string; title: string } | null;
  onClose: () => void;
}

interface Message {
  role: 'user' | 'assistant';
  content: string;
  timestamp: number;
}

export function ZioPanel({ pageContext, onClose }: Props) {
  const { token } = useAuthStore();
  const [messages, setMessages] = useState<Message[]>([]);
  const [input, setInput] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [sessionId, setSessionId] = useState<string | null>(null);
  const [trimmedCtx, setTrimmedCtx] = useState<TrimmedContext | null>(null);
  const [activeTab, setActiveTab] = useState<'chat' | 'contacts' | 'collections'>('chat');
  const [error, setError] = useState<string | null>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  const getClient = useCallback((): ApiClient | null => {
    if (!token) return null;
    const baseUrl = 'https://1in.me'; // default; loaded from prefs at startup
    return new ApiClient({ baseUrl, token });
  }, [token]);

  // Extract page context from the active tab
  useEffect(() => {
    if (!pageContext) return;

    void (async () => {
      try {
        const order = await window.zio.tabs.getOrder() as string[];
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

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const sendMessage = useCallback(async () => {
    if (!input.trim() || isLoading) return;
    const client = getClient();
    if (!client) { setError('Sign in to use Zio AI'); return; }

    const userMsg: Message = { role: 'user', content: input.trim(), timestamp: Date.now() };
    setMessages(prev => [...prev, userMsg]);
    setInput('');
    setIsLoading(true);
    setError(null);

    try {
      let sid = sessionId;
      if (!sid) {
        const contextStr = trimmedCtx ? JSON.stringify(trimmedCtx) : undefined;
        const res = await client.assistantSession(contextStr);
        sid = res.session_id;
        setSessionId(sid);
      }

      const contextStr = trimmedCtx ? JSON.stringify({ excerpt: trimmedCtx.excerpt.slice(0, 2000) }) : undefined;
      const res = await client.assistantMessage(sid, userMsg.content, contextStr);
      setMessages(prev => [...prev, { role: 'assistant', content: res.reply, timestamp: Date.now() }]);
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to send message';
      setError(msg);
    } finally {
      setIsLoading(false);
    }
  }, [input, isLoading, getClient, sessionId, trimmedCtx]);

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

  return (
    <div style={{
      width: 'var(--sidebar-width)',
      background: 'var(--color-bg-surface)',
      borderLeft: '1px solid var(--color-border)',
      display: 'flex',
      flexDirection: 'column',
      flexShrink: 0,
    }}>
      {/* Header */}
      <div style={{
        padding: '12px 16px',
        borderBottom: '1px solid var(--color-border)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <span style={{ fontSize: 18 }}>⚡</span>
          <span style={{ fontWeight: 700, fontSize: 15 }}>Zio</span>
          {pageContext && (
            <span style={{ fontSize: 11, color: 'var(--color-text-muted)', maxWidth: 180, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
              — {pageContext.title}
            </span>
          )}
        </div>
        <button onClick={onClose} style={{ opacity: 0.6, fontSize: 16 }}>✕</button>
      </div>

      {/* Tab nav */}
      <div style={{
        display: 'flex',
        borderBottom: '1px solid var(--color-border)',
        padding: '0 16px',
      }}>
        {(['chat', 'contacts', 'collections'] as const).map(tab => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            style={{
              padding: '8px 12px',
              fontSize: 12,
              fontWeight: activeTab === tab ? 600 : 400,
              color: activeTab === tab ? 'var(--color-primary)' : 'var(--color-text-muted)',
              borderBottom: activeTab === tab ? '2px solid var(--color-primary)' : '2px solid transparent',
              marginBottom: -1,
              textTransform: 'capitalize',
            }}
          >{tab}</button>
        ))}
      </div>

      {/* Content */}
      <div style={{ flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
        {activeTab === 'chat' && (
          <>
            <div style={{ flex: 1, overflowY: 'auto', padding: 16, display: 'flex', flexDirection: 'column', gap: 12 }}>
              {messages.length === 0 && (
                <div style={{ textAlign: 'center', color: 'var(--color-text-muted)', marginTop: 40 }}>
                  <div style={{ fontSize: 32, marginBottom: 12 }}>⚡</div>
                  <p style={{ fontSize: 14 }}>Ask Zio anything about this page</p>
                  {trimmedCtx && (
                    <p style={{ fontSize: 11, marginTop: 8, opacity: 0.6 }}>
                      Context loaded: {trimmedCtx.title}
                    </p>
                  )}
                </div>
              )}
              {messages.map((msg, i) => (
                <div key={i} style={{
                  display: 'flex',
                  justifyContent: msg.role === 'user' ? 'flex-end' : 'flex-start',
                }}>
                  <div style={{
                    maxWidth: '85%',
                    padding: '8px 12px',
                    borderRadius: msg.role === 'user' ? '12px 12px 4px 12px' : '12px 12px 12px 4px',
                    background: msg.role === 'user' ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
                    color: msg.role === 'user' ? '#fff' : 'var(--color-text)',
                    fontSize: 13,
                    lineHeight: 1.5,
                    whiteSpace: 'pre-wrap',
                  }}>
                    {msg.content}
                  </div>
                </div>
              ))}
              {isLoading && (
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
            <div style={{ padding: 12, borderTop: '1px solid var(--color-border)' }}>
              <div style={{ display: 'flex', gap: 8, alignItems: 'flex-end' }}>
                <textarea
                  value={input}
                  onChange={e => setInput(e.target.value)}
                  onKeyDown={handleKeyDown}
                  placeholder="Ask about this page… (Enter to send)"
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
                  onClick={() => void sendMessage()}
                  disabled={!input.trim() || isLoading}
                  style={{
                    padding: '8px 14px',
                    borderRadius: 10,
                    background: 'var(--color-primary)',
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
        )}

        {activeTab === 'contacts' && pageContext && (
          <ContactExtractorView url={pageContext.url} title={pageContext.title} trimmedCtx={trimmedCtx} />
        )}

        {activeTab === 'collections' && (
          <CollectionsView onSaveCurrent={saveCurrentPageToCollection} currentUrl={pageContext?.url} />
        )}
      </div>
    </div>
  );
}

function ContactExtractorView({ url, trimmedCtx }: { url: string; title: string; trimmedCtx: TrimmedContext | null }) {
  const { token } = useAuthStore();

  const savePhonesAndEmails = useCallback(async () => {
    if (!token || !trimmedCtx) return;
    const client = new ApiClient({ baseUrl: 'https://1in.me', token });
    if (trimmedCtx.emails.length > 0 || trimmedCtx.phones.length > 0) {
      await client.createContact({
        emails: trimmedCtx.emails.map(e => ({ value: e })),
        phones: trimmedCtx.phones.map(p => ({ value: p })),
        source_url: url,
      });
    }
  }, [token, trimmedCtx, url]);

  if (!trimmedCtx || (trimmedCtx.emails.length === 0 && trimmedCtx.phones.length === 0)) {
    return (
      <div style={{ padding: 24, textAlign: 'center', color: 'var(--color-text-muted)' }}>
        <p style={{ fontSize: 14 }}>No contacts detected on this page</p>
      </div>
    );
  }

  return (
    <div style={{ padding: 16, overflowY: 'auto', flex: 1 }}>
      <p style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 12 }}>
        Found on page:
      </p>
      {trimmedCtx.emails.length > 0 && (
        <div style={{ marginBottom: 16 }}>
          <p style={{ fontSize: 11, fontWeight: 600, color: 'var(--color-text-muted)', marginBottom: 8, textTransform: 'uppercase', letterSpacing: 1 }}>Emails</p>
          {trimmedCtx.emails.map(e => (
            <div key={e} style={{ fontSize: 13, padding: '4px 0', borderBottom: '1px solid var(--color-border)' }}>
              {e}
            </div>
          ))}
        </div>
      )}
      {trimmedCtx.phones.length > 0 && (
        <div style={{ marginBottom: 16 }}>
          <p style={{ fontSize: 11, fontWeight: 600, color: 'var(--color-text-muted)', marginBottom: 8, textTransform: 'uppercase', letterSpacing: 1 }}>Phone Numbers</p>
          {trimmedCtx.phones.map(p => (
            <div key={p} style={{ fontSize: 13, padding: '4px 0', borderBottom: '1px solid var(--color-border)' }}>
              {p}
            </div>
          ))}
        </div>
      )}
      {token && (
        <button
          onClick={() => void savePhonesAndEmails()}
          style={{
            width: '100%',
            padding: '10px',
            borderRadius: 10,
            background: 'var(--color-primary)',
            color: '#fff',
            fontSize: 13,
            fontWeight: 600,
            marginTop: 8,
          }}
        >
          Save to Sayzio Contacts
        </button>
      )}
    </div>
  );
}

function CollectionsView({ onSaveCurrent, currentUrl }: { onSaveCurrent: () => Promise<void>; currentUrl?: string }) {
  const [collections, setCollections] = useState<Array<{ id: string; name: string; item_count?: number }>>([]);

  useEffect(() => {
    void (async () => {
      const cols = await window.zio.collections.all() as Array<{ id: string; name: string; item_count?: number }>;
      setCollections(cols);
    })();
  }, []);

  return (
    <div style={{ padding: 16, flex: 1, overflowY: 'auto' }}>
      {currentUrl && (
        <button
          onClick={() => void onSaveCurrent()}
          style={{
            width: '100%',
            padding: '10px',
            borderRadius: 10,
            background: 'var(--color-primary)',
            color: '#fff',
            fontSize: 13,
            fontWeight: 600,
            marginBottom: 16,
          }}
        >
          Save This Page
        </button>
      )}
      <p style={{ fontSize: 11, fontWeight: 600, color: 'var(--color-text-muted)', marginBottom: 8, textTransform: 'uppercase', letterSpacing: 1 }}>
        Collections
      </p>
      {collections.length === 0 ? (
        <p style={{ fontSize: 13, color: 'var(--color-text-muted)' }}>No collections yet</p>
      ) : (
        collections.map(col => (
          <div key={col.id} style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            padding: '8px 10px',
            borderRadius: 8,
            marginBottom: 4,
            background: 'var(--color-bg-elevated)',
          }}>
            <span style={{ fontSize: 13 }}>{col.name}</span>
            <span style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>{col.item_count ?? 0}</span>
          </div>
        ))
      )}
    </div>
  );
}
