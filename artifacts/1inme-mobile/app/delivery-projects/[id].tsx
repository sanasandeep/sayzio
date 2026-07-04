import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Modal,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  createDeliveryTask,
  deleteDeliveryTask,
  getDeliveryProject,
  updateDeliveryTask,
  type DeliveryTask,
  type DeliveryTaskStatus,
} from "@/lib/api/deliveryProjects";

const STATUS_ORDER: DeliveryTaskStatus[] = ["todo", "in_progress", "done"];

function nextStatus(s: DeliveryTaskStatus): DeliveryTaskStatus {
  const i = STATUS_ORDER.indexOf(s);
  return STATUS_ORDER[(i + 1) % STATUS_ORDER.length];
}

const PROGRESS_PRESETS = [0, 25, 50, 75, 100];

type TimelineRow = { task: DeliveryTask; left: number; width: number };

function buildTimeline(
  tasks: DeliveryTask[],
): { rows: TimelineRow[]; axisMin: string; axisMax: string } | null {
  const dated = tasks.filter((t) => t.start_date || t.due_date);
  if (!dated.length) return null;
  const toTs = (d: string) => new Date(`${d}T00:00:00`).getTime();
  let min = Infinity;
  let max = -Infinity;
  dated.forEach((t) => {
    const s = toTs((t.start_date || t.due_date) as string);
    const e = toTs((t.due_date || t.start_date) as string);
    if (s < min) min = s;
    if (e > max) max = e;
  });
  const span = Math.max(1, max - min);
  const fmt = (ts: number) =>
    new Date(ts).toLocaleDateString(undefined, {
      month: "short",
      day: "numeric",
    });
  const rows: TimelineRow[] = dated.map((t) => {
    const s = toTs((t.start_date || t.due_date) as string);
    const e = toTs((t.due_date || t.start_date) as string);
    let left = ((s - min) / span) * 100;
    let width = Math.max(4, ((e - s) / span) * 100);
    left = Math.max(0, Math.min(100, left));
    width = Math.min(100 - left, width);
    return { task: t, left, width };
  });
  return { rows, axisMin: fmt(min), axisMax: fmt(max) };
}

export default function DeliveryProjectDetailScreen() {
  const params = useLocalSearchParams<{ id: string }>();
  const id = Number(params.id);
  const colors = useColors();
  const qc = useQueryClient();
  const [addOpen, setAddOpen] = useState(false);
  const [title, setTitle] = useState("");

  const q = useQuery({
    queryKey: ["delivery-project", id],
    queryFn: () => getDeliveryProject(id),
    enabled: Number.isFinite(id),
  });

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["delivery-project", id] });
    qc.invalidateQueries({ queryKey: ["delivery-projects"] });
  };

  const add = useMutation({
    mutationFn: (t: string) => createDeliveryTask(id, { title: t }),
    onSuccess: () => {
      setTitle("");
      setAddOpen(false);
      invalidate();
    },
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't add task", e?.message ?? "Try again."),
  });

  const cycle = useMutation({
    mutationFn: (task: DeliveryTask) =>
      updateDeliveryTask(task.id, { status: nextStatus(task.status) }),
    onSuccess: invalidate,
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't update", e?.message ?? "Try again."),
  });

  const setProgress = useMutation({
    mutationFn: ({ taskId, progress }: { taskId: number; progress: number }) =>
      updateDeliveryTask(taskId, { progress }),
    onSuccess: invalidate,
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't update", e?.message ?? "Try again."),
  });

  const remove = useMutation({
    mutationFn: (taskId: number) => deleteDeliveryTask(taskId),
    onSuccess: invalidate,
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't remove", e?.message ?? "Try again."),
  });

  const confirmRemove = (task: DeliveryTask) =>
    Alert.alert("Remove task", `Remove "${task.title}"?`, [
      { text: "Cancel", style: "cancel" },
      {
        text: "Remove",
        style: "destructive",
        onPress: () => remove.mutate(task.id),
      },
    ]);

  const statusTint = (s: DeliveryTaskStatus): string =>
    s === "done"
      ? colors.success
      : s === "in_progress"
        ? colors.primary
        : colors.mutedForeground;

  const project = q.data?.project;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: project?.title ?? "Project",
          headerRight: () =>
            project ? (
              <Pressable
                onPress={() => setAddOpen(true)}
                hitSlop={10}
                style={({ pressed }) => ({
                  opacity: pressed ? 0.6 : 1,
                  paddingHorizontal: 6,
                })}
              >
                <Feather name="plus" size={22} color={colors.primary} />
              </Pressable>
            ) : null,
        }}
      />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : q.isError || !project ? (
        <EmptyState
          icon="alert-circle"
          title="Couldn't load project"
          body={
            (q.error as { message?: string })?.message ??
            "Check your connection and try again."
          }
        />
      ) : (
        <ScrollView
          contentContainerStyle={{ padding: 20, gap: 16 }}
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
        >
          <View
            style={[
              styles.card,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <View style={styles.rowTop}>
              <Text style={[styles.h1, { color: colors.foreground }]}>
                {project.title}
              </Text>
              <Text style={[styles.pct, { color: colors.primary }]}>
                {project.progress}%
              </Text>
            </View>
            <View style={[styles.barTrack, { backgroundColor: colors.border }]}>
              <View
                style={[
                  styles.barFill,
                  {
                    width: `${project.progress}%`,
                    backgroundColor: colors.primary,
                  },
                ]}
              />
            </View>
            <Text style={[styles.meta, { color: colors.mutedForeground }]}>
              {project.status_label}
              {project.source_label ? ` · ${project.source_label}` : ""}
              {project.client_name ? ` · ${project.client_name}` : ""}
            </Text>
            {project.description ? (
              <Text style={[styles.desc, { color: colors.foreground }]}>
                {project.description}
              </Text>
            ) : null}
            {project.warranty_expires_at ? (
              <Text
                style={[
                  styles.meta,
                  {
                    color: project.warranty_expired
                      ? colors.destructive
                      : colors.success,
                  },
                ]}
              >
                {project.warranty_expired ? "Warranty expired " : "Warranty until "}
                {project.warranty_expires_at.slice(0, 10)}
              </Text>
            ) : null}
          </View>

          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Tasks
          </Text>

          {project.tasks.length === 0 ? (
            <EmptyState
              icon="check-square"
              title="No tasks yet"
              body="Add tasks to track delivery progress."
              action={
                <Button label="Add task" onPress={() => setAddOpen(true)} />
              }
            />
          ) : (
            project.tasks.map((task) => (
              <View
                key={task.id}
                style={[
                  styles.taskRow,
                  {
                    backgroundColor: colors.card,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                  },
                ]}
              >
                <Pressable
                  onPress={() => cycle.mutate(task)}
                  hitSlop={8}
                  style={{ paddingRight: 4 }}
                >
                  <Feather
                    name={
                      task.status === "done"
                        ? "check-circle"
                        : task.status === "in_progress"
                          ? "clock"
                          : "circle"
                    }
                    size={22}
                    color={statusTint(task.status)}
                  />
                </Pressable>
                <View style={{ flex: 1, gap: 6 }}>
                  <Text
                    style={[styles.taskTitle, { color: colors.foreground }]}
                    numberOfLines={2}
                  >
                    {task.title}
                  </Text>
                  <Text
                    style={[styles.sub, { color: colors.mutedForeground }]}
                    numberOfLines={1}
                  >
                    {task.status_label}
                    {task.assignee_name ? ` · ${task.assignee_name}` : ""}
                    {task.due_date ? ` · due ${task.due_date.slice(0, 10)}` : ""}
                  </Text>
                  <View
                    style={[styles.taskBarTrack, { backgroundColor: colors.border }]}
                  >
                    <View
                      style={[
                        styles.taskBarFill,
                        {
                          width: `${task.progress}%`,
                          backgroundColor: statusTint(task.status),
                        },
                      ]}
                    />
                  </View>
                  <View style={styles.chipRow}>
                    {PROGRESS_PRESETS.map((p) => {
                      const active = task.progress === p;
                      return (
                        <Pressable
                          key={p}
                          onPress={() =>
                            setProgress.mutate({ taskId: task.id, progress: p })
                          }
                          disabled={setProgress.isPending}
                          hitSlop={4}
                          style={[
                            styles.chip,
                            {
                              borderColor: active ? colors.primary : colors.border,
                              backgroundColor: active
                                ? colors.primary
                                : "transparent",
                            },
                          ]}
                        >
                          <Text
                            style={[
                              styles.chipTxt,
                              { color: active ? "#fff" : colors.mutedForeground },
                            ]}
                          >
                            {p}%
                          </Text>
                        </Pressable>
                      );
                    })}
                  </View>
                </View>
                <Pressable onPress={() => confirmRemove(task)} hitSlop={8}>
                  <Feather name="trash-2" size={18} color={colors.destructive} />
                </Pressable>
              </View>
            ))
          )}

          {(() => {
            const tl = buildTimeline(project.tasks);
            if (!tl) return null;
            return (
              <>
                <Text
                  style={[
                    styles.sectionLabel,
                    { color: colors.mutedForeground },
                  ]}
                >
                  Timeline
                </Text>
                <View
                  style={[
                    styles.card,
                    {
                      backgroundColor: colors.card,
                      borderColor: colors.border,
                      borderRadius: colors.radius,
                    },
                  ]}
                >
                  {tl.rows.map(({ task, left, width }) => (
                    <View key={task.id} style={styles.ganttRow}>
                      <Text
                        style={[
                          styles.ganttName,
                          { color: colors.mutedForeground },
                        ]}
                        numberOfLines={1}
                      >
                        {task.title}
                      </Text>
                      <View
                        style={[
                          styles.ganttTrack,
                          { backgroundColor: colors.border },
                        ]}
                      >
                        <View
                          style={[
                            styles.ganttBar,
                            {
                              left: `${left}%`,
                              width: `${width}%`,
                              backgroundColor: statusTint(task.status),
                            },
                          ]}
                        >
                          <View
                            style={[
                              styles.ganttFill,
                              { width: `${task.progress}%` },
                            ]}
                          />
                        </View>
                      </View>
                    </View>
                  ))}
                  <View style={styles.ganttAxis}>
                    <Text
                      style={[styles.axisTxt, { color: colors.mutedForeground }]}
                    >
                      {tl.axisMin}
                    </Text>
                    <Text
                      style={[styles.axisTxt, { color: colors.mutedForeground }]}
                    >
                      {tl.axisMax}
                    </Text>
                  </View>
                </View>
              </>
            );
          })()}
        </ScrollView>
      )}

      <Modal
        visible={addOpen}
        transparent
        animationType="fade"
        onRequestClose={() => setAddOpen(false)}
      >
        <Pressable style={styles.backdrop} onPress={() => setAddOpen(false)}>
          <Pressable
            style={[
              styles.modalCard,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
            onPress={(e) => e.stopPropagation()}
          >
            <Text style={[styles.h1, { color: colors.foreground }]}>
              New task
            </Text>
            <TextInput
              value={title}
              onChangeText={setTitle}
              placeholder="Task title"
              placeholderTextColor={colors.mutedForeground}
              style={[
                styles.input,
                {
                  color: colors.foreground,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
              autoFocus
            />
            <Button
              label={add.isPending ? "Adding…" : "Add task"}
              onPress={() => {
                const t = title.trim();
                if (t) add.mutate(t);
              }}
              disabled={add.isPending || !title.trim()}
            />
          </Pressable>
        </Pressable>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  card: { padding: 16, borderWidth: 1, gap: 10 },
  rowTop: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 8,
  },
  h1: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 18, flex: 1 },
  pct: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  barTrack: { height: 8, borderRadius: 999, overflow: "hidden" },
  barFill: { height: "100%", borderRadius: 999 },
  meta: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  desc: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, lineHeight: 19 },
  sectionLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
    letterSpacing: 0.6,
    textTransform: "uppercase",
  },
  taskRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  taskTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  sub: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11, letterSpacing: 0.3 },
  taskBarTrack: { height: 6, borderRadius: 999, overflow: "hidden", marginTop: 2 },
  taskBarFill: { height: "100%", borderRadius: 999 },
  chipRow: { flexDirection: "row", flexWrap: "wrap", gap: 6, marginTop: 2 },
  chip: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
    borderWidth: 1,
  },
  chipTxt: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 11 },
  ganttRow: { flexDirection: "row", alignItems: "center", gap: 10, marginBottom: 8 },
  ganttName: { width: 96, fontFamily: "SpaceGrotesk_500Medium", fontSize: 11 },
  ganttTrack: { flex: 1, height: 18, borderRadius: 6, position: "relative" },
  ganttBar: {
    position: "absolute",
    top: 3,
    height: 12,
    borderRadius: 6,
    overflow: "hidden",
    opacity: 0.9,
  },
  ganttFill: { height: "100%", backgroundColor: "rgba(255,255,255,0.45)" },
  ganttAxis: {
    flexDirection: "row",
    justifyContent: "space-between",
    marginTop: 2,
    paddingLeft: 106,
  },
  axisTxt: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 10 },
  backdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.5)",
    alignItems: "center",
    justifyContent: "center",
    padding: 24,
  },
  modalCard: { width: "100%", maxWidth: 420, padding: 20, borderWidth: 1, gap: 14 },
  input: { borderWidth: 1, padding: 12, fontFamily: "SpaceGrotesk_500Medium", fontSize: 15 },
});
