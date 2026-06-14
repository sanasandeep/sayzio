import { BlurView } from "expo-blur";
import { LinearGradient } from "expo-linear-gradient";
import { useRouter } from "expo-router";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Animated,
  Dimensions,
  Easing,
  FlatList,
  ImageBackground,
  Platform,
  StyleSheet,
  Text,
  View,
  type ImageSourcePropType,
  type NativeScrollEvent,
  type NativeSyntheticEvent,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { BrandWordmark } from "@/components/Brand";
import { Button } from "@/components/Button";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { onboarding as onboardingApi, type OnboardingSlide } from "@/lib/api";
import { setOnboardingComplete } from "@/lib/secure";

// Bundled fallbacks. Used only if the slides endpoint is unreachable
// (offline, fresh install with no network) so the splash never breaks.
// Admin-managed slides from the API take priority.
const FALLBACK_IMAGES: Record<string, ImageSourcePropType> = {
  creators: require("@/assets/images/onboarding/creators.png"),
  business: require("@/assets/images/onboarding/business.png"),
  freelancer: require("@/assets/images/onboarding/freelancer.png"),
  networker: require("@/assets/images/onboarding/networker.png"),
  students: require("@/assets/images/onboarding/students.png"),
  coaches: require("@/assets/images/onboarding/coaches.png"),
};

const FALLBACK_SLIDES: OnboardingSlide[] = [
  {
    id: -1,
    slug: "creators",
    category: "For creators",
    title: "Every link, every channel — one tap away",
    body: "Bundle your latest video, store, sponsorships and socials into a single biolink your audience can save, share, or tap.",
    image_url: null,
    image_urls: [],
    sort_order: 10,
  },
  {
    id: -2,
    slug: "business",
    category: "For small businesses",
    title: "Your menu, hours and reviews on the counter",
    body: "Stick a 1INME NFC tag at the till. Customers tap their phone to see your menu, hours, directions and leave a review — no app needed.",
    image_url: null,
    image_urls: [],
    sort_order: 20,
  },
  {
    id: -3,
    slug: "freelancer",
    category: "For freelancers",
    title: "Pitch your portfolio in one link",
    body: "Send one tidy 1INME profile instead of five attachments. Show case studies, rates and a booking link, and see exactly who clicked what.",
    image_url: null,
    image_urls: [],
    sort_order: 30,
  },
  {
    id: -4,
    slug: "networker",
    category: "For networkers",
    title: "Replace your business card",
    body: "Tap a 1INME NFC card to share contact, LinkedIn, calendar and portfolio in seconds — and the other person doesn't need to install anything.",
    image_url: null,
    image_urls: [],
    sort_order: 40,
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

// How long each gallery photo stays on screen before crossfading
// to the next one (in ms).
const GALLERY_INTERVAL = 3500;
const FADE_DURATION = 700;

type SlideImage = ImageSourcePropType;

// Resolve a slide into the list of images we should rotate through.
// Prefer admin-managed remote URLs; fall back to bundled assets so
// offline / fresh-install users still see the right photo.
function resolveImages(slide: OnboardingSlide): SlideImage[] {
  const remote =
    slide.image_urls && slide.image_urls.length > 0
      ? slide.image_urls
      : slide.image_url
        ? [slide.image_url]
        : [];

  if (remote.length > 0) {
    return remote.map((uri) => ({ uri }));
  }

  const bundled =
    FALLBACK_IMAGES[slide.slug] ?? FALLBACK_IMAGES.creators;
  return [bundled];
}

/**
 * Auto-rotating image gallery for one onboarding slide. True
 * crossfade: the current image stays visible while the next one
 * fades in on top, then we promote the next to current. If only one
 * image is available we just render it statically.
 *
 * Reports the currently visible image index up to the parent so the
 * little indicator above the glass card can highlight it.
 */
function SlideGallery({
  images,
  active,
  onIndexChange,
}: {
  images: SlideImage[];
  active: boolean;
  onIndexChange?: (i: number) => void;
}) {
  const [current, setCurrent] = useState(0);
  const [incoming, setIncoming] = useState<number | null>(null);
  const fade = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    onIndexChange?.(current);
  }, [current, onIndexChange]);

  useEffect(() => {
    if (!active || images.length <= 1) return;
    const tick = setInterval(() => {
      const nextIdx = (current + 1) % images.length;
      setIncoming(nextIdx);
      fade.setValue(0);
      Animated.timing(fade, {
        toValue: 1,
        duration: FADE_DURATION,
        easing: Easing.inOut(Easing.quad),
        useNativeDriver: true,
      }).start(({ finished }) => {
        if (finished) {
          setCurrent(nextIdx);
          setIncoming(null);
        }
      });
    }, GALLERY_INTERVAL);
    return () => clearInterval(tick);
  }, [active, current, images.length, fade]);

  // Restart from the first photo each time the slide becomes active
  // so users always see the "hero" shot first when swiping back.
  useEffect(() => {
    if (active) {
      setCurrent(0);
      setIncoming(null);
      fade.setValue(0);
    }
  }, [active, fade]);

  return (
    <View style={StyleSheet.absoluteFill}>
      <ImageBackground
        source={images[current]}
        resizeMode="cover"
        style={StyleSheet.absoluteFill}
      />
      {incoming !== null && incoming !== current ? (
        <Animated.View style={[StyleSheet.absoluteFill, { opacity: fade }]}>
          <ImageBackground
            source={images[incoming]}
            resizeMode="cover"
            style={StyleSheet.absoluteFill}
          />
        </Animated.View>
      ) : null}
    </View>
  );
}

/**
 * One full-screen onboarding slide. Owns its own gallery-index state
 * so the small indicator above the glass card highlights whichever
 * photo is currently on screen.
 */
function SlideContent({
  images,
  active,
  category,
  title,
  body,
  paddingBottom,
  primaryColor,
}: {
  images: SlideImage[];
  active: boolean;
  category: string;
  title: string;
  body: string | null;
  paddingBottom: number;
  primaryColor: string;
}) {
  const [galleryIndex, setGalleryIndex] = useState(0);
  const handleIndexChange = useCallback((i: number) => {
    setGalleryIndex(i);
  }, []);

  return (
    <View style={[styles.slide, { width, height }]}>
      <SlideGallery
        images={images}
        active={active}
        onIndexChange={handleIndexChange}
      />

      {/* Subtle top + bottom darkening so the brand wordmark, Skip
          button and the glass card all stay legible regardless of
          the underlying photograph. */}
      <LinearGradient
        colors={[
          "rgba(10,10,15,0.55)",
          "rgba(10,10,15,0.05)",
          "rgba(10,10,15,0.55)",
        ]}
        locations={[0, 0.4, 1]}
        style={StyleSheet.absoluteFill}
      />

      <View
        style={[styles.copyWrap, { paddingHorizontal: 20, paddingBottom }]}
      >
        {/* Tiny per-image indicator so users know more photos are
            cycling behind the card. The active photo is solid. */}
        {images.length > 1 ? (
          <View style={styles.galleryDots}>
            {images.map((_, gi) => (
              <View
                key={gi}
                style={[
                  styles.galleryDot,
                  {
                    backgroundColor:
                      gi === galleryIndex
                        ? primaryColor
                        : "rgba(255,255,255,0.4)",
                    width: gi === galleryIndex ? 22 : 12,
                  },
                ]}
              />
            ))}
          </View>
        ) : null}

        {/* Glassmorphism card. expo-blur renders a real backdrop blur
            on iOS / web; on Android it falls back to a translucent
            tint. The semi-opaque inner overlay + hairline border give
            it the frosted-glass look on every platform. */}
        <BlurView
          intensity={Platform.OS === "android" ? 40 : 60}
          tint="dark"
          style={styles.glassCard}
        >
          <View style={styles.glassInner}>
            <View style={styles.categoryChip}>
              <Text style={styles.categoryText}>{category}</Text>
            </View>
            <Text style={styles.title}>{title}</Text>
            {body ? <Text style={styles.body}>{body}</Text> : null}
          </View>
        </BlurView>
      </View>
    </View>
  );
}

export default function Onboarding() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { user } = useAuth();
  const listRef = useRef<FlatList<OnboardingSlide>>(null);
  const [index, setIndex] = useState(0);
  const [slides, setSlides] = useState<OnboardingSlide[] | null>(null);

  // Fetch slides from the admin-managed endpoint. If the request
  // fails for any reason we fall back to the bundled set so the
  // splash always renders something.
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const res = await onboardingApi.slides();
        if (cancelled) return;
        const items = (res.items ?? []).filter((s) => !!s);
        setSlides(items.length > 0 ? items : FALLBACK_SLIDES);
      } catch {
        if (!cancelled) setSlides(FALLBACK_SLIDES);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const finish = async () => {
    await setOnboardingComplete(true);
    router.replace(user ? "/(tabs)" : "/(auth)");
  };

  const onScroll = (e: NativeSyntheticEvent<NativeScrollEvent>) => {
    const i = Math.round(e.nativeEvent.contentOffset.x / width);
    if (i !== index) setIndex(i);
  };

  const next = async () => {
    const total = slides?.length ?? 0;
    if (index < total - 1) {
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

  // Loading state — keeps the gradient background visible so there's
  // no white flash before slides arrive.
  if (slides === null) {
    return (
      <View style={[styles.root, { backgroundColor: "#0a0a0f" }]}>
        <LinearGradient
          colors={["#1a0d2e", "#0a0a0f"]}
          style={StyleSheet.absoluteFill}
        />
        <View style={styles.loader}>
          <ActivityIndicator color={colors.primary} />
        </View>
      </View>
    );
  }

  const total = slides.length;

  return (
    <View style={[styles.root, { backgroundColor: "#0a0a0f" }]}>
      <FlatList
        ref={listRef}
        data={slides}
        keyExtractor={(s) => String(s.id) + ":" + s.slug}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onScroll={onScroll}
        scrollEventThrottle={16}
        renderItem={({ item, index: i }) => (
          <SlideContent
            images={resolveImages(item)}
            active={i === index}
            category={item.category}
            title={item.title}
            body={item.body}
            paddingBottom={insets.bottom + 200 + webBottom}
            primaryColor={colors.primary}
          />
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
        <Text accessibilityRole="button" onPress={skip} style={styles.skip}>
          Skip
        </Text>
      </View>

      {/* Bottom dots + CTA + info links also float above */}
      <View
        pointerEvents="box-none"
        style={[
          styles.bottom,
          {
            paddingBottom: insets.bottom + 24 + webBottom,
            paddingHorizontal: 24,
          },
        ]}
      >
        <View style={styles.dots}>
          {slides.map((_, i) => (
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
          label={index === total - 1 ? "Get started" : "Continue"}
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
  loader: { flex: 1, alignItems: "center", justifyContent: "center" },
  slide: { flex: 1 },
  copyWrap: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
  },
  galleryDots: {
    flexDirection: "row",
    alignSelf: "center",
    gap: 6,
    marginBottom: 14,
  },
  galleryDot: {
    height: 4,
    borderRadius: 2,
  },
  glassCard: {
    borderRadius: 24,
    overflow: "hidden",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.18)",
  },
  glassInner: {
    padding: 22,
    backgroundColor: "rgba(20,16,32,0.35)",
  },
  categoryChip: {
    alignSelf: "flex-start",
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 6,
    marginBottom: 14,
    backgroundColor: "rgba(255,255,255,0.14)",
    borderColor: "rgba(255,255,255,0.22)",
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
    fontSize: 26,
    letterSpacing: -0.5,
    lineHeight: 32,
    marginBottom: 10,
  },
  body: {
    color: "rgba(255,255,255,0.88)",
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    lineHeight: 21,
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
