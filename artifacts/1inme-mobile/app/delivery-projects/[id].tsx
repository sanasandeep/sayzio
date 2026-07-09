import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
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
  reorderDeliveryTasks,
  listDeliveryProjectComments,
  postDeliveryProjectComment,
  updateDeliveryTask,
  type DeliveryProjectMember,
  type DeliveryTask,
  type DeliveryTaskStatus,
  type DeliveryTaskUpdate,
} from "@/lib/api/deliveryProjects";
import { showAlert } from "@/lib/webAlert";

const STATUS_ORDER: DeliveryTaskStatus[] = ["todo", "in_progress", "done"];

function nextStatus(s: DeliveryTaskStatus): DeliveryTaskStatus {
  const i = STATUS_ORDER.indexOf(s);
  return STATUS_ORDER[(i + 1) % STATUS_ORDER.length];
}

const PROGRESS_PRESETS = [0, 25, 50, 75, 100];

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

/** Accept an empty string (clears the date) or a valid YYYY-MM-DD calendar date. */
function isValidDateInput(v: string): boolean {
  const t = v.trim();
  if (!t) return true;
  if (!DATE_RE.test(t)) return false;
  const d = new Date(`${t}T00:00:00`);
  return !Number.isNaN(d.getTime()) && t === d.toISOString().slice(0, 10);
}

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
  const [editTask, setEditTask] = useState<DeliveryTask | null>(null);
  const [commentBody, setCommentBody] = useState("");

  const q = useQuery({
    queryKey: ["delivery-project", id],
    queryFn: () => getDeliveryProject(id),
    enabled: Number.isFinite(id),
  });

  const commentsQ = useQuery({
    queryKey: ["delivery-project-comments", id],
    queryFn: () => listDeliveryProjectComments(id),
    enabled: Number.isFinite(id),
  });

  const postComment = useMutation({
    mutationFn: (body: string) => postDeliveryProjectComment(id, body),
    onSuccess: () => {
      setCommentBody("");
      qc.invalidateQueries({ queryKey: ["delivery-project-comments", id] });
      // Refresh the project + list summaries so the unanswered-client badge
      // clears everywhere as soon as the team replies (Task #3574).
      invalidate();
    },
    onError: (e: { message?: string }) =>
      showAlert("Couldn't send", e?.message ?? "Try again."),
  });

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["delivery-project", id] });
    qc.invalidateQueries({ queryKey: ["delivery-projects"] });
  };

  const members: DeliveryProjectMember[] = q.data?.members ?? [];

  // Task #3574 — a client comment still awaits a reply when it was posted
  // after the most recent team reply. Derived from the live comment thread so
  // the badge clears as soon as the team sends a reply.
  const comments = commentsQ.data ?? [];
  const lastTeamId = comments.reduce(
    (max, c) => (c.is_team && c.id > max ? c.id : max),
    0,
  );
  const isAwaiting = (c: (typeof comments)[number]) =>
    !c.is_team && c.id > lastTeamId;
  const awaitingCount = comments.filter(isAwaiting).length;

  const add = useMutation({
    mutationFn: (t: string) => createDeliveryTask(id, { title: t }),
    onSuccess: () => {
      setTitle("");
      setAddOpen(false);
      invalidate();
    },
    onError: (e: { message?: string }) =>
      showAlert("Couldn't add task", e?.message ?? "Try again."),
  });

  const cycle = useMutation({
    mutationFn: (task: DeliveryTask) =>
      updateDeliveryTask(task.id, { status: nextStatus(task.status) }),
    onSuccess: invalidate,
    onError: (e: { message?: string }) =>
      showAlert("Couldn't update", e?.message ?? "Try again."),
  });

  const edit = useMutation({
    mutationFn: ({ taskId, input }: { taskId: number; input: DeliveryTaskUpdate }) =>
      updateDeliveryTask(taskId, input),
    onSuccess: () => {
      setEditTask(null);
      invalidate();
    },
    onError: (e: { message?: string }) =>
      showAlert("Couldn't save task", e?.message ?? "Try again."),
  });

  const reorder = useMutation({
    mutationFn: (order: number[]) => reorderDeliveryTasks(id, order),
    onSuccess: invalidate,
    onError: (e: { message?: string }) => {
      invalidate();
      showAlert("Couldn't reorder", e?.message ?? "Try again.");
    },
  });

  // Optimistically swap two adjacent tasks in the cache, then persist the
  // full id order. On error we invalidate to snap back to the server truth.
  const moveTask = (task: DeliveryTask, dir: -1 | 1) => {
    const current = q.data;
    if (!current) return;
    const tasks = current.project.tasks;
    const idx = tasks.findIndex((t) => t.id === task.id);
    const target = idx + dir;
    if (idx < 0 || target < 0 || target >= tasks.length) return;
    const reordered = [...tasks];
    [reordered[idx], reordered[target]] = [reordered[target], reordered[idx]];
    qc.setQueryData(["delivery-project", id], {
      ...current,
      project: { ...current.project, tasks: reordered },
    });
    reorder.mutate(reordered.map((t) => t.id));
  };

  const setProgress = useMutation({
    mutationFn: ({ taskId, progress }: { taskId: number; progress: number }) =>
      updateDeliveryTask(taskId, { progress }),
    onSuccess: invalidate,
    onError: (e: { message?: string }) =>
      showAlert("Couldn't update", e?.message ?? "Try again."),
  });

  const remove = useMutation({
    mutationFn: (taskId: number) => deleteDeliveryTask(taskId),
    onSuccess: invalidate,
    onError: (e: { message?: string }) =>
      showAlert("Couldn't remove", e?.message ?? "Try again."),
  });

  const confirmRemove = (task: DeliveryTask) =>
    showAlert("Remove task", `Remove "${task.title}"?`, [
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
            project.tasks.map((task, idx) => (
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
                <View style={styles.reorderCol}>
                  <Pressable
                    onPress={() => moveTask(task, -1)}
                    disabled={idx === 0 || reorder.isPending}
                    hitSlop={8}
                    accessibilityLabel="Move task up"
                  >
                    <Feather
                      name="chevron-up"
                      size={18}
                      color={idx === 0 ? colors.border : colors.mutedForeground}
                    />
                  </Pressable>
                  <Pressable
                    onPress={() => moveTask(task, 1)}
                    disabled={idx === project.tasks.length - 1 || reorder.isPending}
                    hitSlop={8}
                    accessibilityLabel="Move task down"
                  >
                    <Feather
                      name="chevron-down"
                      size={18}
                      color={
                        idx === project.tasks.length - 1
                          ? colors.border
                          : colors.mutedForeground
                      }
                    />
                  </Pressable>
                </View>
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
                <View style={styles.actionCol}>
                  <Pressable
                    onPress={() => setEditTask(task)}
                    hitSlop={8}
                    accessibilityLabel="Edit task"
                  >
                    <Feather name="edit-2" size={17} color={colors.primary} />
                  </Pressable>
                  <Pressable
                    onPress={() => confirmRemove(task)}
                    hitSlop={8}
                    accessibilityLabel="Delete task"
                  >
                    <Feather name="trash-2" size={18} color={colors.destructive} />
                  </Pressable>
                </View>
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

          <View style={styles.sectionRow}>
            <Text
              style={[styles.sectionLabel, { color: colors.mutedForeground }]}
            >
              Questions & comments
            </Text>
            {awaitingCount > 0 ? (
              <View
                style={[
                  styles.replyBadge,
                  { backgroundColor: colors.destructive + "1F" },
                ]}
              >
                <Feather
                  name="corner-up-left"
                  size={10}
                  color={colors.destructive}
                />
                <Text
                  style={[styles.replyBadgeTxt, { color: colors.destructive }]}
                >
                  {awaitingCount} awaiting reply
                </Text>
              </View>
            ) : null}
          </View>
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
            {commentsQ.isLoading ? (
              <ActivityIndicator color={colors.primary} />
            ) : (commentsQ.data?.length ?? 0) === 0 ? (
              <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                No messages yet.
              </Text>
            ) : (
              commentsQ.data!.map((c) => {
                const awaiting = isAwaiting(c);
                return (
                  <View
                    key={c.id}
                    style={[
                      styles.commentBubble,
                      {
                        backgroundColor: c.is_team
                          ? colors.primary + "14"
                          : colors.muted,
                        borderRadius: colors.radius,
                      },
                      awaiting
                        ? { borderWidth: 1, borderColor: colors.destructive }
                        : null,
                    ]}
                  >
                    <View style={styles.rowTop}>
                      <Text
                        style={[
                          styles.commentAuthor,
                          {
                            color: c.is_team
                              ? colors.primary
                              : colors.foreground,
                          },
                        ]}
                      >
                        {c.author_name} · {c.is_team ? "Team" : "Client"}
                      </Text>
                      {c.created_at ? (
                        <Text
                          style={[
                            styles.axisTxt,
                            { color: colors.mutedForeground },
                          ]}
                        >
                          {c.created_at.slice(0, 10)}
                        </Text>
                      ) : null}
                    </View>
                    {awaiting ? (
                      <Text
                        style={[
                          styles.needsReplyTag,
                          { color: colors.destructive },
                        ]}
                      >
                        Needs reply
                      </Text>
                    ) : null}
                    <Text style={[styles.desc, { color: colors.foreground }]}>
                      {c.body}
                    </Text>
                  </View>
                );
              })
            )}
            <TextInput
              value={commentBody}
              onChangeText={setCommentBody}
              placeholder="Reply to the client…"
              placeholderTextColor={colors.mutedForeground}
              multiline
              style={[
                styles.input,
                {
                  color: colors.foreground,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                  minHeight: 64,
                  textAlignVertical: "top",
                },
              ]}
            />
            <Button
              label={postComment.isPending ? "Sending…" : "Send reply"}
              onPress={() => {
                const b = commentBody.trim();
                if (b) postComment.mutate(b);
              }}
              disabled={postComment.isPending || !commentBody.trim()}
            />
          </View>
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

      {editTask ? (
        <EditTaskModal
          task={editTask}
          members={members}
          saving={edit.isPending}
          onClose={() => setEditTask(null)}
          onSave={(input) => edit.mutate({ taskId: editTask.id, input })}
        />
      ) : null}
    </View>
  );
}

function EditTaskModal({
  task,
  members,
  saving,
  onClose,
  onSave,
}: {
  task: DeliveryTask;
  members: DeliveryProjectMember[];
  saving: boolean;
  onClose: () => void;
  onSave: (input: DeliveryTaskUpdate) => void;
}) {
  const colors = useColors();
  const [title, setTitle] = useState(task.title);
  const [assignee, setAssignee] = useState<number | null>(task.assignee_user_id);
  const [startDate, setStartDate] = useState(task.start_date?.slice(0, 10) ?? "");
  const [dueDate, setDueDate] = useState(task.due_date?.slice(0, 10) ?? "");

  const startOk = isValidDateInput(startDate);
  const dueOk = isValidDateInput(dueDate);
  const titleOk = title.trim().length > 0;
  const canSave = titleOk && startOk && dueOk && !saving;

  const submit = () => {
    if (!canSave) return;
    onSave({
      title: title.trim(),
      assignee_user_id: assignee,
      start_date: startDate.trim() || null,
      due_date: dueDate.trim() || null,
    });
  };

  return (
    <Modal visible transparent animationType="fade" onRequestClose={onClose}>
      <Pressable style={styles.backdrop} onPress={onClose}>
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
          <ScrollView
            contentContainerStyle={{ gap: 14 }}
            keyboardShouldPersistTaps="handled"
          >
            <Text style={[styles.h1, { color: colors.foreground }]}>Edit task</Text>

            <View style={{ gap: 6 }}>
              <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
                Title
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
              />
            </View>

            <View style={{ gap: 6 }}>
              <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
                Assignee
              </Text>
              <View style={styles.chipRow}>
                <AssigneeChip
                  label="Unassigned"
                  active={assignee === null}
                  onPress={() => setAssignee(null)}
                />
                {members.map((m) => (
                  <AssigneeChip
                    key={m.user_id}
                    label={m.name ?? `#${m.user_id}`}
                    active={assignee === m.user_id}
                    onPress={() => setAssignee(m.user_id)}
                  />
                ))}
              </View>
              {members.length === 0 ? (
                <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                  No workspace members to assign yet.
                </Text>
              ) : null}
            </View>

            <View style={{ gap: 6 }}>
              <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
                Start date (YYYY-MM-DD)
              </Text>
              <TextInput
                value={startDate}
                onChangeText={setStartDate}
                placeholder="2026-01-15"
                placeholderTextColor={colors.mutedForeground}
                autoCapitalize="none"
                autoCorrect={false}
                keyboardType="numbers-and-punctuation"
                style={[
                  styles.input,
                  {
                    color: colors.foreground,
                    borderColor: startOk ? colors.border : colors.destructive,
                    borderRadius: colors.radius,
                  },
                ]}
              />
            </View>

            <View style={{ gap: 6 }}>
              <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
                Due date (YYYY-MM-DD)
              </Text>
              <TextInput
                value={dueDate}
                onChangeText={setDueDate}
                placeholder="2026-02-01"
                placeholderTextColor={colors.mutedForeground}
                autoCapitalize="none"
                autoCorrect={false}
                keyboardType="numbers-and-punctuation"
                style={[
                  styles.input,
                  {
                    color: colors.foreground,
                    borderColor: dueOk ? colors.border : colors.destructive,
                    borderRadius: colors.radius,
                  },
                ]}
              />
            </View>

            <View style={styles.rowGap}>
              <Button
                label="Cancel"
                variant="outline"
                onPress={onClose}
                style={{ flex: 1 }}
              />
              <Button
                label={saving ? "Saving…" : "Save"}
                onPress={submit}
                disabled={!canSave}
                style={{ flex: 1 }}
              />
            </View>
          </ScrollView>
        </Pressable>
      </Pressable>
    </Modal>
  );
}

function AssigneeChip({
  label,
  active,
  onPress,
}: {
  label: string;
  active: boolean;
  onPress: () => void;
}) {
  const colors = useColors();
  return (
    <Pressable
      onPress={onPress}
      hitSlop={4}
      style={[
        styles.chip,
        {
          borderColor: active ? colors.primary : colors.border,
          backgroundColor: active ? colors.primary : "transparent",
        },
      ]}
    >
      <Text
        style={[
          styles.chipTxt,
          { color: active ? "#fff" : colors.mutedForeground },
        ]}
      >
        {label}
      </Text>
    </Pressable>
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
  sectionRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 8,
    flexWrap: "wrap",
  },
  replyBadge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
  },
  replyBadgeTxt: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 11 },
  needsReplyTag: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 10 },
  taskRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  reorderCol: { alignItems: "center", justifyContent: "center", gap: 2 },
  actionCol: { alignItems: "center", gap: 14 },
  fieldLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
    letterSpacing: 0.4,
  },
  rowGap: { flexDirection: "row", gap: 10 },
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
  modalCard: { width: "100%", maxWidth: 420, maxHeight: "85%", padding: 20, borderWidth: 1, gap: 14 },
  input: { borderWidth: 1, padding: 12, fontFamily: "SpaceGrotesk_500Medium", fontSize: 15 },
  commentBubble: { padding: 12, gap: 4 },
  commentAuthor: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
});
