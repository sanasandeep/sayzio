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

// Best-effort biolink page-visit tracking. Mirrors the web's
// RedirectController::track() call so opening a creator's biolink in the
// mobile app is counted toward their total/unique visit analytics.
// Never throws — analytics is fire-and-forget.
export function trackBiolinkVisit(alias: string): void {
  if (!alias) return;
  void apiFetch(`/biolinks/${encodeURIComponent(alias)}/visit`, {
    method: "POST",
    body: JSON.stringify({}),
  }).catch(() => {
    // Swallow — analytics must never disrupt the page load.
  });
}

// Best-effort block tap tracking. Mirrors the web's per-block click tracker
// (the `/{alias}/b/{blockId}` redirect on the website) so taps that happen
// inside the in-app biolink viewer also show up in the creator's analytics.
// Never throws — a failed analytics ping must not break the link open.
export function trackBiolinkBlockTap(
  alias: string,
  blockId: number,
  destinationUrl?: string | null,
): void {
  if (!alias || !Number.isFinite(blockId)) return;
  const body: Record<string, unknown> = {};
  if (destinationUrl) body.destination_url = destinationUrl;
  void apiFetch(
    `/biolinks/${encodeURIComponent(alias)}/blocks/${blockId}/tap`,
    {
      method: "POST",
      body: JSON.stringify(body),
    },
  ).catch(() => {
    // Swallow — analytics is best-effort and must never disrupt the tap.
  });
}
