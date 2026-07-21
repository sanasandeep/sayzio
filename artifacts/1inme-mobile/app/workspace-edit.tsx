import { Feather } from "@expo/vector-icons";
import { useQuery, useQueryClient } from "@tanstack/react-query";
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
import { showAlert } from "@/lib/webAlert";
import {
  deleteWorkspace,
  updateWorkspace,
  workspaceFeatherIcon,
  WORKSPACE_COLOR_CHOICES,
  WORKSPACE_ICON_CHOICES,
} from "@/lib/api/workspaces";
import {
  getTransferCapability,
  transferWorkspace,
} from "@/lib/api/transfers";

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
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Admin-granted asset transfer: the capability probe drives visibility,
  // the server re-checks the grant + ownership on submit.
  const transferCap = useQuery({
    queryKey: ["transfer-capability"],
    queryFn: getTransferCapability,
    staleTime: 5 * 60 * 1000,
  });
  const [transferOpen, setTransferOpen] = useState(false);
  const [transferEmail, setTransferEmail] = useState("");
  const [transferring, setTransferring] = useState(false);
  const [transferError, setTransferError] = useState<string | null>(null);

  // The owner's personal workspace can never be deleted, and neither can their
  // last remaining workspace — both mirror the web guard. Hiding the button
  // when we already know it would be rejected keeps the UI honest (the server
  // still enforces it either way).
  const ownedCount = workspaces.filter((w) => w.is_owner).length;
  const canDelete =
    !!workspace && workspace.is_owner && !workspace.is_personal && ownedCount > 1;

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

  const performDelete = async () => {
    if (!workspace || deleting) return;
    setDeleting(true);
    setError(null);
    try {
      await deleteWorkspace(workspace.id);
      // Drop the deleted workspace from every list surface, then let the
      // WorkspaceContext re-pick an active workspace (falls back to personal).
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
          : "Couldn't delete workspace. Please try again.";
      setError(msg);
    } finally {
      setDeleting(false);
    }
  };

  const performTransfer = async () => {
    const email = transferEmail.trim();
    setTransferring(true);
    setTransferError(null);
    try {
      const t = await transferWorkspace(workspace.id, email);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["workspaces"] }),
        queryClient.invalidateQueries({ queryKey: ["workspaces-list"] }),
      ]);
      refresh();
      showAlert(
        "Workspace transferred",
        `Ownership moved to ${t.to_email ?? email}.`,
        [{ text: "OK", onPress: () => router.back() }],
      );
    } catch (e) {
      const msg =
        e && typeof e === "object" && typeof (e as { message?: unknown }).message === "string"
          ? (e as { message: string }).message
          : "Transfer failed. Please try again.";
      setTransferError(msg);
    } finally {
      setTransferring(false);
    }
  };

  const onTransfer = () => {
    if (transferring) return;
    const email = transferEmail.trim();
    if (!email) {
      setTransferError("Enter the recipient's email.");
      return;
    }
    showAlert(
      `Transfer "${workspace.name}"?`,
      `Move this workspace, its links and data to ${email}. This is instant and cannot be undone.`,
      [
        { text: "Cancel", style: "cancel" },
        { text: "Transfer", onPress: () => void performTransfer() },
      ],
    );
  };

  const onDelete = () => {
    if (!canDelete || deleting) return;
    showAlert(
      `Delete "${workspace.name}"?`,
      "This permanently removes the workspace, its members and pending invites. Anything created inside it may become inaccessible. This cannot be undone.",
      [
        { text: "Cancel", style: "cancel" },
        { text: "Delete", style: "destructive", onPress: () => void performDelete() },
      ],
    );
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

        {/* Manage teammates natively: invite by email, change roles and remove
            members without leaving the app. Team workspaces only — the personal
            workspace can't have collaborators. */}
        {!workspace.is_personal ? (
          <Pressable
            onPress={() =>
              router.push(
                `/workspace-members?id=${workspace.id}&name=${encodeURIComponent(workspace.name)}` as never,
              )
            }
            style={[
              styles.memberLink,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
            accessibilityRole="button"
            accessibilityLabel="Manage members and invites"
          >
            <View style={[styles.memberIcon, { backgroundColor: colors.primary + "1c" }]}>
              <Feather name="users" size={16} color={colors.primary} />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={[styles.memberTitle, { color: colors.foreground }]}>
                Members & invites
              </Text>
              <Text style={[styles.memberSub, { color: colors.mutedForeground }]}>
                Invite teammates, change roles and remove members.
              </Text>
            </View>
            <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
          </Pressable>
        ) : null}

        {/* Advanced settings live on the web (approvals, billing seats). */}
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

        {/* Admin-granted asset transfer: visible only when the capability
            probe says the user may transfer; the server re-checks the grant
            + ownership on submit. */}
        {transferCap.data?.can_transfer && !workspace.is_personal ? (
          <View
            style={[
              styles.dangerZone,
              { borderColor: colors.primary, borderRadius: colors.radius },
            ]}
          >
            <Text style={[styles.dangerTitle, { color: colors.primary }]}>
              Transfer workspace
            </Text>
            <Text style={[styles.dangerBody, { color: colors.mutedForeground }]}>
              Move this workspace, its links and data to another user's account. Instant and cannot be undone.
            </Text>
            {transferOpen ? (
              <View style={{ gap: 10 }}>
                <TextInput
                  value={transferEmail}
                  onChangeText={(t) => {
                    setTransferEmail(t);
                    setTransferError(null);
                  }}
                  placeholder="recipient@example.com"
                  placeholderTextColor={colors.mutedForeground}
                  autoCapitalize="none"
                  keyboardType="email-address"
                  style={{
                    borderWidth: 1,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                    paddingHorizontal: 12,
                    paddingVertical: 10,
                    color: colors.foreground,
                    fontSize: 14,
                  }}
                  accessibilityLabel="Recipient email"
                />
                {transferError ? (
                  <Text style={{ color: colors.destructive, fontSize: 13 }}>
                    {transferError}
                  </Text>
                ) : null}
                <Pressable
                  onPress={onTransfer}
                  disabled={transferring}
                  style={[
                    styles.deleteBtn,
                    {
                      backgroundColor: colors.primary,
                      borderRadius: colors.radius,
                      opacity: transferring ? 0.7 : 1,
                    },
                  ]}
                  accessibilityRole="button"
                  accessibilityLabel="Transfer ownership"
                >
                  {transferring ? (
                    <ActivityIndicator color="#fff" />
                  ) : (
                    <>
                      <Feather name="send" size={15} color="#fff" />
                      <Text style={styles.deleteText}>Transfer ownership</Text>
                    </>
                  )}
                </Pressable>
              </View>
            ) : (
              <Pressable
                onPress={() => setTransferOpen(true)}
                style={[
                  styles.deleteBtn,
                  { backgroundColor: colors.primary, borderRadius: colors.radius },
                ]}
                accessibilityRole="button"
                accessibilityLabel="Transfer workspace"
              >
                <Feather name="send" size={15} color="#fff" />
                <Text style={styles.deleteText}>Transfer workspace</Text>
              </Pressable>
            )}
          </View>
        ) : null}

        {/* Danger zone: delete a team workspace you own. Hidden for the
            personal workspace and when this is the owner's only workspace,
            both of which the server rejects too. */}
        {canDelete ? (
          <View
            style={[
              styles.dangerZone,
              { borderColor: colors.destructive ?? "#ef4444", borderRadius: colors.radius },
            ]}
          >
            <Text style={[styles.dangerTitle, { color: colors.destructive ?? "#ef4444" }]}>
              Delete workspace
            </Text>
            <Text style={[styles.dangerBody, { color: colors.mutedForeground }]}>
              Permanently removes this workspace, its members and pending invites. This can't be undone.
            </Text>
            <Pressable
              onPress={onDelete}
              disabled={deleting}
              style={[
                styles.deleteBtn,
                {
                  backgroundColor: colors.destructive ?? "#ef4444",
                  borderRadius: colors.radius,
                  opacity: deleting ? 0.7 : 1,
                },
              ]}
              accessibilityRole="button"
              accessibilityLabel="Delete workspace"
            >
              {deleting ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <>
                  <Feather name="trash-2" size={15} color="#fff" />
                  <Text style={styles.deleteText}>Delete workspace</Text>
                </>
              )}
            </Pressable>
          </View>
        ) : !workspace.is_personal ? (
          <Text style={[styles.dangerHint, { color: colors.mutedForeground }]}>
            You can't delete your only workspace. Create another first.
          </Text>
        ) : null}
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
  memberLink: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  memberIcon: {
    width: 36,
    height: 36,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
  },
  memberTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  memberSub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, marginTop: 2 },
  webLink: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    paddingVertical: 8,
  },
  webLinkText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  dangerZone: {
    borderWidth: 1,
    padding: 16,
    gap: 10,
    marginTop: 4,
  },
  dangerTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 15 },
  dangerBody: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12, lineHeight: 18 },
  deleteBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    paddingVertical: 13,
    marginTop: 4,
  },
  deleteText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
    color: "#fff",
  },
  dangerHint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    textAlign: "center",
    marginTop: 4,
  },
});
