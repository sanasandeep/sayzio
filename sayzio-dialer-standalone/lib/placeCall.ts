import * as IntentLauncher from "expo-intent-launcher";
import { Alert, Linking, PermissionsAndroid, Platform } from "react-native";

/**
 * Place a REAL phone call through the device.
 *
 * On Android we ask for the CALL_PHONE runtime permission (declared in
 * app.json) and fire ACTION_CALL directly, so the call starts immediately
 * in the system's in-call UI. If the permission is declined — or on any
 * other platform — we fall back to `tel:`, which opens the system dialer
 * pre-filled so the user confirms with one tap.
 */
export async function placeRealCall(number: string): Promise<void> {
  const trimmed = number.trim();
  if (!trimmed) return;
  const telUrl = `tel:${encodeURIComponent(trimmed)}`;

  if (Platform.OS === "android") {
    try {
      const granted = await PermissionsAndroid.request(
        PermissionsAndroid.PERMISSIONS.CALL_PHONE,
      );
      if (granted === PermissionsAndroid.RESULTS.GRANTED) {
        await IntentLauncher.startActivityAsync("android.intent.action.CALL", {
          data: telUrl,
        });
        return;
      }
    } catch {
      /* fall through to the system dialer */
    }
  }

  try {
    await Linking.openURL(telUrl);
  } catch {
    Alert.alert(
      "Can't place call",
      "Calling isn't available on this device.",
    );
  }
}
