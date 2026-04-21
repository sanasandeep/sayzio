import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { useRouter } from "expo-router";
import { useRef, useState } from "react";
import {
  Dimensions,
  FlatList,
  Platform,
  StyleSheet,
  Text,
  View,
  type NativeScrollEvent,
  type NativeSyntheticEvent,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { BrandWordmark } from "@/components/Brand";
import { Button } from "@/components/Button";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { setOnboardingComplete } from "@/lib/secure";

type Slide = {
  icon: keyof typeof Feather.glyphMap;
  title: string;
  body: string;
};

const SLIDES: Slide[] = [
  {
    icon: "link-2",
    title: "One link, every you",
    body: "Bundle every link, contact, and channel into one shareable 1INME profile.",
  },
  {
    icon: "wifi",
    title: "Tap. Share. Done.",
    body: "Write your profile to any NFC tag and share it with a single tap — no app needed for your audience.",
  },
  {
    icon: "phone",
    title: "Call from anywhere",
    body: "Built-in dialer turns your contacts and biolink leads into one tap calls or texts.",
  },
  {
    icon: "shield",
    title: "Yours, end to end",
    body: "Sign in once with email, phone, Google, or a demo account — your profile syncs across web and mobile.",
  },
];

const { width } = Dimensions.get("window");

export default function Onboarding() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { user } = useAuth();
  const listRef = useRef<FlatList<Slide>>(null);
  const [index, setIndex] = useState(0);

  const finish = async () => {
    await setOnboardingComplete(true);
    router.replace(user ? "/(tabs)" : "/(auth)");
  };

  const onScroll = (e: NativeSyntheticEvent<NativeScrollEvent>) => {
    const i = Math.round(e.nativeEvent.contentOffset.x / width);
    if (i !== index) setIndex(i);
  };

  const next = async () => {
    if (index < SLIDES.length - 1) {
      listRef.current?.scrollToIndex({ index: index + 1, animated: true });
      return;
    }
    await finish();
  };

  const skip = async () => {
    await finish();
  };

  const webTop = Platform.OS === "web" ? 67 : 0;
  const webBottom = Platform.OS === "web" ? 34 : 0;

  return (
    <View style={[styles.root, { backgroundColor: colors.background }]}>
      <LinearGradient
        colors={[colors.primary + "1f", "transparent", colors.accent + "1f"]}
        start={{ x: 0, y: 0 }}
        end={{ x: 0, y: 1 }}
        style={StyleSheet.absoluteFill}
      />

      <View
        style={[
          styles.topBar,
          { paddingTop: insets.top + 16 + webTop, paddingHorizontal: 24 },
        ]}
      >
        <BrandWordmark size={28} />
        <Text
          accessibilityRole="button"
          onPress={skip}
          style={[styles.skip, { color: colors.mutedForeground }]}
        >
          Skip
        </Text>
      </View>

      <FlatList
        ref={listRef}
        data={SLIDES}
        keyExtractor={(_, i) => String(i)}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onScroll={onScroll}
        scrollEventThrottle={16}
        renderItem={({ item }) => (
          <View style={[styles.slide, { width }]}>
            <View
              style={[
                styles.iconWrap,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius * 2,
                },
              ]}
            >
              <LinearGradient
                colors={[colors.primary, colors.accent]}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 1 }}
                style={[
                  StyleSheet.absoluteFill,
                  { borderRadius: colors.radius * 2, opacity: 0.18 },
                ]}
              />
              <Feather name={item.icon} size={48} color={colors.primary} />
            </View>
            <Text style={[styles.h1, { color: colors.foreground }]}>
              {item.title}
            </Text>
            <Text style={[styles.body, { color: colors.mutedForeground }]}>
              {item.body}
            </Text>
          </View>
        )}
      />

      <View style={styles.dots}>
        {SLIDES.map((_, i) => (
          <View
            key={i}
            style={[
              styles.dot,
              {
                backgroundColor: i === index ? colors.primary : colors.border,
                width: i === index ? 24 : 8,
              },
            ]}
          />
        ))}
      </View>

      <View
        style={[
          styles.cta,
          { paddingBottom: insets.bottom + 24 + webBottom, paddingHorizontal: 24 },
        ]}
      >
        <Button
          label={index === SLIDES.length - 1 ? "Get started" : "Continue"}
          onPress={next}
        />
        <View style={styles.infoLinks}>
          {[
            { href: "/info/about", label: "About" },
            { href: "/info/nfc", label: "NFC" },
            { href: "/info/help", label: "Help" },
            { href: "/info/privacy", label: "Privacy" },
            { href: "/info/terms", label: "Terms" },
          ].map((l, i, arr) => (
            <View key={l.href} style={styles.infoLinkRow}>
              <Text
                accessibilityRole="link"
                onPress={() => router.push(l.href as any)}
                style={[styles.infoLink, { color: colors.mutedForeground }]}
              >
                {l.label}
              </Text>
              {i < arr.length - 1 ? (
                <Text style={[styles.infoDot, { color: colors.border }]}>·</Text>
              ) : null}
            </View>
          ))}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  topBar: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  skip: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 15, padding: 8 },
  slide: {
    paddingHorizontal: 36,
    alignItems: "center",
    justifyContent: "center",
    gap: 24,
    flex: 1,
  },
  iconWrap: {
    width: 132,
    height: 132,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  h1: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 30,
    textAlign: "center",
    letterSpacing: -0.5,
  },
  body: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 17,
    textAlign: "center",
    lineHeight: 25,
    maxWidth: 320,
  },
  dots: {
    flexDirection: "row",
    justifyContent: "center",
    alignItems: "center",
    gap: 6,
    paddingVertical: 16,
  },
  dot: { height: 8, borderRadius: 4 },
  cta: { gap: 12 },
  infoLinks: {
    flexDirection: "row",
    flexWrap: "wrap",
    justifyContent: "center",
    alignItems: "center",
    gap: 4,
    paddingTop: 8,
  },
  infoLinkRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  infoLink: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13, padding: 4 },
  infoDot: { fontSize: 13 },
});
