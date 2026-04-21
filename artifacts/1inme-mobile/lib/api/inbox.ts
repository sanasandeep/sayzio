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
};

export type Message = {
  id: number;
  sender_type: string;
  body: string;
  created_at: string | null;
  read_at: string | null;
};

export async function listConversations(
  status?: "open" | "archived" | "blocked",
): Promise<{ items: Conversation[]; unread: number }> {
  const qs = status ? `?status=${status}` : "";
  const res = await apiFetch<{
    data: { items: Conversation[]; meta: { unread: number } };
  }>(`/inbox/conversations${qs}`);
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
