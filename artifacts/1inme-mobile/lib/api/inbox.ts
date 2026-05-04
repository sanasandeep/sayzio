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
