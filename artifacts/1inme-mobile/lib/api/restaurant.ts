import { apiFetch } from "@/lib/api";

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
