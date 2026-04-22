import { fetch as expoFetch } from "expo/fetch";

import { MOBILE_USER_AGENT, apiFetch, getBaseUrl } from "@/lib/api";
import { getToken } from "@/lib/secure";

export type CoachThread = {
  id: number;
  title: string;
  last_message_at: string | null;
};

export type CoachAction = { label: string; url: string; reason?: string };
export type CoachCitation = { label?: string; source?: string };
export type CoachInsight = { tool: string; data: Record<string, unknown> };

export type CoachMessage = {
  id: number;
  role: "user" | "assistant";
  content: string;
  meta?: {
    credits_spent?: number;
    tools_used?: string[];
    citations?: CoachCitation[];
    insights?: CoachInsight[];
    actions?: CoachAction[];
  } | null;
  feedback?: "up" | "down" | null;
  created_at?: string | null;
};

export const askCoach = {
  threads: () =>
    apiFetch<{ threads: CoachThread[] }>("/ai/ask-coach/threads"),
  create: () =>
    apiFetch<{ thread: CoachThread }>("/ai/ask-coach/threads", {
      method: "POST",
    }),
  messages: (id: number) =>
    apiFetch<{ thread: CoachThread; messages: CoachMessage[] }>(
      `/ai/ask-coach/threads/${id}`,
    ),
  send: (id: number, message: string) =>
    apiFetch<{ message: CoachMessage; balance: number }>(
      `/ai/ask-coach/threads/${id}/send`,
      { method: "POST", body: JSON.stringify({ message }) },
    ),
  destroy: (id: number) =>
    apiFetch<{ ok: boolean }>(`/ai/ask-coach/threads/${id}`, {
      method: "DELETE",
    }),
  feedback: (
    messageId: number,
    feedback: "up" | "down" | "clear",
    note?: string,
  ) =>
    apiFetch<{ message: CoachMessage }>(
      `/ai/ask-coach/messages/${messageId}/feedback`,
      { method: "POST", body: JSON.stringify({ feedback, note }) },
    ),

  /**
   * Stream a Coach reply token-by-token. The same /send endpoint
   * branches into SSE when called with `Accept: text/event-stream`.
   * `onToken` is fired for each delta so the UI can append words as
   * they're generated; `onDone` is fired once with the persisted
   * message (with citations / actions / insights / id) so the bubble
   * matches a fresh page load.
   */
  sendStream: async (
    id: number,
    message: string,
    handlers: {
      onToken: (delta: string) => void;
      onDone: (data: { message: CoachMessage; balance: number }) => void;
      onError: (err: { code?: string; message: string }) => void;
      signal?: AbortSignal;
    },
  ): Promise<void> => {
    const token = await getToken();
    const url = `${getBaseUrl()}/api/v1/ai/ask-coach/threads/${id}/send`;
    const headers: Record<string, string> = {
      Accept: "text/event-stream",
      "Content-Type": "application/json",
      "User-Agent": MOBILE_USER_AGENT,
      "X-1INME-Client": MOBILE_USER_AGENT,
    };
    if (token) headers.Authorization = `Bearer ${token}`;

    const res = await expoFetch(url, {
      method: "POST",
      headers,
      body: JSON.stringify({ message }),
      signal: handlers.signal,
    });

    if (!res.ok || !res.body) {
      let bodyText = "";
      try { bodyText = await res.text(); } catch {}
      handlers.onError({
        code: String(res.status),
        message: bodyText || `Stream failed (${res.status}).`,
      });
      return;
    }

    const reader = res.body.getReader();
    const decoder = new TextDecoder("utf-8");
    let buffer = "";

    const isRecord = (v: unknown): v is Record<string, unknown> =>
      typeof v === "object" && v !== null;

    const flushFrame = (frame: string) => {
      let event = "message";
      let data = "";
      for (const line of frame.split("\n")) {
        if (line.startsWith("event:")) event = line.slice(6).trim();
        else if (line.startsWith("data:")) data += line.slice(5).trim();
      }
      if (!data) return;
      let parsed: unknown;
      try { parsed = JSON.parse(data); } catch { return; }
      if (!isRecord(parsed)) return;

      if (event === "token") {
        const delta = parsed.delta;
        if (typeof delta === "string") handlers.onToken(delta);
      } else if (event === "done") {
        const message = parsed.message;
        const balance = parsed.balance;
        if (isRecord(message) && typeof balance === "number") {
          handlers.onDone({
            message: message as unknown as CoachMessage,
            balance,
          });
        }
      } else if (event === "error") {
        const code = typeof parsed.code === "string" ? parsed.code : undefined;
        const msg = typeof parsed.message === "string" ? parsed.message : "Coach error.";
        handlers.onError({ code, message: msg });
      }
    };

    // eslint-disable-next-line no-constant-condition
    while (true) {
      const { value, done } = await reader.read();
      if (done) break;
      buffer += decoder.decode(value, { stream: true }).replace(/\r\n/g, "\n");
      let idx;
      while ((idx = buffer.indexOf("\n\n")) !== -1) {
        const frame = buffer.slice(0, idx);
        buffer = buffer.slice(idx + 2);
        flushFrame(frame);
      }
    }
  },
};
