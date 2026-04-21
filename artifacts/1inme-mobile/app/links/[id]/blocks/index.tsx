import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  BLOCK_KINDS,
  blockKind,
  createBlock,
  deleteBlock,
  listBlocks,
  reorderBlocks,
  updateBlock,
  type Block,
} from "@/lib/api/blocks";

function confirm(title: string, msg: string, onYes: () => void) {
  if (Platform.OS === "web") {
    if (typeof window !== "undefined" && window.confirm(`${title}\n\n${msg}`)) {
      onYes();
    }
    return;
  }
  Alert.alert(title, msg, [
    { text: "Cancel", style: "cancel" },
    { text: "OK", style: "destructive", onPress: onYes },
  ]);
}

export default function BlocksScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = useQuery({
    queryKey: ["blocks", id],
    queryFn: () => listBlocks(id),
    enabled: Number.isFinite(id),
  });

  const [order, setOrder] = useState<Block[]>([]);
  useEffect(() => {
    if (q.data) setOrder(q.data);
  }, [q.data]);

  const [picker, setPicker] = useState(false);

  const create = useMutation({
    mutationFn: (type: string) => createBlock(id, { type, settings: {} }),
    onSuccess: (b) => {
      qc.invalidateQueries({ queryKey: ["blocks", id] });
      setPicker(false);
      router.push(`/links/${id}/blocks/${b.id}` as any);
    },
  });

  const toggle = useMutation({
    mutationFn: (b: Block) =>
      updateBlock(id, b.id, { is_active: !b.is_active }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["blocks", id] }),
  });

  const remove = useMutation({
    mutationFn: (blockId: number) => deleteBlock(id, blockId),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["blocks", id] }),
  });

  const persistOrder = useMutation({
    mutationFn: (ids: number[]) => reorderBlocks(id, ids),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["blocks", id] }),
  });

  function move(idx: number, dir: -1 | 1) {
    const next = order.slice();
    const j = idx + dir;
    if (j < 0 || j >= next.length) return;
    [next[idx], next[j]] = [next[j], next[idx]];
    setOrder(next);
    persistOrder.mutate(next.map((b) => b.id));
  }

  if (q.isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: "Blocks" }} />
      <ScrollView contentContainerStyle={styles.body}>
        {order.length === 0 ? (
          <EmptyState
            icon="grid"
            title="No blocks yet"
            body="Add a header, a link button, an image, or any other block to start building your biolink."
            action={
              <Button label="Add a block" onPress={() => setPicker(true)} />
            }
          />
        ) : (
          <View style={{ gap: 10 }}>
            {order.map((b, i) => {
              const meta = blockKind(b.type);
              const label =
                (b.settings?.label as string) ||
                (b.settings?.title as string) ||
                meta?.label ||
                b.type;
              return (
                <View
                  key={b.id}
                  style={[
                    styles.row,
                    {
                      backgroundColor: colors.card,
                      borderColor: colors.border,
                      borderRadius: colors.radius,
                      opacity: b.is_active ? 1 : 0.5,
                    },
                  ]}
                >
                  <View style={styles.handle}>
                    <Pressable onPress={() => move(i, -1)} hitSlop={6}>
                      <Feather
                        name="chevron-up"
                        size={18}
                        color={i === 0 ? colors.border : colors.foreground}
                      />
                    </Pressable>
                    <Pressable onPress={() => move(i, 1)} hitSlop={6}>
                      <Feather
                        name="chevron-down"
                        size={18}
                        color={
                          i === order.length - 1
                            ? colors.border
                            : colors.foreground
                        }
                      />
                    </Pressable>
                  </View>
                  <Pressable
                    style={{ flex: 1, gap: 2 }}
                    onPress={() => router.push(`/links/${id}/blocks/${b.id}` as any)}
                  >
                    <Text
                      numberOfLines={1}
                      style={[styles.rowTitle, { color: colors.foreground }]}
                    >
                      {label}
                    </Text>
                    <Text
                      style={[styles.rowSub, { color: colors.mutedForeground }]}
                    >
                      {meta?.label || b.type}
                    </Text>
                  </Pressable>
                  <Switch
                    value={b.is_active}
                    onValueChange={() => toggle.mutate(b)}
                    trackColor={{ true: colors.primary, false: colors.border }}
                  />
                  <Pressable
                    onPress={() =>
                      confirm("Delete block?", "Remove this block?", () =>
                        remove.mutate(b.id),
                      )
                    }
                    hitSlop={8}
                  >
                    <Feather
                      name="trash-2"
                      size={16}
                      color={colors.destructive}
                    />
                  </Pressable>
                </View>
              );
            })}
            <Button label="Add a block" onPress={() => setPicker(true)} />
          </View>
        )}
      </ScrollView>

      <Modal
        visible={picker}
        animationType="slide"
        transparent
        onRequestClose={() => setPicker(false)}
      >
        <View style={styles.modalBackdrop}>
          <View
            style={[
              styles.modalCard,
              { backgroundColor: colors.background, borderColor: colors.border },
            ]}
          >
            <View style={styles.modalHeader}>
              <Text style={[styles.modalTitle, { color: colors.foreground }]}>
                Add block
              </Text>
              <Pressable onPress={() => setPicker(false)} hitSlop={8}>
                <Feather name="x" size={20} color={colors.mutedForeground} />
              </Pressable>
            </View>
            <ScrollView contentContainerStyle={{ gap: 8, paddingBottom: 20 }}>
              {BLOCK_KINDS.map((k) => (
                <Pressable
                  key={k.type}
                  onPress={() => create.mutate(k.type)}
                  style={({ pressed }) => [
                    styles.kindRow,
                    {
                      backgroundColor: colors.card,
                      borderColor: colors.border,
                      borderRadius: colors.radius,
                      opacity: pressed ? 0.85 : 1,
                    },
                  ]}
                >
                  <Text style={[styles.kindLabel, { color: colors.foreground }]}>
                    {k.label}
                  </Text>
                  <Text
                    style={[styles.kindBlurb, { color: colors.mutedForeground }]}
                  >
                    {k.blurb}
                  </Text>
                </Pressable>
              ))}
            </ScrollView>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  body: { padding: 20, gap: 14, paddingBottom: 40 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 12,
    borderWidth: 1,
  },
  handle: { gap: 2 },
  rowTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  rowSub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 11 },
  modalBackdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.5)",
    justifyContent: "flex-end",
  },
  modalCard: {
    maxHeight: "80%",
    padding: 20,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    borderTopWidth: 1,
    borderLeftWidth: 1,
    borderRightWidth: 1,
    gap: 12,
  },
  modalHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  modalTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 20 },
  kindRow: { padding: 14, borderWidth: 1, gap: 4 },
  kindLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  kindBlurb: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
});
