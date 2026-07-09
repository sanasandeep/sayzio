import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import {
  ActivityIndicator,
  FlatList,
  Linking,
  Modal,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  attachCloudFiles,
  detachCloudFile,
  getCloudAttachments,
  getCloudLibrary,
  type CloudAttachTargetType,
} from "@/lib/api/cloudFiles";
import { showAlert } from "@/lib/webAlert";

/**
 * Reusable "Cloud files" attach section for composers (post / task_card /
 * inbox_reply). Lists the current attachments with detach, and opens a picker
 * over the shared workspace library to attach more. Mirrors the web
 * cloud-files/_attach-picker partial. Honours files.view server-side; the
 * picker is purely additive (no upload here — files are saved into the library
 * from the Cloud files screen first).
 */
export function CloudAttachSection({
  targetType,
  targetId,
}: {
  targetType: CloudAttachTargetType;
  targetId: number;
}) {
  const colors = useColors();
  const qc = useQueryClient();
  const [pickerOpen, setPickerOpen] = useState(false);

  const key = ["cloud-attachments", targetType, targetId] as const;
  const attachments = useQuery({
    queryKey: key,
    queryFn: () => getCloudAttachments(targetType, targetId),
    enabled: Number.isFinite(targetId) && targetId > 0,
  });

  const detach = useMutation({
    mutationFn: (attachmentId: number) => detachCloudFile(attachmentId),
    onSuccess: () => qc.invalidateQueries({ queryKey: key }),
    onError: (e: any) => showAlert("Could not detach", e?.message ?? "Try again"),
  });

  const confirmDetach = (id: number, name: string) => {
    const go = () => detach.mutate(id);
    if (Platform.OS === "web") {
      if (confirm(`Detach ${name}?`)) go();
    } else {
      showAlert("Detach file?", name, [
        { text: "Cancel", style: "cancel" },
        { text: "Detach", style: "destructive", onPress: go },
      ]);
    }
  };

  return (
    <View style={{ gap: 8 }}>
      <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
        <Text
          style={{
            color: colors.foreground,
            fontFamily: "SpaceGrotesk_600SemiBold",
            fontSize: 14,
          }}
        >
          Cloud files
        </Text>
        <Pressable
          onPress={() => setPickerOpen(true)}
          style={{ flexDirection: "row", alignItems: "center", gap: 6 }}
          hitSlop={6}
        >
          <Feather name="plus" size={16} color={colors.primary} />
          <Text style={{ color: colors.primary, fontFamily: "SpaceGrotesk_600SemiBold" }}>
            Attach
          </Text>
        </Pressable>
      </View>

      {attachments.isLoading ? (
        <ActivityIndicator color={colors.primary} />
      ) : (attachments.data?.length ?? 0) === 0 ? (
        <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
          No cloud files attached.
        </Text>
      ) : (
        attachments.data!.map((a) => (
          <View
            key={a.id}
            style={[
              styles.row,
              { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            <Feather name="paperclip" size={16} color={colors.primary} />
            <Pressable
              style={{ flex: 1 }}
              onPress={() => a.link && Linking.openURL(a.link)}
            >
              <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                {a.name ?? "File"}
              </Text>
              {a.provider_label ? (
                <Text style={{ color: colors.mutedForeground, fontSize: 11 }} numberOfLines={1}>
                  {a.provider_label}
                  {a.human_size ? ` • ${a.human_size}` : ""}
                </Text>
              ) : null}
            </Pressable>
            <Pressable onPress={() => confirmDetach(a.id, a.name ?? "File")} hitSlop={6}>
              <Feather name="x" size={18} color={colors.destructive} />
            </Pressable>
          </View>
        ))
      )}

      {pickerOpen ? (
        <LibraryPicker
          targetType={targetType}
          targetId={targetId}
          existing={new Set((attachments.data ?? []).map((a) => a.cloud_file_id))}
          onClose={() => setPickerOpen(false)}
          onAttached={() => qc.invalidateQueries({ queryKey: key })}
        />
      ) : null}
    </View>
  );
}

function LibraryPicker({
  targetType,
  targetId,
  existing,
  onClose,
  onAttached,
}: {
  targetType: CloudAttachTargetType;
  targetId: number;
  existing: Set<number>;
  onClose: () => void;
  onAttached: () => void;
}) {
  const colors = useColors();
  const [selected, setSelected] = useState<Set<number>>(new Set());

  const library = useQuery({
    queryKey: ["cloud-library", "picker"],
    queryFn: () => getCloudLibrary({ page: 1 }),
  });

  const attach = useMutation({
    mutationFn: () =>
      attachCloudFiles({
        target_type: targetType,
        target_id: targetId,
        cloud_file_ids: Array.from(selected),
      }),
    onSuccess: () => {
      onAttached();
      onClose();
    },
    onError: (e: any) => showAlert("Could not attach", e?.message ?? "Try again"),
  });

  const toggle = (id: number) =>
    setSelected((s) => {
      const next = new Set(s);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });

  return (
    <Modal animationType="slide" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <View style={[styles.modalHeader, { borderColor: colors.border }]}>
          <Text
            style={{
              color: colors.foreground,
              fontFamily: "SpaceGrotesk_700Bold",
              fontSize: 17,
              flex: 1,
            }}
          >
            Attach from library
          </Text>
          <Pressable onPress={onClose} hitSlop={8}>
            <Feather name="x" size={22} color={colors.foreground} />
          </Pressable>
        </View>

        {library.isLoading ? (
          <View style={styles.center}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : (
          <FlatList
            data={library.data?.files ?? []}
            keyExtractor={(f) => String(f.id)}
            contentContainerStyle={{ padding: 12, gap: 8, paddingBottom: 90 }}
            renderItem={({ item }) => {
              const already = existing.has(item.id);
              const picked = selected.has(item.id);
              return (
                <Pressable
                  disabled={already}
                  onPress={() => toggle(item.id)}
                  style={[
                    styles.row,
                    {
                      backgroundColor: colors.card,
                      borderColor: picked ? colors.primary : colors.border,
                      borderRadius: colors.radius,
                      opacity: already ? 0.5 : 1,
                    },
                  ]}
                >
                  <Feather
                    name={already ? "check" : picked ? "check-square" : "square"}
                    size={18}
                    color={already || picked ? colors.primary : colors.mutedForeground}
                  />
                  <View style={{ flex: 1 }}>
                    <Text style={[styles.name, { color: colors.foreground }]} numberOfLines={1}>
                      {item.name}
                    </Text>
                    <Text style={{ color: colors.mutedForeground, fontSize: 11 }} numberOfLines={1}>
                      {item.provider_label} • {item.human_size}
                      {already ? " • attached" : ""}
                    </Text>
                  </View>
                </Pressable>
              );
            }}
            ListEmptyComponent={
              <EmptyState
                icon="cloud"
                title="Library is empty"
                body="Save files from the Cloud files screen first, then attach them here."
              />
            }
          />
        )}

        {selected.size > 0 ? (
          <View style={[styles.saveBar, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Button
              label={attach.isPending ? "Attaching…" : `Attach ${selected.size}`}
              onPress={() => attach.mutate()}
              disabled={attach.isPending}
            />
          </View>
        ) : null}
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 12,
    borderWidth: 1,
  },
  name: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  modalHeader: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 16,
    paddingTop: 52,
    borderBottomWidth: 1,
  },
  saveBar: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    padding: 16,
    borderTopWidth: 1,
  },
});
