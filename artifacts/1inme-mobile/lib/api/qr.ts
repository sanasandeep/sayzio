import { apiFetch } from "@/lib/api";

export type QrCode = {
  id: number;
  name: string;
  type: string;
  link_id: number | null;
  project_id: number | null;
  payload: Record<string, unknown> | null;
  design: Record<string, unknown> | null;
  preview_url: string | null;
  created_at: string | null;
};

export async function listQrCodes(): Promise<QrCode[]> {
  const res = await apiFetch<{ data: { items: QrCode[] } }>("/qr-codes");
  return res.data.items;
}

export async function createQrCode(p: {
  name: string;
  type: string;
  link_id?: number | null;
  payload?: Record<string, unknown>;
}): Promise<QrCode> {
  const res = await apiFetch<{ data: { qr_code: QrCode } }>("/qr-codes", {
    method: "POST",
    body: JSON.stringify(p),
  });
  return res.data.qr_code;
}

export async function deleteQrCode(id: number): Promise<void> {
  await apiFetch(`/qr-codes/${id}`, { method: "DELETE" });
}
