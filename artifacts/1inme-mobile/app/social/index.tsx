import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Modal,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  connectAccount,
  createProof,
  deleteProof,
  disconnect,
  listConnections,
  listProofs,
  refreshConnection,
  updateProof,
  updateSearchable,
} from "@/lib/api/social";

export default function SocialScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const [showConnect, setShowConnect] = useState(false);
  const [showProof, setShowProof] = useState(false);

  const conns = useQuery({ queryKey: ["social", "conns"], queryFn: listConnections });
  const proofs = useQuery({ queryKey: ["social", "proofs"], queryFn: listProofs });

  const refresh = useMutation({
    mutationFn: (id: number) => refreshConnection(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["social", "conns"] }),
  });
  const remove = useMutation({
    mutationFn: (id: number) => disconnect(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["social", "conns"] }),
  });
  const toggleSearchable = useMutation({
    mutationFn: ({ id, on }: { id: number; on: boolean }) => updateSearchable(id, on),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["social", "conns"] }),
  });
  const toggleProof = useMutation({
    mutationFn: ({ id, on }: { id: number; on: boolean }) =>
      updateProof(id, { is_active: on }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["social", "proofs"] }),
  });
  const removeProof = useMutation({
    mutationFn: (id: number) => deleteProof(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["social", "proofs"] }),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Social",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
        }}
      />
      <ScrollView
        contentContainerStyle={{ padding: 20, gap: 16, paddingBottom: 40 }}
        refreshControl={
          <RefreshControl
            refreshing={conns.isFetching || proofs.isFetching}
            onRefresh={() => {
              conns.refetch();
              proofs.refetch();
            }}
            tintColor={colors.primary}
          />
        }
      >
        <SectionHeader
          icon="link-2"
          title="Social accounts"
          actionLabel="Connect"
          onAction={() => setShowConnect(true)}
        />
        {conns.isLoading ? (
          <ActivityIndicator color={colors.primary} />
        ) : (conns.data?.items ?? []).length === 0 ? (
          <Text style={[styles.empty, { color: colors.mutedForeground }]}>
            No social accounts connected yet.
          </Text>
        ) : (
          conns.data?.items.map((c) => (
            <View
              key={c.id}
              style={[
                styles.connCard,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            >
              <View style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
                <View style={{ flex: 1 }}>
                  <Text style={[styles.name, { color: colors.foreground }]}>
                    {c.platform_label} · @{c.handle}
                  </Text>
                  <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                    {c.follower_count.toLocaleString()} followers
                    {c.last_refresh_status === "error" ? " · sync error" : ""}
                  </Text>
                </View>
                <Pressable
                  onPress={() => refresh.mutate(c.id)}
                  hitSlop={6}
                  style={{ paddingHorizontal: 8 }}
                >
                  <Feather name="refresh-cw" size={16} color={colors.primary} />
                </Pressable>
                <Pressable
                  onPress={() =>
                    Alert.alert("Disconnect?", `Remove @${c.handle}?`, [
                      { text: "Cancel", style: "cancel" },
                      {
                        text: "Remove",
                        style: "destructive",
                        onPress: () => remove.mutate(c.id),
                      },
                    ])
                  }
                  hitSlop={6}
                  style={{ paddingHorizontal: 8 }}
                >
                  <Feather name="x" size={16} color={colors.destructive} />
                </Pressable>
              </View>

              <View
                style={[
                  styles.searchableRow,
                  { borderTopColor: colors.border },
                ]}
              >
                <View style={{ flex: 1 }}>
                  <Text style={[styles.name, { color: colors.foreground, fontSize: 13 }]}>
                    Searchable in public
                  </Text>
                  <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                    {c.sync_summary?.label ?? "Not synced anywhere yet"}
                  </Text>
                </View>
                <Switch
                  value={c.is_searchable}
                  onValueChange={(on) => toggleSearchable.mutate({ id: c.id, on })}
                  trackColor={{ true: colors.primary }}
                />
              </View>
            </View>
          ))
        )}

        <View style={{ height: 8 }} />
        <SectionHeader
          icon="zap"
          title="Social proof"
          actionLabel="New"
          onAction={() => setShowProof(true)}
        />
        {proofs.isLoading ? (
          <ActivityIndicator color={colors.primary} />
        ) : (proofs.data?.items ?? []).length === 0 ? (
          <Text style={[styles.empty, { color: colors.mutedForeground }]}>
            No social proof notifications yet.
          </Text>
        ) : (
          proofs.data?.items.map((p) => (
            <View
              key={p.id}
              style={[
                styles.card,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            >
              <View style={{ flex: 1 }}>
                <Text style={[styles.name, { color: colors.foreground }]}>
                  {p.name}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                  {p.type_label} · {p.impressions.toLocaleString()} views
                </Text>
              </View>
              <Switch
                value={p.is_active}
                onValueChange={(on) => toggleProof.mutate({ id: p.id, on })}
                trackColor={{ true: colors.primary }}
              />
              <Pressable
                onPress={() =>
                  Alert.alert("Delete?", `Remove "${p.name}"?`, [
                    { text: "Cancel", style: "cancel" },
                    {
                      text: "Delete",
                      style: "destructive",
                      onPress: () => removeProof.mutate(p.id),
                    },
                  ])
                }
                hitSlop={6}
                style={{ paddingLeft: 8 }}
              >
                <Feather name="x" size={16} color={colors.destructive} />
              </Pressable>
            </View>
          ))
        )}
      </ScrollView>

      <ConnectModal
        visible={showConnect}
        onClose={() => setShowConnect(false)}
        platforms={conns.data?.platforms ?? []}
        onSubmit={async (p, h) => {
          await connectAccount({ platform: p, handle: h });
          qc.invalidateQueries({ queryKey: ["social", "conns"] });
        }}
      />
      <ProofModal
        visible={showProof}
        onClose={() => setShowProof(false)}
        types={proofs.data?.types ?? []}
        onSubmit={async (name, type) => {
          await createProof({ name, type });
          qc.invalidateQueries({ queryKey: ["social", "proofs"] });
        }}
      />
    </View>
  );
}

function SectionHeader({
  icon,
  title,
  actionLabel,
  onAction,
}: {
  icon: keyof typeof Feather.glyphMap;
  title: string;
  actionLabel: string;
  onAction: () => void;
}) {
  const colors = useColors();
  return (
    <View style={styles.sectionHead}>
      <Feather name={icon} size={18} color={colors.primary} />
      <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
        {title}
      </Text>
      <View style={{ flex: 1 }} />
      <Pressable
        onPress={onAction}
        style={[styles.addBtn, { borderColor: colors.primary }]}
      >
        <Feather name="plus" size={14} color={colors.primary} />
        <Text style={{ color: colors.primary, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 }}>
          {actionLabel}
        </Text>
      </Pressable>
    </View>
  );
}

function ConnectModal({
  visible,
  onClose,
  platforms,
  onSubmit,
}: {
  visible: boolean;
  onClose: () => void;
  platforms: { platform: string; label: string }[];
  onSubmit: (p: string, h: string) => Promise<void>;
}) {
  const colors = useColors();
  const [platform, setPlatform] = useState("");
  const [handle, setHandle] = useState("");
  const [busy, setBusy] = useState(false);

  return (
    <Modal visible={visible} animationType="slide" presentationStyle="pageSheet" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: colors.background, padding: 20, gap: 14 }}>
        <Text style={[styles.modalTitle, { color: colors.foreground }]}>
          Connect a social account
        </Text>
        <ScrollView contentContainerStyle={{ gap: 8, paddingBottom: 8 }}>
          {platforms.map((p) => (
            <Pressable
              key={p.platform}
              onPress={() => setPlatform(p.platform)}
              style={[
                styles.platformItem,
                {
                  backgroundColor:
                    platform === p.platform ? colors.primary + "1c" : colors.card,
                  borderColor:
                    platform === p.platform ? colors.primary : colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            >
              <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_500Medium" }}>
                {p.label}
              </Text>
            </Pressable>
          ))}
        </ScrollView>
        <TextField
          label="Handle"
          value={handle}
          onChangeText={setHandle}
          placeholder="@yourname"
          autoCapitalize="none"
        />
        <View style={{ flexDirection: "row", gap: 10 }}>
          <View style={{ flex: 1 }}>
            <Button label="Cancel" variant="outline" onPress={onClose} />
          </View>
          <View style={{ flex: 1 }}>
            <Button
              label={busy ? "Connecting…" : "Connect"}
              disabled={busy || !platform || !handle.trim()}
              onPress={async () => {
                setBusy(true);
                try {
                  await onSubmit(platform, handle.trim());
                  setHandle("");
                  setPlatform("");
                  onClose();
                } catch (e: any) {
                  Alert.alert("Failed", e?.message ?? "Try again");
                } finally {
                  setBusy(false);
                }
              }}
            />
          </View>
        </View>
      </View>
    </Modal>
  );
}

function ProofModal({
  visible,
  onClose,
  types,
  onSubmit,
}: {
  visible: boolean;
  onClose: () => void;
  types: { type: string; label: string }[];
  onSubmit: (name: string, type: string) => Promise<void>;
}) {
  const colors = useColors();
  const [name, setName] = useState("");
  const [type, setType] = useState("");
  const [busy, setBusy] = useState(false);

  return (
    <Modal visible={visible} animationType="slide" presentationStyle="pageSheet" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: colors.background, padding: 20, gap: 14 }}>
        <Text style={[styles.modalTitle, { color: colors.foreground }]}>
          New social proof
        </Text>
        <TextField label="Name" value={name} onChangeText={setName} placeholder="Holiday sale promo" />
        <ScrollView contentContainerStyle={{ gap: 8, paddingBottom: 8 }}>
          {types.map((t) => (
            <Pressable
              key={t.type}
              onPress={() => setType(t.type)}
              style={[
                styles.platformItem,
                {
                  backgroundColor:
                    type === t.type ? colors.primary + "1c" : colors.card,
                  borderColor: type === t.type ? colors.primary : colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            >
              <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_500Medium" }}>
                {t.label}
              </Text>
            </Pressable>
          ))}
        </ScrollView>
        <View style={{ flexDirection: "row", gap: 10 }}>
          <View style={{ flex: 1 }}>
            <Button label="Cancel" variant="outline" onPress={onClose} />
          </View>
          <View style={{ flex: 1 }}>
            <Button
              label={busy ? "Creating…" : "Create"}
              disabled={busy || !name.trim() || !type}
              onPress={async () => {
                setBusy(true);
                try {
                  await onSubmit(name.trim(), type);
                  setName("");
                  setType("");
                  onClose();
                } catch (e: any) {
                  Alert.alert("Failed", e?.message ?? "Try again");
                } finally {
                  setBusy(false);
                }
              }}
            />
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  sectionHead: { flexDirection: "row", alignItems: "center", gap: 8 },
  sectionTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  addBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 4,
  },
  card: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    padding: 14,
    borderWidth: 1,
  },
  connCard: {
    padding: 14,
    borderWidth: 1,
  },
  searchableRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    marginTop: 10,
    paddingTop: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, marginTop: 2 },
  empty: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 22 },
  platformItem: { padding: 14, borderWidth: 1 },
});
