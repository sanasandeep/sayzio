import { apiFetch } from "@/lib/api";
import type {
  CreatorProfile,
  CreatorProfilePost,
  ProfileResponse,
  ViewerSubscriptionState,
} from "@/lib/api/creatorProfile";

/**
 * Mobile-friendly projection of a Paid Page design template, mirroring
 * `PaidPageTemplates::mobileTokens()` on the Laravel side. Gradients are
 * decomposed into ordered colour stops so expo-linear-gradient can paint
 * them; the radius is already in pixels.
 */
export type PaidPageTemplate = {
  id: string;
  name: string;
  page_colors: string[];
  hero_colors: string[];
  accent: string;
  accent_soft: string;
  text: string;
  text_muted: string;
  card_bg: string;
  card_text: string;
  radius: number;
  font: string;
  hero_style: string;
  motion: boolean;
};

export type PaidPageMeta = {
  alias: string;
  handle: string | null;
  title: string;
  description: string | null;
  visibility: string;
  is_owner: boolean;
};

export type PaidPageResponse = {
  page: PaidPageMeta;
  template: PaidPageTemplate;
  profile: CreatorProfile;
  reactions_catalog: { key: string; label: string; emoji: string }[];
  tiers?: ProfileResponse["tiers"];
  viewer_subscription?: ViewerSubscriptionState | null;
};

export const paidPage = {
  show: async (alias: string) => {
    const res = await apiFetch<{ data: PaidPageResponse }>(
      `/paid-page/${encodeURIComponent(alias)}`,
    );
    return res.data;
  },

  feed: async (alias: string, page = 1) => {
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
    }>(`/paid-page/${encodeURIComponent(alias)}/posts?page=${page}`);
    return res.data;
  },
};
