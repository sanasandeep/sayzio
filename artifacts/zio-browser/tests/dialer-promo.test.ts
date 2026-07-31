import { describe, it, expect } from 'vitest';
import { decideDialerPromo } from '../src/renderer/lib/dialer-promo';

/**
 * Promo branching for the Dialer pane's phone-link banners (task #6353):
 * the APK download QR must only show when NO device is linked; a linked
 * device without push gets the "enable notifications" hint instead.
 */
describe('decideDialerPromo', () => {
  it('shows nothing while the status is unknown', () => {
    expect(decideDialerPromo({ deviceLinked: null, pushAvailable: null })).toBeNull();
  });

  it('shows the download promo when no device is linked', () => {
    expect(decideDialerPromo({ deviceLinked: false, pushAvailable: false })).toBe('download');
  });

  it('shows the enable-push hint when linked without push', () => {
    expect(decideDialerPromo({ deviceLinked: true, pushAvailable: false })).toBe('enable-push');
  });

  it('shows nothing when linked with push available', () => {
    expect(decideDialerPromo({ deviceLinked: true, pushAvailable: true })).toBeNull();
  });

  it('treats linked-with-unknown-push as fine (no banner)', () => {
    expect(decideDialerPromo({ deviceLinked: true, pushAvailable: null })).toBeNull();
  });

  it('a failed call with no_dialer_device forces the download promo', () => {
    expect(decideDialerPromo({
      deviceLinked: null,
      pushAvailable: null,
      lastCallErrorCode: 'no_dialer_device',
    })).toBe('download');
  });

  it('a failed call with no_push_token forces the enable-push hint', () => {
    expect(decideDialerPromo({
      deviceLinked: true,
      pushAvailable: null,
      lastCallErrorCode: 'no_push_token',
    })).toBe('enable-push');
    // …even over a stale "not linked" status check.
    expect(decideDialerPromo({
      deviceLinked: false,
      pushAvailable: false,
      lastCallErrorCode: 'no_push_token',
    })).toBe('enable-push');
  });

  it('other call errors fall back to the status check', () => {
    expect(decideDialerPromo({
      deviceLinked: false,
      pushAvailable: false,
      lastCallErrorCode: 'server_error',
    })).toBe('download');
    expect(decideDialerPromo({
      deviceLinked: true,
      pushAvailable: true,
      lastCallErrorCode: 'server_error',
    })).toBeNull();
  });
});
