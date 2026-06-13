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
