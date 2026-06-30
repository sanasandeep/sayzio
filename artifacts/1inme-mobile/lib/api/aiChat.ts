import { apiFetch } from "@/lib/api";

export type AiChatTheme = "auto" | "light" | "dark";

export type AiChatConfig = {
  greeting: string | null;
  placeholder: string;
  accent: string;
  theme: AiChatTheme;
  show_branding: boolean;
  ground_in_profile: boolean;
  avatar_url: string | null;
  custom_branding_text: string | null;
  custom_branding_url: string | null;
};

export type AiChatBranding = {
  can_hide_branding: boolean;
  can_custom_branding: boolean;
  can_avatar: boolean;
};

export type AiChatPersona = { id: number; name: string };

export type AiChatPage = {
  link_id: number;
  alias: string;
  public_url: string;
  name: string;
  persona_id: number | null;
  config: AiChatConfig;
  branding: AiChatBranding;
  starters: string[];
  usage: {
    turns: number;
    free_turns_per_month: number;
    hard_cap_per_month: number;
  };
  ai_enabled: boolean;
};

export type AiChatEditor = {
  ai_chat: AiChatPage;
  personas: AiChatPersona[];
};

export async function getAiChat(id: number): Promise<AiChatEditor> {
  const res = await apiFetch<{ data: AiChatEditor }>(`/links/${id}/ai-chat`);
  return res.data;
}

export async function saveAiChat(
  id: number,
  payload: {
    name: string;
    persona_id: number;
    config: AiChatConfig;
    starters: string[];
  },
): Promise<AiChatEditor> {
  const res = await apiFetch<{ data: AiChatEditor }>(`/links/${id}/ai-chat`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
  return res.data;
}
