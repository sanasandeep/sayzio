import { apiFetch } from "@/lib/api";

export type Conversation = {
  id: number;
  link_id: number;
  link_alias: string | null;
  link_title: string | null;
  viewer_user_id: number | null;
  viewer_name: string | null;
  viewer_avatar: string | null;
  status: "open" | "archived" | "blocked";
  last_message_at: string | null;
  last_message_preview: string | null;
  last_sender: string | null;
  owner_unread_count: number;
  viewer_msg_count: number;
  owner_msg_count: number;
  assignee_user_id: number | null;
  assignee_name: string | null;
};

export type Message = {
  id: number;
  sender_type: string;
  body: string;
  created_at: string | null;
  read_at: string | null;
};

export type Teammate = { id: number; name: string };

export async function listConversations(
  status?: "open" | "archived" | "blocked",
  assignee?: "me",
): Promise<{ items: Conversation[]; unread: number }> {
  const qs = new URLSearchParams();
  if (status) qs.set("status", status);
  if (assignee) qs.set("assignee", assignee);
  const query = qs.toString() ? `?${qs.toString()}` : "";
  const res = await apiFetch<{
    data: { items: Conversation[]; meta: { unread: number } };
  }>(`/inbox/conversations${query}`);
  return { items: res.data.items, unread: res.data.meta.unread };
}

export async function getConversation(
  id: number,
): Promise<{ conversation: Conversation; messages: Message[] }> {
  const res = await apiFetch<{
    data: { conversation: Conversation; messages: Message[] };
  }>(`/inbox/conversations/${id}`);
  return res.data;
}

export async function replyConversation(
  id: number,
  body: string,
): Promise<Message> {
  const res = await apiFetch<{ data: { message: Message } }>(
    `/inbox/conversations/${id}/reply`,
    { method: "POST", body: JSON.stringify({ body }) },
  );
  return res.data.message;
}

export async function setConversationStatus(
  id: number,
  status: Conversation["status"],
): Promise<void> {
  await apiFetch(`/inbox/conversations/${id}/status`, {
    method: "PATCH",
    body: JSON.stringify({ status }),
  });
}

export async function deleteConversation(id: number): Promise<void> {
  await apiFetch(`/inbox/conversations/${id}`, { method: "DELETE" });
}

export async function assignConversation(
  id: number,
  assigneeUserId: number | null,
  note?: string,
): Promise<Conversation> {
  const res = await apiFetch<{ data: { conversation: Conversation } }>(
    `/inbox/conversations/${id}/assign`,
    {
      method: "POST",
      body: JSON.stringify({
        assignee_user_id: assigneeUserId,
        note: note ?? null,
      }),
    },
  );
  return res.data.conversation;
}

export async function listTeammates(): Promise<Teammate[]> {
  const res = await apiFetch<{ data: { items: Teammate[] } }>(
    `/inbox/teammates`,
  );
  return res.data.items;
}

// ---- Spam settings (mobile parity for the web Spam Settings page) -------

export type SpamSettings = {
  blocked_keywords: string[];
  disabled_default_keywords: string[];
  trusted_emails: string[];
  trusted_phones: string[];
};

export type DisabledDefault = { keyword: string; disabled_at: string | null };

export type SpamPayload = {
  spam: SpamSettings;
  defaults: string[];
  disabled_defaults: DisabledDefault[];
};

export type TrustedImportStats = {
  rows_read: number;
  emails_added: number;
  phones_added: number;
  duplicates: number;
  invalid_values: number;
  invalid_rows: number;
};

export async function getSpamSettings(): Promise<SpamPayload> {
  const res = await apiFetch<{ data: SpamPayload }>(`/inbox/spam-settings`);
  return res.data;
}

export async function updateSpamSettings(
  payload: SpamSettings,
): Promise<SpamPayload> {
  const res = await apiFetch<{ data: SpamPayload }>(`/inbox/spam-settings`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
  return res.data;
}

export async function disableSpamKeyword(
  keyword: string,
): Promise<{ message: string; spam: SpamSettings }> {
  const res = await apiFetch<{ data: { message: string; spam: SpamSettings } }>(
    `/inbox/spam-settings/disable-keyword`,
    { method: "POST", body: JSON.stringify({ keyword }) },
  );
  return res.data;
}

export async function enableDefaultSpamKeyword(
  keyword: string,
): Promise<{ message: string; spam: SpamSettings }> {
  const res = await apiFetch<{ data: { message: string; spam: SpamSettings } }>(
    `/inbox/spam-settings/enable-keyword`,
    { method: "POST", body: JSON.stringify({ keyword }) },
  );
  return res.data;
}

export async function importTrustedCsv(file: {
  uri: string;
  name?: string;
  mimeType?: string;
}): Promise<{ stats: TrustedImportStats; spam: SpamSettings }> {
  const { getBaseUrl, MOBILE_USER_AGENT } = await import("@/lib/api");
  const { getToken } = await import("@/lib/secure");
  const url = `${getBaseUrl()}/api/v1/inbox/spam-settings/import-trusted`;
  const token = await getToken();
  const fd = new FormData();
  fd.append("csv", {
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment
    // @ts-ignore – RN-specific FormData entry.
    uri: file.uri,
    name: file.name || "trusted.csv",
    type: file.mimeType || "text/csv",
  } as any);
  const headers: Record<string, string> = {
    Accept: "application/json",
    "User-Agent": MOBILE_USER_AGENT,
    "X-1INME-Client": MOBILE_USER_AGENT,
  };
  if (token) headers.Authorization = `Bearer ${token}`;
  const res = await fetch(url, { method: "POST", body: fd as any, headers });
  const text = await res.text();
  const body = text ? JSON.parse(text) : null;
  if (!res.ok) {
    const nested = body && typeof body.error === "object" ? body.error : null;
    throw {
      status: res.status,
      message:
        nested?.message ||
        (body && body.message) ||
        `Upload failed (${res.status})`,
      code: nested?.code,
    };
  }
  return (body as { data: { stats: TrustedImportStats; spam: SpamSettings } })
    .data;
}

// ---- Forwarding rules (mobile parity for the web Forwarding page) -------

export type ForwardDestination = {
  id: number;
  label: string;
  type: "email" | "webhook";
  target: string;
  method: string;
  sources: string[];
  header_key: string | null;
  has_secret: boolean;
  is_active: boolean;
  last_status: string | null;
  last_delivered_at: string | null;
  created_at: string | null;
};

export type ForwardDelivery = {
  id: number;
  destination_id: number;
  destination_label: string | null;
  destination_type: string | null;
  source_type: string | null;
  is_test: boolean;
  status: string;
  attempts: number;
  last_error: string | null;
  last_response_code: number | null;
  last_attempt_at: string | null;
  delivered_at: string | null;
  created_at: string | null;
};

export type ForwardPayload = {
  destinations: ForwardDestination[];
  deliveries: ForwardDelivery[];
  source_labels: Record<string, string>;
};

export type ForwardInput = {
  label: string;
  type: "email" | "webhook";
  target: string;
  method?: "POST" | "PUT" | "GET";
  sources?: string[] | null;
  header_key?: string | null;
  header_value?: string | null;
  secret?: string | null;
  is_active?: boolean;
};

export async function listForwards(): Promise<ForwardPayload> {
  const res = await apiFetch<{ data: ForwardPayload }>(`/inbox/forwards`);
  return res.data;
}

export async function createForward(
  input: ForwardInput,
): Promise<ForwardDestination> {
  const res = await apiFetch<{ data: { destination: ForwardDestination } }>(
    `/inbox/forwards`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.destination;
}

export async function updateForward(
  id: number,
  input: ForwardInput,
): Promise<ForwardDestination> {
  const res = await apiFetch<{ data: { destination: ForwardDestination } }>(
    `/inbox/forwards/${id}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
  return res.data.destination;
}

export async function toggleForward(id: number): Promise<ForwardDestination> {
  const res = await apiFetch<{ data: { destination: ForwardDestination } }>(
    `/inbox/forwards/${id}/toggle`,
    { method: "POST" },
  );
  return res.data.destination;
}

export async function deleteForward(id: number): Promise<void> {
  await apiFetch(`/inbox/forwards/${id}`, { method: "DELETE" });
}

export async function testForward(
  id: number,
): Promise<{ sent: boolean; message: string; delivery: ForwardDelivery | null }> {
  const res = await apiFetch<{
    data: { sent: boolean; message: string; delivery: ForwardDelivery | null };
  }>(`/inbox/forwards/${id}/test`, { method: "POST" });
  return res.data;
}

export async function retryForwardDelivery(
  id: number,
): Promise<ForwardDelivery> {
  const res = await apiFetch<{ data: { delivery: ForwardDelivery } }>(
    `/inbox/forward-deliveries/${id}/retry`,
    { method: "POST" },
  );
  return res.data.delivery;
}
