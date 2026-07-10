/**
 * AboutPage — animated, features-centric About screen for Sayzio mobile.
 *
 * Uses react-native-reanimated for entrance animations and expo-linear-gradient
 * for the hero background accent. Content is scroll-reveal triggered: sections
 * fade+slide in as they enter the viewport, stat counters count up on reveal.
 * Respects the OS "Reduce Motion" accessibility setting throughout.
 *
 * Used by app/info/about.tsx. The scroll-reveal primitives are shared with
 * InfoPage.tsx (the other /info/* pages) via ./ScrollReveal.
 */

import { Image } from "expo-image";
import { LinearGradient } from "expo-linear-gradient";
import { Stack } from "expo-router";
import {
  createContext,
  Fragment,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import {
  Linking,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import Animated, {
  useAnimatedStyle,
  useSharedValue,
  withSpring,
  withTiming,
} from "react-native-reanimated";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";
import { useReducedMotion } from "@/hooks/useReducedMotion";

import {
  ScrollReveal,
  ScrollRevealCtx,
  useScrollRevealRegistry,
} from "./ScrollReveal";
import type { EefindBlock, FounderBlock, InfoSection } from "./InfoPage";

export type { EefindBlock, FounderBlock, InfoSection };

// ---------------------------------------------------------------------------
// CountUpStat — animates a numeric string (e.g. "4,000+") from 0 on reveal.
// ---------------------------------------------------------------------------

function parseStatValue(raw: string): { num: number; suffix: string } {
  const trimmed = raw.trim();
  const match = trimmed.match(/^([\d,]+)(.*)$/);
  if (!match) return { num: 0, suffix: trimmed };
  const num = parseInt(match[1].replace(/,/g, ""), 10);
  return { num: Number.isFinite(num) ? num : 0, suffix: match[2] };
}

function CountUpStat({
  value,
  label,
  reduceMotion,
  revealed,
}: {
  value: string;
  label: string;
  reduceMotion: boolean;
  revealed: boolean;
}) {
  const colors = useColors();
  const parsed = useMemo(() => parseStatValue(value), [value]);
  const [display, setDisplay] = useState(
    reduceMotion ? value : `0${parsed.suffix}`,
  );
  const animFrame = useRef<ReturnType<typeof setTimeout> | null>(null);
  const started = useRef(false);

  useEffect(() => {
    if (!revealed || started.current || reduceMotion) {
      if (reduceMotion) setDisplay(value);
      return;
    }
    started.current = true;
    const duration = 1400;
    const startTime = Date.now();
    const tick = () => {
      const elapsed = Date.now() - startTime;
      const progress = Math.min(elapsed / duration, 1);
      // Cubic ease-out
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = Math.round(eased * parsed.num);
      setDisplay(`${current.toLocaleString("en-US")}${parsed.suffix}`);
      if (progress < 1) {
        animFrame.current = setTimeout(tick, 16);
      }
    };
    tick();
    return () => {
      if (animFrame.current) clearTimeout(animFrame.current);
    };
  }, [revealed, reduceMotion, parsed.num, parsed.suffix, value]);

  return (
    <View style={statStyles.container}>
      <Text style={[statStyles.value, { color: colors.primary }]}>
        {display}
      </Text>
      <Text style={[statStyles.label, { color: colors.mutedForeground }]}>
        {label}
      </Text>
    </View>
  );
}

const statStyles = StyleSheet.create({
  container: { alignItems: "center" },
  value: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 30,
    letterSpacing: -0.5,
  },
  label: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    letterSpacing: 0.6,
    textTransform: "uppercase",
    marginTop: 2,
  },
});

// ---------------------------------------------------------------------------
// FeatureCard — icon + heading + body with press-scale micro-interaction.
// ---------------------------------------------------------------------------

const FEATURE_CARDS: Array<{
  icon: string;
  heading: string;
  body: string;
}> = [
  {
    icon: "🔗",
    heading: "Link in Bio",
    body: "One beautiful page for every link, social, and piece of content you create.",
  },
  {
    icon: "🛍️",
    heading: "Storefronts & Menus",
    body: "Digital storefronts and restaurant menus with live ordering — no app download needed.",
  },
  {
    icon: "📱",
    heading: "QR & NFC",
    body: "Generate QR codes and write NFC cards in seconds. Built-in scan analytics.",
  },
  {
    icon: "📊",
    heading: "Deep Analytics",
    body: "Click heatmaps, visitor trends, device breakdowns, and geo insights — all in real time.",
  },
  {
    icon: "🤖",
    heading: "AI-Powered Tools",
    body: "AI builds your biolink, writes bios, tailors your résumé, and chats with visitors on your behalf.",
  },
  {
    icon: "🌐",
    heading: "One Account, Every Surface",
    body: "Your profile, analytics, and links stay in sync between web and this mobile app instantly.",
  },
];

function FeatureCard({
  icon,
  heading,
  body,
  reduceMotion,
}: {
  icon: string;
  heading: string;
  body: string;
  reduceMotion: boolean;
}) {
  const colors = useColors();
  const scale = useSharedValue(1);

  const pressStyle = useAnimatedStyle(() => ({
    transform: [{ scale: scale.value }],
  }));

  const onPressIn = () => {
    if (!reduceMotion) {
      scale.value = withSpring(0.96, { damping: 20, stiffness: 300 });
    }
  };
  const onPressOut = () => {
    if (!reduceMotion) {
      scale.value = withSpring(1, { damping: 20, stiffness: 300 });
    }
  };

  return (
    <Animated.View style={[{ flex: 1 }, pressStyle]}>
      <Pressable
        onPressIn={onPressIn}
        onPressOut={onPressOut}
        style={[
          cardStyles.card,
          {
            backgroundColor:
              colors.scheme === "dark"
                ? "rgba(255,255,255,0.04)"
                : colors.card,
            borderColor: colors.border,
          },
        ]}
      >
        <View
          style={[
            cardStyles.iconCircle,
            {
              backgroundColor:
                colors.scheme === "dark"
                  ? "rgba(61,107,255,0.18)"
                  : "rgba(61,107,255,0.10)",
              borderColor:
                colors.scheme === "dark"
                  ? "rgba(61,107,255,0.35)"
                  : "rgba(61,107,255,0.20)",
            },
          ]}
        >
          <Text style={cardStyles.iconText}>{icon}</Text>
        </View>
        <Text style={[cardStyles.heading, { color: colors.foreground }]}>
          {heading}
        </Text>
        <Text style={[cardStyles.body, { color: colors.mutedForeground }]}>
          {body}
        </Text>
      </Pressable>
    </Animated.View>
  );
}

const cardStyles = StyleSheet.create({
  card: {
    borderRadius: 20,
    borderWidth: StyleSheet.hairlineWidth,
    padding: 18,
    gap: 8,
    flex: 1,
  },
  iconCircle: {
    width: 46,
    height: 46,
    borderRadius: 14,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 4,
  },
  iconText: { fontSize: 22 },
  heading: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
    lineHeight: 20,
  },
  body: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 19,
  },
});

// ---------------------------------------------------------------------------
// Main AboutPage component
// ---------------------------------------------------------------------------

// Bundled fallback hero stats, shown until (and if) the admin-editable values
// load from the /about endpoint, or when the endpoint is unreachable / returns
// no stats. Mirrors the web /about hero defaults.
const FALLBACK_HERO_STATS: Array<{ value: string; label: string }> = [
  { value: "120,000+", label: "Creators" },
  { value: "3+", label: "Years" },
  { value: "9+", label: "Team" },
];

export function AboutPage({
  title,
  intro,
  sections,
  founder,
  eefind,
  heroStats,
}: {
  title: string;
  intro?: string;
  sections: InfoSection[];
  founder?: FounderBlock;
  eefind?: EefindBlock;
  heroStats?: Array<{ value: string; label: string }>;
}) {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const reduceMotion = useReducedMotion();
  const [registry, notifyScroll] = useScrollRevealRegistry();
  const webBottom = Platform.OS === "web" ? 34 : 0;

  const heroOpacity = useSharedValue(reduceMotion ? 1 : 0);
  const heroTranslateY = useSharedValue(reduceMotion ? 0 : 20);

  useEffect(() => {
    if (!reduceMotion) {
      heroOpacity.value = withTiming(1, { duration: 600 });
      heroTranslateY.value = withSpring(0, { damping: 20, stiffness: 100 });
    } else {
      heroOpacity.value = 1;
      heroTranslateY.value = 0;
    }
  }, [reduceMotion, heroOpacity, heroTranslateY]);

  const heroAnimStyle = useAnimatedStyle(() => ({
    opacity: heroOpacity.value,
    transform: [{ translateY: heroTranslateY.value }],
  }));

  const isDark = colors.scheme === "dark";

  // Prefer the admin-editable stats from the /about endpoint; fall back to the
  // bundled defaults when none were provided (offline / endpoint unavailable /
  // no stats configured).
  const resolvedHeroStats =
    heroStats && heroStats.length > 0 ? heroStats : FALLBACK_HERO_STATS;

  return (
    <ScrollRevealCtx.Provider value={registry}>
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <Stack.Screen
          options={{
            title,
            headerStyle: { backgroundColor: colors.background },
            headerTitleStyle: {
              color: colors.foreground,
              fontFamily: "SpaceGrotesk_600SemiBold",
            },
            headerTintColor: colors.primary,
          }}
        />

        <ScrollView
          scrollEventThrottle={16}
          onScroll={(e) => notifyScroll(e.nativeEvent.contentOffset.y)}
          contentContainerStyle={[
            styles.content,
            { paddingBottom: insets.bottom + 40 + webBottom },
          ]}
        >
          {/* ── Hero ─────────────────────────────────────────────────── */}
          <View style={styles.heroWrapper}>
            <LinearGradient
              colors={
                isDark
                  ? [
                      "rgba(61,107,255,0.22)",
                      "rgba(110,97,255,0.12)",
                      "transparent",
                    ]
                  : [
                      "rgba(61,107,255,0.10)",
                      "rgba(110,97,255,0.06)",
                      "transparent",
                    ]
              }
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 1 }}
              style={StyleSheet.absoluteFillObject}
            />
            {/* Decorative mesh circles */}
            <View
              style={[
                styles.meshCircle,
                styles.meshCircle1,
                {
                  backgroundColor: isDark
                    ? "rgba(61,107,255,0.12)"
                    : "rgba(61,107,255,0.07)",
                },
              ]}
            />
            <View
              style={[
                styles.meshCircle,
                styles.meshCircle2,
                {
                  backgroundColor: isDark
                    ? "rgba(110,97,255,0.10)"
                    : "rgba(110,97,255,0.06)",
                },
              ]}
            />

            <Animated.View style={heroAnimStyle}>
              {/* Badge */}
              <View
                style={[
                  styles.badge,
                  {
                    backgroundColor: isDark
                      ? "rgba(61,107,255,0.15)"
                      : "rgba(61,107,255,0.10)",
                    borderColor: isDark
                      ? "rgba(61,107,255,0.30)"
                      : "rgba(61,107,255,0.20)",
                  },
                ]}
              >
                <Text style={[styles.badgeText, { color: colors.primary }]}>
                  ✦ About
                </Text>
              </View>

              <Text style={[styles.heroTitle, { color: colors.foreground }]}>
                {title}
              </Text>

              {intro ? (
                <Text
                  style={[styles.heroIntro, { color: colors.mutedForeground }]}
                >
                  {intro}
                </Text>
              ) : null}

              {/* Hero stats row — admin-editable values from the /about
                  endpoint, falling back to the bundled defaults. */}
              <ScrollReveal delay={200} direction="up" reduceMotion={reduceMotion}>
                {(revealed) => (
                  <View style={styles.heroStats}>
                    {resolvedHeroStats.map((stat, i) => (
                      <Fragment key={`${stat.label}-${i}`}>
                        {i > 0 ? (
                          <View
                            style={[
                              styles.statDivider,
                              { backgroundColor: colors.border },
                            ]}
                          />
                        ) : null}
                        <CountUpStat
                          value={stat.value}
                          label={stat.label}
                          reduceMotion={reduceMotion}
                          revealed={revealed}
                        />
                      </Fragment>
                    ))}
                  </View>
                )}
              </ScrollReveal>
            </Animated.View>
          </View>

          {/* ── Feature cards ────────────────────────────────────────── */}
          <View style={styles.featureSection}>
            <ScrollReveal delay={0} direction="up" reduceMotion={reduceMotion}>
              {() => (
                <View style={styles.sectionHeader}>
                  <Text
                    style={[
                      styles.sectionEyebrow,
                      { color: colors.primary },
                    ]}
                  >
                    WHAT YOU CAN DO
                  </Text>
                  <Text
                    style={[styles.sectionTitle, { color: colors.foreground }]}
                  >
                    Everything you need,{"\n"}one tap away
                  </Text>
                </View>
              )}
            </ScrollReveal>

            <View style={styles.featureGrid}>
              {FEATURE_CARDS.map((card, i) => (
                <ScrollReveal
                  key={card.heading}
                  delay={i * 60}
                  direction="up"
                  reduceMotion={reduceMotion}
                >
                  {() => (
                    <FeatureCard
                      icon={card.icon}
                      heading={card.heading}
                      body={card.body}
                      reduceMotion={reduceMotion}
                    />
                  )}
                </ScrollReveal>
              ))}
            </View>
          </View>

          {/* ── Story sections ───────────────────────────────────────── */}
          {sections.length > 0 ? (
            <View style={styles.storySection}>
              <ScrollReveal delay={0} direction="up" reduceMotion={reduceMotion}>
                {() => (
                  <Text
                    style={[styles.sectionEyebrow, { color: colors.primary }]}
                  >
                    OUR STORY
                  </Text>
                )}
              </ScrollReveal>
              {sections.map((s, i) => (
                <ScrollReveal
                  key={i}
                  delay={i * 80}
                  direction="up"
                  reduceMotion={reduceMotion}
                >
                  {() => (
                    <View
                      style={[
                        styles.storyCard,
                        {
                          backgroundColor: isDark
                            ? "rgba(255,255,255,0.03)"
                            : colors.card,
                          borderColor: colors.border,
                        },
                      ]}
                    >
                      {s.heading ? (
                        <Text
                          style={[styles.storyH2, { color: colors.foreground }]}
                        >
                          {s.heading}
                        </Text>
                      ) : null}
                      <Text
                        style={[
                          styles.storyBody,
                          { color: colors.mutedForeground },
                        ]}
                      >
                        {s.body}
                      </Text>
                    </View>
                  )}
                </ScrollReveal>
              ))}
            </View>
          ) : null}

          {/* ── Founder card ─────────────────────────────────────────── */}
          {founder ? (
            <ScrollReveal delay={0} direction="up" reduceMotion={reduceMotion}>
              {() => (
                <View
                  style={[
                    styles.founderCard,
                    {
                      backgroundColor: isDark
                        ? "rgba(61,107,255,0.10)"
                        : "rgba(61,107,255,0.06)",
                      borderColor: isDark
                        ? "rgba(61,107,255,0.25)"
                        : "rgba(61,107,255,0.15)",
                    },
                  ]}
                >
                  <Text
                    style={[styles.eyebrow, { color: colors.primary }]}
                  >
                    {founder.eyebrow.toUpperCase()}
                  </Text>
                  {/* Founder photo, with a letter-initial avatar fallback
                      when no photo URL is provided. */}
                  {founder.photo ? (
                    <Image
                      source={{ uri: founder.photo }}
                      style={[
                        styles.founderAvatar,
                        {
                          borderColor: isDark
                            ? "rgba(61,107,255,0.40)"
                            : "rgba(61,107,255,0.25)",
                        },
                      ]}
                      contentFit="cover"
                      transition={200}
                      accessibilityLabel={founder.name}
                    />
                  ) : (
                    <View
                      style={[
                        styles.founderAvatar,
                        {
                          backgroundColor: isDark
                            ? "rgba(61,107,255,0.20)"
                            : "rgba(61,107,255,0.12)",
                          borderColor: isDark
                            ? "rgba(61,107,255,0.40)"
                            : "rgba(61,107,255,0.25)",
                        },
                      ]}
                    >
                      <Text style={styles.founderInitial}>
                        {founder.name.charAt(0)}
                      </Text>
                    </View>
                  )}
                  <Text
                    style={[styles.founderName, { color: colors.foreground }]}
                  >
                    {founder.name}
                  </Text>
                  <Text style={[styles.founderRole, { color: colors.primary }]}>
                    {founder.role}
                  </Text>
                  <Text
                    style={[styles.founderBio, { color: colors.mutedForeground }]}
                  >
                    {founder.bio}
                  </Text>
                </View>
              )}
            </ScrollReveal>
          ) : null}

          {/* ── EEFind card ──────────────────────────────────────────── */}
          {eefind ? (
            <ScrollReveal delay={0} direction="up" reduceMotion={reduceMotion}>
              {(revealed) => (
                <View
                  style={[
                    styles.eefindCard,
                    {
                      backgroundColor: isDark
                        ? "rgba(255,255,255,0.03)"
                        : colors.card,
                      borderColor: colors.border,
                    },
                  ]}
                >
                  <Text style={[styles.eyebrow, { color: colors.primary }]}>
                    {eefind.eyebrow.toUpperCase()}
                  </Text>
                  <Text
                    style={[styles.eefindHeading, { color: colors.foreground }]}
                  >
                    {eefind.heading}
                  </Text>
                  <Text
                    style={[
                      styles.eefindBody,
                      { color: colors.mutedForeground },
                    ]}
                  >
                    {eefind.body}
                  </Text>

                  {/* Animated stat row */}
                  {eefind.stats.length > 0 ? (
                    <View style={styles.eefindStats}>
                      {eefind.stats.map((stat) => (
                        <View
                          key={stat.label}
                          style={[
                            styles.eefindStatCard,
                            {
                              backgroundColor: isDark
                                ? "rgba(255,255,255,0.05)"
                                : colors.muted,
                              borderColor: colors.border,
                            },
                          ]}
                        >
                          <CountUpStat
                            value={stat.value}
                            label={stat.label}
                            reduceMotion={reduceMotion}
                            revealed={revealed}
                          />
                        </View>
                      ))}
                    </View>
                  ) : null}

                  {/* Contact details */}
                  <View style={styles.eefindMeta}>
                    {eefind.address ? (
                      <View style={styles.metaRow}>
                        <Text
                          style={[
                            styles.metaIcon,
                            { color: colors.primary },
                          ]}
                        >
                          📍
                        </Text>
                        <Text
                          style={[
                            styles.metaValue,
                            { color: colors.mutedForeground },
                          ]}
                        >
                          {eefind.address}
                        </Text>
                      </View>
                    ) : null}
                    {eefind.email ? (
                      <Pressable
                        style={styles.metaRow}
                        onPress={() =>
                          Linking.openURL(`mailto:${eefind.email}`)
                        }
                      >
                        <Text
                          style={[styles.metaIcon, { color: colors.primary }]}
                        >
                          ✉️
                        </Text>
                        <Text
                          style={[styles.metaLink, { color: colors.primary }]}
                        >
                          {eefind.email}
                        </Text>
                      </Pressable>
                    ) : null}
                    {eefind.whatsapp ? (
                      <Pressable
                        style={styles.metaRow}
                        onPress={() =>
                          Linking.openURL(
                            `https://wa.me/${eefind.whatsapp.replace(/[^0-9]/g, "")}`,
                          )
                        }
                      >
                        <Text
                          style={[styles.metaIcon, { color: colors.primary }]}
                        >
                          💬
                        </Text>
                        <Text
                          style={[styles.metaLink, { color: colors.primary }]}
                        >
                          {eefind.whatsapp}
                        </Text>
                      </Pressable>
                    ) : null}
                    {eefind.website ? (
                      <Pressable
                        style={styles.metaRow}
                        onPress={() => Linking.openURL(eefind.websiteUrl)}
                      >
                        <Text
                          style={[styles.metaIcon, { color: colors.primary }]}
                        >
                          🌐
                        </Text>
                        <Text
                          style={[styles.metaLink, { color: colors.primary }]}
                        >
                          {eefind.website}
                        </Text>
                      </Pressable>
                    ) : null}
                  </View>
                </View>
              )}
            </ScrollReveal>
          ) : null}
        </ScrollView>
      </View>
    </ScrollRevealCtx.Provider>
  );
}

// ---------------------------------------------------------------------------
// Styles
// ---------------------------------------------------------------------------

const styles = StyleSheet.create({
  content: { gap: 20 },

  // Hero
  heroWrapper: {
    paddingHorizontal: 24,
    paddingTop: 28,
    paddingBottom: 32,
    overflow: "hidden",
    borderRadius: 0,
    gap: 0,
  },
  meshCircle: {
    position: "absolute",
    borderRadius: 9999,
  },
  meshCircle1: {
    width: 260,
    height: 260,
    top: -80,
    right: -80,
  },
  meshCircle2: {
    width: 180,
    height: 180,
    bottom: -40,
    left: -60,
  },
  badge: {
    alignSelf: "flex-start",
    borderRadius: 100,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 5,
    marginBottom: 16,
  },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
    letterSpacing: 1,
    textTransform: "uppercase",
  },
  heroTitle: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 36,
    letterSpacing: -0.8,
    lineHeight: 42,
    marginBottom: 12,
  },
  heroIntro: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 16,
    lineHeight: 25,
    marginBottom: 28,
  },
  heroStats: {
    flexDirection: "row",
    alignItems: "center",
    gap: 0,
  },
  statDivider: {
    width: 1,
    height: 36,
    marginHorizontal: 20,
  },

  // Feature section
  featureSection: {
    paddingHorizontal: 20,
    gap: 16,
  },
  sectionHeader: { gap: 6, marginBottom: 4 },
  sectionEyebrow: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 1.5,
    textTransform: "uppercase",
  },
  sectionTitle: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 24,
    letterSpacing: -0.4,
    lineHeight: 30,
  },
  featureGrid: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 12,
  },

  // Story
  storySection: {
    paddingHorizontal: 20,
    gap: 12,
  },
  storyCard: {
    borderRadius: 20,
    borderWidth: StyleSheet.hairlineWidth,
    padding: 20,
    gap: 8,
  },
  storyH2: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 18,
    lineHeight: 24,
  },
  storyBody: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    lineHeight: 23,
  },

  // Founder
  founderCard: {
    marginHorizontal: 20,
    borderRadius: 24,
    borderWidth: StyleSheet.hairlineWidth,
    padding: 24,
    gap: 6,
  },
  founderAvatar: {
    width: 72,
    height: 72,
    borderRadius: 36,
    borderWidth: 2,
    alignItems: "center",
    justifyContent: "center",
    marginTop: 12,
    marginBottom: 4,
  },
  founderInitial: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 30,
    color: "#3d6bff",
  },
  founderName: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 22,
    letterSpacing: -0.3,
  },
  founderRole: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.5,
    textTransform: "uppercase",
    marginBottom: 4,
  },
  founderBio: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    lineHeight: 22,
    marginTop: 4,
  },

  // EEFind
  eefindCard: {
    marginHorizontal: 20,
    borderRadius: 24,
    borderWidth: StyleSheet.hairlineWidth,
    padding: 24,
    gap: 6,
  },
  eefindHeading: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 20,
    letterSpacing: -0.3,
    marginTop: 4,
  },
  eefindBody: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    lineHeight: 22,
    marginTop: 4,
    marginBottom: 4,
  },
  eefindStats: {
    flexDirection: "row",
    gap: 10,
    marginTop: 14,
    marginBottom: 6,
  },
  eefindStatCard: {
    flex: 1,
    borderRadius: 16,
    borderWidth: StyleSheet.hairlineWidth,
    paddingVertical: 14,
    paddingHorizontal: 6,
    alignItems: "center",
  },
  eefindMeta: { marginTop: 16, gap: 10 },
  metaRow: { flexDirection: "row", gap: 10, alignItems: "flex-start" },
  metaIcon: { fontSize: 15, marginTop: 1 },
  metaValue: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 20,
    flex: 1,
  },
  metaLink: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    lineHeight: 20,
    flex: 1,
  },

  // Shared
  eyebrow: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 1.5,
    textTransform: "uppercase",
  },
});
