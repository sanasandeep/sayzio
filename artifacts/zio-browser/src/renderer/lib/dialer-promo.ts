/**
 * Decide which (if any) helper banner the Dialer pane shows about the
 * phone link, from the handoff status check and the last call attempt.
 *
 *  - 'download'      — no Zio Dialer install is linked to the account:
 *                      show the APK download QR promo.
 *  - 'enable-push'   — a phone is linked but can't receive click-to-call
 *                      pushes (notifications denied / no Expo token):
 *                      show a gentle "enable notifications" hint instead.
 *  - null            — a phone is linked and reachable, or we simply
 *                      don't know yet (status check pending/failed and no
 *                      failed call told us otherwise).
 *
 * A failed call attempt is authoritative over a stale status check: the
 * server's `no_dialer_device` / `no_push_token` error codes map to the
 * same two banners.
 */
export type DialerPromo = 'download' | 'enable-push' | null;

export interface DialerLinkState {
  /** From GET /dialer/handoff/status; null while unknown. */
  deviceLinked: boolean | null;
  /** From GET /dialer/handoff/status; null while unknown. */
  pushAvailable: boolean | null;
  /** Error code of the last failed call attempt, if any. */
  lastCallErrorCode?: string | null;
}

export function decideDialerPromo(state: DialerLinkState): DialerPromo {
  // A failed call is the freshest signal we have.
  if (state.lastCallErrorCode === 'no_dialer_device') return 'download';
  if (state.lastCallErrorCode === 'no_push_token') return 'enable-push';

  if (state.deviceLinked === false) return 'download';
  if (state.deviceLinked === true && state.pushAvailable === false) {
    return 'enable-push';
  }
  return null;
}
