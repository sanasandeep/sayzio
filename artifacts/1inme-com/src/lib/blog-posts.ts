import { LOGIN_URL } from "@/config";

/**
 * The marketing blog is no longer a hardcoded array — it reads from the same
 * database-driven blog as the product app via a public JSON feed, so new and
 * edited posts appear here automatically without code edits.
 *
 * Base origin of the product app that serves the blog feed. Defaults to the
 * origin of the login URL (https://1in.me) and can be overridden at build time
 * with VITE_BLOG_API_BASE (handy for local dev / staging).
 */
function resolveBlogApiBase(): string {
  const override = import.meta.env.VITE_BLOG_API_BASE as string | undefined;
  if (override && override.trim() !== "") {
    return override.replace(/\/$/, "");
  }
  try {
    return new URL(LOGIN_URL).origin;
  } catch {
    return "https://1in.me";
  }
}

export const BLOG_API_BASE = resolveBlogApiBase();

export interface BlogPost {
  slug: string;
  title: string;
  excerpt: string;
  date: string | null;
  readingTime: string;
  author: string;
  category: string;
  coverImage?: string | null;
  /** Full post HTML — only present on the single-post endpoint. */
  bodyHtml?: string;
  metaTitle?: string;
  metaDescription?: string;
}

interface FeedListResponse {
  data: BlogPost[];
}

interface FeedShowResponse {
  data: BlogPost;
}

export async function fetchBlogPosts(signal?: AbortSignal): Promise<BlogPost[]> {
  const res = await fetch(`${BLOG_API_BASE}/blogs/feed.json`, {
    signal,
    headers: { Accept: "application/json" },
  });
  if (!res.ok) {
    throw new Error(`Failed to load blog posts (${res.status})`);
  }
  const json = (await res.json()) as FeedListResponse;
  return json.data ?? [];
}

export async function fetchBlogPost(
  slug: string,
  signal?: AbortSignal,
): Promise<BlogPost | null> {
  const res = await fetch(
    `${BLOG_API_BASE}/blogs/feed/${encodeURIComponent(slug)}.json`,
    { signal, headers: { Accept: "application/json" } },
  );
  if (res.status === 404) {
    return null;
  }
  if (!res.ok) {
    throw new Error(`Failed to load blog post (${res.status})`);
  }
  const json = (await res.json()) as FeedShowResponse;
  return json.data ?? null;
}

export function formatPostDate(date: string | null | undefined): string {
  if (!date) return "";
  const parsed = new Date(date);
  if (Number.isNaN(parsed.getTime())) return "";
  return parsed.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}
