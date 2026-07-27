import { describe, it, expect } from 'vitest';
import { buildSessions, formatElapsed, sessionDuration } from '../src/renderer/lib/dialer-call-sessions';
import type { DialerCallEvent } from '../src/shared/api-client';

function ev(id: number, status: DialerCallEvent['status'], number: string, at: string, name: string | null = null): DialerCallEvent {
  return { id, status, number, caller_name: name, occurred_at: at };
}

describe('buildSessions', () => {
  it('groups ringing → answered → ended into one session', () => {
    const sessions = buildSessions([
      ev(1, 'ringing', '+1555', '2026-07-26T10:00:00Z', 'Ana'),
      ev(2, 'answered', '+1555', '2026-07-26T10:00:05Z'),
      ev(3, 'ended', '+1555', '2026-07-26T10:02:36Z'),
    ]);
    expect(sessions).toHaveLength(1);
    const s = sessions[0];
    expect(s.status).toBe('ended');
    expect(s.caller_name).toBe('Ana');
    expect(s.answeredAt).toBe('2026-07-26T10:00:05Z');
    expect(s.endedAt).toBe('2026-07-26T10:02:36Z');
    expect(sessionDuration(s)).toBe('02:31');
  });

  it('leaves an answered call without ended as the active (open) session', () => {
    const sessions = buildSessions([
      ev(1, 'ringing', '+1555', '2026-07-26T10:00:00Z'),
      ev(2, 'answered', '+1555', '2026-07-26T10:00:05Z'),
    ]);
    expect(sessions).toHaveLength(1);
    expect(sessions[0].status).toBe('answered');
    expect(sessions[0].endedAt).toBeNull();
  });

  it('a new ringing on the same number starts a fresh session', () => {
    const sessions = buildSessions([
      ev(1, 'ringing', '+1555', '2026-07-26T10:00:00Z'),
      ev(2, 'ended', '+1555', '2026-07-26T10:00:20Z'),
      ev(3, 'ringing', '+1555', '2026-07-26T11:00:00Z'),
    ]);
    expect(sessions).toHaveLength(2);
    expect(sessions[0].status).toBe('ended');
    expect(sessions[1].status).toBe('ringing');
  });

  it('interleaved numbers stay in separate sessions', () => {
    const sessions = buildSessions([
      ev(1, 'ringing', '+1555', '2026-07-26T10:00:00Z'),
      ev(2, 'ringing', '+1666', '2026-07-26T10:00:01Z'),
      ev(3, 'answered', '+1666', '2026-07-26T10:00:05Z'),
      ev(4, 'ended', '+1555', '2026-07-26T10:00:10Z'),
    ]);
    expect(sessions).toHaveLength(2);
    expect(sessions.find(s => s.number === '+1555')?.status).toBe('ended');
    expect(sessions.find(s => s.number === '+1666')?.status).toBe('answered');
  });

  it('an orphan answered/ended event (missed ringing) still opens a session', () => {
    const sessions = buildSessions([ev(1, 'answered', '+1777', '2026-07-26T10:00:00Z')]);
    expect(sessions).toHaveLength(1);
    expect(sessions[0].status).toBe('answered');
    expect(sessions[0].answeredAt).toBe('2026-07-26T10:00:00Z');
  });

  it('missed call (ringing then ended, never answered) has no duration', () => {
    const sessions = buildSessions([
      ev(1, 'ringing', '+1555', '2026-07-26T10:00:00Z'),
      ev(2, 'ended', '+1555', '2026-07-26T10:00:30Z'),
    ]);
    expect(sessionDuration(sessions[0])).toBeNull();
  });
});

describe('formatElapsed', () => {
  it('formats mm:ss and h:mm:ss', () => {
    expect(formatElapsed(0)).toBe('00:00');
    expect(formatElapsed(151)).toBe('02:31');
    expect(formatElapsed(3661)).toBe('1:01:01');
    expect(formatElapsed(-5)).toBe('00:00');
  });
});
