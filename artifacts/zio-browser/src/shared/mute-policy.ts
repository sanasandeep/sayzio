/**
 * Per-domain mute memory + global audio policy helpers.
 *
 * The muted-domain list is stored as a JSON array of lowercase hostnames in
 * the SQLite `preferences` table (key `muted_domains`). The "mute all tabs"
 * session-level policy is stored under `mute_all_tabs` ('1' / '0').
 *
 * Kept in shared/ so tests can exercise the parsing/serialization logic
 * without importing better-sqlite3.
 */

/**
 * Extract the hostname used as the mute-memory key for a URL.
 * Only http(s) pages participate in mute memory; anything else returns null.
 */
export function hostForMutePolicy(url: string | null | undefined): string | null {
  if (!url) return null;
  try {
    const u = new URL(url);
    if (u.protocol !== 'http:' && u.protocol !== 'https:') return null;
    const host = u.hostname.toLowerCase();
    return host.length > 0 ? host : null;
  } catch {
    return null;
  }
}

/** Parse the stored JSON list of muted hosts. Invalid input yields []. */
export function parseMutedDomains(json: string | null | undefined): string[] {
  if (!json) return [];
  try {
    const parsed = JSON.parse(json) as unknown;
    if (!Array.isArray(parsed)) return [];
    const out: string[] = [];
    for (const item of parsed) {
      if (typeof item === 'string' && item.length > 0 && !out.includes(item.toLowerCase())) {
        out.push(item.toLowerCase());
      }
    }
    return out;
  } catch {
    return [];
  }
}

export function serializeMutedDomains(hosts: string[]): string {
  return JSON.stringify(hosts);
}

/** Return a new list with the host added (deduplicated, lowercase). */
export function addMutedDomain(list: string[], host: string): string[] {
  const h = host.toLowerCase();
  return list.includes(h) ? [...list] : [...list, h];
}

/** Return a new list with the host removed. */
export function removeMutedDomain(list: string[], host: string): string[] {
  const h = host.toLowerCase();
  return list.filter(item => item !== h);
}

export function isDomainInMuteList(list: string[], host: string): boolean {
  return list.includes(host.toLowerCase());
}
