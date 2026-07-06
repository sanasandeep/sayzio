import { Feather } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import { Pressable, StyleSheet, Text, View } from "react-native";

import { useAuth } from "@/contexts/AuthContext";
import { type EventConnectionTip } from "@/lib/api/events";
import { setPendingPostAuthNext } from "@/lib/authNext";
import { pairingCreatePath, pairingIcon } from "@/lib/linkPairings";

/**
 * "10x your connections" coaching module for event screens — the mobile
 * mirror of common/partials/event-connection-tips.blade.php.
 *
 * Distinct from LinkTypePairings ("Perfect pairings"): those are factual
 * cross-promo cards, these are encouraging, benefit-led tips coaching the
 * host on turning one-time attendees into lasting followers/contacts
 * (link-in-bio, vCard, calendar, reviews).
 *
 * Each card is audience-aware and deep-links into the create flow for its
 * suggested type: a logged-in creator goes straight to the type-specific
 * create screen; a guest is stashed a post-auth redirect and routed into the
 * signup/OTP flow, landing on the create screen afterwards.
 *
 * Renders nothing when there are no tips, so callers can drop it in
 * unconditionally.
 *
 * `compact` (default false) shows a tighter, 2-card version for the event
 * detail screen so it doesn't crowd event details — mirroring the web
 * public event page's compact variant.
 */
export type EventConnectionTipsProps = {
  tips: EventConnectionTip[] | null | undefined;
  compact?: boolean;
};

export function EventConnectionTips({
  tips,
  compact = false,
}: EventConnectionTipsProps) {
  const router = useRouter();
  const { token } = useAuth();
  const loggedIn = !!token;

  if (!tips || tips.length === 0) return null;

  const list = compact ? tips.slice(0, 2) : tips;
  if (list.length === 0) return null;

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
    <View style={styles.section} accessibilityLabel="Tips to grow your connections">
      <View style={styles.header}>
        <View style={styles.eyebrowRow}>
          <Feather name="zap" size={12} color={ACCENT} />
          <Text style={styles.eyebrow}>10x your connections</Text>
        </View>
        <Text style={styles.heading}>
          {compact
            ? "Turn attendees into lasting connections"
            : "Make every event work harder for you"}
        </Text>
        {!compact ? (
          <Text style={styles.sub}>
            A few small additions turn one-time attendees into followers,
            contacts and reviewers you can reach again.
          </Text>
        ) : null}
      </View>
      <View style={styles.grid}>
        {list.map((item, i) => (
          <Pressable
            key={`${item.type}-${i}`}
            onPress={() => onPress(item.type)}
            style={({ pressed }) => [styles.card, { opacity: pressed ? 0.85 : 1 }]}
            accessibilityRole="button"
            accessibilityLabel={`${loggedIn ? "" : "Sign up to "}${item.cta}`}
          >
            <View style={styles.iconWrap}>
              <Feather name={pairingIcon(item.type)} size={15} color={ICON} />
            </View>
            <Text style={styles.cardTitle}>{item.title}</Text>
            <Text style={styles.cardTip}>{item.tip}</Text>
            <Text style={styles.cardCta}>{item.cta} →</Text>
          </Pressable>
        ))}
      </View>
    </View>
  );
}

// These event modules sit on the app's dark event surfaces, so — like the
// web partial's fixed "dark" theme — the palette is a fixed blue-tinted dark
// scheme rather than following the app's light/dark preference.
const ACCENT = "#8fa8ff";
const ICON = "#a3b3ff";
const TEXT = "#f4f4f8";
const MUTED = "#9aa0ad";

const styles = StyleSheet.create({
  section: {
    width: "100%",
    maxWidth: 880,
    alignSelf: "center",
    marginTop: 36,
    paddingHorizontal: 4,
  },
  header: { alignItems: "center", marginBottom: 14 },
  eyebrowRow: { flexDirection: "row", alignItems: "center", gap: 5 },
  eyebrow: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 11.5,
    letterSpacing: 0.7,
    textTransform: "uppercase",
    color: ACCENT,
  },
  heading: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 16,
    color: TEXT,
    textAlign: "center",
    marginTop: 6,
  },
  sub: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 17,
    color: MUTED,
    textAlign: "center",
    marginTop: 6,
    maxWidth: 480,
  },
  grid: { gap: 12 },
  card: {
    padding: 16,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "rgba(110,97,255,.28)",
    backgroundColor: "rgba(61,107,255,.1)",
    gap: 8,
  },
  iconWrap: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: "rgba(110,97,255,.18)",
    alignItems: "center",
    justifyContent: "center",
  },
  cardTitle: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 13.5,
    lineHeight: 18,
    color: TEXT,
  },
  cardTip: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 18,
    color: MUTED,
  },
  cardCta: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 11.5,
    color: ACCENT,
    marginTop: 2,
  },
});
