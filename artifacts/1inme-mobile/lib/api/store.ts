import { apiFetch } from "@/lib/api";

// ── Product storefront types ─────────────────────────────────────
// Mirrors what the Laravel BiolinkStoreApiController returns.
// Source of truth: app/Modules/Api/Controllers/BiolinkStoreApiController.php
// (orderShape / ownerOrderShape).

export type StoreUserRef = {
  id: number;
  name: string;
  handle: string | null;
  avatar: string | null;
};

export type OrderItem = {
  id: number;
  name: string;
  quantity: number;
  unit_price_cents: number;
  line_total_cents: number;
  currency: string;
  product_type: "digital" | "physical" | string;
  image_url: string | null;
  download_url: string | null;
};

export type ProductOrder = {
  id: number;
  status: "pending" | "paid" | "fulfilled" | "cancelled" | string;
  status_label: string;
  is_paid: boolean;
  currency: string;
  subtotal_cents: number;
  contains_physical: boolean;
  contains_digital: boolean;
  public_token: string;
  thank_you_message: string;
  paid_at: string | null;
  created_at: string | null;
  creator: StoreUserRef | null;
  items: OrderItem[];
};

export type OwnerOrder = ProductOrder & {
  buyer: StoreUserRef | null;
  fulfilled_at: string | null;
};

export type CheckoutResult = {
  checkout_url: string | null;
  order: ProductOrder;
};

export type Paginated<T> = {
  items: T[];
  meta: { current_page: number; last_page: number; total: number };
};

// ── Buyer-side ───────────────────────────────────────────────────

/** Single-product "Buy Now". */
export async function buyProduct(
  alias: string,
  blockId: number,
): Promise<CheckoutResult> {
  const res = await apiFetch<{ data: CheckoutResult }>(
    `/store/${encodeURIComponent(alias)}/buy`,
    { method: "POST", body: JSON.stringify({ block_id: blockId }) },
  );
  return res.data;
}

/** Combined cart checkout. */
export async function checkoutCart(
  alias: string,
  items: { block_id: number; quantity: number }[],
): Promise<CheckoutResult> {
  const res = await apiFetch<{ data: CheckoutResult }>(
    `/store/${encodeURIComponent(alias)}/checkout`,
    { method: "POST", body: JSON.stringify({ items }) },
  );
  return res.data;
}

/** Buyer's view of a single order — poll this after returning from checkout. */
export async function getOrder(orderId: number): Promise<ProductOrder> {
  const res = await apiFetch<{ data: ProductOrder }>(
    `/store/orders/${orderId}`,
  );
  return res.data;
}

// ── Owner-side dashboard ─────────────────────────────────────────

export async function getOwnerOrders(
  status?: "paid" | "fulfilled" | "cancelled",
): Promise<Paginated<OwnerOrder>> {
  const qs = status ? `?status=${encodeURIComponent(status)}` : "";
  const res = await apiFetch<{ data: Paginated<OwnerOrder> }>(
    `/me/creator/orders${qs}`,
  );
  return res.data;
}

export async function fulfillOrder(orderId: number): Promise<OwnerOrder> {
  const res = await apiFetch<{ data: OwnerOrder }>(
    `/me/creator/orders/${orderId}/fulfill`,
    { method: "POST" },
  );
  return res.data;
}
