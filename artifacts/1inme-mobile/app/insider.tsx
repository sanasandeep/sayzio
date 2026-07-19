import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Platform,
  Pressable,
  ScrollView,
  Share,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import { getBaseUrl } from "@/lib/api";
import { getProfile } from "@/lib/api/profile";
import { showAlert } from "@/lib/webAlert";

export default function InsiderScreen() {
  const colors = useColors();
  const [copied, setCopied] = useState(false);

  const q = useQuery({ queryKey: ["profile-insider"], queryFn: getProfile });

  const handle = q.data?.handle ?? null;
  const referralUrl = handle
    ? `${getBaseUrl()}/${handle}?ref=${handle}`
    : null;

  const onCopy = async () => {
    if (!referralUrl) return;
    try {
      if (
        Platform.OS === "web" &&
        typeof navigator !== "undefined" &&
        navigator.clipboard
      ) {
        await navigator.clipboard.writeText(referralUrl);
      } else {
        // Fallback to the share sheet on native — gives the user a way
        // to send the link without pulling in another dependency.
        await Share.share({ message: referralUrl });
      }
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch (e) {
      const msg = e instanceof Error ? e.message : "Could not copy";
      showAlert("Couldn't copy", msg);
    }
  };

  const onShare = async () => {
    if (!referralUrl) return;
    try {
      await Share.share({
        message: `Join me on Sayzio: ${referralUrl}`,
        url: Platform.OS === "ios" ? referralUrl : undefined,
      });
    } catch {
      /* user cancelled */
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Insider & referrals" }} />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : q.isError ? (
        <EmptyState
          icon="alert-circle"
          title="Couldn't load your profile"
          body={(q.error as { message?: string })?.message ?? "Try again."}
        />
      ) : !handle ? (
        <EmptyState
          icon="user"
          title="Set a handle to start referring"
          body="Pick a Sayzio handle from the profile screen; your referral link uses it."
        />
      ) : (
        <ScrollView contentContainerStyle={styles.body}>
          <View
            style={[
              styles.hero,
              {
                backgroundColor: colors.primary + "12",
                borderColor: colors.primary + "33",
                borderRadius: colors.radius,
              },
            ]}
          >
            <View style={[styles.iconWrap, { backgroundColor: colors.primary + "20" }]}>
              <Feather name="award" size={26} color={colors.primary} />
            </View>
            <Text style={[styles.title, { color: colors.foreground }]}>
              Your referral link
            </Text>
            <Text style={[styles.blurb, { color: colors.mutedForeground }]}>
              Share this link: when someone signs up through it, you both
              unlock insider perks on your next plan.
            </Text>
            <Pressable
              onPress={onCopy}
              style={({ pressed }) => [
                styles.linkBox,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius - 4,
                  opacity: pressed ? 0.7 : 1,
                },
              ]}
            >
              <Text
                style={[styles.linkText, { color: colors.foreground }]}
                numberOfLines={1}
              >
                {referralUrl}
              </Text>
              <Feather
                name={copied ? "check" : "copy"}
                size={16}
                color={colors.primary}
              />
            </Pressable>
            <View style={{ flexDirection: "row", gap: 8 }}>
              <Button label="Copy link" variant="secondary" onPress={onCopy} style={{ flex: 1 }} />
              <Button label="Share" onPress={onShare} style={{ flex: 1 }} />
            </View>
          </View>

          <View
            style={[
              styles.statsRow,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            {[
              { label: "Sign-ups", value: 0 },
              { label: "Conversions", value: 0 },
              { label: "Earned", value: "$0" },
            ].map((s, i) => (
              <View
                key={s.label}
                style={[
                  styles.stat,
                  i > 0 && {
                    borderLeftWidth: StyleSheet.hairlineWidth,
                    borderLeftColor: colors.border,
                  },
                ]}
              >
                <Text style={[styles.statValue, { color: colors.foreground }]}>
                  {s.value}
                </Text>
                <Text style={[styles.statLabel, { color: colors.mutedForeground }]}>
                  {s.label}
                </Text>
              </View>
            ))}
          </View>

          <Text style={[styles.hint, { color: colors.mutedForeground }]}>
            Detailed referral history and tier progress live on the web for now;
            we'll surface them natively in a future update.
          </Text>
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  body: { padding: 20, gap: 16, paddingBottom: 40 },
  hero: { padding: 20, gap: 10, borderWidth: 1 },
  iconWrap: {
    width: 52,
    height: 52,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 4,
  },
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 20 },
  blurb: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 20 },
  linkBox: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: 12,
    borderWidth: 1,
  },
  linkText: { flex: 1, fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  statsRow: { flexDirection: "row", borderWidth: 1, paddingVertical: 12 },
  stat: { flex: 1, alignItems: "center", paddingVertical: 4, gap: 4 },
  statValue: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 20 },
  statLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 11,
    letterSpacing: 0.5,
    textTransform: "uppercase",
  },
  hint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 18,
    paddingHorizontal: 4,
    textAlign: "center",
  },
});
