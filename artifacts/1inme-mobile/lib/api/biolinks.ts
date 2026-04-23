import AsyncStorage from "@react-native-async-storage/async-storage";

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

// Local memory of "I already responded to this poll/RSVP" so a viewer who
// reopens a creator's biolink doesn't see the same prompt again. The web
// version uses the Laravel session for this; mobile has no shared cookie
// jar with the WebView, so we persist a small per-(alias, block) marker
// in AsyncStorage instead. Storing the chosen label (not just a flag)
// lets us echo it back on the "Thanks for responding" card.
const RESPONSE_KEY_PREFIX = "biolink:response:v1:";

function responseKey(alias: string, blockId: number): string {
  return `${RESPONSE_KEY_PREFIX}${alias}:${blockId}`;
}

export async function getRememberedBlockResponse(
  alias: string,
  blockId: number,
): Promise<string | null> {
  if (!alias || !Number.isFinite(blockId)) return null;
  try {
    return await AsyncStorage.getItem(responseKey(alias, blockId));
  } catch {
    return null;
  }
}

export async function rememberBlockResponse(
  alias: string,
  blockId: number,
  value: string,
): Promise<void> {
  if (!alias || !Number.isFinite(blockId)) return;
  try {
    await AsyncStorage.setItem(responseKey(alias, blockId), value);
  } catch {
    // Persistence is best-effort — failing here just means the viewer
    // will see the prompt again next time, which is no worse than today.
  }
}

export async function forgetBlockResponse(
  alias: string,
  blockId: number,
): Promise<void> {
  if (!alias || !Number.isFinite(blockId)) return;
  try {
    await AsyncStorage.removeItem(responseKey(alias, blockId));
  } catch {
    // No-op — see rememberBlockResponse comment.
  }
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
