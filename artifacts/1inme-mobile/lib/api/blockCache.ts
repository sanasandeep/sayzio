import type { Block } from "@/lib/api/blocks";

// Pure cache-patching helpers for the mobile block editor.
//
// The editor mirrors the web behaviour: instead of refetching the whole
// list after every add / move / delete / toggle, it patches the React
// Query cache in place. These transforms are the heart of that and are
// easy to regress silently, so they live here as pure functions that can
// be unit-tested in isolation (see `scripts/test-block-cache.mjs`).

/**
 * Append a freshly-created block to the end of the list. A missing list
 * (cache not yet populated) becomes a single-element list so the new
 * block still shows up.
 */
export function appendBlock(
  old: Block[] | undefined,
  block: Block,
): Block[] {
  return old ? [...old, block] : [block];
}

/**
 * Insert a freshly-applied sub-tree (a card parent followed by its child
 * blocks) into the list right after the block with id `afterId`. Used by
 * the card-template apply flow, which creates a parent + children in one
 * shot — patching them in keeps the editor in sync without a full refetch.
 *
 * When `afterId` is null (or not found), the sub-tree is appended to the
 * end, matching where the apply endpoint places a card inserted after the
 * last top-level block. A missing list becomes the sub-tree itself.
 */
export function insertBlockTree(
  old: Block[] | undefined,
  blocks: Block[],
  afterId: number | null,
): Block[] {
  if (!old) return [...blocks];
  if (blocks.length === 0) return old;
  const idx = afterId == null ? -1 : old.findIndex((b) => b.id === afterId);
  if (idx < 0) return [...old, ...blocks];
  return [...old.slice(0, idx + 1), ...blocks, ...old.slice(idx + 1)];
}

/**
 * Replace a single block in place by id (used after a toggle or any
 * single-block update). Leaves the list untouched if the cache is empty.
 */
export function replaceBlock(
  old: Block[] | undefined,
  updated: Block,
): Block[] | undefined {
  return old ? old.map((b) => (b.id === updated.id ? updated : b)) : old;
}

/**
 * Remove a block and every child that hangs off it (card containers own
 * child blocks via `parent_id`). Deleting the parent must take the whole
 * sub-tree with it so the list never shows orphaned children.
 */
export function removeBlockTree(
  old: Block[] | undefined,
  blockId: number,
): Block[] | undefined {
  return old
    ? old.filter((b) => b.id !== blockId && b.parent_id !== blockId)
    : old;
}

/**
 * Move the block at `idx` one slot up (-1) or down (+1), returning the
 * reordered list. Returns `null` when the move would fall off either end
 * so the caller can no-op without mutating anything.
 */
export function moveBlock(
  order: Block[],
  idx: number,
  dir: -1 | 1,
): Block[] | null {
  const next = order.slice();
  const j = idx + dir;
  if (j < 0 || j >= next.length) return null;
  [next[idx], next[j]] = [next[j], next[idx]];
  return next;
}

/** The id list sent to the reorder endpoint to persist a new order. */
export function orderIds(order: Block[]): number[] {
  return order.map((b) => b.id);
}
