/**
 * Smart link collection model for Zio Browser.
 *
 * Collections are local-first (SQLite) with optional cloud sync.
 * This module defines the data model and pure operations on collections.
 * The actual SQLite I/O is in src/main/db.ts.
 */

export interface Collection {
  id: string;        // UUID
  name: string;
  description: string | null;
  color: string | null;    // hex color for the collection card
  icon: string | null;     // emoji or icon name
  created_at: string;
  updated_at: string;
  deleted: boolean;
  synced_at: string | null;
  item_count?: number;
}

export interface SavedLink {
  id: string;              // UUID
  collection_id: string;
  url: string;
  title: string;
  description: string | null;
  /** AI-generated summary of the page */
  ai_summary: string | null;
  /** AI-generated tags */
  ai_tags: string[];
  /** AI-generated business context (why this page is relevant) */
  ai_context: string | null;
  /** User notes */
  notes: string | null;
  favicon_url: string | null;
  screenshot_url: string | null;
  /** Normalized URL for deduplication */
  normalized_url: string;
  saved_at: string;
  updated_at: string;
  deleted: boolean;
  synced_at: string | null;
  /** Whether AI enrichment has been requested/completed */
  ai_enriched: boolean;
  /** Coins charged for AI enrichment */
  ai_coins_used: number | null;
}

export interface CollectionSearchResult {
  collections: Collection[];
  links: SavedLink[];
}

/**
 * Generate a new UUID (pure function for testability).
 * In production, use crypto.randomUUID().
 */
export function generateId(random?: () => number): string {
  const r = random ?? Math.random;
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
    const v = c === 'x' ? Math.floor(r() * 16) : (Math.floor(r() * 4) + 8);
    return v.toString(16);
  });
}

/**
 * Create a new collection object (in-memory — caller persists to SQLite).
 */
export function createCollection(
  name: string,
  options: Partial<Omit<Collection, 'id' | 'name' | 'created_at' | 'updated_at' | 'deleted' | 'synced_at'>> = {},
): Collection {
  const now = new Date().toISOString();
  return {
    id: generateId(),
    name,
    description: options.description ?? null,
    color: options.color ?? null,
    icon: options.icon ?? null,
    created_at: now,
    updated_at: now,
    deleted: false,
    synced_at: null,
  };
}

/**
 * Create a new saved link object (in-memory — caller persists to SQLite).
 */
export function createSavedLink(
  collectionId: string,
  url: string,
  title: string,
  options: Partial<Omit<SavedLink, 'id' | 'collection_id' | 'url' | 'title' | 'saved_at' | 'updated_at' | 'deleted' | 'synced_at' | 'ai_enriched'>> = {},
): SavedLink {
  const now = new Date().toISOString();
  return {
    id: generateId(),
    collection_id: collectionId,
    url,
    title,
    description: options.description ?? null,
    ai_summary: null,
    ai_tags: [],
    ai_context: null,
    notes: options.notes ?? null,
    favicon_url: options.favicon_url ?? null,
    screenshot_url: null,
    normalized_url: normalizeCollectionUrl(url),
    saved_at: now,
    updated_at: now,
    deleted: false,
    synced_at: null,
    ai_enriched: false,
    ai_coins_used: null,
  };
}

/**
 * Normalize a URL for collection deduplication.
 * Strips fragment, normalizes trailing slashes, lowercases scheme/host.
 */
export function normalizeCollectionUrl(url: string): string {
  try {
    const u = new URL(url);
    u.hash = '';
    const normalized = u.toString().replace(/\/$/, '');
    return normalized;
  } catch {
    return url.toLowerCase();
  }
}

/**
 * Search collections and links by keyword.
 * Pure function — accepts all collections and links, returns matches.
 */
export function searchCollections(
  allCollections: Collection[],
  allLinks: SavedLink[],
  query: string,
): CollectionSearchResult {
  const q = query.toLowerCase().trim();
  if (!q) {
    return { collections: allCollections.filter(c => !c.deleted), links: [] };
  }

  const collections = allCollections.filter(c =>
    !c.deleted && (
      c.name.toLowerCase().includes(q) ||
      c.description?.toLowerCase().includes(q)
    ),
  );

  const links = allLinks.filter(l =>
    !l.deleted && (
      l.title.toLowerCase().includes(q) ||
      l.url.toLowerCase().includes(q) ||
      l.description?.toLowerCase().includes(q) ||
      l.ai_summary?.toLowerCase().includes(q) ||
      l.ai_tags.some(t => t.toLowerCase().includes(q)) ||
      l.notes?.toLowerCase().includes(q)
    ),
  );

  return { collections, links };
}

/**
 * Apply AI enrichment data to a saved link.
 */
export function applyAiEnrichment(
  link: SavedLink,
  enrichment: {
    summary: string;
    tags: string[];
    context: string;
    coins_used: number;
  },
): SavedLink {
  return {
    ...link,
    ai_summary: enrichment.summary,
    ai_tags: enrichment.tags,
    ai_context: enrichment.context,
    ai_enriched: true,
    ai_coins_used: enrichment.coins_used,
    updated_at: new Date().toISOString(),
  };
}

/**
 * Sort collections by recent activity (links saved_at).
 */
export function sortCollectionsByActivity(
  collections: Collection[],
  links: SavedLink[],
): Collection[] {
  const latestLinkDate = new Map<string, number>();
  for (const link of links) {
    if (link.deleted) continue;
    const existing = latestLinkDate.get(link.collection_id) ?? 0;
    const ts = new Date(link.saved_at).getTime();
    if (ts > existing) {
      latestLinkDate.set(link.collection_id, ts);
    }
  }

  return [...collections].sort((a, b) => {
    const aDate = latestLinkDate.get(a.id) ?? new Date(a.created_at).getTime();
    const bDate = latestLinkDate.get(b.id) ?? new Date(b.created_at).getTime();
    return bDate - aDate;
  });
}
