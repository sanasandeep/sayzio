/**
 * Unit tests for the six new API extension methods added in the feature
 * expansion: notifications, dialer lookup, biolinks list, QR codes,
 * calendars, and review capture.
 *
 * Follows the existing test pattern in storage.sync.test.ts:
 *  - in-memory fetch recorder via globalThis.fetch mock
 *  - webextension-polyfill stubs wired by test-setup.ts
 */
import { describe, it, before, beforeEach, afterEach } from "node:test";
import assert from "node:assert/strict";
import { api } from "./api.js";

interface FetchCall {
  url: string;
  method: string;
  body: unknown;
  headers: Record<string, string>;
}

let calls: FetchCall[] = [];
let responder: (call: FetchCall) => { status?: number; body?: unknown };

function ok(body: unknown) {
  return { status: 200, body: { data: body } };
}

before(async () => {
  // Seed a token so api methods include an Authorization header.
  await (await import("./storage.js")).setSettings({
    apiBaseUrl: "https://test.example/api/v1",
    token: "test-token",
  });
});

beforeEach(() => {
  calls = [];
  responder = () => ({ status: 500, body: { message: "no responder set" } });

  (globalThis as any).fetch = async (url: string, init: RequestInit = {}) => {
    const headers = (init.headers as Record<string, string>) || {};
    const body = init.body ? JSON.parse(String(init.body)) : undefined;
    const call: FetchCall = { url, method: (init.method || "GET").toUpperCase(), body, headers };
    calls.push(call);
    const out = responder(call);
    return {
      ok: (out.status ?? 200) < 300,
      status: out.status ?? 200,
      text: async () => JSON.stringify(out.body),
    };
  };
});

afterEach(() => {
  (globalThis as any).fetch = undefined;
});

// ── Notifications ──────────────────────────────────────────────────────

describe("api.getNotifications", () => {
  it("makes a GET /notifications request", async () => {
    responder = () => ok({ items: [], meta: { total: 0, unread: 0 } });
    await api.getNotifications();
    assert.equal(calls.length, 1);
    assert.equal(calls[0].method, "GET");
    assert.ok(calls[0].url.includes("/notifications"));
  });

  it("passes per_page query param", async () => {
    responder = () => ok({ items: [], meta: { total: 0, unread: 0 } });
    await api.getNotifications({ perPage: 5 });
    assert.ok(calls[0].url.includes("per_page=5"));
  });

  it("returns items from the data envelope", async () => {
    const item = { id: 1, type: "new_subscriber", data: {}, read_at: null, created_at: "2026-01-01T00:00:00Z" };
    responder = () => ok({ items: [item], meta: { total: 1, unread: 1 } });
    const result = await api.getNotifications();
    assert.equal(result.items.length, 1);
    assert.equal(result.items[0].id, 1);
  });
});

describe("api.markNotificationRead", () => {
  it("sends POST /notifications/{id}/read", async () => {
    responder = () => ok(null);
    await api.markNotificationRead(42);
    assert.equal(calls[0].method, "POST");
    assert.ok(calls[0].url.endsWith("/notifications/42/read"));
  });
});

describe("api.markAllNotificationsRead", () => {
  it("sends POST /notifications/read-all", async () => {
    responder = () => ok(null);
    await api.markAllNotificationsRead();
    assert.equal(calls[0].method, "POST");
    assert.ok(calls[0].url.endsWith("/notifications/read-all"));
  });
});

// ── Dialer ─────────────────────────────────────────────────────────────

describe("api.dialerLookup", () => {
  it("sends POST /dialer/lookup with number_e164", async () => {
    responder = () => ok({ contact: null, biolink: null, activity: [], is_spam: false, is_blocked: false, is_favorite: false });
    await api.dialerLookup("+14155551234");
    assert.equal(calls[0].method, "POST");
    assert.ok(calls[0].url.endsWith("/dialer/lookup"));
    assert.equal((calls[0].body as any).number_e164, "+14155551234");
  });

  it("returns contact + flags from response", async () => {
    responder = () => ok({
      contact: { id: 5, name: "Alice" },
      biolink: null,
      activity: [],
      is_spam: false,
      is_blocked: true,
      is_favorite: false,
    });
    const r = await api.dialerLookup("+1234567890");
    assert.equal(r.contact?.name, "Alice");
    assert.equal(r.is_blocked, true);
  });
});

// ── Biolinks ───────────────────────────────────────────────────────────

describe("api.getBiolinks", () => {
  it("requests links with type=biolink", async () => {
    responder = () => ok({ items: [] });
    await api.getBiolinks();
    assert.ok(calls[0].url.includes("type=biolink"));
    assert.equal(calls[0].method, "GET");
  });

  it("respects per_page param", async () => {
    responder = () => ok({ items: [] });
    await api.getBiolinks(5);
    assert.ok(calls[0].url.includes("per_page=5"));
  });

  it("returns item array", async () => {
    responder = () => ok({ items: [{ id: 1, alias: "my-page", title: "My Page" }] });
    const r = await api.getBiolinks();
    assert.equal(r.items[0].alias, "my-page");
  });
});

// ── QR codes ───────────────────────────────────────────────────────────

describe("api.getQrCatalog", () => {
  it("fetches GET /qr-codes/catalog", async () => {
    responder = () => ok({ presets: [], dots: [], outer_eyes: [], inner_eyes: [], frames: [], fonts: [], types: [], default_design: {} });
    await api.getQrCatalog();
    assert.ok(calls[0].url.endsWith("/qr-codes/catalog"));
    assert.equal(calls[0].method, "GET");
  });
});

describe("api.createQrCode", () => {
  it("sends POST /qr-codes with required fields", async () => {
    responder = () => ok({ qr_code: { id: 7, name: "Test QR" } });
    await api.createQrCode("Test QR", "url", { url: "https://example.com" });
    assert.equal(calls[0].method, "POST");
    assert.ok(calls[0].url.endsWith("/qr-codes"));
    assert.equal((calls[0].body as any).name, "Test QR");
    assert.equal((calls[0].body as any).type, "url");
  });

  it("includes design when provided", async () => {
    responder = () => ok({ qr_code: { id: 8, name: "QR2" } });
    const design = { dots_color: "#ff0000" };
    await api.createQrCode("QR2", "url", { url: "https://x.com" }, undefined, design);
    assert.deepEqual((calls[0].body as any).design, design);
  });

  it("omits undefined optional fields", async () => {
    responder = () => ok({ qr_code: { id: 9, name: "QR3" } });
    await api.createQrCode("QR3", "url");
    const body = calls[0].body as any;
    assert.equal(body.link_id, undefined);
    assert.equal(body.design, undefined);
  });
});

// ── Calendars ──────────────────────────────────────────────────────────

describe("api.getCalendars", () => {
  it("fetches GET /calendars", async () => {
    responder = () => ok({ items: [] });
    await api.getCalendars();
    assert.ok(calls[0].url.endsWith("/calendars"));
    assert.equal(calls[0].method, "GET");
  });
});

describe("api.createCalendarEvent", () => {
  it("sends POST /calendars/{id}/events with required title", async () => {
    responder = () => ok({ event: { id: 1, title: "Meeting" } });
    await api.createCalendarEvent(3, { title: "Meeting", start_date: "2026-08-01T10:00:00Z" });
    assert.ok(calls[0].url.endsWith("/calendars/3/events"));
    assert.equal(calls[0].method, "POST");
    assert.equal((calls[0].body as any).title, "Meeting");
  });
});

// ── Review capture ─────────────────────────────────────────────────────

describe("api.captureReviewSource", () => {
  it("sends POST /me/reviews/capture-source", async () => {
    responder = () => ok({ connection_id: 1, provider: "google", status: "connected", preview: false });
    await api.captureReviewSource("google", "ChIJtest123");
    assert.ok(calls[0].url.endsWith("/me/reviews/capture-source"));
    assert.equal(calls[0].method, "POST");
  });

  it("includes provider and external_ref in body", async () => {
    responder = () => ok({ connection_id: 2, provider: "trustpilot", status: "connected", preview: false });
    await api.captureReviewSource("trustpilot", "example.com", "My Biz");
    const body = calls[0].body as any;
    assert.equal(body.provider, "trustpilot");
    assert.equal(body.external_ref, "example.com");
    assert.equal(body.name, "My Biz");
  });

  it("omits name when not provided", async () => {
    responder = () => ok({ connection_id: 3, provider: "google", status: "connected", preview: false });
    await api.captureReviewSource("google", "ChIJ456");
    const body = calls[0].body as any;
    assert.equal(body.name, undefined);
  });

  it("returns preview flag from the response", async () => {
    responder = () => ok({ connection_id: 4, provider: "google", status: "preview", preview: true });
    const r = await api.captureReviewSource("google", "ChIJpreview");
    assert.equal(r.preview, true);
    assert.equal(r.status, "preview");
  });
});
