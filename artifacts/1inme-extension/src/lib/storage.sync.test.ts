import { describe, it, beforeEach, afterEach } from "node:test";
import assert from "node:assert/strict";

import {
  syncPendingThanks,
  savePendingThanksLocallyAndPush,
  setSettings,
  getSettings,
  PENDING_THANKS_TTL_MS,
  type PendingThank,
} from "./storage";

// In-memory fetch recorder. Each test sets `responder` to script the
// next response and consults `calls` to assert the request shape.
interface FetchCall {
  url: string;
  method: string;
  body: unknown;
  headers: Record<string, string>;
}

let calls: FetchCall[] = [];
let responder: (call: FetchCall) => { status?: number; body?: unknown } | Promise<{ status?: number; body?: unknown }>;

beforeEach(() => {
  (globalThis as any).__resetExtStorage?.();
  calls = [];
  responder = () => ({ status: 500, body: { message: "no responder set" } });

  (globalThis as any).fetch = async (url: string, init: RequestInit = {}) => {
    const headers = (init.headers as Record<string, string>) || {};
    const body = init.body ? JSON.parse(String(init.body)) : undefined;
    const call: FetchCall = {
      url,
      method: (init.method || "GET").toUpperCase(),
      body,
      headers,
    };
    calls.push(call);
    const out = await responder(call);
    const status = out.status ?? 200;
    const text = out.body === undefined ? "" : JSON.stringify(out.body);
    return {
      ok: status >= 200 && status < 300,
      status,
      async text() { return text; },
    } as unknown as Response;
  };
});

afterEach(() => {
  delete (globalThis as any).fetch;
});

function thank(overrides: Partial<PendingThank> = {}): PendingThank {
  return {
    id: overrides.id ?? "id-1",
    templateId: "tmpl-email",
    channel: "email",
    subject: "Thanks!",
    body: "Body",
    recipient: null,
    pageUrl: "https://example.com/post",
    matchedUrl: "https://1inme.com/x",
    anchor: "anchor",
    createdAt: overrides.createdAt ?? Date.now(),
    ...overrides,
  };
}

async function seedSignedIn(opts: {
  pendingThanks?: PendingThank[];
  pendingThanksUpdatedAtMs?: number | null;
  pendingThanksWorkspaceId?: number | null;
  workspaceId?: number;
} = {}): Promise<void> {
  await setSettings({
    token: "test-token",
    workspaceId: opts.workspaceId ?? 7,
    pendingThanks: opts.pendingThanks ?? [],
    pendingThanksUpdatedAtMs: opts.pendingThanksUpdatedAtMs ?? null,
    pendingThanksWorkspaceId: opts.pendingThanksWorkspaceId ?? null,
  });
}

function serverPayload(items: PendingThank[], updated_at_ms: number | null): unknown {
  return {
    data: {
      workspace_id: 7,
      items,
      updated_at_ms,
      max: 50,
    },
  };
}

describe("syncPendingThanks", () => {
  it("is a no-op when not signed in", async () => {
    // No token / workspaceId set -> never hits the network.
    await syncPendingThanks();
    assert.equal(calls.length, 0);
  });

  it("server wins on the very first sync for a workspace (different workspace id)", async () => {
    // Local state belongs to workspace 99; the active workspace is 7.
    // Even though local has a newer timestamp, we MUST adopt the
    // server copy because we've never synced workspace 7 before.
    const localOld = thank({ id: "local-old", createdAt: Date.now() - 1_000 });
    await seedSignedIn({
      pendingThanks: [localOld],
      pendingThanksUpdatedAtMs: Date.now(),
      pendingThanksWorkspaceId: 99,
      workspaceId: 7,
    });
    const serverItem = thank({ id: "server-1", createdAt: Date.now() - 500 });
    responder = (call) => {
      assert.equal(call.method, "GET");
      assert.match(call.url, /\/me\/pending-thanks\?workspace_id=7$/);
      return { body: serverPayload([serverItem], 1234) };
    };

    await syncPendingThanks();

    const after = await getSettings();
    assert.deepEqual(after.pendingThanks.map((p) => p.id), ["server-1"]);
    assert.equal(after.pendingThanksUpdatedAtMs, 1234);
    assert.equal(after.pendingThanksWorkspaceId, 7);
    // No PUT — we adopted, not pushed.
    assert.equal(calls.filter((c) => c.method === "PUT").length, 0);
  });

  it("server wins when local timestamp is null even on the same workspace", async () => {
    await seedSignedIn({
      pendingThanks: [thank({ id: "local-x" })],
      pendingThanksUpdatedAtMs: null,
      pendingThanksWorkspaceId: 7,
      workspaceId: 7,
    });
    const serverItem = thank({ id: "server-y", createdAt: Date.now() - 200 });
    responder = () => ({ body: serverPayload([serverItem], 9000) });

    await syncPendingThanks();

    const after = await getSettings();
    assert.deepEqual(after.pendingThanks.map((p) => p.id), ["server-y"]);
    assert.equal(after.pendingThanksUpdatedAtMs, 9000);
  });

  it("last-write-wins: newer server timestamp adopts server payload", async () => {
    await seedSignedIn({
      pendingThanks: [thank({ id: "local" })],
      pendingThanksUpdatedAtMs: 1000,
      pendingThanksWorkspaceId: 7,
      workspaceId: 7,
    });
    responder = () => ({ body: serverPayload([thank({ id: "server-newer" })], 2000) });

    await syncPendingThanks();

    const after = await getSettings();
    assert.deepEqual(after.pendingThanks.map((p) => p.id), ["server-newer"]);
    assert.equal(after.pendingThanksUpdatedAtMs, 2000);
    assert.equal(calls.filter((c) => c.method === "PUT").length, 0);
  });

  it("last-write-wins: newer local timestamp pushes local payload to server", async () => {
    const localItem = thank({ id: "local-newer" });
    await seedSignedIn({
      pendingThanks: [localItem],
      pendingThanksUpdatedAtMs: 5000,
      pendingThanksWorkspaceId: 7,
      workspaceId: 7,
    });
    responder = (call) => {
      if (call.method === "GET") {
        return { body: serverPayload([thank({ id: "server-stale" })], 1000) };
      }
      // PUT: server echoes back what was sent and stamps a fresh ts.
      assert.equal(call.method, "PUT");
      assert.match(call.url, /\/me\/pending-thanks\?workspace_id=7$/);
      const sent = call.body as { items: PendingThank[]; updated_at_ms: number };
      assert.equal(sent.updated_at_ms, 5000);
      assert.deepEqual(sent.items.map((i) => i.id), ["local-newer"]);
      return { body: serverPayload(sent.items, 5500) };
    };

    await syncPendingThanks();

    const after = await getSettings();
    assert.deepEqual(after.pendingThanks.map((p) => p.id), ["local-newer"]);
    assert.equal(after.pendingThanksUpdatedAtMs, 5500);
    assert.equal(calls.filter((c) => c.method === "PUT").length, 1);
  });

  it("equal timestamps converge to the server payload", async () => {
    await seedSignedIn({
      pendingThanks: [thank({ id: "local-equal" })],
      pendingThanksUpdatedAtMs: 4242,
      pendingThanksWorkspaceId: 7,
      workspaceId: 7,
    });
    responder = (call) => {
      assert.equal(call.method, "GET");
      return { body: serverPayload([thank({ id: "server-equal" })], 4242) };
    };

    await syncPendingThanks();

    const after = await getSettings();
    assert.deepEqual(after.pendingThanks.map((p) => p.id), ["server-equal"]);
    assert.equal(after.pendingThanksUpdatedAtMs, 4242);
    assert.equal(calls.filter((c) => c.method === "PUT").length, 0);
  });

  it("TTL-prunes server items older than 30 days when adopting", async () => {
    // First sync (different workspace) → adopt-server path. The server
    // has one fresh item and one stale item; the stale one must NOT
    // land in local storage.
    await seedSignedIn({
      pendingThanksWorkspaceId: 99,
      workspaceId: 7,
    });
    const now = Date.now();
    const fresh = thank({ id: "fresh", createdAt: now - 1_000 });
    const stale = thank({ id: "stale", createdAt: now - PENDING_THANKS_TTL_MS - 10_000 });
    responder = () => ({ body: serverPayload([fresh, stale], now) });

    await syncPendingThanks();

    const after = await getSettings();
    assert.deepEqual(after.pendingThanks.map((p) => p.id), ["fresh"]);
  });

  it("server 5xx is swallowed and leaves local state untouched", async () => {
    const localItem = thank({ id: "untouched" });
    await seedSignedIn({
      pendingThanks: [localItem],
      pendingThanksUpdatedAtMs: 1234,
      pendingThanksWorkspaceId: 7,
      workspaceId: 7,
    });
    responder = () => ({ status: 503, body: { message: "down" } });

    await syncPendingThanks();

    const after = await getSettings();
    assert.deepEqual(after.pendingThanks.map((p) => p.id), ["untouched"]);
    assert.equal(after.pendingThanksUpdatedAtMs, 1234);
  });
});

describe("savePendingThanksLocallyAndPush", () => {
  it("persists locally and pushes to the server with a fresh timestamp", async () => {
    await seedSignedIn({
      pendingThanksWorkspaceId: 7,
      workspaceId: 7,
    });
    const items = [thank({ id: "a" }), thank({ id: "b" })];
    const before = Date.now();
    responder = (call) => {
      assert.equal(call.method, "PUT");
      const sent = call.body as { items: PendingThank[]; updated_at_ms: number };
      assert.deepEqual(sent.items.map((i) => i.id), ["a", "b"]);
      assert.ok(sent.updated_at_ms >= before);
      return { body: serverPayload(sent.items, sent.updated_at_ms) };
    };

    const result = await savePendingThanksLocallyAndPush(items);

    assert.equal(result.pushed, true);
    const after = await getSettings();
    assert.deepEqual(after.pendingThanks.map((p) => p.id), ["a", "b"]);
    assert.equal(after.pendingThanksWorkspaceId, 7);
    assert.ok((after.pendingThanksUpdatedAtMs ?? 0) >= before);
  });

  it("persists locally even when the server push fails", async () => {
    await seedSignedIn({
      pendingThanksWorkspaceId: 7,
      workspaceId: 7,
    });
    responder = () => ({ status: 503, body: { message: "down" } });

    const result = await savePendingThanksLocallyAndPush([thank({ id: "offline" })]);

    assert.equal(result.pushed, false);
    assert.ok(result.error);
    const after = await getSettings();
    // Local copy survived even though the push failed.
    assert.deepEqual(after.pendingThanks.map((p) => p.id), ["offline"]);
    assert.ok(after.pendingThanksUpdatedAtMs != null);
  });

  it("skips the network entirely when there is no token", async () => {
    await setSettings({
      token: null,
      workspaceId: 7,
    });

    const result = await savePendingThanksLocallyAndPush([thank({ id: "anon" })]);

    assert.equal(result.pushed, false);
    assert.equal(calls.length, 0);
    const after = await getSettings();
    assert.deepEqual(after.pendingThanks.map((p) => p.id), ["anon"]);
  });
});
