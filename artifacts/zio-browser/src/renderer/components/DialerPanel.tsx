/**
 * DialerPanel — sidebar pane that brings the Sayzio universal dialer to
 * Zio Browser with phone call handoff:
 *
 *  - Search box backed by the grouped universal dialer search (text + T9
 *    smart-dial both work — the server handles digit queries).
 *  - Click-to-call: "Call on phone" pushes the number to the linked Zio
 *    Dialer phone app via an Expo push (`dialer.call_request`); the user
 *    confirms the call on the phone keypad.
 *  - Incoming-call mirror: while the pane is open we short-poll
 *    GET /dialer/call-events (~4s) and surface "your phone is ringing"
 *    entries (with a desktop notification per new ring), when the user has
 *    enabled "Show calls in Zio Browser" in the phone app.
 *
 * Desktop VoIP / answering calls from the desktop is intentionally out of
 * scope — the phone stays the calling device.
 */
import { useState, useEffect, useCallback, useRef } from 'react';
import { useAuthStore } from '../store/auth-store';
import { ApiClient, ApiClientError } from '../../shared/api-client';
import type { DialerSearchResult, DialerCallEvent } from '../../shared/api-client';
import { buildSessions, formatElapsed, sessionDuration } from '../lib/dialer-call-sessions';
import { quickQrImageUrl } from '../../shared/link-tools';

const BASE_URL = 'https://sayzio.app';

/**
 * Site-hosted APK delivery endpoint — always serves the newest uploaded
 * Zio Dialer build (see AndroidApkPublicController on the Laravel side).
 */
const APK_DOWNLOAD_URL = `${BASE_URL}/android/download`;
/** Human-friendly landing page (version, size, download button). */
const APK_LANDING_URL = `${BASE_URL}/android`;
/** Public JSON descriptor of the live APK release (404 = no release). */
const APK_INFO_URL = `${BASE_URL}/android/app.json`;

/** Live APK release info fetched from the public descriptor endpoint. */
interface ApkInfo {
  version_name: string | null;
  build_number: string | number | null;
  size_human: string | null;
}

/** Poll cadence for the incoming-call mirror while the pane is open. */
const CALL_EVENTS_POLL_MS = 4000;

interface Props {
  onClose: () => void;
  onNavigate: (url: string) => void;
}

/** One row from the grouped universal search (loosely typed server payload). */
interface SearchItem {
  type?: string;
  title?: string;
  subtitle?: string;
  action?: {
    kind?: string;
    url?: string | null;
    number?: string | null;
  };
}

/** Extract a dialable number from a search row, if it has one. */
function itemNumber(item: SearchItem): string | null {
  const n = item.action?.number;
  return typeof n === 'string' && n.trim() !== '' ? n.trim() : null;
}

/** Loose "looks like a phone number" check for the typed query itself. */
function queryLooksDialable(q: string): boolean {
  return /^\+?[0-9][0-9 \-().]{2,30}$/.test(q.trim());
}

function formatEventTime(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

const STATUS_LABEL: Record<DialerCallEvent['status'], string> = {
  ringing: 'Ringing on your phone',
  answered: 'Answered',
  ended: 'Call ended',
};

export function DialerPanel({ onClose, onNavigate }: Props) {
  const { token, user } = useAuthStore();
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<DialerSearchResult | null>(null);
  const [searching, setSearching] = useState(false);
  const [callState, setCallState] = useState<{ number: string; status: 'sending' | 'sent' | 'error'; message?: string; noDevice?: boolean } | null>(null);
  const [events, setEvents] = useState<DialerCallEvent[]>([]);
  // null = unknown (check pending/failed) — only `false` shows the proactive offer.
  const [deviceLinked, setDeviceLinked] = useState<boolean | null>(null);

  const getClient = useCallback((): ApiClient | null => {
    if (!token) return null;
    return new ApiClient({ baseUrl: BASE_URL, token });
  }, [token]);

  // ── Debounced universal search ────────────────────────────────────────────
  const searchSeqRef = useRef(0);
  useEffect(() => {
    const q = query.trim();
    if (q.length < 2) {
      setResults(null);
      setSearching(false);
      return;
    }
    const client = getClient();
    if (!client) return;
    const seq = ++searchSeqRef.current;
    setSearching(true);
    const timer = setTimeout(() => {
      client.dialerSearch(q)
        .then((res) => {
          if (searchSeqRef.current === seq) setResults(res);
        })
        .catch(() => {
          if (searchSeqRef.current === seq) setResults(null);
        })
        .finally(() => {
          if (searchSeqRef.current === seq) setSearching(false);
        });
    }, 250);
    return () => clearTimeout(timer);
  }, [query, getClient]);

  // ── Click-to-call handoff ─────────────────────────────────────────────────
  const handleCall = useCallback(async (number: string, name?: string | null) => {
    const client = getClient();
    if (!client) return;
    setCallState({ number, status: 'sending' });
    try {
      await client.dialerRequestCall(number, name ?? undefined);
      setCallState({ number, status: 'sent' });
      setDeviceLinked(true);
    } catch (err) {
      const noDevice = err instanceof ApiClientError && err.code === 'no_dialer_device';
      if (noDevice) setDeviceLinked(false);
      const message = noDevice
        ? 'No phone linked — sign in to the Zio Dialer app on your phone first.'
        : 'Could not reach your phone. Try again.';
      setCallState({ number, status: 'error', message, noDevice });
    }
  }, [getClient]);

  // ── Linked-device check on open (proactive app-download offer) ───────────
  useEffect(() => {
    if (!token) {
      setDeviceLinked(null);
      return;
    }
    let cancelled = false;
    const client = getClient();
    if (!client) return;
    client.dialerHandoffStatus()
      .then((res) => {
        if (!cancelled) setDeviceLinked(res.device_linked);
      })
      .catch(() => { /* unknown — fall back to the post-failure offer */ });
    return () => { cancelled = true; };
  }, [token, getClient]);

  // ── Live APK release info for the download offer ─────────────────────────
  // Fetched lazily the first time the "no phone linked" offer appears
  // (proactively via the linked-device check, or after a failed call):
  // 'loading' while in flight, ApkInfo on success, 'unavailable' when the
  // endpoint 404s (no APK uploaded) or the request fails.
  const [apkInfo, setApkInfo] = useState<ApkInfo | 'loading' | 'unavailable' | null>(null);
  const showApkOffer = deviceLinked === false || (callState?.status === 'error' && !!callState.noDevice);
  useEffect(() => {
    if (!showApkOffer || apkInfo !== null) return;
    let cancelled = false;
    setApkInfo('loading');
    fetch(APK_INFO_URL)
      .then(async (res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const body = await res.json();
        const d = body?.data;
        if (cancelled) return;
        setApkInfo({
          version_name: typeof d?.version_name === 'string' ? d.version_name : null,
          build_number: d?.build_number ?? null,
          size_human: typeof d?.size_human === 'string' ? d.size_human : null,
        });
      })
      .catch(() => {
        if (!cancelled) setApkInfo('unavailable');
      });
    return () => { cancelled = true; };
  }, [showApkOffer, apkInfo]);

  // ── Incoming-call mirror (short poll while the pane is open) ─────────────
  const cursorRef = useRef(0);
  const primedRef = useRef(false);
  const notifiedIdsRef = useRef<Set<number>>(new Set());

  useEffect(() => {
    if (!token) {
      // Signed out: stop polling and clear mirrored state.
      setEvents([]);
      cursorRef.current = 0;
      primedRef.current = false;
      return;
    }
    let cancelled = false;

    const poll = async () => {
      const client = getClient();
      if (!client || cancelled) return;
      try {
        const res = await client.dialerCallEvents(cursorRef.current);
        if (cancelled) return;
        cursorRef.current = res.cursor;
        if (res.events.length > 0) {
          setEvents(prev => {
            const seen = new Set(prev.map(e => e.id));
            const merged = [...prev, ...res.events.filter(e => !seen.has(e.id))];
            return merged.slice(-30);
          });
          // Desktop notification for NEW rings only — skip the first
          // (priming) poll so reopening the pane doesn't re-notify.
          if (primedRef.current) {
            for (const e of res.events) {
              if (e.status !== 'ringing' || notifiedIdsRef.current.has(e.id)) continue;
              notifiedIdsRef.current.add(e.id);
              try {
                new Notification('Your phone is ringing', {
                  body: e.caller_name ? `${e.caller_name} — ${e.number}` : e.number,
                });
              } catch { /* notifications unavailable — the in-pane list still shows it */ }
            }
          } else {
            for (const e of res.events) notifiedIdsRef.current.add(e.id);
          }
        }
        primedRef.current = true;
      } catch { /* transient network/server errors — next tick retries */ }
    };

    void poll();
    const interval = setInterval(() => void poll(), CALL_EVENTS_POLL_MS);
    return () => {
      cancelled = true;
      clearInterval(interval);
    };
  }, [token, getClient]);

  const dialableQuery = queryLooksDialable(query) ? query.trim() : null;

  // ── Live call status derived from the mirrored event stream ──────────────
  const sessions = buildSessions(events);
  // Active call: the most recent session whose latest status is `answered`
  // (i.e. an answered event with no following ended event).
  const lastSession = sessions.length > 0 ? sessions[sessions.length - 1] : null;
  const activeCall = lastSession && lastSession.status === 'answered' ? lastSession : null;
  const recentSessions = sessions
    .filter(s => s !== activeCall)
    .slice(-5)
    .reverse();

  // 1s tick while a call is active so the elapsed timer counts up.
  const [nowMs, setNowMs] = useState(() => Date.now());
  useEffect(() => {
    if (!activeCall) return;
    setNowMs(Date.now());
    const t = setInterval(() => setNowMs(Date.now()), 1000);
    return () => clearInterval(t);
  }, [activeCall?.key]);

  let activeElapsed: string | null = null;
  if (activeCall?.answeredAt) {
    const started = new Date(activeCall.answeredAt).getTime();
    if (!Number.isNaN(started)) activeElapsed = formatElapsed((nowMs - started) / 1000);
  }

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
        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
        padding: '12px 14px', borderBottom: '1px solid var(--color-border)',
      }}>
        <div style={{ fontWeight: 600, fontSize: 14 }}>📞 Dialer</div>
        <button onClick={onClose} title="Close" style={{ fontSize: 14, padding: '2px 6px' }}>✕</button>
      </div>

      {!token ? (
        <div style={{ padding: 16, fontSize: 13, color: 'var(--color-text-secondary)' }}>
          Sign in to search your contacts and hand calls off to your phone.
        </div>
      ) : (
        <>
          {/* Search */}
          <div style={{ padding: '10px 12px', borderBottom: '1px solid var(--color-border)' }}>
            <input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search people, contacts, links — or dial"
              autoFocus
              style={{
                width: '100%', boxSizing: 'border-box',
                padding: '7px 10px', fontSize: 13,
                borderRadius: 8, border: '1px solid var(--color-border)',
                background: 'var(--color-bg)', color: 'var(--color-text)',
              }}
            />
            <div style={{ fontSize: 11, marginTop: 4, color: 'var(--color-text-secondary)' }}>
              Tip: digits work like T9 — "742" finds "Sia".
            </div>
          </div>

          {/* Active call banner — answered on the phone, not yet ended */}
          {activeCall && (
            <div style={{
              margin: '8px 12px 0', padding: '9px 11px', borderRadius: 8,
              background: 'rgba(60,160,90,0.14)',
              border: '1px solid rgba(60,160,90,0.35)',
              display: 'flex', alignItems: 'center', gap: 8,
            }}>
              <span style={{ fontSize: 15 }}>🟢</span>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 13, fontWeight: 600, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                  On a call with {activeCall.caller_name || activeCall.number}
                </div>
                <div style={subStyle}>
                  {activeCall.caller_name ? `${activeCall.number} · ` : ''}on your phone
                </div>
              </div>
              {activeElapsed && (
                <div style={{ fontSize: 13, fontWeight: 600, fontVariantNumeric: 'tabular-nums', flexShrink: 0 }}>
                  {activeElapsed}
                </div>
              )}
            </div>
          )}

          {/* Call handoff status */}
          {callState && (
            <div style={{
              margin: '8px 12px 0', padding: '8px 10px', borderRadius: 8, fontSize: 12.5,
              background: callState.status === 'error' ? 'rgba(220,60,60,0.12)' : 'rgba(60,160,90,0.12)',
              color: callState.status === 'error' ? 'var(--color-danger, #d33)' : 'var(--color-text)',
            }}>
              {callState.status === 'sending' && `Sending ${callState.number} to your phone…`}
              {callState.status === 'sent' && `Sent to your phone — tap the notification to call ${callState.number}.`}
              {callState.status === 'error' && callState.message}
            </div>
          )}

          {/* No phone linked (proactive check on open, or a failed call
              attempt) → offer the latest Zio Dialer APK before the user
              wastes a call attempt. */}
          {showApkOffer && (
            <div style={{
              margin: '8px 12px 0', padding: '10px 11px', borderRadius: 8,
              border: '1px solid var(--color-border)',
              background: 'var(--color-bg)',
              display: 'flex', gap: 10, alignItems: 'center',
            }}>
              {apkInfo !== 'unavailable' && (
                <img
                  src={quickQrImageUrl(APK_DOWNLOAD_URL, 96)}
                  alt="QR code — download the Zio Dialer app"
                  width={96}
                  height={96}
                  style={{ borderRadius: 6, background: '#fff', flexShrink: 0 }}
                />
              )}
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 2 }}>
                  Get the Zio Dialer app
                </div>
                {apkInfo === 'unavailable' ? (
                  <div style={{ ...subStyle, whiteSpace: 'normal', lineHeight: 1.4 }}>
                    No Android build is available for download right now — check back soon.
                  </div>
                ) : (
                  <>
                    <div style={{ ...subStyle, whiteSpace: 'normal', lineHeight: 1.4 }}>
                      Scan the QR with your phone to download the latest APK, then sign in.
                    </div>
                    {apkInfo !== null && apkInfo !== 'loading' && (apkInfo.version_name || apkInfo.size_human) && (
                      <div style={{ ...subStyle, whiteSpace: 'normal', marginTop: 2 }}>
                        {[
                          apkInfo.version_name ? `Version ${apkInfo.version_name}` : null,
                          apkInfo.size_human,
                        ].filter(Boolean).join(' · ')}
                      </div>
                    )}
                    <button
                      onClick={() => onNavigate(APK_LANDING_URL)}
                      style={{ ...callBtnStyle, marginTop: 6 }}
                      title={`Open the download page (${APK_LANDING_URL})`}
                    >
                      ⬇️ Open download page
                    </button>
                  </>
                )}
              </div>
            </div>
          )}

          {/* Results + incoming calls */}
          <div style={{ flex: 1, overflowY: 'auto', padding: '8px 12px', display: 'flex', flexDirection: 'column', gap: 10 }}>
            {/* Direct dial of a typed number */}
            {dialableQuery && (
              <div style={rowStyle}>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={titleStyle}>{dialableQuery}</div>
                  <div style={subStyle}>Dial this number</div>
                </div>
                <button onClick={() => void handleCall(dialableQuery)} style={callBtnStyle} title="Call on your phone">
                  📱 Call
                </button>
              </div>
            )}

            {searching && (
              <div style={{ fontSize: 12.5, color: 'var(--color-text-secondary)' }}>Searching…</div>
            )}

            {results?.groups.filter(g => g.items.length > 0).map(group => (
              <div key={group.key}>
                <div style={{
                  fontSize: 11, fontWeight: 600, textTransform: 'uppercase', letterSpacing: 0.4,
                  color: 'var(--color-text-secondary)', margin: '2px 0 6px',
                }}>
                  {group.label}
                </div>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                  {(group.items as SearchItem[]).map((item, i) => {
                    const number = itemNumber(item);
                    const url = item.action?.url ?? null;
                    return (
                      <div key={`${group.key}-${i}`} style={rowStyle}>
                        <div
                          style={{ flex: 1, minWidth: 0, cursor: url ? 'pointer' : 'default' }}
                          onClick={() => { if (url) onNavigate(url); }}
                          title={url ?? undefined}
                        >
                          <div style={titleStyle}>{item.title ?? '—'}</div>
                          {item.subtitle ? <div style={subStyle}>{item.subtitle}</div> : null}
                        </div>
                        {number && (
                          <button
                            onClick={() => void handleCall(number, item.title ?? null)}
                            style={callBtnStyle}
                            title={`Call ${number} on your phone`}
                          >
                            📱 Call
                          </button>
                        )}
                      </div>
                    );
                  })}
                </div>
              </div>
            ))}

            {results && results.total === 0 && !searching && (
              <div style={{ fontSize: 12.5, color: 'var(--color-text-secondary)' }}>No matches.</div>
            )}

            {/* Recent calls mirrored from the phone */}
            {recentSessions.length > 0 && (
              <div>
                <div style={{
                  fontSize: 11, fontWeight: 600, textTransform: 'uppercase', letterSpacing: 0.4,
                  color: 'var(--color-text-secondary)', margin: '6px 0 6px',
                }}>
                  Recent calls
                </div>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                  {recentSessions.map(s => {
                    const duration = sessionDuration(s);
                    return (
                      <div key={s.key} style={rowStyle}>
                        <div style={{ flex: 1, minWidth: 0 }}>
                          <div style={titleStyle}>{s.caller_name || s.number}</div>
                          <div style={subStyle}>
                            {STATUS_LABEL[s.status]}
                            {duration ? ` · ${duration}` : ''}
                            {s.caller_name ? ` · ${s.number}` : ''}
                            {formatEventTime(s.endedAt ?? s.startedAt) ? ` · ${formatEventTime(s.endedAt ?? s.startedAt)}` : ''}
                          </div>
                        </div>
                        {s.status === 'ringing' ? <span style={{ fontSize: 14 }}>🔔</span> : null}
                        <button
                          onClick={() => void handleCall(s.number, s.caller_name)}
                          style={callBtnStyle}
                          title={`Call ${s.number} on your phone`}
                        >
                          📱 Call
                        </button>
                      </div>
                    );
                  })}
                </div>
              </div>
            )}

            {!results && !dialableQuery && !activeCall && recentSessions.length === 0 && !searching && (
              <div style={{ fontSize: 12.5, color: 'var(--color-text-secondary)', lineHeight: 1.5 }}>
                Search anything — contacts, people on Sayzio, your links — then
                hand the call to your phone with one click.
                {user ? ' Incoming calls appear here when "Show calls in Zio Browser" is on in the Zio Dialer app.' : ''}
              </div>
            )}
          </div>
        </>
      )}
    </div>
  );
}

const rowStyle: React.CSSProperties = {
  display: 'flex', alignItems: 'center', gap: 8,
  padding: '7px 9px', borderRadius: 8,
  border: '1px solid var(--color-border)',
  background: 'var(--color-bg)',
};

const titleStyle: React.CSSProperties = {
  fontSize: 13, fontWeight: 500,
  whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
};

const callBtnStyle: React.CSSProperties = {
  fontSize: 12, fontWeight: 600,
  padding: '4px 9px', borderRadius: 7, flexShrink: 0,
  border: '1px solid var(--color-border)',
  background: 'var(--color-bg-surface)',
  color: 'var(--color-primary)',
  cursor: 'pointer',
  whiteSpace: 'nowrap',
};

const subStyle: React.CSSProperties = {
  fontSize: 11.5, color: 'var(--color-text-secondary)',
  whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
};
