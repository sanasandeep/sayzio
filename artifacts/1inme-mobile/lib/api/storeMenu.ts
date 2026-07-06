import { apiFetch, getBaseUrl, MOBILE_USER_AGENT } from "@/lib/api";
import { getToken } from "@/lib/secure";
import { type LinkTypePairing } from "@/lib/linkPairings";

export type StoreProduct = {
  id: number;
  name: string;
  description: string | null;
  price: string;
  photo_url: string | null;
  is_out_of_stock: boolean;
};

export type StoreCategory = {
  id: number;
  name: string;
  description: string | null;
  products: StoreProduct[];
};

export type Store = {
  menu: {
    mode: "display" | "order";
    currency: string;
    accent_color: string | null;
    order_enabled: boolean;
    accepting_orders: boolean;
  };
  link: { alias: string; title: string | null };
  categories: StoreCategory[];
  /** Cross-promo "Perfect pairings" cards from the shared SitePagesContent catalog. */
  pairings?: LinkTypePairing[];
};

export type GuestOrderItem = {
  name: string;
  quantity: number;
  line_total: string;
};

export type WhatsappOrderLink = {
  number: string;
  message: string;
  url: string;
};

export type GuestOrder = {
  public_token: string;
  status: string;
  status_label: string;
  subtotal: string;
  total: string;
  currency: string;
  is_estimate: boolean;
  items: GuestOrderItem[];
  whatsapp: WhatsappOrderLink | null;
  created_at: string | null;
};

export type OwnerOrderItem = {
  id: number;
  name: string;
  quantity: number;
  unit_price: string;
  line_total: string;
  note: string | null;
};

export type OwnerOrder = {
  id: number;
  status: string;
  status_label: string;
  customer_name: string | null;
  customer_contact: string | null;
  customer_note: string | null;
  subtotal: string;
  total: string;
  currency: string;
  created_at: string | null;
  updated_at: string | null;
  items: OwnerOrderItem[];
};

export type OwnerOrdersResponse = {
  orders: OwnerOrder[];
  open_count: number;
  server_time: string;
};

export type PlaceOrderInput = {
  customer_name?: string | null;
  customer_contact?: string | null;
  customer_note?: string | null;
  items: { product_id: number; quantity: number; note?: string | null }[];
};

export async function getStore(alias: string): Promise<Store> {
  const res = await apiFetch<{ data: Store }>(
    `/store/${encodeURIComponent(alias)}`,
  );
  return res.data;
}

export async function placeStoreOrder(
  alias: string,
  input: PlaceOrderInput,
): Promise<GuestOrder> {
  const res = await apiFetch<{ data: { order: GuestOrder } }>(
    `/store/${encodeURIComponent(alias)}/order`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.order;
}

export async function getStoreOrderStatus(token: string): Promise<GuestOrder> {
  const res = await apiFetch<{ data: { order: GuestOrder } }>(
    `/store/orders/${encodeURIComponent(token)}/status`,
  );
  return res.data.order;
}

export async function getStoreOwnerOrders(
  linkId: number | string,
): Promise<OwnerOrdersResponse> {
  const res = await apiFetch<{ data: OwnerOrdersResponse }>(
    `/store/links/${linkId}/orders`,
  );
  return res.data;
}

export async function pollStoreOwnerOrders(
  linkId: number | string,
  since?: string | null,
): Promise<OwnerOrdersResponse> {
  const qs = since ? `?since=${encodeURIComponent(since)}` : "";
  const res = await apiFetch<{ data: OwnerOrdersResponse }>(
    `/store/links/${linkId}/orders/poll${qs}`,
  );
  return res.data;
}

export async function updateStoreOwnerOrderStatus(
  linkId: number | string,
  orderId: number,
  status: string,
): Promise<OwnerOrder> {
  const res = await apiFetch<{ data: { order: OwnerOrder } }>(
    `/store/links/${linkId}/orders/${orderId}/status`,
    { method: "POST", body: JSON.stringify({ status }) },
  );
  return res.data.order;
}

export const ORDER_STATUS_FLOW: Record<string, string[]> = {
  new: ["accepted", "cancelled"],
  accepted: ["packing", "ready", "cancelled"],
  packing: ["ready", "cancelled"],
  ready: ["completed", "cancelled"],
  completed: [],
  cancelled: [],
};

export const ORDER_ACTION_LABELS: Record<string, string> = {
  accepted: "Accept",
  packing: "Start packing",
  ready: "Mark ready",
  completed: "Complete",
  cancelled: "Cancel",
};

export const OPEN_ORDER_STATUSES = ["new", "accepted", "packing", "ready"];

// ── Owner store builder (Task #3072) ─────────────────────────────

export type OwnerStoreProduct = {
  id: number;
  category_id: number;
  name: string;
  description: string | null;
  price: string;
  photo_url: string | null;
  is_out_of_stock: boolean;
  is_active: boolean;
};

export type OwnerStoreCategory = {
  id: number;
  name: string;
  description: string | null;
  is_active: boolean;
  products: OwnerStoreProduct[];
};

export type OwnerStore = {
  mode: "display" | "order";
  currency: string;
  accent_color: string | null;
  whatsapp_number: string | null;
  accepting_orders: boolean;
  order_enabled: boolean;
  public_url: string;
  categories: OwnerStoreCategory[];
};

export async function getOwnerStore(
  linkId: number | string,
): Promise<OwnerStore> {
  const res = await apiFetch<{ data: { menu: OwnerStore } }>(
    `/store/links/${linkId}/menu`,
  );
  return res.data.menu;
}

export async function saveOwnerStoreSettings(
  linkId: number | string,
  input: {
    mode: "display" | "order";
    currency: string;
    accent_color?: string | null;
    whatsapp_number?: string | null;
    accepting_orders?: boolean;
  },
): Promise<OwnerStore> {
  const res = await apiFetch<{ data: { menu: OwnerStore } }>(
    `/store/links/${linkId}/menu/settings`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.menu;
}

export async function createStoreCategory(
  linkId: number | string,
  input: { name: string; description?: string | null },
): Promise<OwnerStoreCategory> {
  const res = await apiFetch<{ data: { category: OwnerStoreCategory } }>(
    `/store/links/${linkId}/menu/categories`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.category;
}

export async function updateStoreCategory(
  linkId: number | string,
  categoryId: number,
  input: { name?: string; description?: string | null; is_active?: boolean },
): Promise<OwnerStoreCategory> {
  const res = await apiFetch<{ data: { category: OwnerStoreCategory } }>(
    `/store/links/${linkId}/menu/categories/${categoryId}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
  return res.data.category;
}

export async function deleteStoreCategory(
  linkId: number | string,
  categoryId: number,
): Promise<void> {
  await apiFetch(`/store/links/${linkId}/menu/categories/${categoryId}`, {
    method: "DELETE",
  });
}

export type StoreProductInput = {
  category_id: number;
  name: string;
  description?: string | null;
  price?: number | null;
  photo_url?: string | null;
  is_out_of_stock?: boolean;
};

export async function createStoreProduct(
  linkId: number | string,
  input: StoreProductInput,
): Promise<OwnerStoreProduct> {
  const res = await apiFetch<{ data: { product: OwnerStoreProduct } }>(
    `/store/links/${linkId}/menu/products`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.product;
}

export async function updateStoreProduct(
  linkId: number | string,
  productId: number,
  input: Partial<StoreProductInput> & { is_active?: boolean },
): Promise<OwnerStoreProduct> {
  const res = await apiFetch<{ data: { product: OwnerStoreProduct } }>(
    `/store/links/${linkId}/menu/products/${productId}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
  return res.data.product;
}

export async function deleteStoreProduct(
  linkId: number | string,
  productId: number,
): Promise<void> {
  await apiFetch(`/store/links/${linkId}/menu/products/${productId}`, {
    method: "DELETE",
  });
}

/**
 * Upload a product photo from the device. Posted as multipart/form-data to
 * mirror the web editor's upload flow; the server stores it in the vault and
 * returns the public URL to stamp onto the product.
 */
export async function uploadStoreProductPhoto(
  linkId: number | string,
  args: { uri: string; name?: string; mime?: string },
): Promise<string> {
  const token = await getToken();
  const fd = new FormData();
  const mime = args.mime || "image/jpeg";
  const ext = mime.split("/")[1] || "jpg";
  fd.append("photo", {
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment
    // @ts-ignore – RN-specific FormData entry shape.
    uri: args.uri,
    name: args.name || `product.${ext}`,
    type: mime,
  } as unknown as Blob);

  const headers: Record<string, string> = {
    Accept: "application/json",
    "User-Agent": MOBILE_USER_AGENT,
    "X-1INME-Client": MOBILE_USER_AGENT,
    // NB: do NOT set Content-Type — RN fills the multipart boundary in.
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(
    `${getBaseUrl()}/api/v1/store/links/${linkId}/menu/photo`,
    { method: "POST", body: fd as unknown as BodyInit, headers },
  );
  const text = await res.text();
  const body = text ? (JSON.parse(text) as Record<string, unknown>) : null;
  if (!res.ok) {
    const nested =
      body && typeof body.error === "object" && body.error !== null
        ? (body.error as Record<string, unknown>)
        : null;
    const message =
      (nested && typeof nested.message === "string"
        ? (nested.message as string)
        : null) ||
      (body && typeof body.message === "string"
        ? (body.message as string)
        : null) ||
      `Upload failed (${res.status})`;
    throw { status: res.status, message };
  }
  return (body as { data: { photo_url: string } }).data.photo_url;
}
