/**
 * Toolbar pins — lets the user promote up to MAX_PINNED_TOOLS low-frequency
 * tools from the "⋯" overflow menu back onto the toolbar. Stored in the
 * SQLite `preferences` table (key `pinned_toolbar_tools`) as a JSON array
 * of tool ids, so the choice persists across restarts.
 */

/** Tools that can be pinned from the overflow menu onto the toolbar. */
export const PINNABLE_TOOLS = ['reading_list', 'dialer', 'device_lab', 'screenshot'] as const;
export type PinnableTool = (typeof PINNABLE_TOOLS)[number];

/** Preference key used with window.zio.prefs. */
export const PINNED_TOOLS_PREF_KEY = 'pinned_toolbar_tools';

/** Maximum number of tools that may be pinned at once. */
export const MAX_PINNED_TOOLS = 2;

/**
 * Renderer-local window event fired whenever the pinned list changes, so
 * surfaces that manage pins (overflow menu, Settings panel) stay in sync
 * without a restart. `detail` is the new PinnableTool[] list.
 */
export const PINNED_TOOLS_CHANGED_EVENT = 'zio:pinned-tools-changed';

/** Display metadata shared by every surface that lists pinnable tools. */
export const PINNABLE_TOOL_INFO: Record<PinnableTool, { label: string; icon: string; description: string }> = {
  reading_list: { label: 'Reading list', icon: '📖', description: 'Save pages to read later' },
  dialer: { label: 'Dialer', icon: '📞', description: 'Search & call on your phone' },
  device_lab: { label: 'Device Lab', icon: '🔬', description: 'Phone / tablet / desktop preview' },
  screenshot: { label: 'Screenshot', icon: '📷', description: 'Capture the visible area' },
};

export function isPinnableTool(v: unknown): v is PinnableTool {
  return typeof v === 'string' && (PINNABLE_TOOLS as readonly string[]).includes(v);
}

/**
 * Parse the stored preference value. Unknown ids are dropped, duplicates are
 * removed, and the result is capped at MAX_PINNED_TOOLS. Any malformed value
 * yields an empty list (never throws).
 */
export function parsePinnedTools(raw: string | null | undefined): PinnableTool[] {
  if (!raw) return [];
  try {
    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];
    const out: PinnableTool[] = [];
    for (const item of parsed) {
      if (isPinnableTool(item) && !out.includes(item)) {
        out.push(item);
        if (out.length >= MAX_PINNED_TOOLS) break;
      }
    }
    return out;
  } catch {
    return [];
  }
}

/** Serialize a pinned-tools list for storage. */
export function serializePinnedTools(tools: PinnableTool[]): string {
  return JSON.stringify(tools.slice(0, MAX_PINNED_TOOLS));
}

/**
 * Toggle a tool's pinned state. Unpinning always succeeds; pinning is a
 * no-op when the cap is already reached (returns the list unchanged).
 */
export function togglePinnedTool(current: PinnableTool[], tool: PinnableTool): PinnableTool[] {
  if (current.includes(tool)) return current.filter(t => t !== tool);
  if (current.length >= MAX_PINNED_TOOLS) return current;
  return [...current, tool];
}
