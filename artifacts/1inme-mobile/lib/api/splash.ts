import { apiFetch } from "@/lib/api";

export type SplashPage = {
  id: number;
  name: string;
  title: string | null;
  description: string | null;
  cta_label: string | null;
  cta_url: string | null;
  auto_redirect: boolean;
  countdown: number | null;
  project_id: number | null;
  created_at?: string | null;
};

export async function listSplashPages(): Promise<SplashPage[]> {
  const res = await apiFetch<{ data: { items: SplashPage[] } }>("/splash-pages");
  return res.data.items;
}

export async function deleteSplashPage(id: number): Promise<void> {
  await apiFetch(`/splash-pages/${id}`, { method: "DELETE" });
}
