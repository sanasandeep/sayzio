import { apiFetch } from "@/lib/api";

// Task #6015 — curated platform asset galleries (owner-managed S3 folders,
// available on every plan). Mirrors the web AJAX catalog endpoints.

export type PlatformAssetFolder =
  | "biolink-backgrounds"
  | "grid-images"
  | "hand-drawn"
  | "people-avatars"
  | "stock-avatars";

export type PlatformAsset = {
  key: string;
  name: string;
  label: string;
  url: string;
  svg_key?: string;
  svg_url?: string;
};

export async function getPlatformAssets(
  folder: PlatformAssetFolder,
): Promise<PlatformAsset[]> {
  const res = await apiFetch<{
    data: { folder: string; assets: PlatformAsset[] };
  }>(`/platform-assets/${folder}`);
  return res.data.assets ?? [];
}
