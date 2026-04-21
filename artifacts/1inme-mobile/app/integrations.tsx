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
  deleteIntegration,
  listIntegrations,
  type Integration,
} from "@/lib/api/integrations";

const ICON: Record<string, keyof typeof Feather.glyphMap> = {
  email: "mail",
  sms: "message-square",
  whatsapp: "message-circle",
  payment: "credit-card",
  storage: "hard-drive",
  analytics: "bar-chart-2",
};

export default function IntegrationsScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const q = useQuery({ queryKey: ["integrations"], queryFn: listIntegrations });

  const remove = useMutation({
    mutationFn: (id: number) => deleteIntegration(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["integrations"] }),
  });

  const confirmRemove = (i: Integration) => {
    const label = i.name || `${i.provider ?? i.kind}`;
    const go = () => remove.mutate(i.id);
    if (Platform.OS === "web") {
      if (confirm(`Disconnect ${label}?`)) go();
    } else {
      Alert.alert("Disconnect?", label, [
        { text: "Cancel", style: "cancel" },
        { text: "Disconnect", style: "destructive", onPress: go },
      ]);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Integrations" }} />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList<Integration>
          data={q.data ?? []}
          keyExtractor={(i) => String(i.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          renderItem={({ item }) => (
            <View
              style={[
                styles.row,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <View style={[styles.iconWrap, { backgroundColor: colors.primary + "1c" }]}>
                <Feather name={ICON[item.kind] ?? "link"} size={18} color={colors.primary} />
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                  {item.name || item.provider || item.kind}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                  {item.kind}
                  {item.provider ? ` • ${item.provider}` : ""}
                  {item.is_default ? " • default" : ""}
                </Text>
              </View>
              {!item.is_active ? (
                <View style={[styles.badge, { backgroundColor: colors.muted ?? colors.border }]}>
                  <Text style={[styles.badgeText, { color: colors.mutedForeground }]}>off</Text>
                </View>
              ) : null}
              <Pressable onPress={() => confirmRemove(item)} hitSlop={6}>
                <Feather name="trash-2" size={18} color={colors.destructive} />
              </Pressable>
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="link"
              title="No integrations yet"
              body="Connect SMTP, WhatsApp, payments and storage from the web to enable advanced features."
            />
          }
          ListFooterComponent={
            (q.data?.length ?? 0) > 0 ? (
              <Text style={[styles.footer, { color: colors.mutedForeground }]}>
                Add new integrations from the web — most need an OAuth or credentials flow that lives there.
              </Text>
            ) : null
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
  badge: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999 },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 10,
    letterSpacing: 0.3,
    textTransform: "uppercase",
  },
  footer: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    textAlign: "center",
    marginTop: 16,
  },
});
