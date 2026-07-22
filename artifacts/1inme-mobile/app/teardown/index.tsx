import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import { handlePlanLockedError } from "@/lib/upgradePrompt";
import { teardown, type Teardown } from "@/lib/api/teardown";
import { showAlert } from "@/lib/webAlert";

/**
 * "Competitor Biolink Teardown" — mobile parity for the web
 * links-teardown flow. Paste a competitor's public page URL, AI fetches
 * and scores it, then optionally hands the findings to the AI biolink
 * builder to assemble a better version. Charging/gating live server-side.
 */
export default function TeardownIndexScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const [url, setUrl] = useState("");

  const q = useQuery({
    queryKey: ["teardown", "index"],
    queryFn: () => teardown.index(),
  });

  const analyzeM = useMutation({
    mutationFn: (u: string) => teardown.analyze(u),
    onSuccess: (t) => {
      setUrl("");
      qc.invalidateQueries({ queryKey: ["teardown", "index"] });
      router.push(`/teardown/${t.id}` as never);
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      showAlert(
        "Couldn't analyze that page",
        e?.message ?? "Double-check the URL and try again.",
      );
    },
  });

  if (q.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ headerShown: true, title: "Competitor Teardown" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  const data = q.data;

  if (q.isError || !data) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ headerShown: true, title: "Competitor Teardown" }} />
        <Text style={{ color: colors.mutedForeground, marginBottom: 12 }}>
          Couldn't load the teardown tool.
        </Text>
        <Button label="Retry" onPress={() => q.refetch()} />
      </View>
    );
  }

  if (!data.ai_enabled) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ headerShown: true, title: "Competitor Teardown" }} />
        <Feather name="zap-off" size={28} color={colors.mutedForeground} />
        <Text style={{ color: colors.foreground, fontWeight: "600", marginTop: 12 }}>
          Teardown is unavailable
        </Text>
        <Text style={{ color: colors.mutedForeground, textAlign: "center", marginTop: 6 }}>
          AI generation is currently turned off. Try again later.
        </Text>
      </View>
    );
  }

  const urlLooksValid = /^https?:\/\/.+\..+/i.test(url.trim());

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: "Competitor Teardown" }} />
      <FlatList<Teardown>
        data={data.items}
        keyExtractor={(t) => String(t.id)}
        contentContainerStyle={{ padding: 20, gap: 12 }}
        refreshControl={
          <RefreshControl
            refreshing={q.isFetching && !q.isLoading}
            onRefresh={() => q.refetch()}
            tintColor={colors.primary}
          />
        }
        ListHeaderComponent={
          <View style={{ gap: 12, marginBottom: 8 }}>
            <Text style={[styles.intro, { color: colors.mutedForeground }]}>
              Paste a competitor's public Link in Bio (or any page) URL. AI
              will score it and suggest what your page should do better;
              then you can build an improved version in one tap.
            </Text>
            <TextField
              label="Competitor page URL"
              placeholder="https://example.com/@theirpage"
              value={url}
              onChangeText={setUrl}
              autoCapitalize="none"
              autoCorrect={false}
              keyboardType="url"
              hint={`${data.balance} coins available`}
            />
            {!data.allowed ? (
              <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                Competitor Biolink Teardown isn't available on your current
                plan yet.
              </Text>
            ) : null}
            <Button
              label={analyzeM.isPending ? "Analyzing…" : "Analyze competitor"}
              loading={analyzeM.isPending}
              disabled={!urlLooksValid || analyzeM.isPending}
              onPress={() => analyzeM.mutate(url.trim())}
            />
            {data.items.length > 0 ? (
              <Text style={[styles.sectionTitle, { color: colors.mutedForeground }]}>
                Recent teardowns
              </Text>
            ) : null}
          </View>
        }
        renderItem={({ item }) => (
          <Pressable
            onPress={() => router.push(`/teardown/${item.id}` as never)}
            style={({ pressed }) => [
              styles.row,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
                opacity: pressed ? 0.7 : 1,
              },
            ]}
          >
            <View style={[styles.iconWrap, { backgroundColor: colors.primary + "1c" }]}>
              <Feather name="crosshair" size={18} color={colors.primary} />
            </View>
            <View style={{ flex: 1, gap: 2 }}>
              <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                {item.competitor_url}
              </Text>
              <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                {item.status === "completed" && item.analysis
                  ? `Score ${item.analysis.overall_score}/100`
                  : item.status === "failed"
                    ? "Failed"
                    : "Pending"}
              </Text>
            </View>
            <Feather name="chevron-right" size={16} color={colors.mutedForeground} />
          </Pressable>
        )}
        ListEmptyComponent={
          <EmptyState
            icon="crosshair"
            title="No teardowns yet"
            body="Paste a competitor's page URL above to run your first teardown."
          />
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  intro: { fontSize: 13, lineHeight: 18 },
  sectionTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 13,
    letterSpacing: 0.4,
    textTransform: "uppercase",
    marginTop: 8,
  },
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14, borderWidth: 1 },
  iconWrap: {
    width: 38,
    height: 38,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11, letterSpacing: 0.4 },
});
