import { apiFetch } from "@/lib/api";

/**
 * Dialer notes & reminders with full server sync (`/dialer/notes`).
 *
 * A note belongs to you; adding phone numbers under `share_phones` shares it
 * read-only with the Sayzio accounts behind those numbers.
 */
export type ChecklistItem = { text: string; done: boolean };

export type DialerNote = {
  id: number;
  title: string | null;
  body: string | null;
  number: string | null;
  remind_at: string | null;
  done: boolean;
  color: string | null;
  kind: "note" | "checklist";
  checklist: ChecklistItem[];
  /** Attached website (URL + page title), e.g. set from the Zio Browser. */
  attached_url: string | null;
  attached_title: string | null;
  attached_host: string | null;
  /** Auto-task provenance — 'event' | 'callback' | null for manual notes. */
  source_type: string | null;
  source_id: number | null;
  own: boolean;
  owner_name: string | null;
  share_phones: string[];
  updated_at: string | null;
  created_at: string | null;
};

export type NoteInput = {
  title?: string | null;
  body?: string | null;
  number?: string | null;
  remind_at?: string | null;
  done?: boolean;
  color?: string | null;
  kind?: "note" | "checklist";
  checklist?: ChecklistItem[] | null;
  attached_url?: string | null;
  attached_title?: string | null;
  share_phones?: string[];
};

export async function listNotes(): Promise<{
  notes: DialerNote[];
  shared: DialerNote[];
}> {
  const res = await apiFetch<{
    data: { notes: DialerNote[]; shared: DialerNote[] };
  }>("/dialer/notes");
  return res.data;
}

export async function createNote(input: NoteInput): Promise<DialerNote> {
  const res = await apiFetch<{ data: DialerNote }>("/dialer/notes", {
    method: "POST",
    body: JSON.stringify(input),
  });
  return res.data;
}

export async function updateNote(
  id: number,
  input: NoteInput,
): Promise<DialerNote> {
  const res = await apiFetch<{ data: DialerNote }>(`/dialer/notes/${id}`, {
    method: "PATCH",
    body: JSON.stringify(input),
  });
  return res.data;
}

export async function deleteNote(id: number): Promise<void> {
  await apiFetch(`/dialer/notes/${id}`, { method: "DELETE" });
}
