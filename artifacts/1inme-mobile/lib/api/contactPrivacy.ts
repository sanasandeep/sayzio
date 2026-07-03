import { apiFetch } from "@/lib/api";

// Tri-state: null = "not chosen" (shown by default, no forced default),
// true = "always shown", false = "hidden from strangers". Mirrors the web
// Settings > Contact Privacy tab (Task #3497).
export type ContactPrivacyPrefs = {
  share_phone: boolean | null;
  share_email: boolean | null;
  share_location: boolean | null;
  share_socials: boolean | null;
  hidden_channels: string[];
  configured_at: string | null;
};

export type ContactPrivacyCandidate = {
  key: string;
  hidden: boolean;
  label?: string;
  platform?: string;
  type?: string;
  url?: string;
  value?: string;
};

export type ContactPrivacyCandidates = {
  socials: ContactPrivacyCandidate[];
  channels: ContactPrivacyCandidate[];
};

export type ContactPrivacyResponse = {
  prefs: ContactPrivacyPrefs;
  candidates: ContactPrivacyCandidates;
};

export async function getContactPrivacy(): Promise<ContactPrivacyResponse> {
  const res = await apiFetch<{ data: ContactPrivacyResponse }>(
    "/me/contact-privacy",
  );
  return res.data;
}

export async function updateContactPrivacy(
  updates: Partial<{
    share_phone: boolean | null;
    share_email: boolean | null;
    share_location: boolean | null;
    share_socials: boolean | null;
    hidden_channels: string[];
  }>,
): Promise<ContactPrivacyResponse> {
  const body: Record<string, string | string[]> = {};
  for (const field of [
    "share_phone",
    "share_email",
    "share_location",
    "share_socials",
  ] as const) {
    if (field in updates) {
      const v = updates[field];
      body[field] = v === null ? "" : v ? "1" : "0";
    }
  }
  if (updates.hidden_channels) {
    body.hidden_channels = updates.hidden_channels;
  }

  const res = await apiFetch<{ data: ContactPrivacyResponse }>(
    "/me/contact-privacy",
    { method: "PUT", body: JSON.stringify(body) },
  );
  return res.data;
}
