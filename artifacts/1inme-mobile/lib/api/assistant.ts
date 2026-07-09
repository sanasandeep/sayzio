import { apiFetch } from "@/lib/api";

// Mobile parity for the web standalone multi-channel quick-contact widget.
// Posts to the same /assistant/quick-contact contract (validated server-side
// by QuickContactService) so a request lands in the admin Contact Inbox and
// triggers an admin email. The endpoint is not login-gated, but apiFetch
// attaches the bearer token when present so a signed-in caller's name/email
// default in on the server.
//
// Channels:
//   - callback : Indian phone number only (+91 / 10-digit, 6-9 leading)
//   - whatsapp : phone number WITH country code (E.164-ish, +<digits>)
//   - email    : a valid email address
export type QuickContactChannel = "callback" | "whatsapp" | "email";

export type QuickContactInput = {
  channel: QuickContactChannel;
  name?: string | null;
  email?: string | null;
  phone?: string | null;
  message?: string | null;
  website?: string | null;
  elapsedMs?: number | null;
};

export type QuickContactResult = {
  ok: boolean;
  message: string;
};

export async function sendQuickContact(
  input: QuickContactInput,
): Promise<QuickContactResult> {
  return apiFetch<QuickContactResult>("/assistant/quick-contact", {
    method: "POST",
    body: JSON.stringify({
      channel: input.channel,
      name: input.name?.trim() || undefined,
      email: input.email?.trim() || undefined,
      phone: input.phone?.trim() || undefined,
      message: input.message?.trim() || undefined,
      website: input.website?.trim() || undefined,
      elapsed_ms:
        typeof input.elapsedMs === "number" && input.elapsedMs >= 0
          ? Math.round(input.elapsedMs)
          : undefined,
    }),
  });
}

// ── Ask Zio chat API ──────────────────────────────────────────────────────────
// Bearer-token mirror of the web /assistant/* endpoints. Mobile users are
// always authenticated so the in-chat OTP login path is never needed.

export type AssistantBootstrap = {
  enabled: boolean;
  surface: string;
  accent_color: string;
  avatar_url: string | null;
  brand_name: string;
  greeting: string;
  starter_prompts: string[];
  input_placeholder: string;
  send_label: string;
  subheading: string;
  typing_indicator: string;
  handoff_note: string;
  cutoff_notice: string;
  cutoff_retry_label: string;
  error_network: string;
  error_generic: string;
  handoff_enabled: boolean;
  auth_required: boolean;
  templates: unknown[];
};

export type AssistantBlockButton = {
  label: string;
  value: string;
  template?: string;
};

export type AssistantBlockListItem = {
  title: string;
  description?: string;
  image?: string;
  value?: string;
};

export type AssistantFormField = {
  name: string;
  label: string;
  type: "text" | "email" | "phone" | "textarea" | "select";
  placeholder?: string;
  required?: boolean;
  options?: { label: string; value: string }[];
};

export type AssistantBlock =
  | { type: "buttons"; items: AssistantBlockButton[] }
  | { type: "list"; items: AssistantBlockListItem[] }
  | {
      type: "form";
      fields: AssistantFormField[];
      submit_label?: string;
      template?: string;
    }
  | { type: "image"; url: string; alt?: string };

export type AssistantSessionResponse = {
  visitor_token: string;
};

export type AssistantTurnResponse = {
  ok: boolean;
  reply: string;
  blocks: AssistantBlock[];
  handoff_open: boolean;
  is_cutoff: boolean;
  message_id: number | null;
  low_balance: boolean | string;
  error?: string;
  auth_required?: boolean;
};

export type AssistantHandoffResponse = {
  ok: boolean;
  reply?: string;
  error?: string;
};

export async function assistantBootstrap(): Promise<AssistantBootstrap> {
  return apiFetch<AssistantBootstrap>("/assistant/bootstrap");
}

export async function assistantSession(params: {
  visitorToken?: string | null;
  page?: { route?: string; path?: string; title?: string };
}): Promise<AssistantSessionResponse> {
  return apiFetch<AssistantSessionResponse>("/assistant/session", {
    method: "POST",
    body: JSON.stringify({
      visitor_token: params.visitorToken ?? undefined,
      surface: "app",
      page: params.page ?? {},
    }),
  });
}

export async function assistantMessage(params: {
  visitorToken: string;
  message: string;
  page?: { route?: string; path?: string; title?: string };
}): Promise<AssistantTurnResponse> {
  return apiFetch<AssistantTurnResponse>("/assistant/message", {
    method: "POST",
    body: JSON.stringify({
      visitor_token: params.visitorToken,
      surface: "app",
      message: params.message,
      page: params.page ?? {},
    }),
  });
}

export async function assistantChoice(params: {
  visitorToken: string;
  choice: {
    label?: string;
    value?: string;
    template?: string;
    values?: Record<string, string>;
  };
  page?: { route?: string; path?: string; title?: string };
}): Promise<AssistantTurnResponse> {
  return apiFetch<AssistantTurnResponse>("/assistant/choice", {
    method: "POST",
    body: JSON.stringify({
      visitor_token: params.visitorToken,
      surface: "app",
      choice: params.choice,
      page: params.page ?? {},
    }),
  });
}

export async function assistantHandoff(params: {
  visitorToken: string;
  channel: QuickContactChannel;
  name?: string;
  email?: string;
  phone?: string;
  message?: string;
  page?: { route?: string; path?: string; title?: string };
}): Promise<AssistantHandoffResponse> {
  return apiFetch<AssistantHandoffResponse>("/assistant/handoff", {
    method: "POST",
    body: JSON.stringify({
      visitor_token: params.visitorToken,
      surface: "app",
      channel: params.channel,
      name: params.name ?? undefined,
      email: params.email ?? undefined,
      phone: params.phone ?? undefined,
      message: params.message ?? undefined,
      page: params.page ?? {},
    }),
  });
}

export async function assistantLowBalanceClick(params: {
  visitorToken?: string;
  targetUrl: string;
}): Promise<{ ok: boolean }> {
  return apiFetch<{ ok: boolean }>("/assistant/low-balance-click", {
    method: "POST",
    body: JSON.stringify({
      visitor_token: params.visitorToken ?? undefined,
      surface: "app",
      target_url: params.targetUrl,
    }),
  });
}
