import { apiFetch, getBaseUrl, MOBILE_USER_AGENT } from "@/lib/api";
import { getToken } from "@/lib/secure";

export type RestaurantMenuItem = {
  id: number;
  name: string;
  description: string | null;
  price: string;
  photo_url: string | null;
  is_sold_out: boolean;
};

export type RestaurantMenuCategory = {
  id: number;
  name: string;
  description: string | null;
  items: RestaurantMenuItem[];
};

export type RestaurantMenu = {
  menu: {
    mode: "display" | "order";
    currency: string;
    accent_color: string | null;
    order_enabled: boolean;
  };
  link: { alias: string; title: string | null };
  table: { code: string; label: string } | null;
  categories: RestaurantMenuCategory[];
};

export type GuestOrderItem = {
  name: string;
  quantity: number;
  line_total: string;
};

export type GuestOrder = {
  public_token: string;
  status: string;
  status_label: string;
  subtotal: string;
  currency: string;
  table_label: string | null;
  items: GuestOrderItem[];
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
  table_label: string | null;
  customer_name: string | null;
  customer_note: string | null;
  subtotal: string;
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
  table_code?: string | null;
  customer_name?: string | null;
  customer_note?: string | null;
  items: { item_id: number; quantity: number; note?: string | null }[];
};

export async function getRestaurantMenu(
  alias: string,
  tableCode?: string | null,
): Promise<RestaurantMenu> {
  const qs = tableCode ? `?t=${encodeURIComponent(tableCode)}` : "";
  const res = await apiFetch<{ data: RestaurantMenu }>(
    `/restaurant/${encodeURIComponent(alias)}${qs}`,
  );
  return res.data;
}

export async function placeRestaurantOrder(
  alias: string,
  input: PlaceOrderInput,
): Promise<GuestOrder> {
  const res = await apiFetch<{ data: { order: GuestOrder } }>(
    `/restaurant/${encodeURIComponent(alias)}/order`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.order;
}

export async function getRestaurantOrderStatus(
  token: string,
): Promise<GuestOrder> {
  const res = await apiFetch<{ data: { order: GuestOrder } }>(
    `/restaurant/orders/${encodeURIComponent(token)}/status`,
  );
  return res.data.order;
}

export async function getOwnerOrders(
  linkId: number | string,
): Promise<OwnerOrdersResponse> {
  const res = await apiFetch<{ data: OwnerOrdersResponse }>(
    `/restaurant/links/${linkId}/orders`,
  );
  return res.data;
}

export async function pollOwnerOrders(
  linkId: number | string,
  since?: string | null,
): Promise<OwnerOrdersResponse> {
  const qs = since ? `?since=${encodeURIComponent(since)}` : "";
  const res = await apiFetch<{ data: OwnerOrdersResponse }>(
    `/restaurant/links/${linkId}/orders/poll${qs}`,
  );
  return res.data;
}

export async function updateOwnerOrderStatus(
  linkId: number | string,
  orderId: number,
  status: string,
): Promise<OwnerOrder> {
  const res = await apiFetch<{ data: { order: OwnerOrder } }>(
    `/restaurant/links/${linkId}/orders/${orderId}/status`,
    { method: "POST", body: JSON.stringify({ status }) },
  );
  return res.data.order;
}

export const ORDER_STATUS_FLOW: Record<string, string[]> = {
  new: ["accepted", "cancelled"],
  accepted: ["preparing", "cancelled"],
  preparing: ["ready"],
  ready: ["completed"],
  completed: [],
  cancelled: [],
};

export const ORDER_ACTION_LABELS: Record<string, string> = {
  accepted: "Accept",
  preparing: "Start preparing",
  ready: "Mark ready",
  completed: "Complete",
  cancelled: "Cancel",
};

export const OPEN_ORDER_STATUSES = ["new", "accepted", "preparing", "ready"];

// ── Owner menu builder (Task #1689) ──────────────────────────────

export type OwnerMenuItem = {
  id: number;
  category_id: number;
  name: string;
  description: string | null;
  price: string;
  photo_url: string | null;
  is_sold_out: boolean;
  is_active: boolean;
};

export type OwnerMenuCategory = {
  id: number;
  name: string;
  description: string | null;
  is_active: boolean;
  items: OwnerMenuItem[];
};

export type OwnerMenuTable = {
  id: number;
  label: string;
  code: string;
  order_url: string;
};

export type OwnerMenu = {
  mode: "display" | "order";
  currency: string;
  accent_color: string | null;
  order_enabled: boolean;
  public_url: string;
  categories: OwnerMenuCategory[];
  tables: OwnerMenuTable[];
};

export async function getOwnerMenu(
  linkId: number | string,
): Promise<OwnerMenu> {
  const res = await apiFetch<{ data: { menu: OwnerMenu } }>(
    `/restaurant/links/${linkId}/menu`,
  );
  return res.data.menu;
}

export async function saveOwnerMenuSettings(
  linkId: number | string,
  input: { mode: "display" | "order"; currency: string; accent_color?: string | null },
): Promise<OwnerMenu> {
  const res = await apiFetch<{ data: { menu: OwnerMenu } }>(
    `/restaurant/links/${linkId}/menu/settings`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.menu;
}

export async function createMenuCategory(
  linkId: number | string,
  input: { name: string; description?: string | null },
): Promise<OwnerMenuCategory> {
  const res = await apiFetch<{ data: { category: OwnerMenuCategory } }>(
    `/restaurant/links/${linkId}/menu/categories`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.category;
}

export async function updateMenuCategory(
  linkId: number | string,
  categoryId: number,
  input: { name?: string; description?: string | null; is_active?: boolean },
): Promise<OwnerMenuCategory> {
  const res = await apiFetch<{ data: { category: OwnerMenuCategory } }>(
    `/restaurant/links/${linkId}/menu/categories/${categoryId}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
  return res.data.category;
}

export async function deleteMenuCategory(
  linkId: number | string,
  categoryId: number,
): Promise<void> {
  await apiFetch(`/restaurant/links/${linkId}/menu/categories/${categoryId}`, {
    method: "DELETE",
  });
}

export type MenuItemInput = {
  category_id: number;
  name: string;
  description?: string | null;
  price?: number | null;
  photo_url?: string | null;
  is_sold_out?: boolean;
};

export async function createMenuItem(
  linkId: number | string,
  input: MenuItemInput,
): Promise<OwnerMenuItem> {
  const res = await apiFetch<{ data: { item: OwnerMenuItem } }>(
    `/restaurant/links/${linkId}/menu/items`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.item;
}

export async function updateMenuItem(
  linkId: number | string,
  itemId: number,
  input: Partial<MenuItemInput> & { is_active?: boolean },
): Promise<OwnerMenuItem> {
  const res = await apiFetch<{ data: { item: OwnerMenuItem } }>(
    `/restaurant/links/${linkId}/menu/items/${itemId}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
  return res.data.item;
}

export async function deleteMenuItem(
  linkId: number | string,
  itemId: number,
): Promise<void> {
  await apiFetch(`/restaurant/links/${linkId}/menu/items/${itemId}`, {
    method: "DELETE",
  });
}

export async function createMenuTable(
  linkId: number | string,
  label: string,
): Promise<OwnerMenuTable> {
  const res = await apiFetch<{ data: { table: OwnerMenuTable } }>(
    `/restaurant/links/${linkId}/menu/tables`,
    { method: "POST", body: JSON.stringify({ label }) },
  );
  return res.data.table;
}

export async function deleteMenuTable(
  linkId: number | string,
  tableId: number,
): Promise<void> {
  await apiFetch(`/restaurant/links/${linkId}/menu/tables/${tableId}`, {
    method: "DELETE",
  });
}

/**
 * Upload a menu-item photo from the device. Posted as multipart/form-data
 * to mirror the web editor's upload flow; the server stores it in the vault
 * and returns the public URL to stamp onto the item.
 */
export async function uploadMenuItemPhoto(
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
    name: args.name || `menu-item.${ext}`,
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
    `${getBaseUrl()}/api/v1/restaurant/links/${linkId}/menu/photo`,
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
