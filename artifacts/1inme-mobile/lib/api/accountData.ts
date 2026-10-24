import { Platform } from "react-native";
import { apiFetch, getBaseUrl } from "@/lib/api";
import { getToken } from "@/lib/secure";
export type DataRequest = {
  id: number;
  type: string;
  label: string;
  status: string;
  can_download: boolean;
  scheduled_at: string | null;
};
export const listDataRequests = () =>
  apiFetch<{ data: { items: DataRequest[] } }>("/me/data-requests");
export const createDataRequest = (input: {
  type: string;
  reason: string;
  confirmation: string;
  data_scopes?: string[];
}) =>
  apiFetch("/me/data-requests", {
    method: "POST",
    body: JSON.stringify(input),
  });
export async function downloadAccountData(id: number) {
  const token = await getToken();
  if (!token) throw new Error("Sign in again to download your data.");
  const url = `${getBaseUrl()}/api/v1/me/data-requests/${id}/download`;
  const headers = { Authorization: `Bearer ${token}` };
  const filename = `sayzio-data-${id}.zip`;
  if (Platform.OS === "web") {
    const r = await fetch(url, { headers });
    if (!r.ok) throw new Error("Download unavailable or expired.");
    const href = URL.createObjectURL(await r.blob());
    const a = document.createElement("a");
    a.href = href;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(href);
    return;
  }
  const fs = await import("expo-file-system/legacy");
  const sharing = await import("expo-sharing");
  const target = `${fs.cacheDirectory}${filename}`;
  try {
    const result = await fs.downloadAsync(url, target, { headers });
    if (result.status !== 200)
      throw new Error("Download unavailable or expired.");
    if (!(await sharing.isAvailableAsync()))
      throw new Error("File sharing is unavailable on this device.");
    await sharing.shareAsync(target, { mimeType: "application/zip" });
  } finally {
    await fs.deleteAsync(target, { idempotent: true }).catch(() => {});
  }
}
