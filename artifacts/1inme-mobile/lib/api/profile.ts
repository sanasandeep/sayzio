import { apiFetch } from "@/lib/api";

export type Profile = {
  id: number | string;
  name?: string | null;
  display_name?: string | null;
  email?: string | null;
  mobile?: string | null;
  handle?: string | null;
  bio?: string | null;
  avatar?: string | null;
  avatar_url?: string | null;
  phone?: string | null;
  timezone?: string | null;
  language?: string | null;
  discoverable?: boolean;
  allow_followers?: boolean;
  role?: string | null;
  // Task #1211 — safety & moderation. Server returns these as text-
  // ready values (lists serialised to comma-separated strings) so the
  // editor form can round-trip without extra parsing on the client.
  mute_words_text?: string | null;
  watermark_enabled?: boolean | null;
  country_block_text?: string | null;
  country_allow_text?: string | null;
  dmca_email?: string | null;
};

export type ProfilePayload = Partial<{
  name: string;
  bio: string | null;
  handle: string | null;
  avatar: string | null;
  phone: string | null;
  timezone: string | null;
  language: string | null;
  discoverable: boolean;
  allow_followers: boolean;
  // Task #1211 — safety & moderation. Server accepts these as plain
  // text inputs (comma/newline separated lists for words + countries)
  // and normalises them on save.
  mute_words_text: string;
  watermark_enabled: boolean;
  country_block_text: string;
  country_allow_text: string;
  dmca_email: string | null;
}>;

export async function getProfile(): Promise<Profile> {
  const res = await apiFetch<{ data: { user: Profile } }>("/profile");
  return res.data.user;
}

export async function updateProfile(p: ProfilePayload): Promise<Profile> {
  const res = await apiFetch<{ data: { user: Profile } }>("/profile", {
    method: "PATCH",
    body: JSON.stringify(p),
  });
  return res.data.user;
}

export type OnboardingStatus = {
  onboarded_at: string | null;
  has_handle: boolean;
  email_verified: boolean;
  has_links: boolean;
  has_biolink: boolean;
};

export async function getOnboardingStatus(): Promise<OnboardingStatus> {
  const res = await apiFetch<{ data: OnboardingStatus }>("/onboarding");
  return res.data;
}

export async function completeOnboarding(): Promise<void> {
  await apiFetch("/onboarding/complete", { method: "POST" });
}
