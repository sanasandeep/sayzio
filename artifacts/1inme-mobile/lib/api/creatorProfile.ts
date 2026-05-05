import { apiFetch } from "@/lib/api";

// The 6 branded reactions are also defined server-side
// (CreatorPostReaction::REACTIONS). Keep this list in sync with the
// PHP constant — the API echoes the catalog under
// `reactions_catalog` so this client copy is only used as the initial
// render seed before the profile request resolves.
export const BRANDED_REACTIONS = [
  { key: "fire",       label: "Fire",        emoji: "🔥" },
  { key: "mind_blown", label: "Mind blown",  emoji: "🤯" },
  { key: "heart_eyes", label: "Love",        emoji: "😍" },
  { key: "clap",       label: "Clap",        emoji: "👏" },
  { key: "wow",        label: "Wow",         emoji: "😮" },
  { key: "bookmark",   label: "Bookmark",    emoji: "🔖" },
] as const;

export type ReactionKey = (typeof BRANDED_REACTIONS)[number]["key"];

export type CreatorProfile = {
  id: number;
  handle: string | null;
  name: string;
  avatar: string | null;
  cover_image: string | null;
  tagline: string | null;
  bio: string | null;
  location: string | null;
  niche_tags: string[];
  socials: Record<string, string>;
  sections: {
    hero: boolean;
    about: boolean;
    socials: boolean;
    biolink: boolean;
    contact: boolean;
    stats: boolean;
  };
  profile_published: boolean;
  followers_count: number;
  posts_count: number;
  is_following: boolean;
  is_owner: boolean;
  biolink_url: string | null;
};

export type CreatorPostType =
  | "text"
  | "image"
  | "gallery"
  | "video"
  | "audio"
  | "link";

export type CreatorPostMedia = {
  url?: string | null;
  poster?: string | null;
  duration?: number | null;
  title?: string | null;
  description?: string | null;
  thumbnail?: string | null;
  items?: { url: string; caption?: string | null }[];
};

export type PostAccess = {
  can: boolean;
  reason:
    | "owner"
    | "free"
    | "subscriber"
    | "ppv_unlocked"
    | "tier_locked"
    | "ppv_locked"
    | "guest";
  requires_subscription: boolean;
  requires_ppv: boolean;
  lowest_tier: {
    id: number;
    name: string;
    badge: string | null;
    price_monthly_cents: number;
    currency: string;
  } | null;
};

export type LockedPreview =
  | {
      kind: "gallery";
      items: { url: string | null; alt: string | null }[];
      total_items: number;
      visible_count: number;
    }
  | {
      kind: "video";
      poster: string | null;
      seconds: number;
    };

export type CreatorProfilePost = {
  id: number;
  post_type: CreatorPostType;
  title: string | null;
  body: string | null;
  body_excerpt?: string | null;
  teaser_caption?: string | null;
  image: string | null;
  media: CreatorPostMedia | null;
  is_pinned: boolean;
  published_at: string | null;
  reactions_count: number;
  comments_count: number;
  reaction_totals: Record<string, number>;
  my_reaction: ReactionKey | null;
  visibility?: "free" | "tier" | "ppv";
  ppv_price_cents?: number | null;
  blur_intensity?: "low" | "medium" | "high";
  access?: PostAccess;
  locked?: boolean;
  preview?: LockedPreview | null;
  paywall_preview?: {
    gallery_preview_count: number;
    video_preview_seconds: number;
  };
};

export type ProfileComment = {
  id: number;
  parent_id: number | null;
  body: string;
  created_at: string | null;
  author: {
    id: number;
    name: string | null;
    handle: string | null;
    avatar: string | null;
  } | null;
  replies: ProfileComment[];
};

export type ViewerSubscriptionState = {
  id: number;
  tier_id: number;
  tier_name: string | null;
  tier_badge: string | null;
  status: string;
  status_label: string;
  billing_cycle: "monthly" | "yearly";
  price_cents: number;
  currency: string;
  current_period_end: string | null;
  cancel_at_period_end: boolean;
  is_current: boolean;
};

export type ProfileResponse = {
  profile: CreatorProfile;
  reactions_catalog: { key: string; label: string; emoji: string }[];
  tiers?: {
    id: number;
    slug: string;
    name: string;
    is_free: boolean;
    is_active: boolean;
    price_monthly_cents: number;
    price_yearly_cents: number | null;
    currency: string;
    badge: string | null;
    color: string | null;
    perks: string[];
    yearly_discount_percent: number | null;
  }[];
  viewer_subscription?: ViewerSubscriptionState | null;
};

const stripHandle = (h: string) => h.replace(/^@/, "");

export const creatorProfile = {
  show: async (handle: string) => {
    const res = await apiFetch<{ data: ProfileResponse }>(
      `/creator-profile/${encodeURIComponent(stripHandle(handle))}`,
    );
    return res.data;
  },

  feed: async (handle: string, page = 1) => {
    const res = await apiFetch<{
      data: {
        items: CreatorProfilePost[];
        meta: {
          current_page: number;
          per_page: number;
          total: number;
          last_page: number;
        };
      };
    }>(
      `/creator-profile/${encodeURIComponent(stripHandle(handle))}/posts?page=${page}`,
    );
    return res.data;
  },

  comments: async (handle: string, postId: number) => {
    const res = await apiFetch<{
      data: { items: ProfileComment[] };
    }>(
      `/creator-profile/${encodeURIComponent(
        stripHandle(handle),
      )}/posts/${postId}/comments`,
    );
    return res.data.items;
  },

  react: async (handle: string, postId: number, reaction: ReactionKey) => {
    const res = await apiFetch<{
      data: {
        reaction: ReactionKey | null;
        totals: Record<string, number>;
        count: number;
      };
    }>(
      `/creator-profile/${encodeURIComponent(
        stripHandle(handle),
      )}/posts/${postId}/react`,
      { method: "POST", body: JSON.stringify({ reaction }) },
    );
    return res.data;
  },

  comment: async (
    handle: string,
    postId: number,
    body: string,
    parentId?: number | null,
  ) => {
    const res = await apiFetch<{ data: { comment: ProfileComment } }>(
      `/creator-profile/${encodeURIComponent(
        stripHandle(handle),
      )}/posts/${postId}/comment`,
      {
        method: "POST",
        body: JSON.stringify({ body, parent_id: parentId ?? null }),
      },
    );
    return res.data.comment;
  },
};
