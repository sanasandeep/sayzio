import Constants from "expo-constants";
import { router } from "expo-router";
import { useEffect } from "react";
import { useShareIntent } from "expo-share-intent";

// Bridges the native iOS/Android share sheet (expo-share-intent) into the
// in-app "Import from URL" picker. When the user taps "Share → Sayzio" in
// Safari/Chrome (or any app sharing a URL/text), the native module stores
// the payload and relaunches the app with sayzio://dataUrl=<key>;
// +native-intent routes that to /import-url and this component reads the
// shared URL + page title from the module and fills in the params.
//
// The native module only exists in dev/production builds — in Expo Go the
// hook is disabled so nothing crashes (shares simply aren't available).

const IN_EXPO_GO = Constants.appOwnership === "expo";

function firstUrlIn(text: string): string | null {
  const match = text.match(/https?:\/\/[^\s"'<>]+/i);
  return match ? match[0] : null;
}

export function ShareIntentHandler() {
  const { hasShareIntent, shareIntent, resetShareIntent } = useShareIntent({
    disabled: IN_EXPO_GO,
    resetOnBackground: true,
  });

  useEffect(() => {
    if (!hasShareIntent) return;
    const text = shareIntent.text ?? "";
    const url = shareIntent.webUrl ?? firstUrlIn(text);
    if (url) {
      const title = shareIntent.meta?.title ?? "";
      router.push({
        pathname: "/import-url",
        params: title ? { url, title } : { url },
      });
    }
    resetShareIntent();
  }, [hasShareIntent, shareIntent, resetShareIntent]);

  return null;
}
