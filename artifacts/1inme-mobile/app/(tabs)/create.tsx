import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import { useEffect, useRef, useState } from "react";
import {
  AccessibilityInfo,
  Animated,
  Easing,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { LinkTypeArt } from "@/components/LinkTypeArt";
import { useColors } from "@/hooks/useColors";
import {
  LINK_KIND_CATEGORIES,
  metaForKind,
  type LinkKind,
} from "@/lib/linkKinds";

export default function CreateTab() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const webTop = Platform.OS === "web" ? 67 : 0;

  // Respect the OS "reduce motion" setting — when on, cards render in their
  // final position with no reveal animation.
  const [reduceMotion, setReduceMotion] = useState(false);
  useEffect(() => {
    let mounted = true;
    AccessibilityInfo.isReduceMotionEnabled().then((on) => {
      if (mounted) setReduceMotion(on);
    });
    const sub = AccessibilityInfo.addEventListener(
      "reduceMotionChanged",
      (on) => setReduceMotion(on),
    );
    return () => {
      mounted = false;
      sub.remove();
    };
  }, []);

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.background }}
      contentContainerStyle={{
        paddingTop: insets.top + 16 + webTop,
        paddingBottom: 32,
        paddingHorizontal: 20,
        gap: 24,
      }}
    >
      <View>
        <Text style={[styles.eyebrow, { color: colors.mutedForeground }]}>
          Pick a kind
        </Text>
        <Text style={[styles.title, { color: colors.foreground }]}>
          Create a new link
        </Text>
        <Text style={[styles.subtitle, { color: colors.mutedForeground }]}>
          Pick one to continue — we&apos;ll only ask for the fields that matter
          for that type.
        </Text>
      </View>

      <Pressable
        onPress={() => router.push("/links/wizard" as any)}
        style={({ pressed }) => [
          styles.wizardCard,
          {
            backgroundColor: colors.primary + "14",
            borderColor: colors.primary + "55",
            borderRadius: colors.radius,
            opacity: pressed ? 0.9 : 1,
          },
        ]}
      >
        <View
          style={[
            styles.wizardBadge,
            { backgroundColor: colors.primary + "26" },
          ]}
        >
          <Feather name="zap" size={18} color={colors.primary} />
        </View>
        <View style={{ flex: 1, gap: 2 }}>
          <Text style={[styles.wizardTitle, { color: colors.foreground }]}>
            Build a Link in Bio with the guided wizard
          </Text>
          <Text
            style={[styles.wizardBlurb, { color: colors.mutedForeground }]}
          >
            Answer a few questions and we&apos;ll auto-generate a tailored page
            for you.
          </Text>
        </View>
        <Feather name="chevron-right" size={20} color={colors.primary} />
      </Pressable>

      {(() => {
        let cardIndex = 0;
        return LINK_KIND_CATEGORIES.map((category) => (
          <View key={category.label} style={{ gap: 12 }}>
            <View style={{ gap: 2 }}>
              <Text
                style={[styles.categoryLabel, { color: colors.foreground }]}
              >
                {category.label}
              </Text>
              <Text
                style={[styles.categoryDesc, { color: colors.mutedForeground }]}
              >
                {category.desc}
              </Text>
            </View>

            <View style={{ gap: 10 }}>
              {category.kinds.map((kind) => {
                const meta = metaForKind(kind as LinkKind);
                return (
                  <RevealCard
                    key={meta.kind}
                    index={cardIndex++}
                    reduceMotion={reduceMotion}
                  >
                    <Pressable
                      onPress={() =>
                        router.push(`/links/create/${meta.kind}`)
                      }
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
                      <View style={styles.artWrap}>
                        <LinkTypeArt
                          kind={meta.kind}
                          width={72}
                          height={43}
                        />
                      </View>
                      <View style={{ flex: 1, gap: 2 }}>
                        <View style={styles.cardTitleRow}>
                          <View
                            style={[
                              styles.iconBadge,
                              { backgroundColor: colors.primary + "1c" },
                            ]}
                          >
                            <Feather
                              name={meta.icon}
                              size={14}
                              color={colors.primary}
                            />
                          </View>
                          <Text
                            style={[
                              styles.cardTitle,
                              { color: colors.foreground },
                            ]}
                          >
                            {meta.label}
                          </Text>
                        </View>
                        <Text
                          style={[
                            styles.cardBlurb,
                            { color: colors.mutedForeground },
                          ]}
                        >
                          {meta.blurb}
                        </Text>
                      </View>
                      <Feather
                        name="chevron-right"
                        size={20}
                        color={colors.mutedForeground}
                      />
                    </Pressable>
                  </RevealCard>
                );
              })}
            </View>
          </View>
        ));
      })()}
    </ScrollView>
  );
}

function RevealCard({
  index,
  reduceMotion,
  children,
}: {
  index: number;
  reduceMotion: boolean;
  children: React.ReactNode;
}) {
  const anim = useRef(new Animated.Value(reduceMotion ? 1 : 0)).current;

  useEffect(() => {
    if (reduceMotion) {
      anim.setValue(1);
      return;
    }
    anim.setValue(0);
    const animation = Animated.timing(anim, {
      toValue: 1,
      duration: 360,
      delay: Math.min(index * 45, 540),
      easing: Easing.out(Easing.cubic),
      useNativeDriver: true,
    });
    animation.start();
    return () => animation.stop();
  }, [anim, index, reduceMotion]);

  return (
    <Animated.View
      style={{
        opacity: anim,
        transform: [
          {
            translateY: anim.interpolate({
              inputRange: [0, 1],
              outputRange: [12, 0],
            }),
          },
        ],
      }}
    >
      {children}
    </Animated.View>
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
  subtitle: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 18,
    marginTop: 6,
  },
  wizardCard: {
    flexDirection: "row",
    alignItems: "center",
    gap: 14,
    padding: 16,
    borderWidth: 1,
  },
  wizardBadge: {
    width: 38,
    height: 38,
    borderRadius: 11,
    alignItems: "center",
    justifyContent: "center",
  },
  wizardTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  wizardBlurb: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 16,
  },
  categoryLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 16 },
  categoryDesc: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 16,
  },
  card: {
    flexDirection: "row",
    alignItems: "center",
    gap: 14,
    padding: 14,
    borderWidth: 1,
  },
  artWrap: {
    width: 72,
    height: 43,
    borderRadius: 10,
    overflow: "hidden",
    alignItems: "center",
    justifyContent: "center",
  },
  cardTitleRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  iconBadge: {
    width: 24,
    height: 24,
    borderRadius: 7,
    alignItems: "center",
    justifyContent: "center",
  },
  cardTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 16, flex: 1 },
  cardBlurb: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 18,
  },
});
