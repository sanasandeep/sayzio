import { Feather } from "@expo/vector-icons";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Platform,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import { useWorkspace } from "@/contexts/WorkspaceContext";
import { handlePlanLockedError } from "@/lib/upgradePrompt";
import {
  createWorkspace,
  listWorkspaces,
  workspaceFeatherIcon,
  WORKSPACE_COLOR_CHOICES,
  WORKSPACE_ICON_CHOICES,
  type Workspace,
} from "@/lib/api/workspaces";

export default function WorkspacesScreen() {
  const colors = useColors();
  const router = useRouter();
  const queryClient = useQueryClient();
  const { switchWorkspace, refresh } = useWorkspace();

  const q = useQuery({ queryKey: ["workspaces"], queryFn: listWorkspaces });

  const [creating, setCreating] = useState(false);
  const [name, setName] = useState("");
  const [icon, setIcon] = useState<string>("users");
  const [color, setColor] = useState<string>(WORKSPACE_COLOR_CHOICES[0]);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const trimmed = name.trim();
  const canCreate = trimmed.length > 0 && trimmed.length <= 120 && !saving;

  const resetForm = () => {
    setName("");
    setIcon("users");
    setColor(WORKSPACE_COLOR_CHOICES[0]);
    setError(null);
  };

  const onCreate = async () => {
    if (!canCreate) return;
    setSaving(true);
    setError(null);
    try {
      const ws = await createWorkspace({ name: trimmed, icon, color });
      // Refresh every surface that reads the workspace list, then switch into
      // the new workspace so the switcher lands on it immediately.
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["workspaces"] }),
        queryClient.invalidateQueries({ queryKey: ["workspaces-list"] }),
      ]);
      refresh();
      await switchWorkspace(ws);
      setCreating(false);
      resetForm();
    } catch (e) {
      // A plan cap (402 / plan_upgrade_required) opens the upgrade prompt
      // instead of a raw error, mirroring the web "Upgrade for more
      // workspaces" affordance in the switcher.
      if (handlePlanLockedError(e)) {
        setSaving(false);
        return;
      }
      const msg =
        e && typeof e === "object" && typeof (e as { message?: unknown }).message === "string"
          ? (e as { message: string }).message
          : "Couldn't create workspace. Please try again.";
      setError(msg);
    } finally {
      setSaving(false);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Workspaces" }} />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList<Workspace>
          data={q.data ?? []}
          keyExtractor={(w) => String(w.id)}
          contentContainerStyle={{ padding: 20, gap: 10 }}
          renderItem={({ item }) => (
            <Pressable
              onPress={() => router.push(`/workspace-members?id=${item.id}&name=${encodeURIComponent(item.name)}` as never)}
              style={[
                styles.row,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
              ]}
            >
              <View
                style={[
                  styles.iconWrap,
                  { backgroundColor: (item.color ?? colors.primary) + "26" },
                ]}
              >
                <Feather
                  name={workspaceFeatherIcon(item)}
                  size={18}
                  color={item.color ?? colors.primary}
                />
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                  {item.name}
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]} numberOfLines={1}>
                  {item.is_personal ? "Personal workspace" : "Team workspace"}
                  {item.slug ? ` • ${item.slug}` : ""}
                </Text>
              </View>
              {item.is_owner ? (
                <Pressable
                  onPress={() => router.push(`/workspace-edit?id=${item.id}` as never)}
                  hitSlop={8}
                  style={({ pressed }) => [
                    styles.gear,
                    { backgroundColor: pressed ? colors.muted : "transparent" },
                  ]}
                  accessibilityRole="button"
                  accessibilityLabel={`Edit workspace ${item.name}`}
                >
                  <Feather name="edit-2" size={16} color={colors.mutedForeground} />
                </Pressable>
              ) : null}
              <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
            </Pressable>
          )}
          ListEmptyComponent={
            <EmptyState
              icon="users"
              title="No workspaces yet"
              body="Workspaces let you collaborate with teammates on the same Link in Bio pages, posts and contacts."
            />
          }
          ListFooterComponent={
            <View style={{ marginTop: 16, gap: 12 }}>
              {creating ? (
                <View
                  style={[
                    styles.createCard,
                    {
                      backgroundColor: colors.card,
                      borderColor: colors.border,
                      borderRadius: colors.radius,
                    },
                  ]}
                >
                  <Text style={[styles.createTitle, { color: colors.foreground }]}>
                    New workspace
                  </Text>

                  <View style={{ gap: 8 }}>
                    <Text style={[styles.label, { color: colors.foreground }]}>Name</Text>
                    <TextInput
                      value={name}
                      onChangeText={setName}
                      placeholder="e.g. Marketing team"
                      placeholderTextColor={colors.mutedForeground}
                      maxLength={120}
                      style={[
                        styles.input,
                        {
                          backgroundColor: colors.background,
                          borderColor: colors.border,
                          borderRadius: colors.radius,
                          color: colors.foreground,
                        },
                      ]}
                    />
                  </View>

                  <View style={{ gap: 10 }}>
                    <Text style={[styles.label, { color: colors.foreground }]}>Icon</Text>
                    <View style={styles.grid}>
                      {WORKSPACE_ICON_CHOICES.map((key) => {
                        const selected = key === icon;
                        return (
                          <Pressable
                            key={key}
                            onPress={() => setIcon(key)}
                            accessibilityRole="button"
                            accessibilityLabel={`Icon ${key}`}
                            accessibilityState={{ selected }}
                            style={[
                              styles.iconChoice,
                              {
                                backgroundColor: selected ? color + "22" : colors.background,
                                borderColor: selected ? color : colors.border,
                                borderWidth: selected ? 2 : 1,
                              },
                            ]}
                          >
                            <Feather
                              name={workspaceFeatherIcon({ icon: key, is_personal: false })}
                              size={18}
                              color={selected ? color : colors.mutedForeground}
                            />
                          </Pressable>
                        );
                      })}
                    </View>
                  </View>

                  <View style={{ gap: 10 }}>
                    <Text style={[styles.label, { color: colors.foreground }]}>Color</Text>
                    <View style={styles.grid}>
                      {WORKSPACE_COLOR_CHOICES.map((c) => {
                        const selected = c === color;
                        return (
                          <Pressable
                            key={c}
                            onPress={() => setColor(c)}
                            accessibilityRole="button"
                            accessibilityLabel={`Color ${c}`}
                            accessibilityState={{ selected }}
                            style={[
                              styles.colorChoice,
                              {
                                backgroundColor: c,
                                borderColor: selected ? colors.foreground : "transparent",
                                borderWidth: selected ? 3 : 0,
                              },
                            ]}
                          >
                            {selected ? <Feather name="check" size={14} color="#fff" /> : null}
                          </Pressable>
                        );
                      })}
                    </View>
                  </View>

                  {error ? (
                    <Text style={[styles.error, { color: colors.destructive ?? "#ef4444" }]}>
                      {error}
                    </Text>
                  ) : null}

                  <View style={styles.createActions}>
                    <Pressable
                      onPress={onCreate}
                      disabled={!canCreate}
                      style={[
                        styles.createBtn,
                        {
                          backgroundColor: canCreate ? colors.primary : colors.muted,
                          borderRadius: colors.radius,
                          opacity: canCreate ? 1 : 0.7,
                        },
                      ]}
                      accessibilityRole="button"
                      accessibilityLabel="Create workspace"
                    >
                      {saving ? (
                        <ActivityIndicator color="#fff" />
                      ) : (
                        <Text style={styles.createBtnText}>Create</Text>
                      )}
                    </Pressable>
                    <Pressable
                      onPress={() => {
                        setCreating(false);
                        resetForm();
                      }}
                      disabled={saving}
                      style={[
                        styles.cancelBtn,
                        { borderColor: colors.border, borderRadius: colors.radius },
                      ]}
                      accessibilityRole="button"
                      accessibilityLabel="Cancel"
                    >
                      <Text style={[styles.cancelText, { color: colors.foreground }]}>
                        Cancel
                      </Text>
                    </Pressable>
                  </View>
                </View>
              ) : (
                <Pressable
                  onPress={() => {
                    resetForm();
                    setCreating(true);
                  }}
                  style={[
                    styles.newBtn,
                    { borderColor: colors.primary, borderRadius: colors.radius },
                  ]}
                  accessibilityRole="button"
                  accessibilityLabel="New workspace"
                >
                  <Feather name="plus" size={16} color={colors.primary} />
                  <Text style={[styles.newBtnText, { color: colors.primary }]}>
                    New workspace
                  </Text>
                </Pressable>
              )}

              <Text style={[styles.footer, { color: colors.mutedForeground }]}>
                Tap the edit icon to rename, restyle or delete a workspace you own.
              </Text>
            </View>
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
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 16, borderWidth: 1 },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  gear: {
    width: 34,
    height: 34,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 999,
  },
  footer: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    textAlign: "center",
  },
  newBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 14,
    borderWidth: 1.5,
    borderStyle: "dashed",
  },
  newBtnText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  createCard: { borderWidth: 1, padding: 16, gap: 16 },
  createTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  label: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  input: {
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: Platform.OS === "ios" ? 12 : 9,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 15,
  },
  grid: { flexDirection: "row", flexWrap: "wrap", gap: 10 },
  iconChoice: {
    width: 46,
    height: 46,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
  },
  colorChoice: {
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  error: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  createActions: { flexDirection: "row", gap: 10 },
  createBtn: {
    flex: 1,
    paddingVertical: 13,
    alignItems: "center",
    justifyContent: "center",
  },
  createBtnText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
    color: "#fff",
  },
  cancelBtn: {
    paddingVertical: 13,
    paddingHorizontal: 20,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1,
  },
  cancelText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
});
