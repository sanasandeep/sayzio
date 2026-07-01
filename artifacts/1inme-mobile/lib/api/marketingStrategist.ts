import { fetch as expoFetch } from "expo/fetch";
import { Platform } from "react-native";

import { MOBILE_USER_AGENT, apiFetch, getBaseUrl } from "@/lib/api";
import { getToken } from "@/lib/secure";

// Mobile parity for the web "AI Marketing Strategist" — the internally
// branded "AI Digital Performer Specialist" (Task #3061). Consumes the
// REST endpoints exposed by
// App\Modules\Api\Controllers\MarketingStrategistController, all under
// auth:sanctum and behind the AI engine + plan gate. Responses use the
// unified {data}/{error} envelope.
//
//   GET    /ai/marketing-strategist                              list + sources + balance
//   POST   /ai/marketing-strategist/estimate                     worst-case credit cost
//   POST   /ai/marketing-strategist                              generate (201)
//   GET    /ai/marketing-strategist/{id}                         strategy + suggestions + chat
//   DELETE /ai/marketing-strategist/{id}                         delete
//   GET    /ai/marketing-strategist/{id}/export?format=pdf|md    download
//   POST   /ai/marketing-strategist/{id}/chat                    refine (SSE or JSON)
//   POST   /ai/marketing-strategist/suggestions/{id}/apply       apply (needs confirm)
//   POST   /ai/marketing-strategist/suggestions/{id}/dismiss     dismiss

// ── types ───────────────────────────────────────────────────────

export type StrategyItem = {
  id: number;
  label: string;
  sub: string;
};

export type StrategySource = {
  key: string;
  label: string;
  description: string;
  // Item-bearing sources (links, pixels, knowledge bases, brand kits,
  // personas, companions) expose `items` so the builder can narrow to a
  // subset; picking none = "use all". Aggregate sources omit both.
  selectable?: boolean;
  items?: StrategyItem[];
};

export type StrategyParameters = {
  budget?: string;
  currency?: string;
  region?: string;
  audience?: string;
  timeframe?: string;
  cadence?: string;
  tone?: string;
  brand_voice?: string;
  competitors?: string;
  main_offer?: string;
  avoid?: string;
  channels?: string;
  plan_type?: "both" | "organic" | "paid";
  content_types?: string[];
  paid_media?: string[];
};

// Per-source selected item IDs. An empty / missing list for a source means
// "use all of them" (the original whole-category behaviour).
export type StrategySelections = Record<string, number[]>;

export type StrategyPlay = {
  title?: string;
  channel?: string;
  budget_hint?: string;
  rationale?: string;
  steps?: string[];
  sayzio_features?: string[];
};

export type StrategyPlan = {
  summary?: string;
  organic?: StrategyPlay[];
  paid?: StrategyPlay[];
  kpis?: string[];
};

export type StrategySummary = {
  id: number;
  title: string;
  goal: string;
  sources: string[];
  credits_spent: number;
  created_at: string | null;
};

export type StrategyDetail = {
  id: number;
  title: string;
  goal: string;
  parameters: StrategyParameters;
  sources: string[];
  source_items?: StrategySelections;
  strategy: StrategyPlan;
  credits_spent: number;
  model: string | null;
  created_at: string | null;
};

export type SuggestionStatus =
  | "pending"
  | "applied"
  | "dismissed"
  | "error";

export type StrategySuggestion = {
  id: number;
  type: string;
  type_label: string;
  title: string;
  description: string | null;
  status: SuggestionStatus;
  applied_ref_type: string | null;
  applied_ref_id: number | null;
  error: string | null;
};

export type StrategyChatMessage = {
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

export type StrategistIndex = {
  strategies: StrategySummary[];
  ai_enabled: boolean;
  sources?: StrategySource[];
  balance?: number;
};

export type StrategyShow = {
  strategy: StrategyDetail;
  suggestions: StrategySuggestion[];
  messages: StrategyChatMessage[];
  balance: number;
};

export type StrategyEstimate = {
  estimate: number;
  balance: number;
};

export type StrategyCreateInput = {
  goal: string;
  sources: string[];
  selections?: StrategySelections;
  parameters: StrategyParameters;
};

export type StrategyCreateResult = {
  strategy: StrategyDetail;
  credits_spent: number;
  balance: number;
};

export type SuggestionApplyResult = {
  status: SuggestionStatus;
  message: string;
  url?: string | null;
};

// ── helpers ─────────────────────────────────────────────────────

function buildPayload(input: StrategyCreateInput): Record<string, unknown> {
  const parameters: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(input.parameters)) {
    if (typeof v === "string") {
      if (v.trim()) parameters[k] = v.trim();
    } else if (Array.isArray(v)) {
      const cleaned = v.map((x) => String(x).trim()).filter(Boolean);
      if (cleaned.length) parameters[k] = cleaned;
    }
  }

  // Keep only selections for sources that are actually toggled on, and
  // drop empty lists (an empty list means "use all", so sending it is moot).
  const source_items: StrategySelections = {};
  if (input.selections) {
    for (const [k, ids] of Object.entries(input.selections)) {
      if (!input.sources.includes(k)) continue;
      const clean = (ids ?? [])
        .map((n) => Number(n))
        .filter((n) => Number.isFinite(n) && n > 0);
      if (clean.length) source_items[k] = Array.from(new Set(clean));
    }
  }

  return {
    goal: input.goal,
    sources: input.sources,
    ...(Object.keys(source_items).length ? { source_items } : {}),
    parameters,
  };
}

// ── calls ───────────────────────────────────────────────────────

export const marketingStrategist = {
  index: () =>
    apiFetch<{ data: StrategistIndex }>("/ai/marketing-strategist").then(
      (r) => r.data,
    ),

  estimate: (input: StrategyCreateInput) =>
    apiFetch<{ data: StrategyEstimate }>("/ai/marketing-strategist/estimate", {
      method: "POST",
      body: JSON.stringify(buildPayload(input)),
    }).then((r) => r.data),

  create: (input: StrategyCreateInput) =>
    apiFetch<{ data: StrategyCreateResult }>("/ai/marketing-strategist", {
      method: "POST",
      body: JSON.stringify(buildPayload(input)),
    }).then((r) => r.data),

  show: (id: number) =>
    apiFetch<{ data: StrategyShow }>(`/ai/marketing-strategist/${id}`).then(
      (r) => r.data,
    ),

  destroy: (id: number) =>
    apiFetch<{ data: { deleted: boolean } }>(
      `/ai/marketing-strategist/${id}`,
      { method: "DELETE" },
    ).then((r) => r.data),

  applySuggestion: (id: number) =>
    apiFetch<{ data: SuggestionApplyResult }>(
      `/ai/marketing-strategist/suggestions/${id}/apply`,
      { method: "POST", body: JSON.stringify({ confirm: true }) },
    ).then((r) => r.data),

  dismissSuggestion: (id: number) =>
    apiFetch<{ data: { status: SuggestionStatus } }>(
      `/ai/marketing-strategist/suggestions/${id}/dismiss`,
      { method: "POST" },
    ).then((r) => r.data),

  /**
   * Chat-refine the strategy, streamed token-by-token. The same /chat
   * endpoint branches into SSE when called with `Accept: text/event-stream`
   * (mirrors Ask Coach). `onToken` fires for each delta; `onDone` fires once
   * with the persisted assistant message so the bubble matches a reload.
   */
  chatStream: async (
    id: number,
    message: string,
    handlers: {
      onToken: (delta: string) => void;
      onDone: (data: {
        message: StrategyChatMessage;
        balance: number;
      }) => void;
      onError: (err: { code?: string; message: string }) => void;
      signal?: AbortSignal;
    },
  ): Promise<void> => {
    const token = await getToken();
    const url = `${getBaseUrl()}/api/v1/ai/marketing-strategist/${id}/chat`;
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
      } catch {
        // ignore
      }
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
        const m = parsed.message;
        const balance = parsed.balance;
        if (isRecord(m) && typeof balance === "number") {
          handlers.onDone({
            message: m as unknown as StrategyChatMessage,
            balance,
          });
        }
      } else if (event === "error") {
        const code = typeof parsed.code === "string" ? parsed.code : undefined;
        const msg =
          typeof parsed.message === "string"
            ? parsed.message
            : "The strategist could not reply right now.";
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

/**
 * Download a strategy as Markdown (default) or PDF and hand it to the OS
 * share sheet. The export endpoint sits behind auth:sanctum so the request
 * MUST carry the bearer token — a plain browser open would 404. On web we
 * fetch a blob and trigger an anchor download; on native we download to the
 * cache with the auth header and share the local file.
 */
export async function exportStrategy(
  id: number,
  format: "md" | "pdf",
  title: string,
): Promise<void> {
  const token = await getToken();
  const url = `${getBaseUrl()}/api/v1/ai/marketing-strategist/${id}/export?format=${format}`;
  const slug =
    (title || "marketing-strategy")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/(^-|-$)/g, "") || "marketing-strategy";
  const ext = format === "pdf" ? "pdf" : "md";
  const mimeType = format === "pdf" ? "application/pdf" : "text/markdown";
  const filename = `${slug}.${ext}`;
  const headers: Record<string, string> = {
    "User-Agent": MOBILE_USER_AGENT,
    "X-1INME-Client": MOBILE_USER_AGENT,
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  if (Platform.OS === "web") {
    const res = await fetch(url, { headers });
    if (!res.ok) throw new Error(`Export failed (${res.status}).`);
    const blob = await res.blob();
    const href = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = href;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(href);
    return;
  }

  const FileSystem = await import("expo-file-system/legacy");
  const Sharing = await import("expo-sharing");
  const target = `${FileSystem.cacheDirectory ?? ""}${filename}`;
  const dl = await FileSystem.downloadAsync(url, target, { headers });
  if (dl.status !== 200) throw new Error(`Export failed (${dl.status}).`);
  if (await Sharing.isAvailableAsync()) {
    await Sharing.shareAsync(dl.uri, { mimeType, dialogTitle: title });
  }
}
