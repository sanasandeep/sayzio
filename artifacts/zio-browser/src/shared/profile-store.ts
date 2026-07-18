/**
 * Browser profile model and helpers.
 *
 * Each Sayzio workspace maps to an isolated browser profile that scopes:
 *   - SQLite data (history, bookmarks, collections)
 *   - Electron session partition (cookies / web storage)
 *   - Cloud sync records
 *
 * The 'default' profile is the personal profile (no workspace).
 * Workspace profiles use the workspace ID (as a string) as their profile ID.
 */

export const DEFAULT_PROFILE_ID = 'default';

export interface BrowserProfile {
  /** 'default' for personal profile; workspace ID string for workspace profiles */
  id: string;
  /** Null for the personal profile */
  workspaceId: string | null;
  name: string;
  isPersonal: boolean;
}

/**
 * Create the default personal profile descriptor.
 */
export function defaultProfile(): BrowserProfile {
  return {
    id: DEFAULT_PROFILE_ID,
    workspaceId: null,
    name: 'Personal',
    isPersonal: true,
  };
}

/**
 * Build a profile descriptor from a Sayzio workspace API response item.
 */
export function profileFromWorkspace(ws: {
  id: number | string;
  name: string;
  is_personal?: boolean;
}): BrowserProfile {
  const wsId = String(ws.id);
  return {
    id: wsId,
    workspaceId: wsId,
    name: ws.name,
    isPersonal: Boolean(ws.is_personal),
  };
}

/**
 * Return the Electron session partition string for a given profile.
 * The default profile uses `persist:zio-default` so it gets its own
 * durable partition distinct from the app chrome's default session
 * (this avoids cookie bleed into the app chrome).
 */
export function sessionPartitionForProfile(profileId: string): string {
  return `persist:zio-profile-${profileId}`;
}

/**
 * Build the sync_state entity key that isolates sync cursors per profile.
 * e.g. 'bookmarks:default', 'history:ws-42'
 */
export function profileSyncEntityKey(entity: string, profileId: string): string {
  return `${entity}:${profileId}`;
}
