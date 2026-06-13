import { apiFetch } from "@/lib/api";

// Biolink-placement AI Companions the user owns. Mirrors the web block
// editor's special-panel $userCompanions list — used by the editor's
// "AI" picker to drop an `ai_companion` block bound to a chosen bot.
export type BiolinkCompanion = {
  id: number;
  public_id: string;
  name: string;
  is_disabled: boolean;
};

export async function listBiolinkCompanions(): Promise<{
  items: BiolinkCompanion[];
}> {
  const res = await apiFetch<{ data: { items: BiolinkCompanion[] } }>(
    `/ai-companions`,
  );
  return { items: res.data.items };
}

// An enabled AI Persona — the "brain" a Companion must be wired to. A
// Companion can only be created on the spot if the user has at least one.
export type AiPersona = { id: number; name: string };

export async function listAiPersonas(): Promise<{ items: AiPersona[] }> {
  const res = await apiFetch<{ data: { items: AiPersona[] } }>(
    `/ai-companions/personas`,
  );
  return { items: res.data.items };
}

export async function createBiolinkCompanion(payload: {
  name: string;
  persona_id: number;
}): Promise<BiolinkCompanion> {
  const res = await apiFetch<{ data: { companion: BiolinkCompanion } }>(
    `/ai-companions`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data.companion;
}

// Mint a minimal AI Persona (name + optional base instructions) on the
// spot so a Companion can be built fully self-serve on mobile. Every
// other persona knob falls back to the same defaults the web "blank"
// template uses; richer editing still lives on the web.
export async function createAiPersona(payload: {
  name: string;
  system_prompt?: string;
}): Promise<AiPersona> {
  const res = await apiFetch<{ data: { persona: AiPersona } }>(
    `/ai-companions/personas`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data.persona;
}
