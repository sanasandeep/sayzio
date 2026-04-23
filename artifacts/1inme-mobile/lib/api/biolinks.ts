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

// Submit a poll vote natively. The server dedupes by viewer (auth user
// id when signed in, else ip+ua fingerprint) so re-tapping a different
// option just updates the previous vote. Returns the recorded option.
export async function submitPollVote(
  alias: string,
  blockId: number,
  optionIndex: number,
  optionLabel?: string,
): Promise<{ option_index: number; option_label: string | null }> {
  const res = await apiFetch<{
    data: {
      recorded: boolean;
      vote_id: number;
      option_index: number;
      option_label: string | null;
    };
  }>(`/biolinks/${encodeURIComponent(alias)}/blocks/${blockId}/poll-vote`, {
    method: "POST",
    body: JSON.stringify({
      option_index: optionIndex,
      option_label: optionLabel ?? undefined,
    }),
  });
  return {
    option_index: res.data.option_index,
    option_label: res.data.option_label,
  };
}

export type PollResults = {
  block_id: number;
  total_votes: number;
  options: { index: number; label: string; count: number; percent: number }[];
};

// Thrown by getPollResults when the creator has set a "reveal results at"
// deadline that hasn't passed yet. Lets the caller render a "Results
// visible after <date>" message instead of a generic 403 fallback.
export type PollResultsLockedError = {
  status: number;
  message: string;
  errors?: Record<string, string[]>;
  code: "results_locked";
  reveal_at: string;
};

// Fetch aggregated tallies for a poll block. Used right after a viewer
// votes (and on first render for viewers who already voted in a previous
// session) so they can see how their pick compares to the rest. Returns
// every configured option, even those with zero votes, so the bar list
// stays stable between fetches.
export async function getPollResults(
  alias: string,
  blockId: number,
): Promise<PollResults> {
  try {
    const res = await apiFetch<{ data: PollResults }>(
      `/biolinks/${encodeURIComponent(alias)}/blocks/${blockId}/poll-results`,
    );
    return res.data;
  } catch (e) {
    // apiFetch puts the server's `error.details` into `errors`, so a
    // results_locked 403 carries the reveal_at timestamp through that
    // field. Re-throw it as a typed PollResultsLockedError so callers
    // can render a "Results visible after <date>" message instead of
    // the generic 403 fallback.
    if (e && typeof e === "object" && "status" in e && (e as { status: number }).status === 403) {
      const details = (e as { errors?: Record<string, unknown> }).errors;
      const revealAt = details && typeof details.reveal_at === "string" ? details.reveal_at : null;
      if (revealAt) {
        const locked: PollResultsLockedError = {
          status: 403,
          message: (e as { message?: string }).message ?? "Results locked",
          code: "results_locked",
          reveal_at: revealAt,
        };
        throw locked;
      }
    }
    throw e;
  }
}

export type RsvpSubmission = {
  name: string;
  email?: string | null;
  phone?: string | null;
  response: "yes" | "no" | "maybe";
  plus_ones?: number | null;
  message?: string | null;
};

// Submit an RSVP from a biolink's RSVP block. The server reads the block's
// event_link_id setting and routes to the right ICS event, so the mobile
// client only needs the biolink alias + block id.
export async function submitRsvp(
  alias: string,
  blockId: number,
  payload: RsvpSubmission,
): Promise<{ rsvp_id: number; response: string }> {
  const res = await apiFetch<{
    data: { recorded: boolean; rsvp_id: number; response: string };
  }>(`/biolinks/${encodeURIComponent(alias)}/blocks/${blockId}/rsvp`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
  return { rsvp_id: res.data.rsvp_id, response: res.data.response };
}
