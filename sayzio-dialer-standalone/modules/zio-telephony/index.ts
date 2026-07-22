import { requireNativeModule } from "expo";
import { Platform } from "react-native";

/**
 * JS wrapper for the local `zio-telephony` Android native module.
 * On web / iOS / Expo Go (where the module isn't compiled into the binary)
 * `ZioTelephony` is `null`, so callers must feature-detect before use.
 */

export type CallLogEntry = {
  number: string;
  /** android.provider.CallLog.Calls type: 1 incoming, 2 outgoing, 3 missed, 5 rejected, 6 blocked */
  type: number;
  /** epoch millis */
  date: number;
  /** seconds */
  duration: number;
  name: string | null;
};

export type CallAccount = {
  index: number;
  label: string;
  id: string;
};

export type ZioTelephonyModule = {
  getCallLog(limit: number): CallLogEntry[];
  getCallAccounts(): CallAccount[];
  placeCall(number: string, accountIndex: number): boolean;
  isPackageInstalled(pkg: string): boolean;
  openUrlWithPackage(pkg: string, url: string): boolean;

  // ── Incoming-call caller-ID alert (Truecaller-style overlay) ────────────
  /** Device supports the alert (Android 10+ with the call-screening role available). */
  isCallerIdAlertSupported(): boolean;
  /** "Display over other apps" (SYSTEM_ALERT_WINDOW) granted? */
  hasOverlayPermission(): boolean;
  /** Open the system settings page for the overlay permission. */
  openOverlayPermissionSettings(): boolean;
  /** Are we currently the device's caller ID & spam app? */
  hasCallScreeningRole(): boolean;
  /** System prompt to become the caller ID & spam app; resolves true if granted. */
  requestCallScreeningRole(): Promise<boolean>;
  /** User's on/off toggle, persisted natively for the screening service. */
  isCallerIdAlertEnabled(): boolean;
  setCallerIdAlertEnabled(enabled: boolean): void;
  /** Push the synced Sayzio contacts (JSON array of {n,name,photo?,org?}) for offline lookup. */
  setCallerDirectory(json: string): boolean;
  /** Queued incoming calls: JSON array of {n,name?,org?,ts} appended by the screening service (name absent for unknown numbers). */
  getIdentifiedCallQueue(): string;
  /** Drop the first `count` queued incoming-call events after a successful sync. */
  clearIdentifiedCallQueue(count: number): boolean;

  /** Numbers reported as spam from the overlay, awaiting a POST /dialer/flag. */
  getPendingSpamReports(): string[];
  /** Remove one number from the pending queue after a successful server POST. */
  removePendingSpamReport(number: string): boolean;
  /** Preview the floating card with a given number. */
  showTestCallerIdAlert(number: string): boolean;
  dismissCallerIdAlert(): void;
};

let native: ZioTelephonyModule | null = null;
if (Platform.OS === "android") {
  try {
    native = requireNativeModule<ZioTelephonyModule>("ZioTelephony");
  } catch {
    native = null; // Expo Go / dev web — native features quietly unavailable
  }
}

export const ZioTelephony = native;
