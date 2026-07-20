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
