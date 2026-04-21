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
