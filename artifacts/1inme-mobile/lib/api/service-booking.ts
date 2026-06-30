import { apiFetch, getBaseUrl, MOBILE_USER_AGENT } from "@/lib/api";
import { getToken } from "@/lib/secure";

// ── Public types ─────────────────────────────────────────────────

export type ServiceBookingTax = {
  enabled: boolean;
  rate: number;
  inclusive: boolean;
  label: string;
};

export type ServiceBookingService = {
  id: number;
  name: string;
  description: string | null;
  price: string;
  duration_minutes: number;
  photo_url: string | null;
  is_unavailable: boolean;
};

export type ServiceBookingCategory = {
  id: number;
  name: string;
  description: string | null;
  services: ServiceBookingService[];
};

export type ServiceBookingPage = {
  config: {
    mode: "display" | "booking";
    currency: string;
    accent_color: string | null;
    booking_enabled: boolean;
    timezone: string;
    tax: ServiceBookingTax;
  };
  link: { alias: string; title: string | null; description: string | null };
  categories: ServiceBookingCategory[];
  uncategorized: ServiceBookingService[];
};

export type ServiceBookingSlot = {
  start: string;
  end: string;
  label: string;
};

export type ServiceBookingDay = {
  date: string;
  label: string;
  slots: ServiceBookingSlot[];
};

export type ServiceBookingSlotsResponse = {
  duration_minutes: number;
  timezone: string;
  days: ServiceBookingDay[];
};

/** Itemised estimated-bill breakdown shared by quote + booking snapshots. */
export type ServiceBookingBill = {
  subtotal: number;
  tax_enabled: boolean;
  tax_inclusive: boolean;
  tax_rate: number;
  tax_label: string;
  tax_amount: number;
  total: number;
  currency: string;
  is_estimate: boolean;
};

export type ServiceBookingQuoteLine = {
  service_id: number;
  name: string;
  unit_price: number;
  duration_minutes: number;
  quantity: number;
  line_total: number;
};

export type ServiceBookingQuote = {
  duration_minutes: number;
  lines: ServiceBookingQuoteLine[];
  bill: ServiceBookingBill;
};

export type GuestBookingItem = {
  name: string;
  quantity: number;
  unit_price: string;
  line_total: string;
};

export type GuestBooking = {
  public_token: string;
  status: string;
  status_label: string;
  customer_name: string | null;
  slot_start: string | null;
  slot_end: string | null;
  duration_minutes: number | null;
  subtotal: string | null;
  tax_inclusive: boolean;
  tax_rate: string | null;
  tax_amount: string | null;
  total: string | null;
  currency: string;
  is_estimate: boolean;
  items: GuestBookingItem[];
  created_at: string | null;
};

// ── Owner types ──────────────────────────────────────────────────

export type OwnerBookingItem = {
  id: number;
  name: string;
  quantity: number;
  unit_price: string;
  line_total: string;
};

export type OwnerBooking = {
  id: number;
  public_token: string;
  status: string;
  status_label: string;
  customer_name: string | null;
  customer_email: string | null;
  customer_phone: string | null;
  customer_note: string | null;
  slot_start: string | null;
  slot_end: string | null;
  duration_minutes: number | null;
  subtotal: string | null;
  tax_inclusive: boolean;
  tax_rate: string | null;
  tax_amount: string | null;
  total: string | null;
  currency: string;
  is_estimate: boolean;
  created_at: string | null;
  updated_at: string | null;
  items: OwnerBookingItem[];
};

export type OwnerBookingsResponse = {
  bookings: OwnerBooking[];
  open_count: number;
  server_time: string;
};

export type OwnerServiceItem = {
  id: number;
  category_id: number | null;
  name: string;
  description: string | null;
  price: string;
  currency: string | null;
  duration_minutes: number;
  photo_url: string | null;
  is_unavailable: boolean;
  is_active: boolean;
};

export type OwnerServiceCategory = {
  id: number;
  name: string;
  description: string | null;
  is_active: boolean;
  services: OwnerServiceItem[];
};

export type OwnerAvailabilityRule = {
  id: number;
  day_of_week: number;
  start_time: string;
  end_time: string;
  is_active: boolean;
};

export type OwnerBlockedDate = {
  id: number;
  date: string;
  reason: string | null;
};

export type OwnerServiceBookingConfig = {
  mode: "display" | "booking";
  currency: string;
  accent_color: string | null;
  booking_enabled: boolean;
  slot_length_minutes: number;
  lead_time_minutes: number;
  max_days_ahead: number;
  timezone: string;
  public_url: string;
  tax: ServiceBookingTax;
  categories: OwnerServiceCategory[];
  uncategorized: OwnerServiceItem[];
  availability_rules: OwnerAvailabilityRule[];
  blocked_dates: OwnerBlockedDate[];
};

export type CartInput = {
  services: { service_id: number; quantity?: number }[];
};

export type BookInput = {
  customer_name: string;
  customer_email?: string | null;
  customer_phone?: string | null;
  customer_note?: string | null;
  slot_start: string;
  services: { service_id: number; quantity?: number }[];
};

// ── Public calls ─────────────────────────────────────────────────

export async function getServiceBookingPage(
  alias: string,
): Promise<ServiceBookingPage> {
  const res = await apiFetch<{ data: ServiceBookingPage }>(
    `/service-booking/${encodeURIComponent(alias)}`,
  );
  return res.data;
}

export async function getServiceBookingSlots(
  alias: string,
  input: CartInput,
): Promise<ServiceBookingSlotsResponse> {
  const res = await apiFetch<{ data: ServiceBookingSlotsResponse }>(
    `/service-booking/${encodeURIComponent(alias)}/slots`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data;
}

export async function quoteServiceBooking(
  alias: string,
  input: CartInput,
): Promise<ServiceBookingQuote> {
  const res = await apiFetch<{ data: ServiceBookingQuote }>(
    `/service-booking/${encodeURIComponent(alias)}/quote`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data;
}

export async function placeServiceBooking(
  alias: string,
  input: BookInput,
): Promise<GuestBooking> {
  const res = await apiFetch<{ data: { booking: GuestBooking } }>(
    `/service-booking/${encodeURIComponent(alias)}/book`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.booking;
}

export async function getGuestBookingStatus(
  token: string,
): Promise<GuestBooking> {
  const res = await apiFetch<{ data: { booking: GuestBooking } }>(
    `/service-booking/bookings/${encodeURIComponent(token)}/status`,
  );
  return res.data.booking;
}

// ── Owner bookings dashboard ─────────────────────────────────────

export async function getOwnerBookings(
  linkId: number | string,
): Promise<OwnerBookingsResponse> {
  const res = await apiFetch<{ data: OwnerBookingsResponse }>(
    `/service-booking/links/${linkId}/bookings`,
  );
  return res.data;
}

export async function pollOwnerBookings(
  linkId: number | string,
  since?: string | null,
): Promise<OwnerBookingsResponse> {
  const qs = since ? `?since=${encodeURIComponent(since)}` : "";
  const res = await apiFetch<{ data: OwnerBookingsResponse }>(
    `/service-booking/links/${linkId}/bookings/poll${qs}`,
  );
  return res.data;
}

export async function updateOwnerBookingStatus(
  linkId: number | string,
  bookingId: number,
  status: string,
): Promise<OwnerBooking> {
  const res = await apiFetch<{ data: { booking: OwnerBooking } }>(
    `/service-booking/links/${linkId}/bookings/${bookingId}/status`,
    { method: "POST", body: JSON.stringify({ status }) },
  );
  return res.data.booking;
}

export const BOOKING_STATUS_FLOW: Record<string, string[]> = {
  pending: ["confirmed", "declined", "cancelled"],
  confirmed: ["completed", "cancelled"],
  completed: [],
  cancelled: [],
  declined: [],
};

export const BOOKING_ACTION_LABELS: Record<string, string> = {
  confirmed: "Confirm",
  completed: "Complete",
  cancelled: "Cancel",
  declined: "Decline",
};

export const OPEN_BOOKING_STATUSES = ["pending", "confirmed"];

// ── Owner builder ────────────────────────────────────────────────

export async function getOwnerServiceBookingConfig(
  linkId: number | string,
): Promise<OwnerServiceBookingConfig> {
  const res = await apiFetch<{ data: { config: OwnerServiceBookingConfig } }>(
    `/service-booking/links/${linkId}/config`,
  );
  return res.data.config;
}

export async function saveOwnerServiceBookingSettings(
  linkId: number | string,
  input: {
    mode: "display" | "booking";
    currency: string;
    accent_color?: string | null;
    slot_length_minutes: number;
    lead_time_minutes: number;
    max_days_ahead: number;
    timezone?: string | null;
    tax_enabled?: boolean;
    tax_rate?: number | null;
    tax_inclusive?: boolean;
    tax_label?: string | null;
  },
): Promise<OwnerServiceBookingConfig> {
  const res = await apiFetch<{ data: { config: OwnerServiceBookingConfig } }>(
    `/service-booking/links/${linkId}/config/settings`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.config;
}

export async function createServiceCategory(
  linkId: number | string,
  input: { name: string; description?: string | null },
): Promise<OwnerServiceCategory> {
  const res = await apiFetch<{ data: { category: OwnerServiceCategory } }>(
    `/service-booking/links/${linkId}/config/categories`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.category;
}

export async function updateServiceCategory(
  linkId: number | string,
  categoryId: number,
  input: { name?: string; description?: string | null; is_active?: boolean },
): Promise<OwnerServiceCategory> {
  const res = await apiFetch<{ data: { category: OwnerServiceCategory } }>(
    `/service-booking/links/${linkId}/config/categories/${categoryId}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
  return res.data.category;
}

export async function deleteServiceCategory(
  linkId: number | string,
  categoryId: number,
): Promise<void> {
  await apiFetch(
    `/service-booking/links/${linkId}/config/categories/${categoryId}`,
    { method: "DELETE" },
  );
}

export type ServiceInput = {
  category_id?: number | null;
  name: string;
  description?: string | null;
  price?: number | null;
  currency?: string | null;
  duration_minutes: number;
  photo_url?: string | null;
  is_unavailable?: boolean;
};

export async function createService(
  linkId: number | string,
  input: ServiceInput,
): Promise<OwnerServiceItem> {
  const res = await apiFetch<{ data: { service: OwnerServiceItem } }>(
    `/service-booking/links/${linkId}/config/services`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.service;
}

export async function updateService(
  linkId: number | string,
  serviceId: number,
  input: Partial<ServiceInput> & { is_active?: boolean },
): Promise<OwnerServiceItem> {
  const res = await apiFetch<{ data: { service: OwnerServiceItem } }>(
    `/service-booking/links/${linkId}/config/services/${serviceId}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
  return res.data.service;
}

export async function deleteService(
  linkId: number | string,
  serviceId: number,
): Promise<void> {
  await apiFetch(
    `/service-booking/links/${linkId}/config/services/${serviceId}`,
    { method: "DELETE" },
  );
}

export async function createAvailabilityRule(
  linkId: number | string,
  input: { day_of_week: number; start_time: string; end_time: string },
): Promise<OwnerAvailabilityRule> {
  const res = await apiFetch<{ data: { rule: OwnerAvailabilityRule } }>(
    `/service-booking/links/${linkId}/config/availability`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.rule;
}

export async function updateAvailabilityRule(
  linkId: number | string,
  ruleId: number,
  input: {
    day_of_week?: number;
    start_time?: string;
    end_time?: string;
    is_active?: boolean;
  },
): Promise<OwnerAvailabilityRule> {
  const res = await apiFetch<{ data: { rule: OwnerAvailabilityRule } }>(
    `/service-booking/links/${linkId}/config/availability/${ruleId}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
  return res.data.rule;
}

export async function deleteAvailabilityRule(
  linkId: number | string,
  ruleId: number,
): Promise<void> {
  await apiFetch(
    `/service-booking/links/${linkId}/config/availability/${ruleId}`,
    { method: "DELETE" },
  );
}

export async function createBlockedDate(
  linkId: number | string,
  input: { date: string; reason?: string | null },
): Promise<OwnerBlockedDate> {
  const res = await apiFetch<{ data: { blocked_date: OwnerBlockedDate } }>(
    `/service-booking/links/${linkId}/config/blocked-dates`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.blocked_date;
}

export async function deleteBlockedDate(
  linkId: number | string,
  blockedDateId: number,
): Promise<void> {
  await apiFetch(
    `/service-booking/links/${linkId}/config/blocked-dates/${blockedDateId}`,
    { method: "DELETE" },
  );
}

/**
 * Upload a service photo from the device. Posted as multipart/form-data to
 * mirror the web editor's upload flow; the server stores it in the vault and
 * returns the public URL to stamp onto the service.
 */
export async function uploadServicePhoto(
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
    name: args.name || `service.${ext}`,
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
    `${getBaseUrl()}/api/v1/service-booking/links/${linkId}/config/photo`,
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
