import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { Platform, Pressable, StyleSheet, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { BrandWordmark } from "@/components/Brand";
import { useColors } from "@/hooks/useColors";

export default function BiolinkViewer() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { handle } = useLocalSearchParams<{ handle: string }>();
  const webTop = Platform.OS === "web" ? 67 : 0;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: false }} />
      <LinearGradient
        colors={[colors.primary + "22", "transparent"]}
        start={{ x: 0, y: 0 }}
        end={{ x: 0, y: 1 }}
        style={StyleSheet.absoluteFill}
      />
      <View
        style={[
          styles.topBar,
          { paddingTop: insets.top + 12 + webTop, paddingHorizontal: 20 },
        ]}
      >
        <Pressable onPress={() => router.back()} hitSlop={12}>
          <Feather name="x" size={26} color={colors.foreground} />
        </Pressable>
        <BrandWordmark size={22} />
        <View style={{ width: 26 }} />
      </View>

      <View style={styles.content}>
        <View
          style={[
            styles.avatar,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              borderRadius: 999,
            },
          ]}
        >
          <Feather name="user" size={48} color={colors.mutedForeground} />
        </View>
        <Text style={[styles.handle, { color: colors.foreground }]}>
          @{handle ?? "creator"}
        </Text>
        <Text style={[styles.note, { color: colors.mutedForeground }]}>
          Public profile preview is on the way. Open this link on the web to view
          the full 1INME profile in the meantime.
        </Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  topBar: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  content: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 32,
    gap: 16,
  },
  avatar: {
    width: 112,
    height: 112,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1,
  },
  handle: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 26,
  },
  note: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    textAlign: "center",
    lineHeight: 22,
    maxWidth: 320,
  },
});
