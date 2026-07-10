import { useQuery } from "@tanstack/react-query";
import { LinearGradient } from "expo-linear-gradient";
import { useRouter } from "expo-router";
import {
  ActivityIndicator,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { EmptyState } from "@/components/EmptyState";
import { LinkRow } from "@/components/LinkRow";
import { StatTile } from "@/components/StatTile";
import { MissingNameReminder } from "@/components/MissingNameReminder";
import { VerifyEmailReminder } from "@/components/VerifyEmailReminder";
import { useAuth } from "@/contexts/AuthContext";
import { TOP_BAR_H, useTabBar, useTabBarBottomInset } from "@/contexts/TabBarContext";
import { useColors } from "@/hooks/useColors";
import { getDashboard } from "@/lib/api/dashboard";

export default function Home() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { user } = useAuth();
  const { reportScroll } = useTabBar();
  const tabBarBottomInset = useTabBarBottomInset();
  const webTop = Platform.OS === "web" ? 0 : 0;

  const q = useQuery({ queryKey: ["dashboard"], queryFn: getDashboard });
  const refreshing = q.isFetching && !q.isLoading;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <LinearGradient
        colors={[colors.primary + "1c", "transparent"]}
        start={{ x: 0, y: 0 }}
        end={{ x: 0, y: 0.5 }}
        style={StyleSheet.absoluteFill}
      />
      <ScrollView
        contentContainerStyle={{
          paddingTop: insets.top + TOP_BAR_H + 16 + webTop,
          paddingBottom: tabBarBottomInset,
          paddingHorizontal: 20,
          gap: 20,
        }}
        scrollEventThrottle={16}
        onScroll={(e) => reportScroll(e.nativeEvent.contentOffset.y)}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => q.refetch()}
            tintColor={colors.primary}
          />
        }
      >
        <View>
          <Text style={[styles.eyebrow, { color: colors.mutedForeground }]}>
            Welcome back
          </Text>
          <Text style={[styles.h1, { color: colors.foreground }]}>
            {user?.display_name || user?.email || user?.mobile || "Member"}
          </Text>
        </View>

        <VerifyEmailReminder />
        <MissingNameReminder />

        {q.isLoading ? (
          <View style={{ paddingVertical: 32 }}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : q.error ? (
          <Text style={{ color: colors.destructive }}>
            Couldn't load dashboard.
          </Text>
        ) : (
          <>
            <View style={styles.tileRow}>
              <StatTile
                label="Total clicks"
                value={q.data?.totals?.total_clicks ?? 0}
                icon="bar-chart-2"
                hint={`${q.data?.totals?.unique_clicks ?? 0} unique`}
              />
              <StatTile
                label="Active links"
                value={q.data?.totals?.active_links ?? 0}
                icon="link"
                hint={`of ${q.data?.totals?.links ?? 0} total`}
              />
            </View>
            <View style={styles.tileRow}>
              <Pressable
                onPress={() => router.push("/(tabs)/links")}
                style={{ flex: 1 }}
                accessibilityRole="button"
                accessibilityLabel="NFC writes — tap to write a tag"
              >
                <StatTile
                  label="NFC writes"
                  value={q.data?.totals?.nfc_writes ?? 0}
                  icon="wifi"
                  hint="Tap to write a tag"
                />
              </Pressable>
              <StatTile
                label="Followers"
                value={q.data?.totals?.followers ?? 0}
                icon="users"
              />
            </View>

            {q.data?.top_link ? (
              <View style={styles.section}>
                <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
                  Top performer
                </Text>
                <LinkRow link={q.data.top_link} />
              </View>
            ) : null}

            <View style={styles.section}>
              <View style={styles.sectionHeader}>
                <Text
                  style={[styles.sectionLabel, { color: colors.mutedForeground }]}
                >
                  Recent links
                </Text>
                <Pressable onPress={() => router.push("/(tabs)/links")}>
                  <Text style={[styles.seeAll, { color: colors.primary }]}>
                    See all
                  </Text>
                </Pressable>
              </View>
              {q.data?.recent_links?.length ? (
                <View style={{ gap: 10 }}>
                  {q.data.recent_links.map((l) => (
                    <LinkRow key={l.id} link={l} />
                  ))}
                </View>
              ) : (
                <EmptyState
                  icon="link"
                  title="No links yet"
                  body="Tap Create to make your first short link or Link in Bio."
                />
              )}
            </View>
          </>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  eyebrow: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  h1: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 28, marginTop: 2 },
  tileRow: { flexDirection: "row", gap: 12 },
  section: { gap: 10 },
  sectionHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  sectionLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  seeAll: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
});
