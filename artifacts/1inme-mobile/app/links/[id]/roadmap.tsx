import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Modal,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  deleteRoadmapItem,
  getRoadmapTriage,
  mergeRoadmapItem,
  updateRoadmapItem,
  type RoadmapItem,
  type RoadmapStatus,
} from "@/lib/api/roadmap";

type Colors = ReturnType<typeof useColors>;

const TAB_ORDER: RoadmapStatus[] = [
  "pending",
  "ideas",
  "planned",
  "in_progress",
  "shipped",
  "rejected",
  "merged",
];

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

function statusColor(status: string, colors: Colors): string {
  switch (status) {
    case "shipped":
      return colors.success;
    case "in_progress":
      return colors.primary;
    case "planned":
      return "#6366f1";
    case "rejected":
      return colors.destructive;
    case "merged":
      return colors.mutedForeground;
    case "ideas":
      return colors.warning;
    default:
      return colors.mutedForeground;
  }
}

export default function RoadmapTriageScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const [status, setStatus] = useState<RoadmapStatus>("pending");
  const [editItem, setEditItem] = useState<RoadmapItem | null>(null);
  const [mergeItem, setMergeItem] = useState<RoadmapItem | null>(null);

  const q = useQuery({
    queryKey: ["roadmap", id, status],
    queryFn: () => getRoadmapTriage(id, { status }),
    enabled: Number.isFinite(id),
  });

  const statuses = q.data?.statuses ?? {};
  const counts = q.data?.counts ?? {};
  const mergeTargets = q.data?.merge_targets ?? [];
  const hasBlocks = (q.data?.blocks?.length ?? 0) > 0;

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["roadmap", id] });
  };

  const updateMut = useMutation({
    mutationFn: (vars: {
      itemId: number;
      patch: Parameters<typeof updateRoadmapItem>[2];
    }) => updateRoadmapItem(id, vars.itemId, vars.patch),
    onSuccess: (res) => {
      invalidate();
      setEditItem(null);
      if (res.message) {
        if (Platform.OS === "web") {
          // silent on web; query refresh reflects the change
        } else {
          Alert.alert("Roadmap", res.message);
        }
      }
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't update", e?.message ?? "Please try again."),
  });

  const delMut = useMutation({
    mutationFn: (itemId: number) => deleteRoadmapItem(id, itemId),
    onSuccess: invalidate,
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't delete", e?.message ?? "Please try again."),
  });

  const mergeMut = useMutation({
    mutationFn: (vars: { itemId: number; intoId: number }) =>
      mergeRoadmapItem(id, vars.itemId, vars.intoId),
    onSuccess: (res) => {
      invalidate();
      setMergeItem(null);
      if (Platform.OS !== "web") Alert.alert("Roadmap", res.message);
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't merge", e?.message ?? "Please try again."),
  });

  if (!Number.isFinite(id)) return null;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: "Roadmap triage" }} />

      <View style={styles.tabsWrap}>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.tabs}
        >
          {TAB_ORDER.map((key) => {
            const active = status === key;
            const label = statuses[key] ?? key;
            const n = counts[key] ?? 0;
            return (
              <Pressable
                key={key}
                onPress={() => setStatus(key)}
                style={[
                  styles.tab,
                  {
                    backgroundColor: active ? colors.primary : colors.card,
                    borderColor: active ? colors.primary : colors.border,
                  },
                ]}
              >
                <Text
                  style={{
                    color: active ? "#fff" : colors.foreground,
                    fontWeight: "600",
                    fontSize: 13,
                  }}
                >
                  {label}
                  {n > 0 ? ` · ${n}` : ""}
                </Text>
              </Pressable>
            );
          })}
        </ScrollView>
      </View>

      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : q.error ? (
        <View style={styles.center}>
          <Text style={{ color: colors.destructive }}>
            Couldn&apos;t load roadmap.
          </Text>
        </View>
      ) : !hasBlocks ? (
        <EmptyState
          icon="map"
          title="No roadmap block yet"
          body="Add a Roadmap block to this link in bio from the web editor. Visitor submissions will then appear here for triage."
        />
      ) : (
        <FlatList
          data={q.data?.items ?? []}
          keyExtractor={(it) => String(it.id)}
          contentContainerStyle={styles.body}
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
          ListEmptyComponent={
            <EmptyState
              icon="inbox"
              title="Nothing here"
              body={`No ideas in "${statuses[status] ?? status}".`}
            />
          }
          renderItem={({ item }) => (
            <RoadmapCard
              item={item}
              colors={colors}
              busy={
                (delMut.isPending && delMut.variables === item.id) ||
                (updateMut.isPending &&
                  updateMut.variables?.itemId === item.id)
              }
              onEdit={() => setEditItem(item)}
              onMerge={() => setMergeItem(item)}
              onDelete={() =>
                confirm(
                  "Delete idea?",
                  "This permanently removes the idea, its votes and comments.",
                  () => delMut.mutate(item.id),
                )
              }
            />
          )}
        />
      )}

      {/* Status / fields editor */}
      <EditModal
        item={editItem}
        statuses={statuses}
        colors={colors}
        saving={updateMut.isPending}
        onClose={() => setEditItem(null)}
        onSave={(patch) =>
          editItem && updateMut.mutate({ itemId: editItem.id, patch })
        }
      />

      {/* Merge picker */}
      <MergeModal
        item={mergeItem}
        targets={mergeTargets}
        colors={colors}
        merging={mergeMut.isPending}
        onClose={() => setMergeItem(null)}
        onMerge={(intoId) =>
          mergeItem && mergeMut.mutate({ itemId: mergeItem.id, intoId })
        }
      />
    </View>
  );
}

function RoadmapCard({
  item,
  colors,
  busy,
  onEdit,
  onMerge,
  onDelete,
}: {
  item: RoadmapItem;
  colors: Colors;
  busy: boolean;
  onEdit: () => void;
  onMerge: () => void;
  onDelete: () => void;
}) {
  return (
    <View
      style={[
        styles.card,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
          opacity: busy ? 0.6 : 1,
        },
      ]}
    >
      <View style={styles.cardHead}>
        <View
          style={[styles.voteBox, { backgroundColor: colors.primary + "1c" }]}
        >
          <Text style={[styles.voteNum, { color: colors.primary }]}>
            {item.votes_count}
          </Text>
          <Text style={[styles.voteLbl, { color: colors.mutedForeground }]}>
            votes
          </Text>
        </View>
        <View style={{ flex: 1 }}>
          <View style={styles.titleRow}>
            <Text
              style={[styles.title, { color: colors.foreground }]}
              numberOfLines={2}
            >
              {item.title}
            </Text>
            <Text style={[styles.idTag, { color: colors.mutedForeground }]}>
              #{item.id}
            </Text>
          </View>
          {item.description ? (
            <Text
              style={[styles.desc, { color: colors.mutedForeground }]}
              numberOfLines={4}
            >
              {item.description}
            </Text>
          ) : null}
          <View style={styles.metaRow}>
            <View
              style={[
                styles.statusPill,
                { backgroundColor: statusColor(item.status, colors) + "22" },
              ]}
            >
              <Text
                style={{
                  color: statusColor(item.status, colors),
                  fontSize: 11,
                  fontWeight: "700",
                }}
              >
                {item.status_label}
              </Text>
            </View>
            {item.is_blocked ? (
              <Text style={[styles.metaTxt, { color: colors.destructive }]}>
                Hidden
              </Text>
            ) : null}
            {item.submitter_name ? (
              <Text style={[styles.metaTxt, { color: colors.mutedForeground }]}>
                {item.submitter_name}
              </Text>
            ) : null}
            {item.task_card_id ? (
              <Text style={[styles.metaTxt, { color: colors.success }]}>
                Card #{item.task_card_id}
              </Text>
            ) : null}
          </View>
        </View>
      </View>

      <View
        style={[styles.actions, { borderTopColor: colors.border }]}
      >
        <CardBtn icon="edit-2" label="Edit" color={colors.primary} onPress={onEdit} />
        <CardBtn
          icon="git-merge"
          label="Merge"
          color={colors.foreground}
          onPress={onMerge}
        />
        <CardBtn
          icon="trash-2"
          label="Delete"
          color={colors.destructive}
          onPress={onDelete}
        />
      </View>
    </View>
  );
}

function CardBtn({
  icon,
  label,
  color,
  onPress,
}: {
  icon: keyof typeof Feather.glyphMap;
  label: string;
  color: string;
  onPress: () => void;
}) {
  return (
    <Pressable onPress={onPress} style={styles.cardBtn} hitSlop={6}>
      <Feather name={icon} size={14} color={color} />
      <Text style={[styles.cardBtnTxt, { color }]}>{label}</Text>
    </Pressable>
  );
}

function EditModal({
  item,
  statuses,
  colors,
  saving,
  onClose,
  onSave,
}: {
  item: RoadmapItem | null;
  statuses: Record<string, string>;
  colors: Colors;
  saving: boolean;
  onClose: () => void;
  onSave: (patch: {
    status?: RoadmapStatus;
    is_blocked?: boolean;
    sync_to_kanban?: boolean;
  }) => void;
}) {
  const [status, setStatus] = useState<RoadmapStatus>("pending");
  const [hidden, setHidden] = useState(false);
  const [sync, setSync] = useState(true);

  useEffect(() => {
    if (item) {
      setStatus(item.status);
      setHidden(item.is_blocked);
      setSync(true);
    }
  }, [item]);

  const keys = Object.keys(statuses);

  return (
    <Modal
      visible={!!item}
      transparent
      animationType="slide"
      onRequestClose={onClose}
    >
      <Pressable style={styles.backdrop} onPress={onClose} />
      <View
        style={[
          styles.sheet,
          { backgroundColor: colors.background, borderColor: colors.border },
        ]}
      >
        <Text style={[styles.sheetTitle, { color: colors.foreground }]}>
          Update idea
        </Text>
        {item ? (
          <Text
            style={[styles.sheetSub, { color: colors.mutedForeground }]}
            numberOfLines={2}
          >
            {item.title}
          </Text>
        ) : null}

        <Text style={[styles.fieldLbl, { color: colors.mutedForeground }]}>
          Status
        </Text>
        <View style={styles.statusGrid}>
          {keys.map((k) => {
            const active = status === k;
            return (
              <Pressable
                key={k}
                onPress={() => setStatus(k)}
                style={[
                  styles.statusOpt,
                  {
                    backgroundColor: active ? colors.primary : colors.card,
                    borderColor: active ? colors.primary : colors.border,
                  },
                ]}
              >
                <Text
                  style={{
                    color: active ? "#fff" : colors.foreground,
                    fontSize: 12,
                    fontWeight: "600",
                  }}
                >
                  {statuses[k]}
                </Text>
              </Pressable>
            );
          })}
        </View>

        <View style={styles.switchRow}>
          <Text style={{ color: colors.foreground }}>Hide from public</Text>
          <Switch value={hidden} onValueChange={setHidden} />
        </View>
        <View style={styles.switchRow}>
          <Text style={{ color: colors.foreground }}>Sync to kanban</Text>
          <Switch value={sync} onValueChange={setSync} />
        </View>

        <Pressable
          disabled={saving}
          onPress={() =>
            onSave({ status, is_blocked: hidden, sync_to_kanban: sync })
          }
          style={[
            styles.primaryBtn,
            { backgroundColor: colors.primary, opacity: saving ? 0.6 : 1 },
          ]}
        >
          {saving ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.primaryBtnTxt}>Save</Text>
          )}
        </Pressable>
      </View>
    </Modal>
  );
}

function MergeModal({
  item,
  targets,
  colors,
  merging,
  onClose,
  onMerge,
}: {
  item: RoadmapItem | null;
  targets: { id: number; title: string; votes_count: number }[];
  colors: Colors;
  merging: boolean;
  onClose: () => void;
  onMerge: (intoId: number) => void;
}) {
  const options = (targets ?? []).filter((t) => t.id !== item?.id);

  return (
    <Modal
      visible={!!item}
      transparent
      animationType="slide"
      onRequestClose={onClose}
    >
      <Pressable style={styles.backdrop} onPress={onClose} />
      <View
        style={[
          styles.sheet,
          { backgroundColor: colors.background, borderColor: colors.border },
        ]}
      >
        <Text style={[styles.sheetTitle, { color: colors.foreground }]}>
          Merge into…
        </Text>
        {item ? (
          <Text
            style={[styles.sheetSub, { color: colors.mutedForeground }]}
            numberOfLines={2}
          >
            &quot;{item.title}&quot; will be merged and its votes moved.
          </Text>
        ) : null}

        {merging ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 16 }} />
        ) : options.length === 0 ? (
          <Text style={{ color: colors.mutedForeground, marginTop: 12 }}>
            No other ideas to merge into.
          </Text>
        ) : (
          <ScrollView style={{ maxHeight: 360, marginTop: 8 }}>
            {options.map((t) => (
              <Pressable
                key={t.id}
                onPress={() => onMerge(t.id)}
                style={[
                  styles.mergeOpt,
                  { borderColor: colors.border, backgroundColor: colors.card },
                ]}
              >
                <Text
                  style={{ color: colors.foreground, flex: 1 }}
                  numberOfLines={1}
                >
                  {t.title}
                </Text>
                <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                  {t.votes_count} · #{t.id}
                </Text>
              </Pressable>
            ))}
          </ScrollView>
        )}
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  body: { padding: 16, gap: 12 },
  tabsWrap: { paddingTop: 8 },
  tabs: { paddingHorizontal: 12, gap: 8, paddingBottom: 8 },
  tab: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 999,
    borderWidth: 1,
  },
  card: { borderWidth: 1, padding: 14 },
  cardHead: { flexDirection: "row", gap: 12 },
  voteBox: {
    minWidth: 52,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 12,
    paddingVertical: 6,
    paddingHorizontal: 4,
  },
  voteNum: { fontSize: 20, fontWeight: "800" },
  voteLbl: { fontSize: 9, textTransform: "uppercase", letterSpacing: 0.5 },
  titleRow: { flexDirection: "row", alignItems: "flex-start", gap: 8 },
  title: { fontSize: 15, fontWeight: "700", flex: 1 },
  idTag: { fontSize: 12 },
  desc: { fontSize: 13, marginTop: 4, lineHeight: 18 },
  metaRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    alignItems: "center",
    gap: 8,
    marginTop: 8,
  },
  statusPill: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999 },
  metaTxt: { fontSize: 12 },
  actions: {
    flexDirection: "row",
    gap: 18,
    borderTopWidth: 1,
    marginTop: 12,
    paddingTop: 10,
  },
  cardBtn: { flexDirection: "row", alignItems: "center", gap: 5 },
  cardBtnTxt: { fontSize: 13, fontWeight: "600" },
  backdrop: { flex: 1, backgroundColor: "rgba(0,0,0,0.4)" },
  sheet: {
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    borderWidth: 1,
    padding: 20,
    paddingBottom: 36,
  },
  sheetTitle: { fontSize: 18, fontWeight: "700" },
  sheetSub: { fontSize: 13, marginTop: 4 },
  fieldLbl: {
    fontSize: 12,
    textTransform: "uppercase",
    letterSpacing: 0.5,
    marginTop: 16,
    marginBottom: 8,
  },
  statusGrid: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  statusOpt: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 999,
    borderWidth: 1,
  },
  switchRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginTop: 14,
  },
  primaryBtn: {
    marginTop: 20,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: "center",
  },
  primaryBtnTxt: { color: "#fff", fontWeight: "700", fontSize: 15 },
  mergeOpt: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderWidth: 1,
    borderRadius: 12,
    padding: 12,
    marginBottom: 8,
  },
});
