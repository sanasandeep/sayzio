import { Feather } from "@expo/vector-icons";
import { BlurView } from "expo-blur";
import { LinearGradient } from "expo-linear-gradient";
import { useRouter } from "expo-router";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Animated,
  Easing,
  FlatList,
  ImageBackground,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  View,
  useWindowDimensions,
  type ImageSourcePropType,
  type NativeScrollEvent,
  type NativeSyntheticEvent,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AiDashboardDemo } from "@/components/AiDashboardDemo";
import { BrandWordmark } from "@/components/Brand";
import { Button } from "@/components/Button";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { onboarding as onboardingApi, type OnboardingSlide } from "@/lib/api";
import {
  getDashboardLayout,
  type DashboardPreset,
} from "@/lib/api/dashboard";
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
    body: "Bundle your latest video, store, sponsorships and socials into a single Link in Bio your audience can save, share, or tap.",
    image_url: null,
    image_urls: [],
    sort_order: 10,
  },
  {
    id: -2,
    slug: "business",
    category: "For small businesses",
    title: "Your menu, hours and reviews on the counter",
    body: "Stick a Sayzio NFC tag at the till. Customers tap their phone to see your menu, hours, directions and leave a review — no app needed.",
    image_url: null,
    image_urls: [],
    sort_order: 20,
  },
  {
    id: -3,
    slug: "freelancer",
    category: "For freelancers",
    title: "Pitch your portfolio in one link",
    body: "Send one tidy Sayzio profile instead of five attachments. Show case studies, rates and a booking link, and see exactly who clicked what.",
    image_url: null,
    image_urls: [],
    sort_order: 30,
  },
  {
    id: -4,
    slug: "networker",
    category: "For networkers",
    title: "Replace your business card",
    body: "Tap a Sayzio NFC card to share contact, LinkedIn, calendar and portfolio in seconds — and the other person doesn't need to install anything.",
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

// Sentinel slug for the extra "AI designs your dashboard" slide we append
// to the end of the intro carousel. It reuses the OnboardingSlide shape so
// it can live in the same FlatList, but `renderItem` renders a dedicated
// component (not the image gallery) when it sees this slug.
const AI_DASHBOARD_SLUG = "ai-dashboard";

const AI_DASHBOARD_SLIDE: OnboardingSlide = {
  id: -1000,
  slug: AI_DASHBOARD_SLUG,
  category: "AI dashboard",
  title: "Let AI arrange your dashboard",
  body: "Describe what you want to keep an eye on and Sayzio picks the right widgets for you — no manual setup.",
  image_url: null,
  image_urls: [],
  sort_order: 10000,
};

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
  width,
  height,
}: {
  images: SlideImage[];
  active: boolean;
  category: string;
  title: string;
  body: string | null;
  paddingBottom: number;
  primaryColor: string;
  width: number;
  height: number;
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

/**
 * The final onboarding slide: introduces the AI dashboard designer. When the
 * user already has a session (returning user who reset the intro), we fetch
 * the same real presets the "Customize dashboard" screen uses and render the
 * live looping demo (`AiDashboardDemo`, which respects reduce-motion). Before
 * sign-in there are no presets to show, so we fall back to a static teaser.
 * Either way a clear CTA lets the user jump straight to the AI designer.
 */
function AiDashboardSlide({
  loading,
  presets,
  onOpenDesigner,
  paddingTop,
  paddingBottom,
  width,
  height,
}: {
  loading: boolean;
  presets: DashboardPreset[] | null;
  onOpenDesigner: () => void;
  paddingTop: number;
  paddingBottom: number;
  width: number;
  height: number;
}) {
  const colors = useColors();
  const hasPresets = !!presets && presets.length > 0;

  return (
    <View style={[styles.slide, { width, height }]}>
      <LinearGradient
        colors={["#1a0d2e", "#0a0a0f"]}
        style={StyleSheet.absoluteFill}
      />
      <ScrollView
        contentContainerStyle={[
          styles.aiScroll,
          { paddingTop, paddingBottom },
        ]}
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.categoryChip}>
          <Text style={styles.categoryText}>{AI_DASHBOARD_SLIDE.category}</Text>
        </View>
        <Text style={styles.title}>{AI_DASHBOARD_SLIDE.title}</Text>
        {AI_DASHBOARD_SLIDE.body ? (
          <Text style={[styles.body, { marginBottom: 20 }]}>
            {AI_DASHBOARD_SLIDE.body}
          </Text>
        ) : null}

        {hasPresets ? (
          <AiDashboardDemo presets={presets!} />
        ) : loading ? (
          <View style={styles.aiTeaser}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : (
          <View style={styles.aiTeaser}>
            <View
              style={[
                styles.aiTeaserIcon,
                { backgroundColor: colors.primary + "22" },
              ]}
            >
              <Feather name="zap" size={22} color={colors.primary} />
            </View>
            <Text style={styles.aiTeaserText}>
              Once you&apos;re in, describe your goal and the AI designer builds
              a dashboard around the metrics that matter to you.
            </Text>
          </View>
        )}

        <Button
          label={hasPresets ? "Open the AI designer" : "Set up my dashboard"}
          onPress={onOpenDesigner}
          leading={
            <Feather name="zap" size={16} color={colors.primaryForeground} />
          }
          style={{ marginTop: 20 }}
        />
      </ScrollView>
    </View>
  );
}

export default function Onboarding() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { width, height } = useWindowDimensions();
  const { user, token } = useAuth();
  const listRef = useRef<FlatList<OnboardingSlide>>(null);
  const [index, setIndex] = useState(0);
  // Render the bundled slides immediately so the intro is never blank
  // while the admin-managed set loads over the network.
  const [slides, setSlides] = useState<OnboardingSlide[]>(FALLBACK_SLIDES);
  // Mirror of `index` for effects/handlers that must read the latest value
  // without re-subscribing (slides-fetch position check, rotation re-snap,
  // resync guard below).
  const indexRef = useRef(0);
  useEffect(() => {
    indexRef.current = index;
  }, [index]);
  // While true we're programmatically re-snapping after a dimension change
  // (rotation / keyboard resize) and ignore intermediate scroll events, which
  // would otherwise compute a bogus index from a stale offset/width pair.
  const resyncing = useRef(false);
  // Admin slides that arrived after the user already swiped past slide 0;
  // swapping mid-carousel would yank them back, so we hold them here.
  const deferredSlidesRef = useRef<OnboardingSlide[] | null>(null);
  // Real dashboard presets for the AI demo slide. Only fetched when a
  // session exists (the /dashboard/layout endpoint is auth-only); null
  // means "not loaded yet", [] means "loaded but none / unavailable".
  const [presets, setPresets] = useState<DashboardPreset[] | null>(null);
  const [presetsLoading, setPresetsLoading] = useState(false);

  // Fetch slides from the admin-managed endpoint in the background while
  // the bundled set is already on screen. If the request fails we simply
  // keep showing the bundled slides. If it succeeds while the user is
  // still on slide 0 we swap seamlessly; if they've already swiped ahead
  // we defer the swap (applied if they ever swipe back to slide 0) so the
  // carousel never resets under them.
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const res = await onboardingApi.slides();
        if (cancelled) return;
        const items = (res.items ?? []).filter((s) => !!s);
        if (items.length === 0) return;
        if (indexRef.current === 0) {
          setSlides(items);
        } else {
          deferredSlidesRef.current = items;
        }
      } catch {
        // Keep the bundled slides already on screen.
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  // Apply any deferred admin slides once the user is back on slide 0,
  // where a content swap isn't disorienting.
  useEffect(() => {
    if (index === 0 && deferredSlidesRef.current) {
      setSlides(deferredSlidesRef.current);
      deferredSlidesRef.current = null;
    }
  }, [index]);

  // Best-effort: pull the real dashboard presets so the AI demo slide can
  // show the same live "describe → arrange → tiles" loop as the customize
  // screen. Skipped entirely before sign-in (no token → 401), where the
  // slide gracefully falls back to a static teaser + CTA.
  useEffect(() => {
    if (!token) return;
    let cancelled = false;
    setPresetsLoading(true);
    (async () => {
      try {
        const layout = await getDashboardLayout();
        if (!cancelled) setPresets(layout.presets ?? []);
      } catch {
        if (!cancelled) setPresets([]);
      } finally {
        if (!cancelled) setPresetsLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [token]);

  const finish = async () => {
    await setOnboardingComplete(true);
    router.replace(user ? "/(tabs)" : "/(auth)");
  };

  // Jump straight to the AI dashboard designer. Signed-in users land on the
  // customize screen (on top of their tabs); everyone else finishes into the
  // auth flow, since the designer requires an account.
  const openDesigner = async () => {
    await setOnboardingComplete(true);
    if (user) {
      router.replace("/(tabs)");
      router.push("/dashboard-customize");
    } else {
      router.replace("/(auth)");
    }
  };

  // The intro photos plus the appended AI-dashboard designer slide.
  const pages = [...slides, AI_DASHBOARD_SLIDE];
  const pageCount = pages.length;

  const onScroll = (e: NativeSyntheticEvent<NativeScrollEvent>) => {
    // Ignore events fired while we're re-snapping after a rotation /
    // keyboard resize — the offset is transiently inconsistent with the
    // new width and would compute a wrong slide index.
    if (resyncing.current) return;
    // Divide by the layout's *measured* width (falls back to the reactive
    // window width) so a stale closure width can't miscalculate the index.
    const measured = e.nativeEvent.layoutMeasurement?.width || width;
    const raw = Math.round(e.nativeEvent.contentOffset.x / measured);
    const i = Math.max(0, Math.min(raw, Math.max(0, pageCount - 1)));
    if (i !== indexRef.current) {
      indexRef.current = i;
      setIndex(i);
    }
  };

  // With fixed-size pages FlatList never needs to measure items, so
  // rotation / keyboard resizes and scrollToIndex stay exact.
  const getItemLayout = useCallback(
    (_: ArrayLike<OnboardingSlide> | null | undefined, i: number) => ({
      length: width,
      offset: width * i,
      index: i,
    }),
    [width],
  );

  // When the width changes (rotation, foldable resize, web window resize)
  // the pixel offset no longer matches `index * width`; re-snap to the
  // slide the user was on, without animation, and swallow the scroll
  // events that re-snap produces.
  useEffect(() => {
    if (!listRef.current) return;
    resyncing.current = true;
    listRef.current.scrollToOffset({
      offset: indexRef.current * width,
      animated: false,
    });
    const t = setTimeout(() => {
      resyncing.current = false;
    }, 120);
    return () => clearTimeout(t);
  }, [width]);

  const next = async () => {
    const total = pageCount;
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

  const total = pages.length;

  return (
    <View style={[styles.root, { backgroundColor: "#0a0a0f" }]}>
      <FlatList
        ref={listRef}
        data={pages}
        keyExtractor={(s) => String(s.id) + ":" + s.slug}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onScroll={onScroll}
        scrollEventThrottle={16}
        getItemLayout={getItemLayout}
        onScrollToIndexFailed={(info) => {
          // Should not happen with getItemLayout, but if it ever does,
          // fall back to an exact offset computed from the reactive width.
          listRef.current?.scrollToOffset({
            offset: info.index * width,
            animated: true,
          });
        }}
        renderItem={({ item, index: i }) =>
          item.slug === AI_DASHBOARD_SLUG ? (
            <AiDashboardSlide
              loading={presetsLoading}
              presets={presets}
              onOpenDesigner={openDesigner}
              paddingTop={insets.top + 72 + webTop}
              paddingBottom={insets.bottom + 200 + webBottom}
              width={width}
              height={height}
            />
          ) : (
            <SlideContent
              images={resolveImages(item)}
              active={i === index}
              category={item.category}
              title={item.title}
              body={item.body}
              paddingBottom={insets.bottom + 200 + webBottom}
              primaryColor={colors.primary}
              width={width}
              height={height}
            />
          )
        }
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
          {pages.map((_, i) => (
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
  aiScroll: {
    paddingHorizontal: 24,
    justifyContent: "center",
    flexGrow: 1,
  },
  aiTeaser: {
    borderRadius: 20,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.16)",
    backgroundColor: "rgba(20,16,32,0.5)",
    padding: 20,
    alignItems: "center",
    gap: 14,
    minHeight: 120,
    justifyContent: "center",
  },
  aiTeaserIcon: {
    width: 48,
    height: 48,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  aiTeaserText: {
    color: "rgba(255,255,255,0.85)",
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    lineHeight: 21,
    textAlign: "center",
  },
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
