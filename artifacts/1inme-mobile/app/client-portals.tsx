import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Modal,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  createClientPortal,
  listClientPortals,
  type ClientPortal,
} from "@/lib/api/client-portals";
import { showAlert } from "@/lib/webAlert";

export default function ClientPortalsScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const [creating, setCreating] = useState(false);
  const [name, setName] = useState("");

  const q = useQuery({
    queryKey: ["client-portals"],
    queryFn: listClientPortals,
  });

  const create = useMutation({
    mutationFn: () => createClientPortal({ name: name.trim() }),
    onSuccess: (portal) => {
      setName("");
      setCreating(false);
      qc.invalidateQueries({ queryKey: ["client-portals"] });
      router.push(`/client-portals/${portal.id}` as never);
    },
    onError: (e: { message?: string }) => {
      showAlert("Couldn't create portal", e?.message ?? "Try again.");
    },
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Client portals",
          headerRight: () => (
            <Pressable
              onPress={() => setCreating(true)}
              hitSlop={10}
              style={({ pressed }) => ({ opacity: pressed ? 0.6 : 1, paddingHorizontal: 6 })}
            >
              <Feather name="plus" size={22} color={colors.primary} />
            </Pressable>
          ),
        }}
      />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : q.isError ? (
        <EmptyState
          icon="alert-circle"
          title="Couldn't load portals"
          body={(q.error as { message?: string })?.message ?? "Try again."}
        />
      ) : (
        <FlatList<ClientPortal>
          data={q.data ?? []}
          keyExtractor={(p) => String(p.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          renderItem={({ item }) => (
            <Pressable
              onPress={() =>
                router.push(`/client-portals/${item.id}` as never)
              }
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
              <View
                style={[
                  styles.swatch,
                  {
                    backgroundColor:
                      item.brand_color ?? colors.primary,
                    borderRadius: 8,
                  },
                ]}
              >
                <Text style={styles.swatchText}>
                  {(item.brand_name ?? item.name).slice(0, 1).toUpperCase()}
                </Text>
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                  {item.name}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                  {item.client_name ?? "No client linked"} ·{" "}
                  {item.is_enabled ? "Active" : "Disabled"}
                </Text>
                <Text style={[styles.meta, { color: colors.mutedForeground }]} numberOfLines={1}>
                  {item.shares_count} shares · {item.links_count} links ·{" "}
                  {item.actions_count} actions
                </Text>
              </View>
              <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
            </Pressable>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="briefcase"
              title="No client portals yet"
              body="Create a portal to share files, invoices and updates with a client."
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

      <Modal
        visible={creating}
        animationType="slide"
        transparent
        onRequestClose={() => setCreating(false)}
      >
        <View style={styles.modalBackdrop}>
          <View
            style={[
              styles.modalCard,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>
              New client portal
            </Text>
            <Text style={[styles.sub, { color: colors.mutedForeground }]}>
              Give it a name — you can add shares and send a magic link from the
              detail screen.
            </Text>
            <TextInput
              value={name}
              onChangeText={setName}
              placeholder="Portal name"
              placeholderTextColor={colors.mutedForeground}
              autoFocus
              style={[
                styles.input,
                {
                  color: colors.foreground,
                  backgroundColor: colors.background,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            />
            <View style={{ flexDirection: "row", gap: 8 }}>
              <View style={{ flex: 1 }}>
                <Button
                  label="Cancel"
                  variant="ghost"
                  onPress={() => {
                    setCreating(false);
                    setName("");
                  }}
                />
              </View>
              <View style={{ flex: 1 }}>
                <Button
                  label="Create"
                  onPress={() => create.mutate()}
                  loading={create.isPending}
                  disabled={name.trim().length < 2}
                />
              </View>
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
  swatch: { width: 40, height: 40, alignItems: "center", justifyContent: "center" },
  swatchText: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 16,
    color: "#fff",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  meta: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11, letterSpacing: 0.3 },
  modalBackdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.45)",
    justifyContent: "center",
    padding: 24,
  },
  modalCard: { padding: 20, gap: 12, borderWidth: 1 },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 18 },
  input: {
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 15,
  },
});
