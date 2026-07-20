import AsyncStorage from "@react-native-async-storage/async-storage";
import * as IntentLauncher from "expo-intent-launcher";
import { Alert, Linking, PermissionsAndroid, Platform } from "react-native";

import { type CallAccount, ZioTelephony } from "@/modules/zio-telephony";

/**
 * Place a REAL phone call through the device.
 *
 * On Android we ask for the CALL_PHONE (+ READ_PHONE_STATE for dual-SIM)
 * runtime permissions and place the call natively. On dual-SIM phones the
 * user's SIM preference applies: a remembered SIM, or an in-app chooser
 * ("Always ask"). If native calling is unavailable (permission declined,
 * Expo Go, iOS, web) we fall back to ACTION_CALL / `tel:`.
 *
 * The "call mode" preference lets the user opt out of direct calling and
 * always hand off to the system phone app instead. On iOS direct calling is
 * never allowed by the platform, so the mode is always "system" there.
 */

const SIM_PREF_KEY = "1inme.dialer.simPref.v1";
const CALL_MODE_KEY = "1inme.dialer.callMode.v1";

/** "ask" (default) or the index into getCallAccounts(). */
export type SimPref = "ask" | number;

/**
 * "direct"  — place the call immediately via the native telephony API
 *             (Android only; default on Android).
 * "system"  — hand off to the OS phone app with the number pre-filled
 *             (the only option on iOS; optional on Android).
 */
export type CallMode = "direct" | "system";

export async function getSimPref(): Promise<SimPref> {
  try {
    const raw = await AsyncStorage.getItem(SIM_PREF_KEY);
    if (raw == null || raw === "ask") return "ask";
    const n = parseInt(raw, 10);
    return Number.isInteger(n) && n >= 0 ? n : "ask";
  } catch {
    return "ask";
  }
}

export async function setSimPref(pref: SimPref): Promise<void> {
  try {
    await AsyncStorage.setItem(SIM_PREF_KEY, String(pref));
  } catch {
    /* non-fatal */
  }
}

/**
 * Retrieve the persisted call mode.
 * - iOS always returns "system" (platform does not allow silent dialing).
 * - Android defaults to "direct" when no preference has been saved yet.
 */
export async function getCallMode(): Promise<CallMode> {
  if (Platform.OS !== "android") return "system";
  try {
    const raw = await AsyncStorage.getItem(CALL_MODE_KEY);
    return raw === "system" ? "system" : "direct";
  } catch {
    return "direct";
  }
}

export async function setCallMode(mode: CallMode): Promise<void> {
  try {
    await AsyncStorage.setItem(CALL_MODE_KEY, mode);
  } catch {
    /* non-fatal */
  }
}

/** Call-capable accounts (one per active SIM). [] when unavailable. */
export function getCallAccounts(): CallAccount[] {
  try {
    return ZioTelephony?.getCallAccounts() ?? [];
  } catch {
    return [];
  }
}

async function ensureCallPermissions(): Promise<boolean> {
  try {
    const res = await PermissionsAndroid.requestMultiple([
      PermissionsAndroid.PERMISSIONS.CALL_PHONE,
      PermissionsAndroid.PERMISSIONS.READ_PHONE_STATE,
    ]);
    return res[PermissionsAndroid.PERMISSIONS.CALL_PHONE] === PermissionsAndroid.RESULTS.GRANTED;
  } catch {
    return false;
  }
}

function chooseSim(accounts: CallAccount[]): Promise<number | null> {
  return new Promise((resolve) => {
    Alert.alert(
      "Call using",
      "Pick the SIM for this call. Set a default from the SIM chip on the keypad.",
      [
        ...accounts.slice(0, 2).map((a) => ({
          text: a.label,
          onPress: () => resolve(a.index),
        })),
        { text: "Cancel", style: "cancel" as const, onPress: () => resolve(null) },
      ],
      { cancelable: true, onDismiss: () => resolve(null) },
    );
  });
}

export async function placeRealCall(number: string): Promise<void> {
  const trimmed = number.trim();
  if (!trimmed) return;
  const telUrl = `tel:${encodeURIComponent(trimmed)}`;

  // "system" mode: skip the direct-call path and hand off to the OS dialer.
  // iOS always behaves as "system" — the platform does not allow silent dialing.
  const mode = await getCallMode();

  if (Platform.OS === "android" && mode === "direct") {
    const granted = await ensureCallPermissions();
    if (granted && ZioTelephony) {
      try {
        const accounts = getCallAccounts();
        let accountIndex = -1;
        if (accounts.length >= 2) {
          const pref = await getSimPref();
          if (pref !== "ask" && pref < accounts.length) {
            accountIndex = pref;
          } else {
            const picked = await chooseSim(accounts);
            if (picked == null) return; // user cancelled the SIM chooser
            accountIndex = picked;
          }
        }
        if (ZioTelephony.placeCall(trimmed, accountIndex)) return;
      } catch {
        /* fall through to the intent path */
      }
    }
    if (granted) {
      try {
        await IntentLauncher.startActivityAsync("android.intent.action.CALL", {
          data: telUrl,
        });
        return;
      } catch {
        /* fall through to the system dialer */
      }
    }
  }

  try {
    await Linking.openURL(telUrl);
  } catch {
    Alert.alert("Can't place call", "Calling isn't available on this device.");
  }
}
