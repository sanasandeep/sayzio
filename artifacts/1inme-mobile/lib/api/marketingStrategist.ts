import { fetch as expoFetch } from "expo/fetch";

import { MOBILE_USER_AGENT, apiFetch, getBaseUrl } from "@/lib/api";
import { getToken } from "@/lib/secure";

/**
 * AI Marketing Strategist (mobile) — REST parity for the web digital
 * performer. The creator toggles which of their OWN Sayzio data to feed
 * in, sets a goal + parameters, and the strategist generates an organic +
 * paid plan built around real Sayzio features. They can then chat-refine
 * (streamed, metered) and one-click apply suggestions.
 *
 * Mirrors `/api/v1/ai/marketing-strategist*`. The unified `{data}`/`{error}`
 * envelope is unwrapped by {@see apiFetch}; the streamed chat keeps the SSE
 * frame format and is read with `expo/fetch` (parity with Ask Coach).
 */

export type MsSource = {
  key: string;
  label: string;
  description: string;
};

export type MsPlay = {
  title?: string;
  channel?: string;
  budget_hint?: string;
  rationale?: string;
  steps?: string[];
  sayzio_features?: string[];
};

export type MsPlan = {
  summary?: string;
  organic?: MsPlay[];
  paid?: MsPlay[];
  kpis?: string[];
};

export type MsSummary = {
  id: number;
  title: string;
  goal: string;
  sources: string[];
  credits_spent: number;
  created_at: string | null;
};

export type MsDetail = {
  id: number;
  title: string;
  goal: string;
  parameters: Record<string, string>;
  sources: string[];
  strategy: MsPlan;
  credits_spent: number;
  model: string | null;
  created_at: string | null;
};

export type MsSuggestionStatus =
  | "pending"
  | "applied"
  | "dismissed"
  | "error";

export type MsSuggestion = {
  id: number;
  type: string;
  type_label: string;
  title: string;
  description: string | null;
  status: MsSuggestionStatus;
  applied_ref_type: string | null;
  applied_ref_id: number | null;
  error: string | null;
};

export type MsMessage = {
  id: number;
  role: "user" | "assistant";
  content: string;
  meta?: {
    credits_spent?: number;
    model?: string | null;
    streamed?: boolean;
  } | null;
  created_at?: string | null;
};

export type MsBuilderInput = {
  goal: string;
  sources: string[];
  parameters: Record<string, string>;
};

export type MsIndexResponse = {
  strategies: MsSummary[];
  ai_enabled: boolean;
  sources?: MsSource[];
  balance?: number;
};

export type MsEstimateResponse = {
  estimate: number;
  balance: number;
};

export type MsStoreResponse = {
  strategy: MsDetail;
  credits_spent: number;
  balance: number;
};

export type MsShowResponse = {
  strategy: MsDetail;
  suggestions: MsSuggestion[];
  messages: MsMessage[];
  balance: number;
};

export const marketingStrategist = {
  index: async (): Promise<MsIndexResponse> => {
    const res = await apiFetch<{ data: MsIndexResponse }>(
      "/ai/marketing-strategist",
    );
    return res.data;
  },

  estimate: async (input: MsBuilderInput): Promise<MsEstimateResponse> => {
    const res = await apiFetch<{ data: MsEstimateResponse }>(
      "/ai/marketing-strategist/estimate",
      { method: "POST", body: JSON.stringify(input) },
    );
    return res.data;
  },

  create: async (input: MsBuilderInput): Promise<MsStoreResponse> => {
    const res = await apiFetch<{ data: MsStoreResponse }>(
      "/ai/marketing-strategist",
      { method: "POST", body: JSON.stringify(input) },
    );
    return res.data;
  },

  show: async (id: number): Promise<MsShowResponse> => {
    const res = await apiFetch<{ data: MsShowResponse }>(
      `/ai/marketing-strategist/${id}`,
    );
    return res.data;
  },

  destroy: async (id: number): Promise<{ deleted: boolean }> => {
    const res = await apiFetch<{ data: { deleted: boolean } }>(
      `/ai/marketing-strategist/${id}`,
      { method: "DELETE" },
    );
    return res.data;
  },

  applySuggestion: async (
    suggestionId: number,
  ): Promise<{
    status: MsSuggestionStatus;
    message: string;
    url: string | null;
  }> => {
    const res = await apiFetch<{
      data: {
        status: MsSuggestionStatus;
        message: string;
        url: string | null;
      };
    }>(`/ai/marketing-strategist/suggestions/${suggestionId}/apply`, {
      method: "POST",
      body: JSON.stringify({ confirm: true }),
    });
    return res.data;
  },

  dismissSuggestion: async (
    suggestionId: number,
  ): Promise<{ status: MsSuggestionStatus }> => {
    const res = await apiFetch<{ data: { status: MsSuggestionStatus } }>(
      `/ai/marketing-strategist/suggestions/${suggestionId}/dismiss`,
      { method: "POST" },
    );
    return res.data;
  },

  /**
   * Chat-refine the strategy, streamed token-by-token. The same /chat
   * endpoint branches into SSE when called with `Accept: text/event-stream`.
   * `onToken` fires for each delta; `onDone` fires once with the persisted
   * assistant message so the bubble matches a fresh load.
   */
  chatStream: async (
    strategyId: number,
    message: string,
    handlers: {
      onToken: (delta: string) => void;
      onDone: (data: { message: MsMessage; balance: number }) => void;
      onError: (err: { code?: string; message: string }) => void;
      signal?: AbortSignal;
    },
  ): Promise<void> => {
    const token = await getToken();
    const url = `${getBaseUrl()}/api/v1/ai/marketing-strategist/${strategyId}/chat`;
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
      try {
        bodyText = await res.text();
      } catch {}
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
      try {
        parsed = JSON.parse(data);
      } catch {
        return;
      }
      if (!isRecord(parsed)) return;

      if (event === "token") {
        const delta = parsed.delta;
        if (typeof delta === "string") handlers.onToken(delta);
      } else if (event === "done") {
        const msg = parsed.message;
        const balance = parsed.balance;
        if (isRecord(msg) && typeof balance === "number") {
          handlers.onDone({
            message: msg as unknown as MsMessage,
            balance,
          });
        }
      } else if (event === "error") {
        const code = typeof parsed.code === "string" ? parsed.code : undefined;
        const msg =
          typeof parsed.message === "string"
            ? parsed.message
            : "The strategist could not reply.";
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
