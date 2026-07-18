import { describe, it, expect } from 'vitest';
import {
  createCollection,
  createSavedLink,
  normalizeCollectionUrl,
  searchCollections,
  applyAiEnrichment,
  sortCollectionsByActivity,
  generateId,
  type Collection,
  type SavedLink,
} from '../src/shared/collection-store';

describe('generateId', () => {
  it('generates a valid UUID-like string', () => {
    const id = generateId();
    expect(id).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/);
  });

  it('generates unique IDs', () => {
    const ids = new Set(Array.from({ length: 100 }, () => generateId()));
    expect(ids.size).toBe(100);
  });
});

describe('createCollection', () => {
  it('creates a collection with required fields', () => {
    const col = createCollection('My Links');
    expect(col.name).toBe('My Links');
    expect(col.id).toBeTruthy();
    expect(col.deleted).toBe(false);
    expect(col.synced_at).toBeNull();
    expect(col.created_at).toBeTruthy();
  });

  it('applies optional fields', () => {
    const col = createCollection('Research', { color: '#ff0000', icon: '🔬', description: 'Research links' });
    expect(col.color).toBe('#ff0000');
    expect(col.icon).toBe('🔬');
    expect(col.description).toBe('Research links');
  });
});

describe('createSavedLink', () => {
  it('creates a link with required fields', () => {
    const link = createSavedLink('col-1', 'https://example.com', 'Example');
    expect(link.collection_id).toBe('col-1');
    expect(link.url).toBe('https://example.com');
    expect(link.title).toBe('Example');
    expect(link.ai_enriched).toBe(false);
    expect(link.ai_tags).toEqual([]);
    expect(link.deleted).toBe(false);
  });

  it('normalizes the URL', () => {
    const link = createSavedLink('col-1', 'https://example.com/#hash', 'Test');
    expect(link.normalized_url).toBe('https://example.com');
  });
});

describe('normalizeCollectionUrl', () => {
  it('removes hash fragment', () => {
    expect(normalizeCollectionUrl('https://example.com/page#section')).toBe('https://example.com/page');
  });

  it('removes trailing slash from root', () => {
    expect(normalizeCollectionUrl('https://example.com/')).toBe('https://example.com');
  });

  it('preserves paths without trailing slash', () => {
    expect(normalizeCollectionUrl('https://example.com/path/to/page')).toBe('https://example.com/path/to/page');
  });

  it('handles invalid URL gracefully', () => {
    expect(normalizeCollectionUrl('not-a-url')).toBe('not-a-url');
  });
});

describe('searchCollections', () => {
  const collections: Collection[] = [
    { id: 'c1', name: 'Research', description: 'Science links', color: null, icon: null, created_at: '2025-01-01T00:00:00Z', updated_at: '2025-01-01T00:00:00Z', deleted: false, synced_at: null },
    { id: 'c2', name: 'Shopping', description: 'Products to buy', color: null, icon: null, created_at: '2025-01-01T00:00:00Z', updated_at: '2025-01-01T00:00:00Z', deleted: false, synced_at: null },
    { id: 'c3', name: 'Deleted', description: null, color: null, icon: null, created_at: '2025-01-01T00:00:00Z', updated_at: '2025-01-01T00:00:00Z', deleted: true, synced_at: null },
  ];
  const links: SavedLink[] = [
    { id: 'l1', collection_id: 'c1', url: 'https://nature.com', normalized_url: 'https://nature.com', title: 'Nature Journal', description: 'Science publication', ai_summary: null, ai_tags: ['science'], ai_context: null, notes: null, favicon_url: null, screenshot_url: null, saved_at: '2025-01-01T00:00:00Z', updated_at: '2025-01-01T00:00:00Z', deleted: false, synced_at: null, ai_enriched: false, ai_coins_used: null },
    { id: 'l2', collection_id: 'c2', url: 'https://amazon.com', normalized_url: 'https://amazon.com', title: 'Amazon', description: null, ai_summary: 'Online shopping', ai_tags: ['shopping', 'ecommerce'], ai_context: null, notes: null, favicon_url: null, screenshot_url: null, saved_at: '2025-01-02T00:00:00Z', updated_at: '2025-01-02T00:00:00Z', deleted: false, synced_at: null, ai_enriched: true, ai_coins_used: 2 },
  ];

  it('returns all non-deleted collections for empty query', () => {
    const result = searchCollections(collections, links, '');
    expect(result.collections).toHaveLength(2);
    expect(result.collections.find(c => c.name === 'Deleted')).toBeUndefined();
  });

  it('matches collection by name', () => {
    const result = searchCollections(collections, links, 'research');
    expect(result.collections).toHaveLength(1);
    expect(result.collections[0]?.name).toBe('Research');
  });

  it('matches links by title', () => {
    const result = searchCollections(collections, links, 'nature');
    expect(result.links).toHaveLength(1);
    expect(result.links[0]?.title).toBe('Nature Journal');
  });

  it('matches links by AI tags', () => {
    const result = searchCollections(collections, links, 'ecommerce');
    expect(result.links).toHaveLength(1);
    expect(result.links[0]?.id).toBe('l2');
  });

  it('matches links by AI summary', () => {
    const result = searchCollections(collections, links, 'online shopping');
    expect(result.links).toHaveLength(1);
  });

  it('does not include deleted links', () => {
    const deletedLinks: SavedLink[] = [{ ...links[0]!, deleted: true }];
    const result = searchCollections(collections, deletedLinks, 'nature');
    expect(result.links).toHaveLength(0);
  });
});

describe('applyAiEnrichment', () => {
  it('applies enrichment data and sets ai_enriched = true', () => {
    // Use a fixed past timestamp so applyAiEnrichment's new Date() is always later.
    const link = createSavedLink('col-1', 'https://example.com', 'Test');
    const fixedPast = '2020-01-01T00:00:00.000Z';
    const linkWithPastDate = { ...link, updated_at: fixedPast };
    const enriched = applyAiEnrichment(linkWithPastDate, {
      summary: 'A summary',
      tags: ['tag1', 'tag2'],
      context: 'Business context',
      coins_used: 3,
    });
    expect(enriched.ai_enriched).toBe(true);
    expect(enriched.ai_summary).toBe('A summary');
    expect(enriched.ai_tags).toEqual(['tag1', 'tag2']);
    expect(enriched.ai_context).toBe('Business context');
    expect(enriched.ai_coins_used).toBe(3);
    expect(enriched.updated_at).not.toBe(fixedPast);
  });

  it('does not mutate the original link', () => {
    const link = createSavedLink('col-1', 'https://example.com', 'Test');
    const enriched = applyAiEnrichment(link, { summary: 'x', tags: [], context: 'y', coins_used: 1 });
    expect(link.ai_enriched).toBe(false);
    expect(enriched).not.toBe(link);
  });
});

describe('sortCollectionsByActivity', () => {
  it('sorts by most recently saved link', () => {
    const collections: Collection[] = [
      { id: 'old', name: 'Old', description: null, color: null, icon: null, created_at: '2025-01-01T00:00:00Z', updated_at: '2025-01-01T00:00:00Z', deleted: false, synced_at: null },
      { id: 'new', name: 'New', description: null, color: null, icon: null, created_at: '2025-01-01T00:00:00Z', updated_at: '2025-01-01T00:00:00Z', deleted: false, synced_at: null },
    ];
    const links: SavedLink[] = [
      { id: 'l1', collection_id: 'old', url: 'https://a.com', normalized_url: 'https://a.com', title: 'A', description: null, ai_summary: null, ai_tags: [], ai_context: null, notes: null, favicon_url: null, screenshot_url: null, saved_at: '2025-01-01T00:00:00Z', updated_at: '2025-01-01T00:00:00Z', deleted: false, synced_at: null, ai_enriched: false, ai_coins_used: null },
      { id: 'l2', collection_id: 'new', url: 'https://b.com', normalized_url: 'https://b.com', title: 'B', description: null, ai_summary: null, ai_tags: [], ai_context: null, notes: null, favicon_url: null, screenshot_url: null, saved_at: '2025-01-03T00:00:00Z', updated_at: '2025-01-03T00:00:00Z', deleted: false, synced_at: null, ai_enriched: false, ai_coins_used: null },
    ];
    const sorted = sortCollectionsByActivity(collections, links);
    expect(sorted[0]?.id).toBe('new');
    expect(sorted[1]?.id).toBe('old');
  });
});
