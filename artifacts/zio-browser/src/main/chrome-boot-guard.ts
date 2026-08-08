/**
 * Chrome boot guard — detects launches where the browser's own UI (the
 * chrome renderer) never painted, and self-heals on the next launch.
 *
 * The most common cause of a permanently-white main window on macOS is GPU
 * compositing failure: the renderer runs fine but nothing ever reaches the
 * screen. Electron's fix is app.disableHardwareAcceleration(), which MUST be
 * called before app 'ready'. The preferences DB isn't open that early, so
 * this guard keeps a tiny JSON marker file in userData instead.
 *
 * Protocol:
 *  - initChromeBootGuard() runs at process start (before whenReady):
 *    reads the marker; if the previous launch never painted the chrome
 *    (pending flag still set), permanently switches to software rendering
 *    and persists that choice. Then re-arms the pending flag for this run.
 *  - markChromePainted() runs on the main window's first did-finish-load:
 *    clears the pending flag (this launch painted fine).
 */
import { app } from 'electron';
import fs from 'node:fs';
import path from 'node:path';

interface BootGuardState {
  /** consecutive launches that never painted the chrome UI */
  pendingCount?: number;
  /** once true, hardware acceleration stays off for every future launch */
  gpuDisabled?: boolean;
}

/**
 * How many consecutive unpainted launches before GPU fallback kicks in.
 * Two, not one: a single stale marker can also come from an ordinary quick
 * quit while the window was still loading — that must never permanently
 * degrade rendering. A genuinely GPU-broken machine fails every launch, so
 * it crosses this threshold on the second try.
 */
const FAILED_BOOTS_BEFORE_GPU_FALLBACK = 2;

function markerPath(): string {
  return path.join(app.getPath('userData'), 'chrome-boot-guard.json');
}

function readState(): BootGuardState {
  try {
    return JSON.parse(fs.readFileSync(markerPath(), 'utf8')) as BootGuardState;
  } catch {
    return {};
  }
}

function writeState(state: BootGuardState): void {
  try {
    fs.mkdirSync(path.dirname(markerPath()), { recursive: true });
    fs.writeFileSync(markerPath(), JSON.stringify(state));
  } catch { /* best-effort — never block startup on the marker */ }
}

/** Call BEFORE app.whenReady(). Returns true if GPU was disabled. */
export function initChromeBootGuard(): boolean {
  const state = readState();
  const pendingCount = state.pendingCount ?? 0;
  // Multiple consecutive launches never painted the chrome UI → assume GPU
  // compositing is broken on this machine and fall back to software
  // rendering from now on.
  const shouldDisableGpu =
    Boolean(state.gpuDisabled) || pendingCount >= FAILED_BOOTS_BEFORE_GPU_FALLBACK;
  if (shouldDisableGpu) {
    try { app.disableHardwareAcceleration(); } catch { /* already ready — too late this run */ }
  }
  writeState({ pendingCount: pendingCount + 1, gpuDisabled: shouldDisableGpu });
  return shouldDisableGpu;
}

/** Call when the main window's chrome renderer has visibly come up. */
export function markChromePainted(): void {
  const state = readState();
  writeState({ ...state, pendingCount: 0 });
}
