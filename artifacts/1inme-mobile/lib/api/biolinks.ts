import { apiFetch } from "@/lib/api";

export type BiolinkBlock = {
  id: number;
  type: string;
  sort_order: number;
  parent_id: number | null;
  settings: Record<string, unknown> | null;
};

export type BiolinkPayload = {
  biolink: {
    id: number;
    alias: string;
    title: string | null;
    visibility: "public" | "registered" | "followers" | "subscribers";
    seo_title: string | null;
    seo_description: string | null;
    seo_image: string | null;
  };
  owner: {
    id: number | null;
    name: string | null;
    handle: string | null;
    avatar: string | null;
    bio: string | null;
    followers_count: number;
  };
  blocks: BiolinkBlock[];
};

export type BiolinkError = {
  status: number;
  code: string;
  message: string;
  visibility?: string;
  owner?: { handle: string | null; name: string | null };
};

export async function getBiolink(alias: string): Promise<BiolinkPayload> {
  const res = await apiFetch<{ data: BiolinkPayload }>(
    `/biolinks/${encodeURIComponent(alias)}`,
  );
  return res.data;
}
