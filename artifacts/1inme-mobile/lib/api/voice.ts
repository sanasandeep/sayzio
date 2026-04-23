import { Platform } from "react-native";

import { MOBILE_USER_AGENT, getBaseUrl } from "@/lib/api";
import { getToken } from "@/lib/secure";

/* ── Types mirror the web's VoiceAssistantService payload. ─────── */

export type VoiceMessage = {
  role: "system" | "user" | "assistant" | "tool";
  content: string;
  tool_calls?: unknown;
  tool_call_id?: string;
  name?: string;
};

export type VoiceToolResult = {
  tool: string;
  arguments: Record<string, unknown>;
  result: {
    summary?: string;
    error?: string;
    data?: unknown;
    navigate_to?: string;
    nfc_write?: { link_id: number; alias: string; url: string };
    confirm_required?: boolean;
    tool?: string;
    arguments?: Record<string, unknown>;
    description?: string;
  };
};

export type VoicePendingConfirmation = {
  confirm_required: true;
  tool: string;
  arguments: Record<string, unknown>;
  description: string;
};

export type VoiceTurnResponse = {
  transcript: string;
  reply: string;
  audio_base64: string | null;
  tool_results: VoiceToolResult[];
  pending_confirmations: VoicePendingConfirmation[];
  credits: { stt: number; llm: number; tts: number; total: number };
  balance: number;
  messages: VoiceMessage[];
};

export type VoiceCapabilityTool = {
  name: string;
  description: string;
  category: string;
  destructive: boolean;
  role: string;
};

export type VoiceCapabilities = {
  enabled: boolean;
  balance: number;
  rate_limit: number;
  pricing: {
    stt_credits_per_minute: number;
    tts_credits_per_1k_chars: number;
  };
  tools: Record<string, VoiceCapabilityTool[]>;
  limitations: string[];
};

/* ── HTTP helpers ─────────────────────────────────────────────── */

async function jsonGet<T>(path: string): Promise<T> {
  const token = await getToken();
  const res = await fetch(`${getBaseUrl()}/api/v1${path}`, {
    headers: {
      Accept: "application/json",
      "User-Agent": MOBILE_USER_AGENT,
      "X-1INME-Client": MOBILE_USER_AGENT,
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
  const text = await res.text();
  const body = text ? safeJson(text) : null;
  if (!res.ok) {
    throw {
      status: res.status,
      message:
        (body && (body.message || body.error)) ||
        `Request failed (${res.status})`,
    };
  }
  return body as T;
}

function safeJson(text: string): Record<string, unknown> | null {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}

export async function fetchCapabilities(): Promise<VoiceCapabilities> {
  return jsonGet<VoiceCapabilities>("/ai/voice/capabilities");
}

/**
 * Upload a recorded audio clip and run a voice turn. Multipart upload
 * — `apiFetch` only handles JSON, so we hand-roll the request here.
 */
export async function runTurn(args: {
  uri: string;
  mime: string;
  filename: string;
  history: VoiceMessage[];
  confirmedTools: Record<string, boolean>;
}): Promise<VoiceTurnResponse> {
  const token = await getToken();
  const form = new FormData();
  // React Native FormData expects { uri, name, type } objects for files.
  form.append("audio", {
    uri: args.uri,
    name: args.filename,
    type: args.mime,
  } as unknown as Blob);
  form.append(
    "context",
    JSON.stringify({
      messages: args.history,
      confirmed_tools: args.confirmedTools,
      client_kind: "mobile",
      platform: Platform.OS,
    }),
  );

  const res = await fetch(`${getBaseUrl()}/api/v1/ai/voice/turn`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "User-Agent": MOBILE_USER_AGENT,
      "X-1INME-Client": MOBILE_USER_AGENT,
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      // NOTE: do NOT set Content-Type — fetch fills in the multipart
      // boundary itself when we pass FormData on React Native.
    },
    body: form as unknown as BodyInit,
  });
  const text = await res.text();
  const body = text ? safeJson(text) : null;
  if (!res.ok) {
    throw {
      status: res.status,
      message:
        (body && (body.message || body.error)) ||
        `Voice turn failed (${res.status})`,
      balance: body?.balance,
    };
  }
  return body as VoiceTurnResponse;
}
