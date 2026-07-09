import { describe, it } from "node:test";
import assert from "node:assert/strict";

import {
  capPendingThanks,
  markPendingThanksSeen,
  prunePendingThanks,
  unreadPendingThanksCount,
  PENDING_THANKS_MAX,
  PENDING_THANKS_TTL_MS,
  type PendingThank,
} from "./storage";

function makeThank(overrides: Partial<PendingThank> = {}): PendingThank {
  return {
    id: overrides.id ?? "id-0",
    templateId: "tmpl-email",
    channel: "email",
    subject: "Thanks!",
    body: "Body",
    recipient: null,
    pageUrl: "https://example.com/post",
    matchedUrl: "https://1inme.com/x",
    anchor: "anchor",
    createdAt: 0,
    ...overrides,
  };
}

function makeQueue(count: number, startCreatedAt = 1_000): PendingThank[] {
  return Array.from({ length: count }, (_, i) =>
    makeThank({ id: `id-${i}`, createdAt: startCreatedAt + i }),
  );
}

describe("capPendingThanks", () => {
  it("returns the input unchanged when below the cap", () => {
    const items = makeQueue(5);
    const result = capPendingThanks(items);
    assert.equal(result.items, items);
    assert.equal(result.items.length, 5);
    assert.equal(result.dropped, 0);
  });

  it("returns the input unchanged when exactly at the cap", () => {
    const items = makeQueue(PENDING_THANKS_MAX);
    const result = capPendingThanks(items);
    assert.equal(result.items, items);
    assert.equal(result.items.length, PENDING_THANKS_MAX);
    assert.equal(result.dropped, 0);
  });

  it("returns an empty array unchanged", () => {
    const items: PendingThank[] = [];
    const result = capPendingThanks(items);
    assert.deepEqual(result.items, []);
    assert.equal(result.dropped, 0);
  });

  it("trims to the last MAX entries when over the cap", () => {
    const items = makeQueue(PENDING_THANKS_MAX + 10);
    const result = capPendingThanks(items);
    assert.equal(result.items.length, PENDING_THANKS_MAX);
    assert.equal(result.dropped, 10);
  });

  it("drops the oldest entries first (keeps the newest tail)", () => {
    const overflow = 7;
    const items = makeQueue(PENDING_THANKS_MAX + overflow);
    const result = capPendingThanks(items);
    // Oldest `overflow` entries are dropped; remaining ids start at `overflow`.
    assert.equal(result.items[0]?.id, `id-${overflow}`);
    assert.equal(result.items[result.items.length - 1]?.id, `id-${PENDING_THANKS_MAX + overflow - 1}`);
    assert.equal(result.dropped, overflow);
  });

  it("preserves the order of the kept items", () => {
    const items = makeQueue(PENDING_THANKS_MAX + 3);
    const result = capPendingThanks(items);
    for (let i = 1; i < result.items.length; i++) {
      assert.ok((result.items[i]!.createdAt) > (result.items[i - 1]!.createdAt));
    }
  });
});

describe("prunePendingThanks", () => {
  const now = 10_000_000_000; // arbitrary fixed "now"

  it("keeps fresh items and reports pruned=false", () => {
    const items = [
      makeThank({ id: "fresh-1", createdAt: now - 1_000 }),
      makeThank({ id: "fresh-2", createdAt: now - PENDING_THANKS_TTL_MS + 1 }),
      makeThank({ id: "fresh-3", createdAt: now }),
    ];
    const result = prunePendingThanks(items, now);
    assert.equal(result.pruned, 0);
    assert.equal(result.items.length, 3);
    assert.deepEqual(
      result.items.map((i) => i.id),
      ["fresh-1", "fresh-2", "fresh-3"],
    );
  });

  it("removes items older than the TTL and reports pruned=true", () => {
    const items = [
      makeThank({ id: "old-1", createdAt: now - PENDING_THANKS_TTL_MS - 1 }),
      makeThank({ id: "fresh", createdAt: now - 100 }),
      makeThank({ id: "ancient", createdAt: 0 }),
    ];
    const result = prunePendingThanks(items, now);
    assert.equal(result.pruned, 2);
    assert.deepEqual(
      result.items.map((i) => i.id),
      ["fresh"],
    );
  });

  it("treats items exactly at the cutoff as fresh (inclusive boundary)", () => {
    const items = [
      makeThank({ id: "boundary", createdAt: now - PENDING_THANKS_TTL_MS }),
    ];
    const result = prunePendingThanks(items, now);
    assert.equal(result.pruned, 0);
    assert.equal(result.items.length, 1);
    assert.equal(result.items[0]?.id, "boundary");
  });

  it("treats items just past the cutoff as expired", () => {
    const items = [
      makeThank({ id: "just-old", createdAt: now - PENDING_THANKS_TTL_MS - 1 }),
    ];
    const result = prunePendingThanks(items, now);
    assert.equal(result.pruned, 1);
    assert.equal(result.items.length, 0);
  });

  it("returns an empty array (and pruned=false) for empty input", () => {
    const result = prunePendingThanks([], now);
    assert.deepEqual(result.items, []);
    assert.equal(result.pruned, 0);
  });

  it("removes everything when all items are expired", () => {
    const items = makeQueue(5, 1_000); // very old createdAt values
    const result = prunePendingThanks(items, now);
    assert.equal(result.pruned, 5);
    assert.deepEqual(result.items, []);
  });

  it("defaults `now` to Date.now() when not provided", () => {
    // Items in the recent past should always survive against the real clock.
    const items = [makeThank({ id: "recent", createdAt: Date.now() - 1_000 })];
    const result = prunePendingThanks(items);
    assert.equal(result.pruned, 0);
    assert.equal(result.items.length, 1);
  });
});

describe("unreadPendingThanksCount", () => {
  it("returns 0 for an empty queue", () => {
    assert.equal(unreadPendingThanksCount([], []), 0);
    assert.equal(unreadPendingThanksCount([], ["id-9"]), 0);
  });

  it("counts every queued item when nothing has been seen yet", () => {
    const items = makeQueue(4);
    assert.equal(unreadPendingThanksCount(items, []), 4);
  });

  it("counts only ids missing from the seen set", () => {
    const items = makeQueue(5);
    const seen = ["id-0", "id-2", "id-4"];
    assert.equal(unreadPendingThanksCount(items, seen), 2);
  });

  it("ignores seen ids that are no longer queued", () => {
    const items = makeQueue(2);
    const seen = ["id-0", "id-1", "id-99-stale"];
    assert.equal(unreadPendingThanksCount(items, seen), 0);
  });
});

describe("markPendingThanksSeen", () => {
  it("marks every queued id as seen on first run", () => {
    const items = makeQueue(3);
    const result = markPendingThanksSeen(items, []);
    assert.equal(result.changed, true);
    assert.deepEqual(result.seenIds, ["id-0", "id-1", "id-2"]);
  });

  it("reports no change when the seen set already matches the queue exactly", () => {
    const items = makeQueue(3);
    const result = markPendingThanksSeen(items, ["id-0", "id-1", "id-2"]);
    assert.equal(result.changed, false);
    assert.deepEqual(result.seenIds, ["id-0", "id-1", "id-2"]);
  });

  it("reports change when a new item arrives", () => {
    const items = makeQueue(3);
    const result = markPendingThanksSeen(items, ["id-0", "id-1"]);
    assert.equal(result.changed, true);
    assert.deepEqual(result.seenIds, ["id-0", "id-1", "id-2"]);
  });

  it("prunes seen ids that no longer correspond to a queued item", () => {
    const items = makeQueue(2);
    const result = markPendingThanksSeen(items, ["id-0", "id-1", "id-stale"]);
    assert.equal(result.changed, true);
    assert.deepEqual(result.seenIds, ["id-0", "id-1"]);
  });

  it("returns an empty seen list when the queue is empty", () => {
    const result = markPendingThanksSeen([], ["id-stale"]);
    assert.equal(result.changed, true);
    assert.deepEqual(result.seenIds, []);
  });

  it("reports no change when both the queue and the seen list are empty", () => {
    const result = markPendingThanksSeen([], []);
    assert.equal(result.changed, false);
    assert.deepEqual(result.seenIds, []);
  });
});

// ── appendDialHistory ────────────────────────────────────────────────

import { appendDialHistory, DIAL_HISTORY_MAX, type DialHistoryEntry } from "./storage";

function makeCall(overrides: Partial<DialHistoryEntry> = {}): DialHistoryEntry {
  return {
    number: overrides.number ?? "+15551234567",
    contactId: overrides.contactId ?? null,
    contactName: overrides.contactName ?? null,
    pageHost: overrides.pageHost ?? "example.com",
    at: overrides.at ?? 1_000_000,
  };
}

describe("appendDialHistory", () => {
  it("prepends the new entry (newest first)", () => {
    const h = appendDialHistory([makeCall({ number: "+1111111111", at: 1000 })], makeCall({ number: "+2222222222", at: 2000 }));
    assert.equal(h.length, 2);
    assert.equal(h[0].number, "+2222222222");
  });

  it("caps the history at DIAL_HISTORY_MAX", () => {
    let h: DialHistoryEntry[] = [];
    for (let i = 0; i < DIAL_HISTORY_MAX + 5; i++) {
      h = appendDialHistory(h, makeCall({ number: `+1000000${i}`, at: i * 120_000 }));
    }
    assert.equal(h.length, DIAL_HISTORY_MAX);
    assert.equal(h[0].number, `+1000000${DIAL_HISTORY_MAX + 4}`);
  });

  it("collapses a re-click of the same number within a minute", () => {
    const first = makeCall({ at: 1_000_000, contactId: null });
    const again = makeCall({ at: 1_030_000, contactId: 7, contactName: "Ada" });
    const h = appendDialHistory([first], again);
    assert.equal(h.length, 1);
    assert.equal(h[0].contactId, 7);
    assert.equal(h[0].at, 1_030_000);
  });

  it("keeps separate entries when the same number is dialed later", () => {
    const first = makeCall({ at: 1_000_000 });
    const later = makeCall({ at: 1_000_000 + 120_000 });
    const h = appendDialHistory([first], later);
    assert.equal(h.length, 2);
  });

  it("tolerates a non-array stored value", () => {
    const h = appendDialHistory(undefined as unknown as DialHistoryEntry[], makeCall());
    assert.equal(h.length, 1);
  });
});
