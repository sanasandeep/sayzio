import { useMutation } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useEffect } from "react";
import { ActivityIndicator, Linking, Text, View } from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { startUnlockPost } from "@/lib/api/monetization";
import { showAlert } from "@/lib/webAlert";

/**
 * Stub screen that immediately fires the per-post unlock checkout.
 * It exists so deep-links from the locked post variant on the
 * Creator Profile screen can route through it: tap Unlock →
 * /monetization/unlock?handle=…&postId=… → hosted checkout in browser.
 */
export default function UnlockScreen() {
  const colors = useColors();
  const router = useRouter();
  const { handle = "", postId = "" } = useLocalSearchParams<{
    handle?: string;
    postId?: string;
  }>();

  const unlock = useMutation({
    mutationFn: () => startUnlockPost(handle, Number(postId)),
    onSuccess: async (r) => {
      if (r.checkout_url) {
        try {
          await WebBrowser.openBrowserAsync(r.checkout_url);
        } catch {
          Linking.openURL(r.checkout_url);
        }
      } else if (r.already) {
        showAlert("Already unlocked", "You already own this post — enjoy!");
      } else {
        showAlert("Unlocked", "Enjoy the post!");
      }
      router.back();
    },
    onError: (e: Error) => {
      showAlert("Couldn't unlock", e.message || "Try again");
      router.back();
    },
  });

  useEffect(() => {
    if (handle && postId) unlock.mutate();
  }, [handle, postId]);

  return (
    <View
      style={{
        flex: 1,
        backgroundColor: colors.background,
        alignItems: "center",
        justifyContent: "center",
        padding: 24,
        gap: 12,
      }}
    >
      <Stack.Screen options={{ title: "Unlocking…" }} />
      <ActivityIndicator color={colors.primary} />
      <Text style={{ color: colors.mutedForeground, textAlign: "center" }}>
        Opening checkout in your browser…
      </Text>
      <Button label="Cancel" variant="ghost" onPress={() => router.back()} />
    </View>
  );
}
