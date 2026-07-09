import { apiFetch } from "@/lib/api";

export type ExtensionStore = {
  key: string;
  label: string;
  url: string;
  isListing: boolean;
};

/**
 * Pre-publish fallbacks (store search pages for "Sayzio") used when the
 * request fails or the server has no admin-configured listing URLs yet.
 * Mirrors ExtensionStoreLinks defaults on the Laravel side.
 */
export const DEFAULT_EXTENSION_STORES: ExtensionStore[] = [
  {
    key: "chrome",
    label: "Chrome Web Store",
    url: "https://chromewebstore.google.com/search/Sayzio",
    isListing: false,
  },
  {
    key: "edge",
    label: "Edge Add-ons",
    url: "https://microsoftedge.microsoft.com/addons/Search/Sayzio",
    isListing: false,
  },
  {
    key: "firefox",
    label: "Firefox Add-ons",
    url: "https://addons.mozilla.org/en-US/firefox/search/?q=Sayzio",
    isListing: false,
  },
];

/**
 * Fetch the browser-extension store links from the shared source of truth
 * (GET /extension/stores → ExtensionStoreLinks over app_settings). Once the
 * extension is published, admins set direct listing URLs and this returns
 * them with isListing=true. Degrades to the search-page defaults on failure.
 */
export async function getExtensionStores(): Promise<ExtensionStore[]> {
  try {
    const res = await apiFetch<{
      data?: {
        stores?: {
          key?: string;
          label?: string;
          url?: string;
          is_listing?: boolean;
        }[];
      };
    }>("/extension/stores");
    const stores = res?.data?.stores;
    if (!Array.isArray(stores) || stores.length === 0) {
      return DEFAULT_EXTENSION_STORES;
    }
    return stores
      .filter((s) => typeof s?.url === "string" && s.url.startsWith("http"))
      .map((s) => ({
        key: String(s.key ?? ""),
        label: String(s.label ?? ""),
        url: String(s.url),
        isListing: Boolean(s.is_listing),
      }));
  } catch {
    return DEFAULT_EXTENSION_STORES;
  }
}
