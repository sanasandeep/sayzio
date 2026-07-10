import { Stack } from "expo-router";
import {
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { Platform } from "react-native";

import { useColors } from "@/hooks/useColors";
import { useReducedMotion } from "@/hooks/useReducedMotion";

import {
  ScrollReveal,
  ScrollRevealCtx,
  useScrollRevealRegistry,
} from "./ScrollReveal";

export type InfoSection = {
  heading?: string;
  body: string;
};

export type InfoStat = {
  value: string;
  label: string;
};

export type EefindBlock = {
  eyebrow: string;
  heading: string;
  body: string;
  stats: InfoStat[];
  address: string;
  email: string;
  whatsapp: string;
  website: string;
  websiteUrl: string;
};

export type FounderBlock = {
  eyebrow: string;
  name: string;
  role: string;
  bio: string;
  photo?: string;
};

export function InfoPage({
  title,
  intro,
  sections,
  founder,
  eefind,
}: {
  title: string;
  intro?: string;
  sections: InfoSection[];
  founder?: FounderBlock;
  eefind?: EefindBlock;
}) {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const reduceMotion = useReducedMotion();
  const [registry, notifyScroll] = useScrollRevealRegistry();
  const webBottom = Platform.OS === "web" ? 34 : 0;

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
            { paddingBottom: insets.bottom + 32 + webBottom },
          ]}
        >
          <ScrollReveal delay={0} direction="up" reduceMotion={reduceMotion}>
            {() => (
              <Text style={[styles.title, { color: colors.foreground }]}>
                {title}
              </Text>
            )}
          </ScrollReveal>
          {intro ? (
            <ScrollReveal delay={60} direction="up" reduceMotion={reduceMotion}>
              {() => (
                <Text style={[styles.intro, { color: colors.mutedForeground }]}>
                  {intro}
                </Text>
              )}
            </ScrollReveal>
          ) : null}
          {sections.map((s, i) => (
            <ScrollReveal
              key={i}
              delay={i * 80}
              direction="up"
              reduceMotion={reduceMotion}
            >
              {() => (
                <View style={styles.section}>
                  {s.heading ? (
                    <Text style={[styles.h2, { color: colors.foreground }]}>
                      {s.heading}
                    </Text>
                  ) : null}
                  <Text style={[styles.body, { color: colors.foreground }]}>
                    {s.body}
                  </Text>
                </View>
              )}
            </ScrollReveal>
          ))}
          {founder ? (
            <ScrollReveal delay={0} direction="up" reduceMotion={reduceMotion}>
              {() => (
                <View
                  style={[
                    styles.eefindCard,
                    { backgroundColor: colors.card, borderColor: colors.border },
                  ]}
                >
                  <Text style={[styles.eyebrow, { color: colors.primary }]}>
                    {founder.eyebrow}
                  </Text>
                  <Text style={[styles.h2, { color: colors.foreground }]}>
                    {founder.name}
                  </Text>
                  <Text style={[styles.founderRole, { color: colors.primary }]}>
                    {founder.role}
                  </Text>
                  <Text
                    style={[
                      styles.body,
                      { color: colors.mutedForeground, marginTop: 10 },
                    ]}
                  >
                    {founder.bio}
                  </Text>
                </View>
              )}
            </ScrollReveal>
          ) : null}
          {eefind ? (
            <ScrollReveal delay={0} direction="up" reduceMotion={reduceMotion}>
              {() => (
                <View
                  style={[
                    styles.eefindCard,
                    { backgroundColor: colors.card, borderColor: colors.border },
                  ]}
                >
                  <Text style={[styles.eyebrow, { color: colors.primary }]}>
                    {eefind.eyebrow}
                  </Text>
                  <Text style={[styles.h2, { color: colors.foreground }]}>
                    {eefind.heading}
                  </Text>
                  <Text
                    style={[
                      styles.body,
                      { color: colors.mutedForeground, marginTop: 8 },
                    ]}
                  >
                    {eefind.body}
                  </Text>
                  <View style={styles.statRow}>
                    {eefind.stats.map((stat) => (
                      <View
                        key={stat.label}
                        style={[
                          styles.statCard,
                          {
                            backgroundColor: colors.muted,
                            borderColor: colors.border,
                          },
                        ]}
                      >
                        <Text
                          style={[styles.statValue, { color: colors.primary }]}
                        >
                          {stat.value}
                        </Text>
                        <Text
                          style={[
                            styles.statLabel,
                            { color: colors.mutedForeground },
                          ]}
                        >
                          {stat.label}
                        </Text>
                      </View>
                    ))}
                  </View>
                  <View style={styles.eefindMeta}>
                    <Text style={[styles.metaLabel, { color: colors.foreground }]}>
                      Registered office
                    </Text>
                    <Text
                      style={[styles.metaValue, { color: colors.mutedForeground }]}
                    >
                      {eefind.address}
                    </Text>

                    <Text
                      style={[
                        styles.metaLabel,
                        { color: colors.foreground, marginTop: 12 },
                      ]}
                    >
                      Email
                    </Text>
                    <Pressable
                      onPress={() => Linking.openURL(`mailto:${eefind.email}`)}
                    >
                      <Text style={[styles.metaLink, { color: colors.primary }]}>
                        {eefind.email}
                      </Text>
                    </Pressable>

                    <Text
                      style={[
                        styles.metaLabel,
                        { color: colors.foreground, marginTop: 12 },
                      ]}
                    >
                      WhatsApp
                    </Text>
                    <Pressable
                      onPress={() =>
                        Linking.openURL(
                          `https://wa.me/${eefind.whatsapp.replace(/[^0-9]/g, "")}`,
                        )
                      }
                    >
                      <Text style={[styles.metaLink, { color: colors.primary }]}>
                        {eefind.whatsapp}
                      </Text>
                    </Pressable>

                    <Text
                      style={[
                        styles.metaLabel,
                        { color: colors.foreground, marginTop: 12 },
                      ]}
                    >
                      Website
                    </Text>
                    <Pressable onPress={() => Linking.openURL(eefind.websiteUrl)}>
                      <Text style={[styles.metaLink, { color: colors.primary }]}>
                        {eefind.website}
                      </Text>
                    </Pressable>
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

const styles = StyleSheet.create({
  content: { padding: 24, gap: 18 },
  title: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 32,
    letterSpacing: -0.5,
  },
  intro: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 17, lineHeight: 26 },
  section: { gap: 8, marginTop: 8 },
  h2: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 20 },
  body: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 16, lineHeight: 25 },
  eefindCard: {
    marginTop: 16,
    padding: 20,
    borderRadius: 20,
    borderWidth: StyleSheet.hairlineWidth,
    gap: 4,
  },
  eyebrow: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
    letterSpacing: 1.5,
    textTransform: "uppercase",
    marginBottom: 4,
  },
  statRow: { flexDirection: "row", gap: 10, marginTop: 20 },
  statCard: {
    flex: 1,
    borderRadius: 16,
    borderWidth: StyleSheet.hairlineWidth,
    paddingVertical: 14,
    paddingHorizontal: 6,
    alignItems: "center",
  },
  statValue: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 22,
    marginBottom: 2,
  },
  statLabel: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 11,
    letterSpacing: 0.5,
    textTransform: "uppercase",
    textAlign: "center",
  },
  founderRole: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
    marginTop: 2,
  },
  eefindMeta: { marginTop: 22 },
  metaLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  metaValue: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    lineHeight: 21,
    marginTop: 2,
  },
  metaLink: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
    marginTop: 2,
  },
});
