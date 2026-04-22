import { apiFetch } from "@/lib/api";

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
};
