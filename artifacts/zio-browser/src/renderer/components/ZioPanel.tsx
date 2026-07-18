/**
 * ZioPanel — the split-screen AI assistant panel.
 * Shows contextual AI responses, contact extraction, collections,
 * and (when on an own Sayzio link) live click stats.
 */
import { useState, useCallback, useRef, useEffect } from 'react';
import { useAuthStore } from '../store/auth-store';
import { ApiClient, ApiClientError } from '../../shared/api-client';
import type { LinkAnalytics, AssistantPage } from '../../shared/api-client';
import { trimPageContext } from '../../shared/context-extractor';
import type { PageContext, TrimmedContext } from '../../shared/context-extractor';
import { detectSayzioLink } from '../../shared/link-tools';
import { AddToBiolinkModal } from './AddToBiolinkModal';

const BASE_URL = 'https://1in.me';

interface Props {
  pageContext: { url: string; title: string } | null;
  onClose: () => void;
  /** When true, renders as a full-area panel (no fixed width) for the split-mode left pane. */
  embedded?: boolean;
}

interface Message {
  role: 'user' | 'assistant';
  content: string;
  timestamp: number;
  /** True while tokens are still streaming into this message */
  streaming?: boolean;
}

const VISITOR_TOKEN_KEY = 'zio.assistant.visitor_token';

function loadVisitorToken(): string | null {
  try { return window.localStorage.getItem(VISITOR_TOKEN_KEY); } catch { return null; }
}

function storeVisitorToken(token: string): void {
  try { window.localStorage.setItem(VISITOR_TOKEN_KEY, token); } catch { /* ignore */ }
}

type PanelTab = 'chat' | 'contacts' | 'collections' | 'stats';

export function ZioPanel({ pageContext, onClose, embedded }: Props) {
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
  const messagesEndRef = useRef<HTMLDivElement>(null);
  // The conversation is keyed by a server-rotatable visitor token, mirroring
  // the web Ask Zio widget. Kept in a ref so mid-stream rotation updates
  // don't race React state.
  const visitorTokenRef = useRef<string | null>(loadVisitorToken());
  const sessionOpenedRef = useRef(false);

  const getClient = useCallback((): ApiClient | null => {
    if (!token) return null;
    return new ApiClient({ baseUrl: BASE_URL, token });
  }, [token]);

  // Detect whether the current page is a Sayzio link
  const sayzioLink = pageContext?.url ? detectSayzioLink(pageContext.url) : null;

  // Auto-switch to Stats tab when we land on an own link and we're signed in
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

  // Listen for the context-menu "Add to my biolink" IPC event from the main process
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

  const sendMessage = useCallback(async () => {
    if (!input.trim() || isLoading) return;
    const client = getClient();
    if (!client) { setError('Sign in to use Zio AI'); return; }

    const text = input.trim();
    // Ground the first question in the extracted page content so the
    // assistant can summarize / answer about the page. Subsequent turns
    // ride on the server-side conversation history.
    const excerpt = trimmedCtx?.excerpt?.trim();
    const outgoing = trimmedCtx && excerpt
      ? `${text}\n\n[Current page: ${trimmedCtx.title} (${trimmedCtx.url})]\n[Page content excerpt]:\n${excerpt.slice(0, 2500)}`
      : text;

    const userMsg: Message = { role: 'user', content: text, timestamp: Date.now() };
    setMessages(prev => [...prev, userMsg]);
    setInput('');
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
        // Server rotated the session (e.g. sign-in changed) — retry once
        // with the fresh token.
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
      // Ensure no message is left flagged as streaming.
      setMessages(prev => prev.map(m => (m.streaming ? { ...m, streaming: false } : m)));
      setIsLoading(false);
    }
  }, [input, isLoading, getClient, trimmedCtx, buildPage]);

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

  // Visible tabs — only show Stats when the page is a Sayzio link
  const visibleTabs: PanelTab[] = ['chat', 'contacts', 'collections'];
  if (sayzioLink) visibleTabs.push('stats');

  return (
    <div style={{
      width: embedded ? '100%' : 'var(--sidebar-width)',
      height: embedded ? '100%' : undefined,
      background: 'var(--color-bg-surface)',
      borderLeft: embedded ? 'none' : '1px solid var(--color-border)',
      display: 'flex',
      flexDirection: 'column',
      flexShrink: embedded ? 1 : 0,
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
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          {/* Quick action: Add to my biolink */}
          {token && pageContext?.url && (
            <button
              onClick={() => {
                setAddToBiolinkPayload({ url: pageContext.url, title: pageContext.title });
                setAddToBiolinkOpen(true);
              }}
              title="Add this page to my biolink"
              style={{
                fontSize: 11,
                padding: '3px 8px',
                borderRadius: 8,
                background: 'var(--color-bg-elevated)',
                border: '1px solid var(--color-border)',
                color: 'var(--color-text)',
                whiteSpace: 'nowrap',
              }}
            >+ Biolink</button>
          )}
          <button onClick={onClose} style={{ opacity: 0.6, fontSize: 16 }}>✕</button>
        </div>
      </div>

      {/* Tab nav */}
      <div style={{
        display: 'flex',
        borderBottom: '1px solid var(--color-border)',
        padding: '0 16px',
      }}>
        {visibleTabs.map(tab => (
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
                  <p style={{ fontSize: 14 }}>{greeting ?? 'Ask Zio anything about this page'}</p>
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

        {activeTab === 'stats' && sayzioLink && (
          <StatsView alias={sayzioLink.alias} baseUrl={BASE_URL} token={token} />
        )}
      </div>

      {/* Add-to-biolink modal (triggered by context menu or "+" button) */}
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

// ── Stats overlay ─────────────────────────────────────────────────────────────

function StatsView({ alias, baseUrl, token }: { alias: string; baseUrl: string; token: string | null }) {
  const [analytics, setAnalytics] = useState<LinkAnalytics | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [linkId, setLinkId] = useState<number | null>(null);

  // Resolve alias → link ID, then load analytics
  useEffect(() => {
    if (!token) return;
    const client = new ApiClient({ baseUrl, token });
    setLoading(true);
    setError(null);
    setAnalytics(null);

    void (async () => {
      try {
        // Find the link by alias in the user's link list
        const page = await client.listLinks({ q: alias, per_page: 20 });
        const match = page.items.find(l => l.alias === alias);
        if (!match) {
          setError('This link was not found in your account.');
          setLoading(false);
          return;
        }
        setLinkId(match.id);
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

  if (!analytics) return null;

  return (
    <div style={{ padding: 16, overflowY: 'auto', flex: 1, display: 'flex', flexDirection: 'column', gap: 16 }}>
      {/* Alias badge */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <span style={{
          fontSize: 12,
          fontWeight: 600,
          color: 'var(--color-primary)',
          background: 'color-mix(in srgb, var(--color-primary) 12%, transparent)',
          padding: '3px 10px',
          borderRadius: 20,
        }}>/{analytics.alias}</span>
        {linkId && (
          <button
            onClick={handleOpenDashboard}
            style={{
              fontSize: 11,
              color: 'var(--color-text-muted)',
              textDecoration: 'underline',
              cursor: 'pointer',
            }}
          >Full dashboard ↗</button>
        )}
      </div>

      {/* Headline numbers */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
        <StatCard label="Total clicks" value={analytics.total_clicks.toLocaleString()} />
        <StatCard label="Unique clicks" value={analytics.unique_clicks.toLocaleString()} />
      </div>

      {/* Top countries */}
      {analytics.by_country.length > 0 && (
        <section>
          <SectionTitle>Top countries</SectionTitle>
          {analytics.by_country.slice(0, 5).map(r => (
            <BarRow
              key={r.country}
              label={r.country || 'Unknown'}
              value={r.clicks}
              max={analytics.by_country[0]!.clicks}
            />
          ))}
        </section>
      )}

      {/* Devices */}
      {analytics.by_device.length > 0 && (
        <section>
          <SectionTitle>Devices</SectionTitle>
          {analytics.by_device.slice(0, 4).map(r => (
            <BarRow
              key={r.device_type}
              label={r.device_type || 'Unknown'}
              value={r.clicks}
              max={analytics.by_device[0]!.clicks}
            />
          ))}
        </section>
      )}

      {/* Recent activity sparkline */}
      {analytics.by_day.length > 0 && (
        <section>
          <SectionTitle>Last 30 days</SectionTitle>
          <Sparkline data={analytics.by_day} />
        </section>
      )}

      {/* Window note */}
      <p style={{ fontSize: 10, color: 'var(--color-text-muted)', marginTop: 'auto' }}>
        30-day window · {new Date(analytics.window.from).toLocaleDateString()} – {new Date(analytics.window.to).toLocaleDateString()}
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
        <div style={{ width: `${pct}%`, height: '100%', background: 'var(--color-primary)', borderRadius: 4 }} />
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

// ── Contact extractor (unchanged) ─────────────────────────────────────────────

function ContactExtractorView({ url, trimmedCtx }: { url: string; title: string; trimmedCtx: TrimmedContext | null }) {
  const { token } = useAuthStore();

  const savePhonesAndEmails = useCallback(async () => {
    if (!token || !trimmedCtx) return;
    const client = new ApiClient({ baseUrl: BASE_URL, token });
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
      <p style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 12 }}>Found on page:</p>
      {trimmedCtx.emails.length > 0 && (
        <div style={{ marginBottom: 16 }}>
          <p style={{ fontSize: 11, fontWeight: 600, color: 'var(--color-text-muted)', marginBottom: 8, textTransform: 'uppercase', letterSpacing: 1 }}>Emails</p>
          {trimmedCtx.emails.map(e => (
            <div key={e} style={{ fontSize: 13, padding: '4px 0', borderBottom: '1px solid var(--color-border)' }}>{e}</div>
          ))}
        </div>
      )}
      {trimmedCtx.phones.length > 0 && (
        <div style={{ marginBottom: 16 }}>
          <p style={{ fontSize: 11, fontWeight: 600, color: 'var(--color-text-muted)', marginBottom: 8, textTransform: 'uppercase', letterSpacing: 1 }}>Phone Numbers</p>
          {trimmedCtx.phones.map(p => (
            <div key={p} style={{ fontSize: 13, padding: '4px 0', borderBottom: '1px solid var(--color-border)' }}>{p}</div>
          ))}
        </div>
      )}
      {token && (
        <button
          onClick={() => void savePhonesAndEmails()}
          style={{ width: '100%', padding: 10, borderRadius: 10, background: 'var(--color-primary)', color: '#fff', fontSize: 13, fontWeight: 600, marginTop: 8 }}
        >Save to Sayzio Contacts</button>
      )}
    </div>
  );
}

// ── Collections (unchanged) ────────────────────────────────────────────────────

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
          style={{ width: '100%', padding: 10, borderRadius: 10, background: 'var(--color-primary)', color: '#fff', fontSize: 13, fontWeight: 600, marginBottom: 16 }}
        >Save This Page</button>
      )}
      <p style={{ fontSize: 11, fontWeight: 600, color: 'var(--color-text-muted)', marginBottom: 8, textTransform: 'uppercase', letterSpacing: 1 }}>Collections</p>
      {collections.length === 0 ? (
        <p style={{ fontSize: 13, color: 'var(--color-text-muted)' }}>No collections yet</p>
      ) : (
        collections.map(col => (
          <div key={col.id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '8px 10px', borderRadius: 8, marginBottom: 4, background: 'var(--color-bg-elevated)' }}>
            <span style={{ fontSize: 13 }}>{col.name}</span>
            <span style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>{col.item_count ?? 0}</span>
          </div>
        ))
      )}
    </div>
  );
}
