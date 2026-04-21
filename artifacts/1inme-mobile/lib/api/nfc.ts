import { apiFetch } from "@/lib/api";

export type NfcWrite = {
  id: number;
  link_id: number;
  written_url: string;
  written_at: string | null;
  tag_uid: string | null;
  tag_type: string | null;
  tag_capacity_bytes: number | null;
  locked: boolean;
  device: string | null;
  device_label: string | null;
  platform: "ios" | "android" | null;
  source: string | null;
  lat: number | null;
  lng: number | null;
  label: string | null;
  meta: Record<string, unknown> | null;
  created_at: string | null;
};

export type NfcWriteList = {
  items: NfcWrite[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
};

export async function listNfcWrites(
  linkId: number,
  page = 1,
  perPage = 25,
): Promise<NfcWriteList> {
  const res = await apiFetch<{ data: NfcWriteList }>(
    `/links/${linkId}/nfc-writes?page=${page}&per_page=${perPage}`,
  );
  return res.data;
}

export async function createNfcWrite(
  linkId: number,
  payload: {
    written_url: string;
    written_at?: string;
    tag_uid?: string | null;
    tag_type?: string | null;
    tag_capacity_bytes?: number | null;
    locked?: boolean;
    device?: string | null;
    device_label?: string | null;
    platform?: "ios" | "android" | null;
    lat?: number | null;
    lng?: number | null;
    label?: string | null;
    meta?: Record<string, unknown> | null;
  },
): Promise<NfcWrite> {
  const res = await apiFetch<{ data: { nfc_write: NfcWrite } }>(
    `/links/${linkId}/nfc-writes`,
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
  );
  return res.data.nfc_write;
}

export async function nfcSummary(): Promise<{
  total: number;
  by_link: { link_id: number; writes_count: number; last_written_at: string | null }[];
}> {
  const res = await apiFetch<{
    data: {
      total: number;
      by_link: { link_id: number; writes_count: number; last_written_at: string | null }[];
    };
  }>(`/nfc-writes/summary`);
  return res.data;
}
