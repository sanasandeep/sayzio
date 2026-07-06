import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import { Pressable, StyleSheet, Text, View } from "react-native";

import { useAuth } from "@/contexts/AuthContext";
import { setPendingPostAuthNext } from "@/lib/authNext";
import {
  pairingCreatePath,
  pairingIcon,
  type LinkTypePairing,
} from "@/lib/linkPairings";

/**
 * "Perfect pairings" cross-promo module for public link-type pages — the
 * mobile mirror of common/partials/link-type-pairings.blade.php.
 *
 * Each card is its own audience-aware CTA that deep-links into the create
 * flow for THAT pairing's type: a logged-in creator goes straight to the
 * type-specific create screen; a guest is stashed a post-auth redirect and
 * routed into the signup/OTP flow, landing on the create screen afterwards.
 *
 * Renders nothing when there are no pairings, so callers can drop it in
 * unconditionally.
 *
 * `theme` picks a fixed palette that reads well on the host page's own
 * background (public pages don't follow the app's light/dark preference):
 *   - "dark"  → event / biolink / reviews
 *   - "light" → restaurant menu / store menu / resume
 * When `theme` is "biolink", `fontColor` (the page's own text/accent color)
 * drives the palette so the module blends into a creator's custom page.
 */
export type LinkTypePairingsProps = {
  pairings: LinkTypePairing[] | null | undefined;
  theme?: "dark" | "light" | "biolink";
  fontColor?: string | null;
};

type Palette = {
  text: string;
  muted: string;
  cardBg: string;
  cardBorder: string;
  iconBg: string;
  iconColor: string;
  link: string;
};

function paletteFor(
  theme: "dark" | "light" | "biolink",
  fontColor?: string | null,
): Palette {
  if (theme === "biolink") {
    const fc = fontColor || "#ffffff";
    return {
      text: fc,
      muted: fc + "aa",
      cardBg: fc + "0d",
      cardBorder: fc + "1f",
      iconBg: fc + "15",
      iconColor: fc,
      link: fc,
    };
  }
  if (theme === "light") {
    return {
      text: "#111827",
      muted: "rgba(17,24,39,.62)",
      cardBg: "#ffffff",
      cardBorder: "rgba(0,0,0,.08)",
      iconBg: "rgba(61,107,255,.1)",
      iconColor: "#3d6bff",
      link: "#3d6bff",
    };
  }
  return {
    text: "#f4f4f8",
    muted: "#9aa0ad",
    cardBg: "rgba(255,255,255,.05)",
    cardBorder: "rgba(255,255,255,.1)",
    iconBg: "rgba(255,255,255,.08)",
    iconColor: "#c7c9d9",
    link: "#8ea2ff",
  };
}

export function LinkTypePairings({
  pairings,
  theme = "dark",
  fontColor,
}: LinkTypePairingsProps) {
  const router = useRouter();
  const { token } = useAuth();
  const loggedIn = !!token;

  if (!pairings || pairings.length === 0) return null;

  const p = paletteFor(theme, fontColor);

  const onPress = async (type: string) => {
    const path = pairingCreatePath(type);
    if (loggedIn) {
      router.push(path as never);
      return;
    }
    // Guest: remember where to land, then send them through signup/OTP.
    await setPendingPostAuthNext(path);
    router.push("/(auth)" as never);
  };

  return (
    <View style={styles.section} accessibilityLabel="Perfect pairings">
      <Text style={[styles.heading, { color: p.text }]}>Perfect pairings</Text>
      <View style={styles.grid}>
        {pairings.map((item, i) => (
          <Pressable
            key={`${item.type}-${i}`}
            onPress={() => onPress(item.type)}
            style={({ pressed }) => [
              styles.card,
              {
                backgroundColor: p.cardBg,
                borderColor: p.cardBorder,
                opacity: pressed ? 0.85 : 1,
              },
            ]}
            accessibilityRole="button"
            accessibilityLabel={`${loggedIn ? "Create" : "Sign up to create"} ${item.name}`}
          >
            <View style={[styles.iconWrap, { backgroundColor: p.iconBg }]}>
              <Feather
                name={pairingIcon(item.type)}
                size={15}
                color={p.iconColor}
              />
            </View>
            <View style={styles.cardBody}>
              <Text style={[styles.cardName, { color: p.text }]}>
                {item.name}
              </Text>
              <Text style={[styles.cardBenefit, { color: p.muted }]}>
                {item.benefit}
              </Text>
              <Text style={[styles.cardCta, { color: p.link }]}>
                {loggedIn ? "Create it →" : "Sign up free →"}
              </Text>
            </View>
          </Pressable>
        ))}
      </View>
      <Text style={[styles.footnote, { color: p.muted }]}>
        {loggedIn
          ? "Tap a card to start building it."
          : "Free to start, no credit card."}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  section: {
    width: "100%",
    maxWidth: 880,
    alignSelf: "center",
    marginTop: 36,
    paddingHorizontal: 4,
  },
  heading: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 13.5,
    letterSpacing: 0.7,
    textTransform: "uppercase",
    textAlign: "center",
    marginBottom: 14,
    opacity: 0.85,
  },
  grid: {
    gap: 12,
  },
  card: {
    flexDirection: "row",
    gap: 12,
    alignItems: "flex-start",
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
  },
  iconWrap: {
    width: 34,
    height: 34,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
  },
  cardBody: { flex: 1, minWidth: 0 },
  cardName: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 13 },
  cardBenefit: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 11.5,
    lineHeight: 16,
    marginTop: 2,
  },
  cardCta: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 11,
    marginTop: 6,
  },
  footnote: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 11.5,
    textAlign: "center",
    marginTop: 16,
  },
});
