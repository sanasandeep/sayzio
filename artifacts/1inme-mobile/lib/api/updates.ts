import { apiFetch } from "@/lib/api";

export interface UpdateEntry {
  id: number;
  link_id: number;
  title: string;
  body: string | null;
  image: string | null;
  tag: string | null;
  status: "draft" | "published";
  published_date: string | null;
  sort_order: number;
  notified_at: string | null;
  anchor_id?: string;
  created_at: string | null;
  updated_at: string | null;
}

export interface UpdatesPageMeta {
  id: number;
  alias: string;
  title: string | null;
  url: string;
  heading: string;
  subheading: string | null;
  per_page: number;
}

export interface UpdatesPageData {
  link: UpdatesPageMeta;
  entries: UpdateEntry[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    has_more: boolean;
    next_page_url: string | null;
  };
}

export interface CreateEntryInput {
  title: string;
  body?: string | null;
  tag?: string | null;
  published_date?: string;
  status?: "draft" | "published";
  image_url?: string | null;
}

export interface UpdateEntryInput {
  title?: string;
  body?: string | null;
  tag?: string | null;
  published_date?: string;
  status?: "draft" | "published";
  image_url?: string | null;
  remove_image?: boolean;
}

export interface UpdatesSettings {
  heading?: string;
  subheading?: string;
  per_page?: number;
}

export const ENTRY_TAGS = [
  "feature",
  "fix",
  "improvement",
  "breaking",
  "announcement",
] as const;
export type EntryTag = (typeof ENTRY_TAGS)[number];

export const ENTRY_TAG_LABELS: Record<string, string> = {
  feature: "Feature",
  fix: "Fix",
  improvement: "Improvement",
  breaking: "Breaking",
  announcement: "Announcement",
};

/** Public: paginated list of published entries for an updates-type link. */
export async function getUpdatesPage(alias: string, page = 1): Promise<UpdatesPageData> {
  const res = await apiFetch<{ data: UpdatesPageData }>(
    `/updates/${encodeURIComponent(alias)}?page=${page}`,
  );
  return res.data;
}

/** Owner: list all entries (draft + published) for a link by numeric ID. */
export async function listOwnerEntries(linkId: number): Promise<UpdateEntry[]> {
  const res = await apiFetch<{ data: { entries: UpdateEntry[] } }>(
    `/me/updates/${linkId}/entries`,
  );
  return res.data.entries;
}

/** Owner: create a new entry. */
export async function createUpdateEntry(linkId: number, input: CreateEntryInput): Promise<UpdateEntry> {
  const res = await apiFetch<{ data: UpdateEntry }>(
    `/me/updates/${linkId}/entries`,
    {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(input),
    },
  );
  return res.data;
}

/** Owner: update an existing entry. */
export async function updateUpdateEntry(
  linkId: number,
  entryId: number,
  input: UpdateEntryInput,
): Promise<UpdateEntry> {
  const res = await apiFetch<{ data: UpdateEntry }>(
    `/me/updates/${linkId}/entries/${entryId}`,
    {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(input),
    },
  );
  return res.data;
}

/** Owner: delete an entry. */
export async function deleteUpdateEntry(linkId: number, entryId: number): Promise<void> {
  await apiFetch<void>(`/me/updates/${linkId}/entries/${entryId}`, {
    method: "DELETE",
  });
}

/** Owner: update page settings (heading / subheading / per_page). */
export async function saveUpdatesSettings(linkId: number, settings: UpdatesSettings): Promise<void> {
  await apiFetch<void>(`/me/updates/${linkId}/settings`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(settings),
  });
}
