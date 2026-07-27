/**
 * Reconstructs call "sessions" from the flat mirrored dialer event stream
 * (ringing / answered / ended), powering the DialerPanel active-call banner
 * and recent-calls list.
 */
import type { DialerCallEvent } from '../../shared/api-client';

/** A call session reconstructed from the mirrored event stream. */
export interface CallSession {
  key: number;               // id of the first event in the session
  number: string;
  caller_name: string | null;
  status: DialerCallEvent['status']; // latest status seen
  startedAt: string | null;  // occurred_at of the first event
  answeredAt: string | null; // occurred_at of the answered event, if any
  endedAt: string | null;    // occurred_at of the ended event, if any
}

/**
 * Group the flat event stream (oldest-first) into call sessions. A `ringing`
 * event (or any event for a number with no open session) opens a session;
 * `answered` updates it; `ended` closes it.
 */
export function buildSessions(events: DialerCallEvent[]): CallSession[] {
  const sessions: CallSession[] = [];
  const open = new Map<string, CallSession>();
  for (const e of events) {
    let s = open.get(e.number);
    if (!s || e.status === 'ringing') {
      s = {
        key: e.id,
        number: e.number,
        caller_name: e.caller_name,
        status: e.status,
        startedAt: e.occurred_at,
        answeredAt: e.status === 'answered' ? e.occurred_at : null,
        endedAt: e.status === 'ended' ? e.occurred_at : null,
      };
      sessions.push(s);
      if (e.status === 'ended') continue; // already closed
      open.set(e.number, s);
      continue;
    }
    s.status = e.status;
    if (e.caller_name && !s.caller_name) s.caller_name = e.caller_name;
    if (e.status === 'answered') s.answeredAt = e.occurred_at;
    if (e.status === 'ended') {
      s.endedAt = e.occurred_at;
      open.delete(e.number);
    }
  }
  return sessions;
}

/** "MM:SS" (or "H:MM:SS") from a number of elapsed seconds. */
export function formatElapsed(totalSeconds: number): string {
  const s = Math.max(0, Math.floor(totalSeconds));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  const mm = String(m).padStart(2, '0');
  const ss = String(sec).padStart(2, '0');
  return h > 0 ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
}

/** Duration label for a finished call, when both timestamps are usable. */
export function sessionDuration(s: CallSession): string | null {
  if (!s.answeredAt || !s.endedAt) return null;
  const a = new Date(s.answeredAt).getTime();
  const b = new Date(s.endedAt).getTime();
  if (Number.isNaN(a) || Number.isNaN(b) || b < a) return null;
  return formatElapsed((b - a) / 1000);
}
