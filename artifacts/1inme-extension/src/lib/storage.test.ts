import { describe, it } from "node:test";
import assert from "node:assert/strict";

import {
  capPendingThanks,
  prunePendingThanks,
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
    assert.equal(result, items);
    assert.equal(result.length, 5);
  });

  it("returns the input unchanged when exactly at the cap", () => {
    const items = makeQueue(PENDING_THANKS_MAX);
    const result = capPendingThanks(items);
    assert.equal(result, items);
    assert.equal(result.length, PENDING_THANKS_MAX);
  });

  it("returns an empty array unchanged", () => {
    const items: PendingThank[] = [];
    const result = capPendingThanks(items);
    assert.deepEqual(result, []);
  });

  it("trims to the last MAX entries when over the cap", () => {
    const items = makeQueue(PENDING_THANKS_MAX + 10);
    const result = capPendingThanks(items);
    assert.equal(result.length, PENDING_THANKS_MAX);
  });

  it("drops the oldest entries first (keeps the newest tail)", () => {
    const overflow = 7;
    const items = makeQueue(PENDING_THANKS_MAX + overflow);
    const result = capPendingThanks(items);
    // Oldest `overflow` entries are dropped; remaining ids start at `overflow`.
    assert.equal(result[0]?.id, `id-${overflow}`);
    assert.equal(result[result.length - 1]?.id, `id-${PENDING_THANKS_MAX + overflow - 1}`);
  });

  it("preserves the order of the kept items", () => {
    const items = makeQueue(PENDING_THANKS_MAX + 3);
    const result = capPendingThanks(items);
    for (let i = 1; i < result.length; i++) {
      assert.ok((result[i]!.createdAt) > (result[i - 1]!.createdAt));
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
    assert.equal(result.pruned, false);
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
    assert.equal(result.pruned, true);
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
    assert.equal(result.pruned, false);
    assert.equal(result.items.length, 1);
    assert.equal(result.items[0]?.id, "boundary");
  });

  it("treats items just past the cutoff as expired", () => {
    const items = [
      makeThank({ id: "just-old", createdAt: now - PENDING_THANKS_TTL_MS - 1 }),
    ];
    const result = prunePendingThanks(items, now);
    assert.equal(result.pruned, true);
    assert.equal(result.items.length, 0);
  });

  it("returns an empty array (and pruned=false) for empty input", () => {
    const result = prunePendingThanks([], now);
    assert.deepEqual(result.items, []);
    assert.equal(result.pruned, false);
  });

  it("removes everything when all items are expired", () => {
    const items = makeQueue(5, 1_000); // very old createdAt values
    const result = prunePendingThanks(items, now);
    assert.equal(result.pruned, true);
    assert.deepEqual(result.items, []);
  });

  it("defaults `now` to Date.now() when not provided", () => {
    // Items in the recent past should always survive against the real clock.
    const items = [makeThank({ id: "recent", createdAt: Date.now() - 1_000 })];
    const result = prunePendingThanks(items);
    assert.equal(result.pruned, false);
    assert.equal(result.items.length, 1);
  });
});
