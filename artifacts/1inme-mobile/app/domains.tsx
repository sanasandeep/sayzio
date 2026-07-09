import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Modal,
  Platform,
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
import {
  addDomain,
  deleteDomain,
  listAvailableDomains,
  listDomains,
  makePrimaryDomain,
  type Domain,
} from "@/lib/api/domains";
import { showAlert } from "@/lib/webAlert";

export default function DomainsScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const [showNew, setShowNew] = useState(false);
  const [host, setHost] = useState("");
  const [error, setError] = useState<string | undefined>();

  const q = useQuery({ queryKey: ["domains"], queryFn: listDomains });
  const availQ = useQuery({
    queryKey: ["domains-available"],
    queryFn: listAvailableDomains,
  });

  const setPrimary = useMutation({
    mutationFn: (id: number) => makePrimaryDomain(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["domains-available"] }),
    onError: (e: any) =>
      showAlert("Couldn't update", e?.message ?? "Please try again."),
  });

  const globalDomains = (availQ.data?.items ?? []).filter((d) => d.is_global);
  const canManage = availQ.data?.can_manage ?? false;
  const defaultHost = availQ.data?.default_host ?? null;
  const hasPrimary = globalDomains.some((d) => d.is_primary);

  const add = useMutation({
    mutationFn: () => addDomain(host.trim().toLowerCase()),
    onSuccess: () => {
      setShowNew(false);
      setHost("");
      setError(undefined);
      qc.invalidateQueries({ queryKey: ["domains"] });
    },
    onError: (e: any) => {
      const msg = e?.errors?.domain?.[0] ?? e?.message ?? "Could not add";
      setError(msg);
    },
  });

  const remove = useMutation({
    mutationFn: (id: number) => deleteDomain(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["domains"] }),
  });

  const confirmRemove = (d: Domain) => {
    const go = () => remove.mutate(d.id);
    if (Platform.OS === "web") {
      if (confirm(`Remove ${d.domain}?`)) go();
    } else {
      showAlert("Remove domain?", d.domain, [
        { text: "Cancel", style: "cancel" },
        { text: "Remove", style: "destructive", onPress: go },
      ]);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Custom domains",
          headerRight: () => (
            <Pressable onPress={() => setShowNew(true)} hitSlop={8} style={{ paddingRight: 12 }}>
              <Feather name="plus" size={20} color={colors.primary} />
            </Pressable>
          ),
        }}
      />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList<Domain>
          data={q.data ?? []}
          keyExtractor={(d) => String(d.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          ListHeaderComponent={
            globalDomains.length > 0 || defaultHost ? (
              <View style={{ gap: 10, marginBottom: 18 }}>
                <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
                  Platform domains
                </Text>
                {defaultHost && !hasPrimary ? (
                  <Text style={[styles.cname, { color: colors.mutedForeground }]}>
                    New links default to {defaultHost}.
                  </Text>
                ) : null}
                {globalDomains.map((d) => (
                  <View
                    key={d.id}
                    style={[
                      styles.row,
                      { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
                    ]}
                  >
                    <View style={[styles.iconWrap, { backgroundColor: colors.primary + "1c" }]}>
                      <Feather name="server" size={18} color={colors.primary} />
                    </View>
                    <View style={{ flex: 1, gap: 4 }}>
                      <Text style={[styles.host, { color: colors.foreground }]} numberOfLines={1}>
                        {d.domain}
                      </Text>
                      {d.is_primary ? (
                        <View style={[styles.badge, { alignSelf: "flex-start", backgroundColor: colors.primary + "33" }]}>
                          <Text style={[styles.badgeText, { color: colors.primary }]}>primary</Text>
                        </View>
                      ) : canManage ? (
                        <Pressable onPress={() => setPrimary.mutate(d.id)} disabled={setPrimary.isPending}>
                          <Text style={[styles.cname, { color: colors.primary }]}>Make primary</Text>
                        </Pressable>
                      ) : null}
                    </View>
                  </View>
                ))}
              </View>
            ) : null
          }
          renderItem={({ item }) => (
            <View
              style={[
                styles.row,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <View style={[styles.iconWrap, { backgroundColor: colors.primary + "1c" }]}>
                <Feather name="globe" size={18} color={colors.primary} />
              </View>
              <View style={{ flex: 1, gap: 4 }}>
                <Text style={[styles.host, { color: colors.foreground }]} numberOfLines={1}>
                  {item.domain}
                </Text>
                <View style={{ flexDirection: "row", gap: 6, flexWrap: "wrap" }}>
                  <View
                    style={[
                      styles.badge,
                      { backgroundColor: (item.is_verified ? colors.success : colors.mutedForeground) + "33" },
                    ]}
                  >
                    <Text
                      style={[
                        styles.badgeText,
                        { color: item.is_verified ? colors.success : colors.mutedForeground },
                      ]}
                    >
                      {item.is_verified ? "verified" : "pending"}
                    </Text>
                  </View>
                  {item.cname_target ? (
                    <Text style={[styles.cname, { color: colors.mutedForeground }]}>
                      CNAME → {item.cname_target}
                    </Text>
                  ) : null}
                </View>
              </View>
              <Pressable onPress={() => confirmRemove(item)} hitSlop={6}>
                <Feather name="trash-2" size={18} color={colors.destructive} />
              </Pressable>
            </View>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="globe"
              title="No custom domains yet"
              body="Point a domain you own at Sayzio to host your Link in Bio pages on your own URL."
              action={<Button label="Add domain" onPress={() => setShowNew(true)} />}
            />
          }
          ListFooterComponent={
            (q.data?.length ?? 0) > 0 ? (
              <Text style={[styles.footer, { color: colors.mutedForeground }]}>
                Finish DNS verification on the web — your registrar may need a CNAME or TXT record.
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

      <Modal visible={showNew} animationType="slide" transparent onRequestClose={() => setShowNew(false)}>
        <View style={styles.modalBackdrop}>
          <View
            style={[
              styles.modalCard,
              { backgroundColor: colors.background, borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>Add domain</Text>
            <TextField
              label="Domain"
              value={host}
              onChangeText={setHost}
              autoCapitalize="none"
              autoCorrect={false}
              keyboardType="url"
              placeholder="links.example.com"
              error={error}
            />
            <View style={{ flexDirection: "row", gap: 8 }}>
              <Button label="Cancel" variant="outline" onPress={() => setShowNew(false)} style={{ flex: 1 }} />
              <Button
                label="Add"
                onPress={() => add.mutate()}
                loading={add.isPending}
                disabled={!host.trim()}
                style={{ flex: 1 }}
              />
            </View>
          </View>
        </View>
      </Modal>
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
  host: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sectionLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  badge: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999 },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 10,
    letterSpacing: 0.3,
    textTransform: "uppercase",
  },
  cname: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11 },
  footer: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    textAlign: "center",
    marginTop: 16,
  },
  modalBackdrop: { flex: 1, backgroundColor: "rgba(0,0,0,0.5)", justifyContent: "flex-end" },
  modalCard: { padding: 20, gap: 14, borderTopWidth: 1 },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 22 },
});
