/**
 * Unit tests for the mailbox reply draft API methods:
 *   api.listKnowledgeBases  — GET /ai/minds
 *   api.draftMailboxReply   — POST /mailbox/draft-reply
 *
 * Follows the pattern from api-extensions.test.ts:
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

// ── listKnowledgeBases ─────────────────────────────────────────────────

describe("api.listKnowledgeBases", () => {
  it("makes a GET /ai/minds request", async () => {
    responder = () => ok({ mine: [], platform: null });
    await api.listKnowledgeBases();
    assert.equal(calls.length, 1);
    assert.equal(calls[0].method, "GET");
    assert.ok(calls[0].url.endsWith("/ai/minds"));
  });

  it("includes Authorization header", async () => {
    responder = () => ok({ mine: [], platform: null });
    await api.listKnowledgeBases();
    assert.equal(calls[0].headers["Authorization"], "Bearer test-token");
  });

  it("returns mine array and platform entry", async () => {
    responder = () => ok({
      mine: [{ id: 1, name: "My KB" }, { id: 2, name: "Product Docs" }],
      platform: { id: 99, name: "Sayzio Platform" },
    });
    const r = await api.listKnowledgeBases();
    assert.equal(r.mine.length, 2);
    assert.equal(r.mine[0].name, "My KB");
    assert.equal(r.platform?.id, 99);
  });

  it("returns null platform when not present", async () => {
    responder = () => ok({ mine: [], platform: null });
    const r = await api.listKnowledgeBases();
    assert.equal(r.platform, null);
  });
});

// ── draftMailboxReply ──────────────────────────────────────────────────

const baseThread = {
  subject: "Project proposal",
  participants: ["alice@example.com", "bob@example.com"],
  messages: [
    { role: "inbound" as const, sender: "alice@example.com", body: "Hi, can you share the proposal?" },
  ],
};

describe("api.draftMailboxReply", () => {
  it("sends POST /mailbox/draft-reply", async () => {
    responder = () => ok({
      draft: "Sure, here it is.",
      citations: [],
      credits_spent: 5,
      model: "gpt-4o-mini",
    });
    await api.draftMailboxReply({ thread: baseThread });
    assert.equal(calls.length, 1);
    assert.equal(calls[0].method, "POST");
    assert.ok(calls[0].url.endsWith("/mailbox/draft-reply"));
  });

  it("sends thread subject and messages in body", async () => {
    responder = () => ok({ draft: "Reply text", citations: [], credits_spent: 3, model: "gpt-4o-mini" });
    await api.draftMailboxReply({ thread: baseThread });
    const body = calls[0].body as any;
    assert.equal(body.thread.subject, "Project proposal");
    assert.equal(body.thread.messages.length, 1);
    assert.equal(body.thread.messages[0].sender, "alice@example.com");
  });

  it("includes knowledge_base_ids when provided", async () => {
    responder = () => ok({ draft: "With KB", citations: [{ id: 1, name: "My KB" }], credits_spent: 8, model: "gpt-4o-mini" });
    await api.draftMailboxReply({ thread: baseThread, knowledge_base_ids: [1, 2] });
    const body = calls[0].body as any;
    assert.deepEqual(body.knowledge_base_ids, [1, 2]);
  });

  it("omits knowledge_base_ids when not provided", async () => {
    responder = () => ok({ draft: "Plain", citations: [], credits_spent: 3, model: "gpt-4o-mini" });
    await api.draftMailboxReply({ thread: baseThread });
    const body = calls[0].body as any;
    assert.equal(body.knowledge_base_ids, undefined);
  });

  it("includes include_links flag", async () => {
    responder = () => ok({ draft: "With links", citations: [], credits_spent: 4, model: "gpt-4o-mini" });
    await api.draftMailboxReply({ thread: baseThread, include_links: false });
    const body = calls[0].body as any;
    assert.equal(body.include_links, false);
  });

  it("includes regenerate instruction when provided", async () => {
    responder = () => ok({ draft: "Shorter reply", citations: [], credits_spent: 3, model: "gpt-4o-mini" });
    await api.draftMailboxReply({ thread: baseThread, instruction: "make it shorter" });
    const body = calls[0].body as any;
    assert.equal(body.instruction, "make it shorter");
  });

  it("returns draft and citations from response envelope", async () => {
    const citations = [{ id: 1, name: "Product Docs" }];
    responder = () => ok({ draft: "Here is my reply.", citations, credits_spent: 7, model: "gpt-4o-mini" });
    const r = await api.draftMailboxReply({ thread: baseThread, knowledge_base_ids: [1] });
    assert.equal(r.draft, "Here is my reply.");
    assert.equal(r.citations.length, 1);
    assert.equal(r.citations[0].name, "Product Docs");
    assert.equal(r.credits_spent, 7);
  });

  it("throws ApiError with status 402 on insufficient coins", async () => {
    responder = () => ({
      status: 402,
      body: {
        error: {
          message: "Not enough coins",
          code: "insufficient_coins",
          details: { required: 10, balance: 2, topup_url: "https://example.com/upgrade" },
        },
      },
    });
    try {
      await api.draftMailboxReply({ thread: baseThread });
      assert.fail("should have thrown");
    } catch (e: any) {
      assert.equal(e.status, 402);
      assert.equal(e.code, "insufficient_coins");
    }
  });

  it("includes Authorization header", async () => {
    responder = () => ok({ draft: "ok", citations: [], credits_spent: 1, model: "gpt-4o-mini" });
    await api.draftMailboxReply({ thread: baseThread });
    assert.equal(calls[0].headers["Authorization"], "Bearer test-token");
  });
});
