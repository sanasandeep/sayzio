import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Platform,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  deleteSplashPage,
  listSplashPages,
  type SplashPage,
} from "@/lib/api/splash";

export default function SplashPagesScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const q = useQuery({ queryKey: ["splash-pages"], queryFn: listSplashPages });

  const remove = useMutation({
    mutationFn: (id: number) => deleteSplashPage(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["splash-pages"] }),
  });

  const confirmRemove = (s: SplashPage) => {
    const go = () => remove.mutate(s.id);
    if (Platform.OS === "web") {
      if (confirm(`Delete splash page “${s.name}”?`)) go();
    } else {
      Alert.alert("Delete splash page?", s.name, [
        { text: "Cancel", style: "cancel" },
        { text: "Delete", style: "destructive", onPress: go },
      ]);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Splash pages" }} />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList<SplashPage>
          data={q.data ?? []}
          keyExtractor={(s) => String(s.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          renderItem={({ item }) => (
            <View
              style={[
                styles.row,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <View style={[styles.iconWrap, { backgroundColor: colors.primary + "1c" }]}>
                <Feather name="layout" size={18} color={colors.primary} />
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                  {item.name}
                </Text>
                {item.title || item.cta_label ? (
                  <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={2}>
                    {[item.title, item.cta_label].filter(Boolean).join(" • ")}
                  </Text>
                ) : null}
              </View>
              <Pressable onPress={() => confirmRemove(item)} hitSlop={6}>
                <Feather name="trash-2" size={18} color={colors.destructive} />
              </Pressable>
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="layout"
              title="No splash pages yet"
              body="Create splash pages on the web to show a branded interstitial before redirecting visitors."
            />
          }
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14, borderWidth: 1 },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
});
