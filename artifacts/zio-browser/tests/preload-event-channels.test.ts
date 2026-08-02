/**
 * Guard: every event channel a renderer component subscribes to via
 * window.zio.on('channel', ...) must be present in the preload's
 * ALLOWED_CHANNELS allow-list (src/preload/index.ts).
 *
 * The preload's on() silently ignores subscriptions to channels that are
 * not allow-listed, so a typo (or a forgotten allow-list entry) means the
 * real app never receives the event — while tests using the shared mock's
 * emit() still pass. This test parses the allow-list from the preload
 * source and scans src/renderer for every literal zio.on(...) channel.
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { describe, expect, it } from 'vitest';

const ROOT = join(__dirname, '..');
const PRELOAD_PATH = join(ROOT, 'src', 'preload', 'index.ts');
const RENDERER_DIR = join(ROOT, 'src', 'renderer');

/** Extract the ALLOWED_CHANNELS Set literal entries from the preload source. */
function extractAllowedChannels(): Set<string> {
  const src = readFileSync(PRELOAD_PATH, 'utf8');
  const match = src.match(/ALLOWED_CHANNELS\s*=\s*new Set\(\s*\[([\s\S]*?)\]\s*\)/);
  if (!match) {
    throw new Error(
      'Could not find `ALLOWED_CHANNELS = new Set([...])` in src/preload/index.ts — ' +
        'if the allow-list moved or was renamed, update tests/preload-event-channels.test.ts.',
    );
  }
  const channels = new Set<string>();
  // Strip line comments first — quotes/apostrophes inside them de-sync the
  // string-literal matcher (e.g. a comment containing "isn't").
  const body = match[1].replace(/\/\/[^\n]*/g, '');
  for (const entry of body.matchAll(/['"`]([^'"`]+)['"`]/g)) {
    channels.add(entry[1]);
  }
  if (channels.size === 0) {
    throw new Error('Parsed an empty ALLOWED_CHANNELS list from src/preload/index.ts.');
  }
  return channels;
}

function listSourceFiles(dir: string): string[] {
  const out: string[] = [];
  for (const name of readdirSync(dir)) {
    const full = join(dir, name);
    if (statSync(full).isDirectory()) {
      out.push(...listSourceFiles(full));
    } else if (/\.(ts|tsx)$/.test(name) && !/\.d\.ts$/.test(name)) {
      out.push(full);
    }
  }
  return out;
}

interface Subscription {
  channel: string;
  file: string;
  line: number;
}

/** Find every literal channel passed to zio.on('...') in renderer sources. */
function collectRendererSubscriptions(): Subscription[] {
  const subs: Subscription[] = [];
  for (const file of listSourceFiles(RENDERER_DIR)) {
    const src = readFileSync(file, 'utf8');
    for (const match of src.matchAll(/\bzio\.on\(\s*(['"`])([^'"`]+)\1/g)) {
      const line = src.slice(0, match.index).split('\n').length;
      subs.push({ channel: match[2], file: relative(ROOT, file), line });
    }
  }
  return subs;
}

describe('renderer zio.on() channels are allow-listed in the preload', () => {
  it('finds renderer subscriptions to scan', () => {
    expect(collectRendererSubscriptions().length).toBeGreaterThan(0);
  });

  it('every subscribed channel is in ALLOWED_CHANNELS', () => {
    const allowed = extractAllowedChannels();
    const offenders = collectRendererSubscriptions()
      .filter(s => !allowed.has(s.channel))
      .map(s => `'${s.channel}' at ${s.file}:${s.line}`)
      .sort();

    expect(
      offenders,
      `Renderer code subscribes to event channels that the preload's on() will ` +
        `silently drop. Add each channel to ALLOWED_CHANNELS in src/preload/index.ts ` +
        `(or fix the typo in the subscription):\n  - ${offenders.join('\n  - ')}`,
    ).toEqual([]);
  });
});
