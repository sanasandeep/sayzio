import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import {
  ActivityIndicator,
  Alert,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { handlePlanLockedError } from "@/lib/upgradePrompt";
import { teardown } from "@/lib/api/teardown";

function Bullets({
  items,
  color,
}: {
  items: string[];
  color: string;
}) {
  if (!items.length) {
    return <Text style={{ color, fontSize: 13 }}>None found.</Text>;
  }
  return (
    <View style={{ gap: 6 }}>
      {items.map((t, i) => (
        <View key={i} style={{ flexDirection: "row", gap: 8 }}>
          <Text style={{ color }}>{"\u2022"}</Text>
          <Text style={{ color, fontSize: 13, flex: 1, lineHeight: 18 }}>{t}</Text>
        </View>
      ))}
    </View>
  );
}

/**
 * Scored teardown results — mobile parity for the web
 * links-teardown/{id} view. Adds a "Build a better version" action that
 * hands the findings off to the AI biolink builder server-side, landing on
 * the freshly-built page's block editor.
 */
export default function TeardownDetailScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const { id } = useLocalSearchParams<{ id: string }>();
  const teardownId = Number(id);

  const q = useQuery({
    queryKey: ["teardown", "show", teardownId],
    queryFn: () => teardown.show(teardownId),
    enabled: Number.isFinite(teardownId),
    refetchInterval: (query) =>
      query.state.data?.status === "pending" ? 3000 : false,
  });

  const buildM = useMutation({
    mutationFn: () => teardown.build(teardownId),
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ["teardown", "index"] });
      Alert.alert("Page built", `Created @${res.alias}.`, [
        {
          text: "Open editor",
          onPress: () => router.replace(`/links/${res.link_id}/blocks` as any),
        },
        { text: "Close", style: "cancel" },
      ]);
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      Alert.alert(
        "Couldn't build the page",
        e?.message ?? "Please try again in a moment.",
      );
    },
  });

  if (q.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ headerShown: true, title: "Teardown" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  const t = q.data;

  if (q.isError || !t) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ headerShown: true, title: "Teardown" }} />
        <Text style={{ color: colors.mutedForeground, marginBottom: 12 }}>
          Couldn't load this teardown.
        </Text>
        <Button label="Retry" onPress={() => q.refetch()} />
      </View>
    );
  }

  if (t.status === "pending") {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ headerShown: true, title: "Teardown" }} />
        <ActivityIndicator color={colors.primary} />
        <Text style={{ color: colors.mutedForeground, marginTop: 12, textAlign: "center" }}>
          Fetching and scoring {t.competitor_url}…
        </Text>
      </View>
    );
  }

  if (t.status === "failed" || !t.analysis) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ headerShown: true, title: "Teardown" }} />
        <Feather name="alert-circle" size={28} color={colors.destructive} />
        <Text style={{ color: colors.foreground, fontWeight: "600", marginTop: 12 }}>
          Teardown failed
        </Text>
        <Text style={{ color: colors.mutedForeground, textAlign: "center", marginTop: 6 }}>
          {t.error ?? "We couldn't analyze that page."}
        </Text>
      </View>
    );
  }

  const a = t.analysis;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: "Teardown" }} />
      <ScrollView contentContainerStyle={styles.body}>
        <Text style={[styles.url, { color: colors.mutedForeground }]} numberOfLines={2}>
          {t.competitor_url}
        </Text>

        <View
          style={[
            styles.scoreCard,
            { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
          ]}
        >
          <Text style={[styles.scoreNum, { color: colors.primary }]}>{a.overall_score}</Text>
          <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>/ 100 overall score</Text>
        </View>

        {a.summary ? (
          <Text style={{ color: colors.foreground, fontSize: 14, lineHeight: 20 }}>
            {a.summary}
          </Text>
        ) : null}

        <View style={{ gap: 8 }}>
          <Text style={[styles.section, { color: colors.foreground }]}>Strengths</Text>
          <Bullets items={a.strengths} color={colors.mutedForeground} />
        </View>

        <View style={{ gap: 8 }}>
          <Text style={[styles.section, { color: colors.foreground }]}>Weaknesses</Text>
          <Bullets items={a.weaknesses} color={colors.mutedForeground} />
        </View>

        <View style={{ gap: 8 }}>
          <Text style={[styles.section, { color: colors.foreground }]}>Missing elements</Text>
          <Bullets items={a.missing_elements} color={colors.mutedForeground} />
        </View>

        <View style={{ gap: 8 }}>
          <Text style={[styles.section, { color: colors.foreground }]}>
            Call to action ({a.cta.present ? `${a.cta.quality_score}/100` : "missing"})
          </Text>
          {a.cta.feedback ? (
            <Text style={{ color: colors.mutedForeground, fontSize: 13, lineHeight: 18 }}>
              {a.cta.feedback}
            </Text>
          ) : null}
        </View>

        <View style={{ gap: 8 }}>
          <Text style={[styles.section, { color: colors.foreground }]}>Recommendations</Text>
          <Bullets items={a.recommendations} color={colors.mutedForeground} />
        </View>

        {t.built_link_id ? (
          <Button
            label="Open the page we built"
            variant="ghost"
            onPress={() => router.push(`/links/${t.built_link_id}/blocks` as any)}
          />
        ) : (
          <Button
            label={buildM.isPending ? "Building…" : "Build a better version with AI"}
            loading={buildM.isPending}
            onPress={() => buildM.mutate()}
          />
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  body: { padding: 16, gap: 16 },
  url: { fontSize: 12 },
  scoreCard: {
    alignItems: "center",
    padding: 20,
    borderWidth: 1,
    gap: 2,
  },
  scoreNum: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 40 },
  section: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 14,
    letterSpacing: 0.2,
  },
});
