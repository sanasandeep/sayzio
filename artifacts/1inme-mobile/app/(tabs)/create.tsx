import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import {
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";
import { LINK_KINDS } from "@/lib/linkKinds";

export default function CreateTab() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const webTop = Platform.OS === "web" ? 67 : 0;

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.background }}
      contentContainerStyle={{
        paddingTop: insets.top + 16 + webTop,
        paddingBottom: 32,
        paddingHorizontal: 20,
        gap: 16,
      }}
    >
      <View>
        <Text style={[styles.eyebrow, { color: colors.mutedForeground }]}>
          Pick a kind
        </Text>
        <Text style={[styles.title, { color: colors.foreground }]}>
          Create a new link
        </Text>
      </View>

      <View style={{ gap: 10 }}>
        {LINK_KINDS.map((m) => (
          <Pressable
            key={m.kind}
            onPress={() => router.push(`/links/create/${m.kind}`)}
            style={({ pressed }) => [
              styles.card,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
                opacity: pressed ? 0.85 : 1,
              },
            ]}
          >
            <View
              style={[
                styles.iconWrap,
                { backgroundColor: colors.primary + "1c" },
              ]}
            >
              <Feather name={m.icon} size={22} color={colors.primary} />
            </View>
            <View style={{ flex: 1, gap: 2 }}>
              <Text style={[styles.cardTitle, { color: colors.foreground }]}>
                {m.label}
              </Text>
              <Text
                style={[styles.cardBlurb, { color: colors.mutedForeground }]}
              >
                {m.blurb}
              </Text>
            </View>
            <Feather name="chevron-right" size={20} color={colors.mutedForeground} />
          </Pressable>
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  eyebrow: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 28, marginTop: 2 },
  card: {
    flexDirection: "row",
    alignItems: "center",
    gap: 14,
    padding: 16,
    borderWidth: 1,
  },
  iconWrap: {
    width: 48,
    height: 48,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  cardTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 16 },
  cardBlurb: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, lineHeight: 18 },
});
