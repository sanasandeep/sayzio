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

// An AI Persona — the "brain" a Companion must be wired to. A Companion
// can only be created on the spot if the user has at least one ENABLED
// persona. `use_brand_kit` is the reversible On-Brand AI setting (Task
// #2664). `is_disabled` is the retired/enabled state (Task #2686): the
// list now includes disabled agents so mobile can show + re-enable them,
// so callers must skip disabled personas when wiring a new Companion.
export type AiPersona = {
  id: number;
  name: string;
  use_brand_kit: boolean;
  is_disabled: boolean;
};

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
  // On-Brand AI (Task #2664): when on (default), the Companion injects the
  // owner's default Brand Kit voice into replies. Send false to opt out.
  use_brand_kit?: boolean;
}): Promise<AiPersona> {
  const res = await apiFetch<{ data: { persona: AiPersona } }>(
    `/ai-companions/personas`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data.persona;
}

// Focused PATCH for an existing agent. The web persona editor only
// exposes these knobs inside a full form save; mobile sends just the
// field(s) it wants to change and the backend writes a new persona
// version, mirroring the web save. Send at least one of:
//  - `name`          rename the agent (Task #2686)
//  - `is_disabled`   retire / re-enable the agent (Task #2686)
//  - `use_brand_kit` toggle On-Brand AI (Task #2664)
export type AiPersonaPatch = {
  name?: string;
  is_disabled?: boolean;
  use_brand_kit?: boolean;
};

export async function updateAiPersona(
  personaId: number,
  patch: AiPersonaPatch,
): Promise<AiPersona> {
  const res = await apiFetch<{ data: { persona: AiPersona } }>(
    `/ai-companions/personas/${personaId}`,
    { method: "PATCH", body: JSON.stringify(patch) },
  );
  return res.data.persona;
}

// On-Brand AI (Task #2664): flip `use_brand_kit` on an existing agent.
export async function updateAiPersonaBrandKit(
  personaId: number,
  useBrandKit: boolean,
): Promise<AiPersona> {
  return updateAiPersona(personaId, { use_brand_kit: useBrandKit });
}
