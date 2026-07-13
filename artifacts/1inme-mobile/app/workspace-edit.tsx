import { Feather } from "@expo/vector-icons";
import { useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import { useWorkspace } from "@/contexts/WorkspaceContext";
import { getBaseUrl } from "@/lib/api";
import {
  updateWorkspace,
  workspaceFeatherIcon,
  WORKSPACE_COLOR_CHOICES,
  WORKSPACE_ICON_CHOICES,
} from "@/lib/api/workspaces";

function openWebSettings(id: number) {
  const webBase = getBaseUrl().replace(/\/api\/?$/, "");
  Linking.openURL(`${webBase}/user/workspaces/${id}/settings`).catch(() => {});
}

export default function WorkspaceEditScreen() {
  const colors = useColors();
  const router = useRouter();
  const queryClient = useQueryClient();
  const { workspaces, refresh } = useWorkspace();
  const { id } = useLocalSearchParams<{ id?: string }>();
  const wsId = Number(id);

  const workspace = useMemo(
    () => workspaces.find((w) => w.id === wsId) ?? null,
    [workspaces, wsId],
  );

  const [name, setName] = useState(workspace?.name ?? "");
  const [icon, setIcon] = useState<string>(
    workspace?.icon ?? (workspace?.is_personal ? "user" : "users"),
  );
  const [color, setColor] = useState<string>(
    workspace?.color ?? WORKSPACE_COLOR_CHOICES[0],
  );
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Only the owner may edit; the switcher/list already hide the entry point for
  // non-owners, but guard here too in case the screen is reached directly.
  if (!workspace || !workspace.is_owner) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <Stack.Screen options={{ title: "Edit workspace" }} />
        <EmptyState
          icon="lock"
          title="Can't edit this workspace"
          body="Only the workspace owner can rename or restyle it."
        />
      </View>
    );
  }

  const trimmed = name.trim();
  const canSave =
    trimmed.length > 0 &&
    trimmed.length <= 120 &&
    !saving &&
    (trimmed !== workspace.name ||
      icon !== workspace.icon ||
      color !== workspace.color);

  const onSave = async () => {
    if (!canSave) return;
    setSaving(true);
    setError(null);
    try {
      await updateWorkspace(workspace.id, { name: trimmed, icon, color });
      // Refresh every surface that reads the workspace list so the rename /
      // restyle shows up immediately (list screen + drawer switcher context).
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["workspaces"] }),
        queryClient.invalidateQueries({ queryKey: ["workspaces-list"] }),
      ]);
      refresh();
      router.back();
    } catch (e) {
      const msg =
        e && typeof e === "object" && typeof (e as { message?: unknown }).message === "string"
          ? (e as { message: string }).message
          : "Couldn't save workspace. Please try again.";
      setError(msg);
    } finally {
      setSaving(false);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Edit workspace" }} />
      <ScrollView contentContainerStyle={{ padding: 20, gap: 22 }} keyboardShouldPersistTaps="handled">
        {/* Live preview */}
        <View style={styles.previewWrap}>
          <View style={[styles.previewIcon, { backgroundColor: color + "cc" }]}>
            <Feather
              name={workspaceFeatherIcon({ icon, is_personal: workspace.is_personal })}
              size={26}
              color="#fff"
            />
          </View>
          <Text style={[styles.previewName, { color: colors.foreground }]} numberOfLines={1}>
            {trimmed || "Untitled workspace"}
          </Text>
          <Text style={[styles.previewSub, { color: colors.mutedForeground }]}>
            {workspace.is_personal ? "Personal workspace" : "Team workspace"}
          </Text>
        </View>

        {/* Name */}
        <View style={{ gap: 8 }}>
          <Text style={[styles.label, { color: colors.foreground }]}>Name</Text>
          <TextInput
            value={name}
            onChangeText={setName}
            placeholder="Workspace name"
            placeholderTextColor={colors.mutedForeground}
            maxLength={120}
            style={[
              styles.input,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
                color: colors.foreground,
              },
            ]}
          />
        </View>

        {/* Icon picker */}
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
                      backgroundColor: selected ? color + "22" : colors.card,
                      borderColor: selected ? color : colors.border,
                      borderWidth: selected ? 2 : 1,
                    },
                  ]}
                >
                  <Feather
                    name={workspaceFeatherIcon({ icon: key, is_personal: workspace.is_personal })}
                    size={20}
                    color={selected ? color : colors.mutedForeground}
                  />
                </Pressable>
              );
            })}
          </View>
        </View>

        {/* Color picker */}
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
                  {selected ? <Feather name="check" size={16} color="#fff" /> : null}
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

        {/* Save */}
        <Pressable
          onPress={onSave}
          disabled={!canSave}
          style={[
            styles.saveBtn,
            {
              backgroundColor: canSave ? colors.primary : colors.muted,
              borderRadius: colors.radius,
              opacity: canSave ? 1 : 0.7,
            },
          ]}
          accessibilityRole="button"
          accessibilityLabel="Save workspace"
        >
          {saving ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.saveText}>Save changes</Text>
          )}
        </Pressable>

        {/* Advanced settings live on the web (delete, members, approvals). */}
        <Pressable
          onPress={() => openWebSettings(workspace.id)}
          style={styles.webLink}
          accessibilityRole="link"
          accessibilityLabel="More settings on the web"
        >
          <Feather name="external-link" size={14} color={colors.mutedForeground} />
          <Text style={[styles.webLinkText, { color: colors.mutedForeground }]}>
            More settings on the web
          </Text>
        </Pressable>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  previewWrap: { alignItems: "center", gap: 8, paddingVertical: 8 },
  previewIcon: {
    width: 64,
    height: 64,
    borderRadius: 18,
    alignItems: "center",
    justifyContent: "center",
  },
  previewName: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 18 },
  previewSub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  label: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  input: {
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: Platform.OS === "ios" ? 14 : 10,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 15,
  },
  grid: { flexDirection: "row", flexWrap: "wrap", gap: 12 },
  iconChoice: {
    width: 52,
    height: 52,
    borderRadius: 14,
    alignItems: "center",
    justifyContent: "center",
  },
  colorChoice: {
    width: 44,
    height: 44,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  error: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  saveBtn: {
    paddingVertical: 15,
    alignItems: "center",
    justifyContent: "center",
  },
  saveText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
    color: "#fff",
  },
  webLink: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    paddingVertical: 8,
  },
  webLinkText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
});
