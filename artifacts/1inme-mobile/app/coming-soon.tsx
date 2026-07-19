import { Feather } from "@expo/vector-icons";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useLocalSearchParams, useRouter } from "expo-router";
import { useMemo, useState } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { useFeatureStates } from "@/hooks/useFeatureStates";
import { errorStatus } from "@/lib/api";
import {
  featherIconFor,
  featureStates,
  type FeatureState,
} from "@/lib/api/featureStates";

/**
 * Branded "Coming soon" preview screen — the mobile parity of the web
 * `user/coming-soon.blade.php`. Opened when a user taps a menu item whose
 * feature resolves to `coming_soon`. Shows the feature's capabilities, an
 * "Available soon" badge, and a "Notify me" button that records deduped
 * interest via `POST /api/v1/feature-states/{key}/notify`.
 */
export default function ComingSoonScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const queryClient = useQueryClient();
  const { key } = useLocalSearchParams<{ key?: string }>();

  const { byKey, isLoading } = useFeatureStates();
  const feature: FeatureState | null = key ? (byKey.get(key) ?? null) : null;

  // Optimistic local flag so the button reflects the deduped state the moment
  // the server confirms (created OR already-existing both mean "on the list").
  const [notifiedLocal, setNotifiedLocal] = useState<boolean | null>(null);
  const notified = notifiedLocal ?? feature?.notified ?? false;

  const tint = feature?.tint ?? colors.primary;
  const icon = useMemo(
    () => (feature ? featherIconFor(feature.key) : "clock"),
    [feature],
  );

  const mutation = useMutation({
    mutationFn: () => featureStates.notify(key ?? ""),
    onSuccess: () => {
      setNotifiedLocal(true);
      queryClient.invalidateQueries({ queryKey: ["feature-states"] });
    },
    onError: (err: unknown) => {
      // A 401 means the token lapsed — surface nothing destructive; the
      // button simply stays actionable. Any other failure is non-fatal.
      if (errorStatus(err) === 401) return;
    },
  });

  if (isLoading && !feature) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Text style={{ color: colors.mutedForeground }}>Loading…</Text>
      </View>
    );
  }

  if (!feature) {
    return (
      <View
        style={[
          styles.center,
          { backgroundColor: colors.background, paddingHorizontal: 24 },
        ]}
      >
        <Feather name="clock" size={28} color={colors.mutedForeground} />
        <Text
          style={[styles.title, { color: colors.foreground, marginTop: 12 }]}
        >
          This feature isn’t available yet
        </Text>
        <Button
          label="Go back"
          variant="outline"
          onPress={() => router.back()}
          style={{ marginTop: 20 }}
        />
      </View>
    );
  }

  return (
    <ScrollView
      style={{ backgroundColor: colors.background }}
      contentContainerStyle={[
        styles.content,
        { paddingBottom: insets.bottom + 32 },
      ]}
    >
      <View
        style={[
          styles.card,
          { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
        ]}
      >
        <View
          style={[styles.iconWrap, { backgroundColor: tint + "1a" }]}
        >
          <Feather name={icon} size={26} color={tint} />
        </View>

        <View style={[styles.soonPill, { borderColor: colors.border, backgroundColor: colors.muted }]}>
          <Feather name="clock" size={11} color={colors.mutedForeground} />
          <Text style={[styles.soonPillText, { color: colors.mutedForeground }]}>
            COMING SOON
          </Text>
        </View>

        <Text style={[styles.title, { color: colors.foreground }]}>
          {feature.label} is available soon
        </Text>
        <Text style={[styles.blurb, { color: colors.mutedForeground }]}>
          {feature.blurb}
        </Text>

        {feature.capabilities.length > 0 ? (
          <View
            style={[
              styles.capsBox,
              { borderColor: colors.border, backgroundColor: colors.background },
            ]}
          >
            <Text style={[styles.capsHeading, { color: colors.foreground }]}>
              What you’ll be able to do
            </Text>
            {feature.capabilities.map((cap) => (
              <View key={cap} style={styles.capRow}>
                <Feather name="check" size={14} color={tint} style={{ marginTop: 2 }} />
                <Text style={[styles.capText, { color: colors.mutedForeground }]}>
                  {cap}
                </Text>
              </View>
            ))}
          </View>
        ) : null}

        {notified ? (
          <View
            style={[
              styles.notifiedRow,
              { borderColor: colors.border, backgroundColor: colors.muted },
            ]}
          >
            <Feather name="bell-off" size={14} color={tint} />
            <Text style={[styles.notifiedText, { color: colors.foreground }]}>
              You’re on the list; we’ll let you know
            </Text>
          </View>
        ) : (
          <Button
            label="Notify me when it’s ready"
            onPress={() => mutation.mutate()}
            loading={mutation.isPending}
            style={{ marginTop: 20, alignSelf: "stretch" }}
            leading={
              <Feather name="bell" size={16} color={colors.primaryForeground} />
            }
          />
        )}

        <Button
          label="Back to menu"
          variant="outline"
          onPress={() => router.back()}
          style={{ marginTop: 12, alignSelf: "stretch" }}
          leading={
            <Feather name="arrow-left" size={16} color={colors.foreground} />
          }
        />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  content: { padding: 16 },
  card: {
    borderWidth: StyleSheet.hairlineWidth,
    padding: 24,
    alignItems: "center",
  },
  iconWrap: {
    height: 56,
    width: 56,
    borderRadius: 18,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 14,
  },
  soonPill: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 5,
    borderRadius: 999,
    borderWidth: StyleSheet.hairlineWidth,
  },
  soonPillText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.6,
  },
  title: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 18,
    textAlign: "center",
    marginTop: 16,
  },
  blurb: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    lineHeight: 20,
    textAlign: "center",
    marginTop: 8,
  },
  capsBox: {
    alignSelf: "stretch",
    borderWidth: StyleSheet.hairlineWidth,
    borderRadius: 14,
    padding: 16,
    marginTop: 20,
  },
  capsHeading: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 14,
    marginBottom: 10,
  },
  capRow: { flexDirection: "row", alignItems: "flex-start", gap: 8, marginTop: 6 },
  capText: {
    flex: 1,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 19,
  },
  notifiedRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    alignSelf: "stretch",
    justifyContent: "center",
    borderWidth: StyleSheet.hairlineWidth,
    borderRadius: 14,
    paddingVertical: 14,
    marginTop: 20,
  },
  notifiedText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 13,
  },
});
