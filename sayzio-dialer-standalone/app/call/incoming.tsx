import { Feather } from "@expo/vector-icons";
import * as Haptics from "expo-haptics";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { ChannelActions } from "@/components/ChannelActions";
import { useColors } from "@/hooks/useColors";

export default function IncomingCallScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  // `from` is the canonical key used by push payloads (matches the
  // universal-link contract `…/call/incoming?from=…`); `number` is
  // accepted as a friendly alias for in-app navigation.
  const params = useLocalSearchParams<{
    from?: string;
    number?: string;
    name?: string;
  }>();
  const number = String(params.from ?? params.number ?? "").trim();
  const name =
    typeof params.name === "string" && params.name.trim() !== ""
      ? params.name.trim()
      : null;

  const decline = () => {
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
    if (router.canGoBack()) router.back();
    else router.replace("/dialer");
  };

  const accept = () => {
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    // Replace so back-press from the active call doesn't bounce back to
    // the incoming screen — a finished call should land on the dialer.
    router.replace({
      pathname: "/call/active",
      params: { number, ...(name ? { name } : {}) },
    });
  };

  return (
    <View
      style={[
        styles.root,
        { backgroundColor: colors.background, paddingTop: insets.top + 24 },
      ]}
    >
      <Stack.Screen
        options={{ headerShown: false, gestureEnabled: false }}
      />

      <View style={styles.header}>
        <Text style={[styles.status, { color: colors.mutedForeground }]}>
          Incoming call
        </Text>
        <Text
          style={[styles.name, { color: colors.foreground }]}
          numberOfLines={1}
        >
          {name || number || "Unknown caller"}
        </Text>
        {name && number ? (
          <Text style={[styles.number, { color: colors.mutedForeground }]}>
            {number}
          </Text>
        ) : null}
        {number ? (
          <View style={{ marginTop: 12 }}>
            <ChannelActions number={number} size="md" />
          </View>
        ) : null}
      </View>

      <View
        style={[
          styles.avatar,
          { backgroundColor: colors.card, borderColor: colors.border },
        ]}
      >
        <Feather name="user" size={84} color={colors.mutedForeground} />
      </View>

      <View
        style={[styles.actions, { paddingBottom: insets.bottom + 24 }]}
      >
        <View style={styles.action}>
          <Pressable
            onPress={decline}
            style={({ pressed }) => [
              styles.btn,
              {
                backgroundColor: colors.destructive,
                opacity: pressed ? 0.8 : 1,
              },
            ]}
          >
            <Feather name="phone-off" size={28} color="#fff" />
          </Pressable>
          <Text style={[styles.actionLabel, { color: colors.mutedForeground }]}>
            Decline
          </Text>
        </View>
        <View style={styles.action}>
          <Pressable
            onPress={accept}
            style={({ pressed }) => [
              styles.btn,
              {
                backgroundColor: "#16a34a",
                opacity: pressed ? 0.8 : 1,
              },
            ]}
          >
            <Feather name="phone" size={28} color="#fff" />
          </Pressable>
          <Text style={[styles.actionLabel, { color: colors.mutedForeground }]}>
            Accept
          </Text>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, alignItems: "center", justifyContent: "space-between" },
  header: { alignItems: "center", gap: 6, paddingHorizontal: 24 },
  status: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
    textTransform: "uppercase",
    letterSpacing: 1,
  },
  name: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 28,
    textAlign: "center",
  },
  number: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 16 },
  avatar: {
    width: 160,
    height: 160,
    borderRadius: 999,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  actions: {
    flexDirection: "row",
    justifyContent: "space-around",
    width: "100%",
    maxWidth: 420,
    paddingHorizontal: 24,
  },
  action: { alignItems: "center", gap: 10 },
  btn: {
    width: 76,
    height: 76,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  actionLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
});
