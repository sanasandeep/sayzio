import AsyncStorage from "@react-native-async-storage/async-storage";

/**
 * Shared dialer preferences (keypad input mode) + a tiny change bus so the
 * settings moved into the navigation drawer stay in sync with the keypad
 * screen without prop-drilling across the layout boundary.
 *
 * SIM preference and call mode already persist via lib/placeCall.ts; the
 * drawer writes through those setters and then calls notifyDialerPrefsChanged()
 * so the dialer screen re-reads everything.
 */

const KEYPAD_MODE_KEY = "1inme.dialer.keypadMode.v1";

export type KeypadMode = "t9" | "abc";

export async function getKeypadMode(): Promise<KeypadMode> {
  try {
    const raw = await AsyncStorage.getItem(KEYPAD_MODE_KEY);
    return raw === "abc" ? "abc" : "t9";
  } catch {
    return "t9";
  }
}

export async function setKeypadMode(mode: KeypadMode): Promise<void> {
  try {
    await AsyncStorage.setItem(KEYPAD_MODE_KEY, mode);
  } catch {
    /* non-fatal */
  }
}

type Listener = () => void;
const listeners = new Set<Listener>();

export function subscribeDialerPrefs(cb: Listener): () => void {
  listeners.add(cb);
  return () => {
    listeners.delete(cb);
  };
}

export function notifyDialerPrefsChanged(): void {
  for (const cb of listeners) {
    try {
      cb();
    } catch {
      /* listener errors must not break the notifier */
    }
  }
}
