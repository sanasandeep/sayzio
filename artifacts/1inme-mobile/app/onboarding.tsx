import { Feather } from "@expo/vector-icons";
import { BlurView } from "expo-blur";
import { Image as ExpoImage } from "expo-image";
import { LinearGradient } from "expo-linear-gradient";
import { useRouter } from "expo-router";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  AccessibilityInfo,
  ActivityIndicator,
  FlatList,
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
import Animated, {
  Easing,
  useAnimatedStyle,
  useSharedValue,
  withDelay,
  withRepeat,
  withSequence,
  withSpring,
  withTiming,
} from "react-native-reanimated";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AiDashboardDemo } from "@/components/AiDashboardDemo";
import { AnimatedBlob } from "@/components/AnimatedBlobBackground";
import { BrandWordmark } from "@/components/Brand";
import { Button } from "@/components/Button";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { onboarding as onboardingApi, type OnboardingSlide } from "@/lib/api";
import {
  getDashboardLayout,
  type DashboardPreset,
} from "@/lib/api/dashboard";
import {
  getCachedOnboardingSlides,
  setCachedOnboardingSlides,
  setOnboardingComplete,
} from "@/lib/secure";
import {
  getLocalSlideImageMap,
  persistSlideImages,
  type SlideImageMap,
} from "@/lib/slideImageCache";

// ─── Bundled fallback images ──────────────────────────────────────────────
// Used only if the slides endpoint is unreachable (offline, fresh install).
// Admin-managed slides from the API always take priority.
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

// ─── Per-slide animated visual config ────────────────────────────────────
// Each entry defines blob colors and which feature icons float above the
// background. Icons reference existing zio-nodes images bundled in the app.
// Positions are expressed as fractions of screen width/height (0–1).

type FloatingIconDef = {
  img: ImageSourcePropType;
  /** Fractional x position 0–1 (measured from left) */
  fx: number;
  /** Fractional y position 0–1 (measured from top) */
  fy: number;
  size: number;
  floatAmplitude: number;
  floatDuration: number;
  delayMs: number;
};

type SlideTheme = {
  blobAColor: string;
  blobBColor: string;
  blobCColor: string;
  accent: string;
  icons: FloatingIconDef[];
};

const SLIDE_THEMES: Record<string, SlideTheme> = {
  creators: {
    blobAColor: "#1a3dff",
    blobBColor: "#0080ff",
    blobCColor: "#005aff",
    accent: "#3d6bff",
    icons: [
      {
        img: require("@/assets/images/zio-nodes/link.png"),
        fx: 0.78, fy: 0.18, size: 50, floatAmplitude: 9, floatDuration: 2800, delayMs: 0,
      },
      {
        img: require("@/assets/images/zio-nodes/analytics.png"),
        fx: 0.12, fy: 0.28, size: 40, floatAmplitude: 7, floatDuration: 3400, delayMs: 600,
      },
      {
        img: require("@/assets/images/zio-nodes/audience.png"),
        fx: 0.82, fy: 0.52, size: 36, floatAmplitude: 11, floatDuration: 2500, delayMs: 300,
      },
      {
        img: require("@/assets/images/zio-nodes/social.png"),
        fx: 0.08, fy: 0.60, size: 32, floatAmplitude: 8, floatDuration: 3100, delayMs: 900,
      },
      {
        img: require("@/assets/images/zio-nodes/growth.png"),
        fx: 0.60, fy: 0.12, size: 34, floatAmplitude: 6, floatDuration: 3700, delayMs: 450,
      },
    ],
  },
  business: {
    blobAColor: "#0066cc",
    blobBColor: "#007799",
    blobCColor: "#004d99",
    accent: "#0099cc",
    icons: [
      {
        img: require("@/assets/images/zio-nodes/store.png"),
        fx: 0.75, fy: 0.15, size: 50, floatAmplitude: 8, floatDuration: 3000, delayMs: 0,
      },
      {
        img: require("@/assets/images/zio-nodes/menu.png"),
        fx: 0.14, fy: 0.30, size: 42, floatAmplitude: 10, floatDuration: 2700, delayMs: 500,
      },
      {
        img: require("@/assets/images/zio-nodes/qr.png"),
        fx: 0.80, fy: 0.54, size: 36, floatAmplitude: 7, floatDuration: 3300, delayMs: 200,
      },
      {
        img: require("@/assets/images/zio-nodes/reviews.png"),
        fx: 0.10, fy: 0.62, size: 34, floatAmplitude: 9, floatDuration: 2900, delayMs: 800,
      },
      {
        img: require("@/assets/images/zio-nodes/forms.png"),
        fx: 0.58, fy: 0.10, size: 32, floatAmplitude: 6, floatDuration: 3600, delayMs: 350,
      },
    ],
  },
  freelancer: {
    blobAColor: "#1a55cc",
    blobBColor: "#2244bb",
    blobCColor: "#0033aa",
    accent: "#4477ee",
    icons: [
      {
        img: require("@/assets/images/zio-nodes/resume.png"),
        fx: 0.76, fy: 0.17, size: 50, floatAmplitude: 9, floatDuration: 2900, delayMs: 0,
      },
      {
        img: require("@/assets/images/zio-nodes/analytics.png"),
        fx: 0.11, fy: 0.27, size: 40, floatAmplitude: 8, floatDuration: 3200, delayMs: 600,
      },
      {
        img: require("@/assets/images/zio-nodes/domain.png"),
        fx: 0.82, fy: 0.50, size: 36, floatAmplitude: 7, floatDuration: 2600, delayMs: 300,
      },
      {
        img: require("@/assets/images/zio-nodes/code.png"),
        fx: 0.09, fy: 0.60, size: 32, floatAmplitude: 11, floatDuration: 3000, delayMs: 900,
      },
      {
        img: require("@/assets/images/zio-nodes/growth.png"),
        fx: 0.62, fy: 0.13, size: 34, floatAmplitude: 6, floatDuration: 3800, delayMs: 450,
      },
    ],
  },
  networker: {
    blobAColor: "#1a44cc",
    blobBColor: "#0055bb",
    blobCColor: "#003399",
    accent: "#3366dd",
    icons: [
      {
        img: require("@/assets/images/zio-nodes/vcard.png"),
        fx: 0.77, fy: 0.16, size: 50, floatAmplitude: 8, floatDuration: 3100, delayMs: 0,
      },
      {
        img: require("@/assets/images/zio-nodes/calls.png"),
        fx: 0.13, fy: 0.29, size: 42, floatAmplitude: 10, floatDuration: 2800, delayMs: 500,
      },
      {
        img: require("@/assets/images/zio-nodes/link.png"),
        fx: 0.81, fy: 0.52, size: 36, floatAmplitude: 7, floatDuration: 3400, delayMs: 200,
      },
      {
        img: require("@/assets/images/zio-nodes/social.png"),
        fx: 0.09, fy: 0.63, size: 32, floatAmplitude: 9, floatDuration: 2700, delayMs: 800,
      },
      {
        img: require("@/assets/images/zio-nodes/audience.png"),
        fx: 0.60, fy: 0.11, size: 34, floatAmplitude: 6, floatDuration: 3500, delayMs: 350,
      },
    ],
  },
  students: {
    blobAColor: "#1155cc",
    blobBColor: "#0066bb",
    blobCColor: "#004499",
    accent: "#3388ff",
    icons: [
      {
        img: require("@/assets/images/zio-nodes/link.png"),
        fx: 0.76, fy: 0.17, size: 50, floatAmplitude: 9, floatDuration: 2900, delayMs: 0,
      },
      {
        img: require("@/assets/images/zio-nodes/social.png"),
        fx: 0.12, fy: 0.28, size: 40, floatAmplitude: 7, floatDuration: 3300, delayMs: 600,
      },
      {
        img: require("@/assets/images/zio-nodes/analytics.png"),
        fx: 0.82, fy: 0.52, size: 36, floatAmplitude: 8, floatDuration: 2600, delayMs: 300,
      },
      {
        img: require("@/assets/images/zio-nodes/forms.png"),
        fx: 0.10, fy: 0.61, size: 32, floatAmplitude: 10, floatDuration: 3000, delayMs: 900,
      },
    ],
  },
  coaches: {
    blobAColor: "#0055bb",
    blobBColor: "#1166cc",
    blobCColor: "#003388",
    accent: "#4488ff",
    icons: [
      {
        img: require("@/assets/images/zio-nodes/audience.png"),
        fx: 0.77, fy: 0.16, size: 50, floatAmplitude: 8, floatDuration: 3000, delayMs: 0,
      },
      {
        img: require("@/assets/images/zio-nodes/calendar.png"),
        fx: 0.13, fy: 0.29, size: 40, floatAmplitude: 10, floatDuration: 2800, delayMs: 500,
      },
      {
        img: require("@/assets/images/zio-nodes/link.png"),
        fx: 0.81, fy: 0.53, size: 36, floatAmplitude: 7, floatDuration: 3200, delayMs: 200,
      },
      {
        img: require("@/assets/images/zio-nodes/forms.png"),
        fx: 0.09, fy: 0.63, size: 32, floatAmplitude: 9, floatDuration: 2700, delayMs: 800,
      },
    ],
  },
};

// Default theme for admin-managed slides whose slug doesn't have a config entry
const DEFAULT_SLIDE_THEME: SlideTheme = {
  blobAColor: "#1a3dff",
  blobBColor: "#0055cc",
  blobCColor: "#003399",
  accent: "#3d6bff",
  icons: [
    {
      img: require("@/assets/images/zio-nodes/link.png"),
      fx: 0.78, fy: 0.18, size: 48, floatAmplitude: 9, floatDuration: 2900, delayMs: 0,
    },
    {
      img: require("@/assets/images/zio-nodes/analytics.png"),
      fx: 0.12, fy: 0.30, size: 38, floatAmplitude: 7, floatDuration: 3300, delayMs: 600,
    },
    {
      img: require("@/assets/images/zio-nodes/growth.png"),
      fx: 0.82, fy: 0.52, size: 34, floatAmplitude: 8, floatDuration: 2700, delayMs: 300,
    },
    {
      img: require("@/assets/images/zio-nodes/qr.png"),
      fx: 0.10, fy: 0.62, size: 32, floatAmplitude: 10, floatDuration: 3000, delayMs: 900,
    },
  ],
};

function resolveSlideTheme(slug: string): SlideTheme {
  return SLIDE_THEMES[slug] ?? DEFAULT_SLIDE_THEME;
}

// ─── Image resolution helpers (unchanged from original) ───────────────────
type SlideImage = ImageSourcePropType;

function resolveImages(
  slide: OnboardingSlide,
  localImages: SlideImageMap,
): SlideImage[] {
  const remote =
    slide.image_urls && slide.image_urls.length > 0
      ? slide.image_urls
      : slide.image_url
        ? [slide.image_url]
        : [];
  if (remote.length > 0) {
    return remote.map((uri) => ({ uri: localImages[uri] ?? uri }));
  }
  const bundled = FALLBACK_IMAGES[slide.slug] ?? FALLBACK_IMAGES.creators;
  return [bundled];
}

async function prefetchSlideImages(items: OnboardingSlide[]): Promise<void> {
  const urls = items.flatMap((s) =>
    s.image_urls && s.image_urls.length > 0
      ? s.image_urls
      : s.image_url
        ? [s.image_url]
        : [],
  );
  if (urls.length === 0) return;
  try {
    await ExpoImage.prefetch(urls, { cachePolicy: "disk" });
  } catch {
    // ignore — underlay covers any photo that isn't cached yet
  }
}

// ─── Floating icon tile ───────────────────────────────────────────────────
// Each icon independently floats up/down on a looping animation.
// Counter-rotate is NOT applied (no orbit rotor here — each icon is stationary
// in X and only bobs in Y so it reads as "floating" rather than "orbiting").
function FloatingIcon({
  def,
  width,
  height,
  reduced,
  startDelay,
}: {
  def: FloatingIconDef;
  width: number;
  height: number;
  reduced: boolean;
  startDelay: number;
}) {
  const ty = useSharedValue(0);
  const opacity = useSharedValue(0);

  useEffect(() => {
    // Fade in with a slight delay
    opacity.value = withDelay(startDelay + 200, withTiming(1, { duration: 500 }));

    if (reduced) return;
    // Float loop: amplitude in both directions, eased
    ty.value = withDelay(
      startDelay,
      withRepeat(
        withSequence(
          withTiming(-def.floatAmplitude, {
            duration: def.floatDuration,
            easing: Easing.inOut(Easing.sin),
          }),
          withTiming(def.floatAmplitude * 0.6, {
            duration: def.floatDuration * 0.85,
            easing: Easing.inOut(Easing.sin),
          }),
        ),
        -1,
        true,
      ),
    );
  }, [reduced]);

  const iconStyle = useAnimatedStyle(() => ({
    opacity: opacity.value,
    transform: [{ translateY: ty.value }],
  }));

  const tileSize = def.size + 16;

  return (
    <Animated.View
      pointerEvents="none"
      style={[
        {
          position: "absolute",
          left: def.fx * width - tileSize / 2,
          top: def.fy * height - tileSize / 2,
          width: tileSize,
          height: tileSize,
          borderRadius: tileSize * 0.28,
          backgroundColor: "rgba(255,255,255,0.07)",
          borderWidth: 1,
          borderColor: "rgba(100,160,255,0.20)",
          alignItems: "center",
          justifyContent: "center",
          shadowColor: "#3d6bff",
          shadowOffset: { width: 0, height: 0 },
          shadowOpacity: 0.35,
          shadowRadius: 10,
          elevation: 6,
        },
        iconStyle,
      ]}
    >
      <ExpoImage
        source={def.img}
        style={{ width: def.size, height: def.size }}
        contentFit="contain"
      />
    </Animated.View>
  );
}

// ─── Animated slide content card ──────────────────────────────────────────
// The glass card that holds category chip, title, and body text. It animates
// up and fades in when the slide becomes active, giving each slide a fresh
// entrance that makes the experience feel alive.
function SlideCard({
  category,
  title,
  body,
  active,
  paddingBottom,
}: {
  category: string;
  title: string;
  body: string | null;
  active: boolean;
  paddingBottom: number;
}) {
  const cardY = useSharedValue(active ? 0 : 28);
  const cardOpacity = useSharedValue(active ? 1 : 0);

  useEffect(() => {
    if (active) {
      cardY.value = withSpring(0, { damping: 22, stiffness: 130, mass: 0.8 });
      cardOpacity.value = withTiming(1, { duration: 320, easing: Easing.out(Easing.quad) });
    } else {
      // Reset to "ready to animate in" state immediately so re-entering
      // the slide feels fresh. No animation — it's off-screen anyway.
      cardY.value = 28;
      cardOpacity.value = 0;
    }
  }, [active]);

  const cardStyle = useAnimatedStyle(() => ({
    opacity: cardOpacity.value,
    transform: [{ translateY: cardY.value }],
  }));

  return (
    <Animated.View
      style={[
        styles.copyWrap,
        { paddingHorizontal: 20, paddingBottom },
        cardStyle,
      ]}
    >
      <BlurView
        intensity={Platform.OS === "android" ? 40 : 64}
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
    </Animated.View>
  );
}

// ─── Full animated slide ───────────────────────────────────────────────────
// Replaces the old SlideGallery + SlideContent combination. Each slide has:
//   • A dark base background with coloured animated blobs (depth)
//   • The photo background (if the admin slide has images) with a dark overlay
//   • Floating feature-icon tiles that gently bob up and down
//   • The glassmorphic content card that animates in when the slide is active
//   • A subtle parallax: the background layer shifts at 0.2× the scroll rate
//     so it feels deeper than the foreground content
function AnimatedSlide({
  slide,
  images,
  hasRemoteImages,
  active,
  width,
  height,
  scrollX,
  slideIndex,
  paddingBottom,
  reduced,
}: {
  slide: OnboardingSlide;
  images: SlideImage[];
  hasRemoteImages: boolean;
  active: boolean;
  width: number;
  height: number;
  scrollX: { value: number };
  slideIndex: number;
  paddingBottom: number;
  reduced: boolean;
}) {
  const theme = resolveSlideTheme(slide.slug);

  // Parallax: background layer translates at 0.2× scroll offset
  const bgParallaxStyle = useAnimatedStyle(() => {
    const offset = scrollX.value - slideIndex * width;
    return {
      transform: [{ translateX: -offset * 0.2 }],
    };
  });

  return (
    <View style={[styles.slide, { width, height }]}>

      {/* ── Base background ───────────────────────────────────────────── */}
      <View style={StyleSheet.absoluteFill}>
        <LinearGradient
          colors={["#0b0e1a", "#080b14", "#070a12"]}
          style={StyleSheet.absoluteFill}
        />
      </View>

      {/* ── Parallax layer (blobs + icons + optional photo) ──────────── */}
      <Animated.View
        pointerEvents="none"
        style={[StyleSheet.absoluteFill, bgParallaxStyle]}
      >
        {/* Animated colour blobs */}
        <AnimatedBlob
          color={theme.blobAColor}
          size={width * 0.7}
          initialX={width * 0.15}
          initialY={height * 0.2}
          driftX={18}
          driftY={14}
          duration={5200}
          opacity={0.18}
          delayMs={0}
          reduced={reduced}
        />
        <AnimatedBlob
          color={theme.blobBColor}
          size={width * 0.55}
          initialX={width * 0.82}
          initialY={height * 0.55}
          driftX={-14}
          driftY={18}
          duration={6400}
          opacity={0.14}
          delayMs={800}
          reduced={reduced}
        />
        <AnimatedBlob
          color={theme.blobCColor}
          size={width * 0.4}
          initialX={width * 0.50}
          initialY={height * 0.35}
          driftX={10}
          driftY={-12}
          duration={7100}
          opacity={0.10}
          delayMs={1600}
          reduced={reduced}
        />

        {/* Admin photo layer (when a slide has remote images, show them
            as a background with a dark overlay to maintain legibility) */}
        {hasRemoteImages ? (
          <>
            <ExpoImage
              source={images[0]}
              contentFit="cover"
              cachePolicy="disk"
              style={[StyleSheet.absoluteFill, { opacity: 0.45 }]}
            />
            {/* Strong dark vignette so blobs and icons still read */}
            <LinearGradient
              colors={[
                "rgba(7,10,18,0.55)",
                "rgba(7,10,18,0.10)",
                "rgba(7,10,18,0.60)",
              ]}
              locations={[0, 0.42, 1]}
              style={StyleSheet.absoluteFill}
            />
          </>
        ) : null}

        {/* Floating feature icons */}
        {theme.icons.map((def, i) => (
          <FloatingIcon
            key={i}
            def={def}
            width={width}
            height={height}
            reduced={reduced}
            startDelay={def.delayMs}
          />
        ))}
      </Animated.View>

      {/* Bottom scrim so the glass card stays readable on any background */}
      <LinearGradient
        pointerEvents="none"
        colors={["transparent", "rgba(7,10,18,0.75)", "rgba(7,10,18,0.92)"]}
        locations={[0.3, 0.65, 1]}
        style={StyleSheet.absoluteFill}
      />

      {/* Animated glass content card */}
      <SlideCard
        category={slide.category}
        title={slide.title}
        body={slide.body}
        active={active}
        paddingBottom={paddingBottom}
      />
    </View>
  );
}

// ─── AI dashboard slide ───────────────────────────────────────────────────
// Final onboarding slide introducing the AI dashboard designer. Visually
// upgraded with animated blobs and floating icons consistent with the rest
// of the carousel, while keeping the AiDashboardDemo widget intact.
function AiDashboardSlide({
  loading,
  presets,
  onOpenDesigner,
  paddingTop,
  paddingBottom,
  width,
  height,
  reduced,
}: {
  loading: boolean;
  presets: DashboardPreset[] | null;
  onOpenDesigner: () => void;
  paddingTop: number;
  paddingBottom: number;
  width: number;
  height: number;
  reduced: boolean;
}) {
  const colors = useColors();
  const hasPresets = !!presets && presets.length > 0;

  return (
    <View style={[styles.slide, { width, height }]}>
      {/* Background */}
      <LinearGradient
        colors={["#08101f", "#070c18", "#060a14"]}
        style={StyleSheet.absoluteFill}
      />

      {/* Animated blobs — blue-toned for the AI slide */}
      <AnimatedBlob
        color="#1a3dff"
        size={width * 0.65}
        initialX={width * 0.18}
        initialY={height * 0.18}
        driftX={16}
        driftY={12}
        duration={5800}
        opacity={0.16}
        delayMs={0}
        reduced={reduced}
      />
      <AnimatedBlob
        color="#0055cc"
        size={width * 0.5}
        initialX={width * 0.80}
        initialY={height * 0.60}
        driftX={-12}
        driftY={16}
        duration={7200}
        opacity={0.12}
        delayMs={1000}
        reduced={reduced}
      />

      {/* Floating AI-related icons in the background */}
      {[
        { img: require("@/assets/images/zio-nodes/ai.png"),        fx: 0.82, fy: 0.14, size: 38, floatAmplitude: 8,  floatDuration: 2900, delayMs: 0 },
        { img: require("@/assets/images/zio-nodes/analytics.png"), fx: 0.10, fy: 0.25, size: 32, floatAmplitude: 7,  floatDuration: 3300, delayMs: 500 },
        { img: require("@/assets/images/zio-nodes/growth.png"),    fx: 0.78, fy: 0.72, size: 30, floatAmplitude: 10, floatDuration: 2600, delayMs: 300 },
      ].map((def, i) => (
        <FloatingIcon
          key={i}
          def={def as FloatingIconDef}
          width={width}
          height={height}
          reduced={reduced}
          startDelay={def.delayMs}
        />
      ))}

      {/* Content */}
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
          variant="cta"
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

// ─── Main Onboarding screen ───────────────────────────────────────────────
export default function Onboarding() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { width, height } = useWindowDimensions();
  const { user, token } = useAuth();
  const listRef = useRef<FlatList<OnboardingSlide>>(null);

  const [index, setIndex] = useState(0);
  const [slides, setSlides] = useState<OnboardingSlide[]>(FALLBACK_SLIDES);
  const [reduced, setReduced] = useState(false);

  const indexRef = useRef(0);
  useEffect(() => {
    indexRef.current = index;
  }, [index]);

  const resyncing = useRef(false);
  const deferredSlidesRef = useRef<OnboardingSlide[] | null>(null);

  const [presets, setPresets] = useState<DashboardPreset[] | null>(null);
  const [presetsLoading, setPresetsLoading] = useState(false);

  const freshSlidesRef = useRef(false);

  const [localImages, setLocalImages] = useState<SlideImageMap>({});
  const mergeLocalImages = useCallback((map: SlideImageMap) => {
    if (Object.keys(map).length === 0) return;
    setLocalImages((prev) => ({ ...prev, ...map }));
  }, []);

  // Shared scroll offset for parallax (updated on every scroll event)
  const scrollX = useSharedValue(0);

  // Detect reduce-motion preference
  useEffect(() => {
    let mounted = true;
    AccessibilityInfo.isReduceMotionEnabled()
      .then((on) => { if (mounted) setReduced(on); })
      .catch(() => {});
    const sub = AccessibilityInfo.addEventListener("reduceMotionChanged", (on) => {
      if (mounted) setReduced(on);
    });
    return () => {
      mounted = false;
      sub.remove();
    };
  }, []);

  // Hydrate from the on-device cache
  useEffect(() => {
    let cancelled = false;
    (async () => {
      const cached = await getCachedOnboardingSlides<OnboardingSlide>();
      if (cancelled || !cached || cached.length === 0) return;
      const localMap = await getLocalSlideImageMap(cached);
      if (cancelled) return;
      mergeLocalImages(localMap);
      if (freshSlidesRef.current) return;
      if (indexRef.current === 0) {
        setSlides(cached);
      } else if (!deferredSlidesRef.current) {
        deferredSlidesRef.current = cached;
      }
    })();
    return () => { cancelled = true; };
  }, []);

  // Fetch admin-managed slides
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const res = await onboardingApi.slides();
        if (cancelled) return;
        const items = (res.items ?? []).filter((s) => !!s);
        if (items.length === 0) return;
        freshSlidesRef.current = true;
        void setCachedOnboardingSlides(items);
        const localMap = await persistSlideImages(items);
        if (cancelled) return;
        mergeLocalImages(localMap);
        if (Platform.OS === "web") {
          await prefetchSlideImages(items);
          if (cancelled) return;
        }
        if (indexRef.current === 0) {
          setSlides(items);
        } else {
          deferredSlidesRef.current = items;
        }
      } catch {
        // keep current slides
      }
    })();
    return () => { cancelled = true; };
  }, []);

  // Apply deferred slides when back on slide 0
  useEffect(() => {
    if (index === 0 && deferredSlidesRef.current) {
      setSlides(deferredSlidesRef.current);
      deferredSlidesRef.current = null;
    }
  }, [index]);

  // Fetch real dashboard presets for the AI slide (signed-in users only)
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
    return () => { cancelled = true; };
  }, [token]);

  const finish = async () => {
    await setOnboardingComplete(true);
    router.replace(user ? "/(tabs)" : "/(auth)");
  };

  const openDesigner = async () => {
    await setOnboardingComplete(true);
    if (user) {
      router.replace("/(tabs)");
      router.push("/dashboard-customize");
    } else {
      router.replace("/(auth)");
    }
  };

  const pages = [...slides, AI_DASHBOARD_SLIDE];
  const pageCount = pages.length;

  const onScroll = (e: NativeSyntheticEvent<NativeScrollEvent>) => {
    if (resyncing.current) return;
    // Update parallax shared value on every scroll frame
    scrollX.value = e.nativeEvent.contentOffset.x;
    const measured = e.nativeEvent.layoutMeasurement?.width || width;
    const raw = Math.round(e.nativeEvent.contentOffset.x / measured);
    const i = Math.max(0, Math.min(raw, Math.max(0, pageCount - 1)));
    if (i !== indexRef.current) {
      indexRef.current = i;
      setIndex(i);
    }
  };

  const getItemLayout = useCallback(
    (_: ArrayLike<OnboardingSlide> | null | undefined, i: number) => ({
      length: width,
      offset: width * i,
      index: i,
    }),
    [width],
  );

  useEffect(() => {
    if (!listRef.current) return;
    resyncing.current = true;
    listRef.current.scrollToOffset({
      offset: indexRef.current * width,
      animated: false,
    });
    const t = setTimeout(() => { resyncing.current = false; }, 120);
    return () => clearTimeout(t);
  }, [width]);

  const next = async () => {
    if (index < pageCount - 1) {
      listRef.current?.scrollToIndex({ index: index + 1, animated: true });
      return;
    }
    await finish();
  };

  const skip = async () => { await finish(); };

  const webBottom = Platform.OS === "web" ? 34 : 0;
  const webTop = Platform.OS === "web" ? 0 : 0;
  const total = pages.length;

  return (
    <View style={[styles.root, { backgroundColor: "#070a12" }]}>
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
              reduced={reduced}
            />
          ) : (
            <AnimatedSlide
              slide={item}
              images={resolveImages(item, localImages)}
              hasRemoteImages={
                !!(item.image_urls?.length || item.image_url)
              }
              active={i === index}
              width={width}
              height={height}
              scrollX={scrollX}
              slideIndex={i}
              paddingBottom={insets.bottom + 200 + webBottom}
              reduced={reduced}
            />
          )
        }
      />

      {/* ── Top brand bar ──────────────────────────────────────────────── */}
      <View
        pointerEvents="box-none"
        style={[
          styles.topBar,
          { paddingTop: insets.top + 16 + webTop, paddingHorizontal: 24 },
        ]}
      >
        <BrandWordmark size={28} forceVariant="dark-bg" />
        <Text accessibilityRole="button" onPress={skip} style={styles.skip}>
          Skip
        </Text>
      </View>

      {/* ── Bottom dots + CTA + info links ────────────────────────────── */}
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
                    i === index ? colors.primary : "rgba(255,255,255,0.32)",
                  width: i === index ? 24 : 8,
                },
              ]}
            />
          ))}
        </View>
        <Button
          label={index === total - 1 ? "Get started" : "Continue"}
          variant="cta"
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
  slide: { overflow: "hidden" },

  // Animated content card
  copyWrap: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
  },
  glassCard: {
    borderRadius: 24,
    overflow: "hidden",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.15)",
  },
  glassInner: {
    padding: 22,
    backgroundColor: "rgba(12,16,30,0.38)",
  },
  categoryChip: {
    alignSelf: "flex-start",
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 6,
    marginBottom: 14,
    backgroundColor: "rgba(61,107,255,0.20)",
    borderColor: "rgba(61,107,255,0.40)",
  },
  categoryText: {
    color: "#a8c4ff",
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.5,
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
    color: "rgba(255,255,255,0.85)",
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    lineHeight: 21,
  },

  // AI dashboard slide
  aiScroll: {
    paddingHorizontal: 24,
    justifyContent: "center",
    flexGrow: 1,
  },
  aiTeaser: {
    borderRadius: 20,
    borderWidth: 1,
    borderColor: "rgba(61,107,255,0.25)",
    backgroundColor: "rgba(12,16,30,0.55)",
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

  // Chrome
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
    color: "rgba(255,255,255,0.85)",
    padding: 8,
    textShadowColor: "rgba(0,0,0,0.5)",
    textShadowRadius: 6,
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
    color: "rgba(255,255,255,0.80)",
    padding: 4,
  },
  infoDot: { fontSize: 13, color: "rgba(255,255,255,0.45)" },
});
