import { LinearGradient } from "expo-linear-gradient";
import { useRouter } from "expo-router";
import { useRef, useState } from "react";
import {
  Dimensions,
  FlatList,
  ImageBackground,
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

// Each slide pairs an audience-specific background with copy that
// answers "what does 1INME do for someone like me?". Images live in
// assets/images/onboarding/ as 9:16 portraits so they fill the phone
// behind the headline; the gradient scrim keeps text legible regardless
// of the underlying art.
type Slide = {
  key: string;
  image: ReturnType<typeof require>;
  category: string;
  title: string;
  body: string;
};

const SLIDES: Slide[] = [
  {
    key: "creators",
    image: require("@/assets/images/onboarding/creators.png"),
    category: "For creators",
    title: "Every link, every channel — one tap away",
    body: "Bundle your latest video, store, sponsorships and socials into a single biolink your audience can save, share, or tap.",
  },
  {
    key: "business",
    image: require("@/assets/images/onboarding/business.png"),
    category: "For small businesses",
    title: "Your menu, hours and reviews on the counter",
    body: "Stick a 1INME NFC tag at the till. Customers tap their phone to see your menu, opening hours, directions and leave a review — no app needed.",
  },
  {
    key: "freelancer",
    image: require("@/assets/images/onboarding/freelancer.png"),
    category: "For freelancers",
    title: "Pitch your portfolio in one link",
    body: "Send one tidy 1INME profile instead of five attachments. Show case studies, rates and a booking link, and see exactly who clicked what.",
  },
  {
    key: "networker",
    image: require("@/assets/images/onboarding/networker.png"),
    category: "For networkers",
    title: "Replace your business card",
    body: "Tap a 1INME NFC card to share contact, LinkedIn, calendar and portfolio in seconds — and the other person doesn't need to install anything.",
  },
  {
    key: "students",
    image: require("@/assets/images/onboarding/students.png"),
    category: "For students & job seekers",
    title: "One link for your CV, projects and socials",
    body: "Hand recruiters a single 1INME link with your résumé, GitHub, portfolio and contact info — and watch which sections they actually open.",
  },
  {
    key: "coaches",
    image: require("@/assets/images/onboarding/coaches.png"),
    category: "For coaches & educators",
    title: "Sell, schedule and stay in touch",
    body: "Group your courses, booking calendar, payment links and follower updates in one biolink — and broadcast announcements to everyone who follows you.",
  },
];

type InfoHref =
  | "/info/about"
  | "/info/nfc"
  | "/info/help"
  | "/info/privacy"
  | "/info/terms";

const INFO_LINKS: { href: InfoHref; label: string }[] = [
  { href: "/info/about", label: "About" },
  { href: "/info/nfc", label: "NFC" },
  { href: "/info/help", label: "Help" },
  { href: "/info/privacy", label: "Privacy" },
  { href: "/info/terms", label: "Terms" },
];

const { width, height } = Dimensions.get("window");

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

  // Web preview is rendered inside a fixed-height frame; padding keeps
  // the chrome clear of the platform's surrounding browser bars.
  const webTop = Platform.OS === "web" ? 67 : 0;
  const webBottom = Platform.OS === "web" ? 34 : 0;

  return (
    <View style={[styles.root, { backgroundColor: colors.background }]}>
      <FlatList
        ref={listRef}
        data={SLIDES}
        keyExtractor={(s) => s.key}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onScroll={onScroll}
        scrollEventThrottle={16}
        renderItem={({ item }) => (
          <ImageBackground
            source={item.image}
            resizeMode="cover"
            style={[styles.slide, { width, height }]}
          >
            {/* Top scrim keeps the brand wordmark + Skip readable on
                bright frames; bottom scrim does the same for the
                category/title/body block. */}
            <LinearGradient
              colors={["rgba(10,10,15,0.55)", "rgba(10,10,15,0.05)", "rgba(10,10,15,0.85)"]}
              locations={[0, 0.35, 1]}
              style={StyleSheet.absoluteFill}
            />

            <View
              style={[
                styles.copy,
                {
                  paddingHorizontal: 28,
                  paddingBottom: insets.bottom + 200 + webBottom,
                },
              ]}
            >
              <View
                style={[
                  styles.categoryChip,
                  {
                    backgroundColor: "rgba(255,255,255,0.14)",
                    borderColor: "rgba(255,255,255,0.22)",
                  },
                ]}
              >
                <Text style={styles.categoryText}>{item.category}</Text>
              </View>
              <Text style={styles.title}>{item.title}</Text>
              <Text style={styles.body}>{item.body}</Text>
            </View>
          </ImageBackground>
        )}
      />

      {/* Top brand bar floats above the carousel */}
      <View
        pointerEvents="box-none"
        style={[
          styles.topBar,
          { paddingTop: insets.top + 16 + webTop, paddingHorizontal: 24 },
        ]}
      >
        <BrandWordmark size={28} />
        <Text
          accessibilityRole="button"
          onPress={skip}
          style={styles.skip}
        >
          Skip
        </Text>
      </View>

      {/* Bottom dots + CTA + info links also float above */}
      <View
        pointerEvents="box-none"
        style={[
          styles.bottom,
          { paddingBottom: insets.bottom + 24 + webBottom, paddingHorizontal: 24 },
        ]}
      >
        <View style={styles.dots}>
          {SLIDES.map((_, i) => (
            <View
              key={i}
              style={[
                styles.dot,
                {
                  backgroundColor:
                    i === index ? colors.primary : "rgba(255,255,255,0.35)",
                  width: i === index ? 24 : 8,
                },
              ]}
            />
          ))}
        </View>
        <Button
          label={index === SLIDES.length - 1 ? "Get started" : "Continue"}
          onPress={next}
        />
        <View style={styles.infoLinks}>
          {INFO_LINKS.map((l, i, arr) => (
            <View key={l.href} style={styles.infoLinkRow}>
              <Text
                accessibilityRole="link"
                onPress={() => router.push(l.href)}
                style={styles.infoLink}
              >
                {l.label}
              </Text>
              {i < arr.length - 1 ? (
                <Text style={styles.infoDot}>·</Text>
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
  slide: { flex: 1 },
  copy: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
  },
  categoryChip: {
    alignSelf: "flex-start",
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 6,
    marginBottom: 14,
  },
  categoryText: {
    color: "#fff",
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  title: {
    color: "#fff",
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 30,
    letterSpacing: -0.5,
    lineHeight: 36,
    marginBottom: 12,
  },
  body: {
    color: "rgba(255,255,255,0.85)",
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    lineHeight: 22,
    maxWidth: 360,
  },
  topBar: {
    position: "absolute",
    top: 0,
    left: 0,
    right: 0,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  skip: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 15,
    color: "#fff",
    padding: 8,
    textShadowColor: "rgba(0,0,0,0.4)",
    textShadowRadius: 4,
  },
  bottom: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    gap: 12,
  },
  dots: {
    flexDirection: "row",
    justifyContent: "center",
    alignItems: "center",
    gap: 6,
    paddingBottom: 12,
  },
  dot: { height: 8, borderRadius: 4 },
  infoLinks: {
    flexDirection: "row",
    flexWrap: "wrap",
    justifyContent: "center",
    alignItems: "center",
    gap: 4,
    paddingTop: 8,
  },
  infoLinkRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  infoLink: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    color: "rgba(255,255,255,0.85)",
    padding: 4,
  },
  infoDot: { fontSize: 13, color: "rgba(255,255,255,0.5)" },
});
