import { BLOG_API_BASE } from "@/lib/blog-posts";

/**
 * Admin-authored public announcement banners, served by the product app over a
 * CORS-open JSON feed (mirroring the blog feed). This site has no auth, so it
 * only receives the marketing/guest-targeted rows.
 */
export interface Announcement {
  audience: string;
  message: string;
  linkUrl: string;
  linkLabel: string;
  version: number;
}

interface FeedResponse {
  data: Announcement[];
}

export async function fetchAnnouncements(
  signal?: AbortSignal,
): Promise<Announcement[]> {
  const res = await fetch(`${BLOG_API_BASE}/announcements/feed.json`, {
    signal,
    headers: { Accept: "application/json" },
  });
  if (!res.ok) {
    throw new Error(`Failed to load announcements (${res.status})`);
  }
  const json = (await res.json()) as FeedResponse;
  return json.data ?? [];
}
