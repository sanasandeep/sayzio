import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import {
  getScheduledJobRuns,
  getScheduledJobs,
  muteScheduledJobAlerts,
  pauseScheduledJob,
  resumeScheduledJob,
  runScheduledJobNow,
  unmuteScheduledJobAlerts,
  updateScheduledJobAlertSettings,
  type ScheduledJob,
} from "@/lib/api/scheduledJobs";

// Admin parity for the web "Scheduled Jobs" control panel: grouped background
// jobs with cadence, last-run health, pause/resume (protected jobs are
// locked), run-now, and a per-job run-history sheet (last 20 runs). Shares the
// same server-side engine as the web page so the two views never drift.

function formatWhen(iso: string | null): string {
  if (!iso) return "—";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "—";
  return d.toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export default function ScheduledJobsScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const [selected, setSelected] = useState<ScheduledJob | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [runNotice, setRunNotice] = useState<string | null>(null);
  // When set, we expect a run to be in flight (Run now was just tapped, or the
  // sheet was opened for a job already running). Drives a short grace window
  // of polling until the new run row shows up in the history.
  const [watchStartedAt, setWatchStartedAt] = useState<number | null>(null);

  const query = useQuery({
    queryKey: ["admin-scheduled-jobs"],
    queryFn: getScheduledJobs,
    refetchInterval: 30_000,
  });

  const runsQuery = useQuery({
    queryKey: ["admin-scheduled-job-runs", selected?.key],
    queryFn: () => getScheduledJobRuns(selected!.key),
    enabled: !!selected,
    // Poll while a run is in flight so the admin sees the outcome live
    // without closing/reopening the sheet. Stops automatically once the
    // latest run is no longer `running` (see the effect below).
    refetchInterval: (q) => {
      const latest = q.state.data?.runs?.[0];
      if (latest?.status === "running") return 3_000;
      // Right after "Run now" the fire-and-forget run row may not exist yet;
      // keep polling for a short grace window until it appears.
      if (watchStartedAt !== null && Date.now() - watchStartedAt < 20_000) {
        return 3_000;
      }
      return false;
    },
  });

  // Once the latest run transitions out of `running`, stop the watch window
  // and refresh the jobs list so badges (Running…/OK/Failed) update too.
  const latestRunStatus = runsQuery.data?.runs?.[0]?.status;
  const prevRunStatus = useRef<string | undefined>(undefined);
  useEffect(() => {
    if (
      prevRunStatus.current === "running" &&
      latestRunStatus !== undefined &&
      latestRunStatus !== "running"
    ) {
      setWatchStartedAt(null);
      qc.invalidateQueries({ queryKey: ["admin-scheduled-jobs"] });
    }
    prevRunStatus.current = latestRunStatus;
  }, [latestRunStatus, qc]);

  const refreshAll = () => {
    qc.invalidateQueries({ queryKey: ["admin-scheduled-jobs"] });
    if (selected) {
      qc.invalidateQueries({
        queryKey: ["admin-scheduled-job-runs", selected.key],
      });
    }
  };

  const pauseMut = useMutation({
    mutationFn: (key: string) => pauseScheduledJob(key),
    onSuccess: (r) => {
      setActionError(null);
      setSelected((s) => (s && s.key === r.job_key ? { ...s, paused: true } : s));
      refreshAll();
    },
    onError: (e: any) =>
      setActionError(e?.message ?? "Couldn't pause this job."),
  });

  const resumeMut = useMutation({
    mutationFn: (key: string) => resumeScheduledJob(key),
    onSuccess: (r) => {
      setActionError(null);
      setSelected((s) =>
        s && s.key === r.job_key ? { ...s, paused: false } : s,
      );
      refreshAll();
    },
    onError: (e: any) =>
      setActionError(e?.message ?? "Couldn't resume this job."),
  });

  const muteMut = useMutation({
    mutationFn: (key: string) => muteScheduledJobAlerts(key),
    onSuccess: (r) => {
      setActionError(null);
      setSelected((s) =>
        s && s.key === r.job_key ? { ...s, alerts_muted: true } : s,
      );
      refreshAll();
    },
    onError: (e: any) =>
      setActionError(e?.message ?? "Couldn't mute alerts for this job."),
  });

  const unmuteMut = useMutation({
    mutationFn: (key: string) => unmuteScheduledJobAlerts(key),
    onSuccess: (r) => {
      setActionError(null);
      setSelected((s) =>
        s && s.key === r.job_key ? { ...s, alerts_muted: false } : s,
      );
      refreshAll();
    },
    onError: (e: any) =>
      setActionError(e?.message ?? "Couldn't re-enable alerts for this job."),
  });

  const runMut = useMutation({
    mutationFn: (key: string) => runScheduledJobNow(key),
    onSuccess: (r) => {
      setActionError(null);
      setRunNotice(r.message);
      // The run is fire-and-forget server-side; start polling the run
      // history so the outcome appears live without reopening the sheet.
      setWatchStartedAt(Date.now());
      // Give the new run row a moment to appear, then refresh once so the
      // list badges pick up the "Running…" state quickly too.
      setTimeout(refreshAll, 2500);
    },
    onError: (e: any) =>
      setActionError(e?.message ?? "Couldn't start this job."),
  });

  const busy =
    pauseMut.isPending ||
    resumeMut.isPending ||
    runMut.isPending ||
    muteMut.isPending ||
    unmuteMut.isPending;
  const data = query.data;

  // Admin-tunable scheduler stale threshold (minutes). Local draft synced
  // from the server value; saved via the alert-settings endpoint.
  const [staleDraft, setStaleDraft] = useState<string | null>(null);
  const [staleNotice, setStaleNotice] = useState<string | null>(null);
  const staleSettings = data?.alert_settings;
  const staleValue =
    staleDraft ?? String(staleSettings?.stale_after_minutes ?? "");

  const staleMut = useMutation({
    mutationFn: (minutes: number) => updateScheduledJobAlertSettings(minutes),
    onSuccess: (r) => {
      setStaleDraft(null);
      setStaleNotice(
        `Saved — scheduler reported down after ${r.stale_after_minutes} min without a tick.`,
      );
      qc.invalidateQueries({ queryKey: ["admin-scheduled-jobs"] });
    },
    onError: (e: any) =>
      setStaleNotice(e?.message ?? "Couldn't save the stale threshold."),
  });

  const saveStale = () => {
    if (!staleSettings) return;
    const parsed = Number.parseInt(staleValue, 10);
    if (
      Number.isNaN(parsed) ||
      parsed < staleSettings.min_stale_after_minutes ||
      parsed > staleSettings.max_stale_after_minutes
    ) {
      setStaleNotice(
        `Enter a value between ${staleSettings.min_stale_after_minutes} and ${staleSettings.max_stale_after_minutes} minutes.`,
      );
      return;
    }
    setStaleNotice(null);
    staleMut.mutate(parsed);
  };

  const openSheet = (job: ScheduledJob) => {
    setSelected(job);
    // If the job is already mid-run, poll the history until it finishes.
    setWatchStartedAt(job.running_now ? Date.now() : null);
  };

  const closeSheet = () => {
    setSelected(null);
    setActionError(null);
    setRunNotice(null);
    setWatchStartedAt(null);
  };

  const schedulerBadge = (() => {
    const state = data?.scheduler.state;
    if (state === "ok") return { label: "Running", color: colors.success };
    if (state === "stale") return { label: "Stale", color: colors.warning };
    if (state === "never")
      return { label: "Never ticked", color: colors.destructive };
    return { label: state ?? "Unknown", color: colors.mutedForeground };
  })();

  const lastRunBadge = (job: ScheduledJob) => {
    if (job.running_now)
      return { label: "Running…", color: colors.primary };
    if (job.last_run_ok === true) return { label: "OK", color: colors.success };
    if (job.last_run_ok === false)
      return { label: "Failed", color: colors.destructive };
    return { label: "No runs yet", color: colors.mutedForeground };
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ title: "Scheduled jobs", headerBackTitle: "Back" }}
      />
      <ScrollView
        contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 48 }}
      >
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <Feather
              name="alert-triangle"
              size={20}
              color={colors.destructive}
            />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 403
                ? "You need admin access to view scheduled jobs."
                : "Couldn't load scheduled jobs."}
            </Text>
          </View>
        ) : data ? (
          <>
            {/* Scheduler status */}
            <View
              style={[
                styles.card,
                { backgroundColor: colors.card, borderColor: colors.border },
              ]}
            >
              <View style={styles.cardHead}>
                <Text style={[styles.cardTitle, { color: colors.foreground }]}>
                  Scheduler
                </Text>
                <View
                  style={[
                    styles.badge,
                    { backgroundColor: schedulerBadge.color + "22" },
                  ]}
                >
                  <Text
                    style={[styles.badgeText, { color: schedulerBadge.color }]}
                  >
                    {schedulerBadge.label}
                  </Text>
                </View>
              </View>
              <Text style={[styles.note, { color: colors.mutedForeground }]}>
                Last tick: {formatWhen(data.scheduler.last_tick)}
                {data.scheduler.overdue_count > 0
                  ? ` · ${data.scheduler.overdue_count} job(s) overdue`
                  : ""}
              </Text>
            </View>

            {/* Open failure-episode banner: which jobs are currently in an
                active failure streak (last run failed, no success since).
                Mirrors the web panel's red banner; same server state as the
                one-alert-per-streak ops notifications. */}
            {(() => {
              const episodes = data.failure_episodes;
              const failingJobs = episodes?.jobs ?? [];
              const schedulerDown = episodes?.scheduler ?? null;
              const count = failingJobs.length + (schedulerDown ? 1 : 0);
              if (count === 0) return null;
              return (
                <View
                  style={[
                    styles.card,
                    {
                      backgroundColor: colors.destructive + "14",
                      borderColor: colors.destructive + "55",
                      gap: 8,
                    },
                  ]}
                >
                  <View style={styles.cardHead}>
                    <Feather
                      name="alert-triangle"
                      size={16}
                      color={colors.destructive}
                    />
                    <Text
                      style={[
                        styles.cardTitle,
                        { color: colors.destructive, flex: 1 },
                      ]}
                    >
                      {count} scheduled job{count === 1 ? " is" : "s are"}{" "}
                      currently failing
                    </Text>
                  </View>
                  {schedulerDown ? (
                    <View style={{ gap: 2 }}>
                      <Text
                        style={{
                          color: colors.destructive,
                          fontSize: 13,
                          fontWeight: "600",
                        }}
                      >
                        scheduler heartbeat
                      </Text>
                      <Text
                        style={{ color: colors.mutedForeground, fontSize: 12 }}
                      >
                        The scheduler itself appears to be down — no jobs are
                        firing at all.
                        {schedulerDown.since
                          ? ` Alerted ${formatWhen(schedulerDown.since)}.`
                          : ""}
                      </Text>
                    </View>
                  ) : null}
                  {failingJobs.map((ep) => (
                    <View key={ep.key} style={{ gap: 2 }}>
                      <Text
                        style={{
                          color: colors.destructive,
                          fontSize: 13,
                          fontWeight: "600",
                        }}
                      >
                        {ep.key}
                        {ep.since ? (
                          <Text
                            style={{
                              color: colors.mutedForeground,
                              fontWeight: "400",
                              fontSize: 12,
                            }}
                          >
                            {"  "}failing since {formatWhen(ep.since)}
                          </Text>
                        ) : null}
                      </Text>
                      {ep.last_error ? (
                        <Text
                          style={{
                            color: colors.mutedForeground,
                            fontSize: 12,
                          }}
                          numberOfLines={3}
                        >
                          {ep.last_error}
                        </Text>
                      ) : null}
                    </View>
                  ))}
                  <Text
                    style={{ color: colors.mutedForeground, fontSize: 11 }}
                  >
                    An all-clear follows each job's next successful run. Tap a
                    job below and use Run now to retry it immediately.
                  </Text>
                </View>
              );
            })()}

            {/* Alert settings — scheduler stale threshold */}
            {staleSettings ? (
              <View
                style={[
                  styles.card,
                  { backgroundColor: colors.card, borderColor: colors.border },
                ]}
              >
                <Text style={[styles.cardTitle, { color: colors.foreground }]}>
                  Alert settings
                </Text>
                <Text style={[styles.note, { color: colors.mutedForeground }]}>
                  Ops admins are alerted when the scheduler stops ticking for
                  this many minutes ({staleSettings.min_stale_after_minutes}–
                  {staleSettings.max_stale_after_minutes}, default{" "}
                  {staleSettings.default_stale_after_minutes}).
                </Text>
                <View style={styles.staleRow}>
                  <TextInput
                    value={staleValue}
                    onChangeText={(t) => {
                      setStaleDraft(t.replace(/[^0-9]/g, ""));
                      setStaleNotice(null);
                    }}
                    keyboardType="number-pad"
                    style={[
                      styles.staleInput,
                      {
                        color: colors.foreground,
                        borderColor: colors.border,
                        backgroundColor: colors.background,
                      },
                    ]}
                  />
                  <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
                    minutes
                  </Text>
                  <Pressable
                    disabled={staleMut.isPending || staleDraft === null}
                    onPress={saveStale}
                    style={[
                      styles.actionBtn,
                      {
                        backgroundColor: colors.primary + "22",
                        opacity:
                          staleMut.isPending || staleDraft === null ? 0.5 : 1,
                        marginLeft: "auto",
                      },
                    ]}
                  >
                    {staleMut.isPending ? (
                      <ActivityIndicator size="small" color={colors.primary} />
                    ) : (
                      <Feather name="save" size={15} color={colors.primary} />
                    )}
                    <Text style={{ color: colors.primary, fontWeight: "600" }}>
                      Save
                    </Text>
                  </Pressable>
                </View>
                {staleNotice ? (
                  <Text
                    style={{
                      color: colors.mutedForeground,
                      fontSize: 12,
                      marginTop: 6,
                    }}
                  >
                    {staleNotice}
                  </Text>
                ) : null}
              </View>
            ) : null}

            {/* Grouped jobs */}
            {data.groups.map((group) => (
              <View key={group.slug} style={{ gap: 8 }}>
                <Text
                  style={[styles.groupLabel, { color: colors.mutedForeground }]}
                >
                  {group.label}
                </Text>
                <View
                  style={[
                    styles.list,
                    {
                      backgroundColor: colors.card,
                      borderColor: colors.border,
                      borderRadius: colors.radius,
                    },
                  ]}
                >
                  {group.jobs.map((job, i) => {
                    const badge = lastRunBadge(job);
                    return (
                      <Pressable
                        key={job.key}
                        onPress={() => openSheet(job)}
                        style={({ pressed }) => [
                          styles.listItem,
                          {
                            borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                            borderTopColor: colors.border,
                            opacity: pressed ? 0.7 : 1,
                          },
                        ]}
                      >
                        <View style={{ flex: 1, gap: 3 }}>
                          <View style={styles.rowHead}>
                            <Text
                              style={[
                                styles.itemLabel,
                                { color: colors.foreground },
                              ]}
                              numberOfLines={1}
                            >
                              {job.command}
                            </Text>
                            {job.protected ? (
                              <Feather
                                name="lock"
                                size={13}
                                color={colors.mutedForeground}
                              />
                            ) : null}
                          </View>
                          <Text
                            style={{
                              color: colors.mutedForeground,
                              fontSize: 12,
                            }}
                            numberOfLines={1}
                          >
                            {job.frequency}
                            {job.last_runtime ? ` · ${job.last_runtime}` : ""}
                            {job.last_exit_code !== null &&
                            job.last_run_ok === false
                              ? ` · exit ${job.last_exit_code}`
                              : ""}
                          </Text>
                          <View style={styles.pillRow}>
                            <View
                              style={[
                                styles.badge,
                                { backgroundColor: badge.color + "22" },
                              ]}
                            >
                              <Text
                                style={[
                                  styles.badgeText,
                                  { color: badge.color },
                                ]}
                              >
                                {badge.label}
                              </Text>
                            </View>
                            {job.paused ? (
                              <View
                                style={[
                                  styles.badge,
                                  {
                                    backgroundColor:
                                      colors.warning + "22",
                                  },
                                ]}
                              >
                                <Text
                                  style={[
                                    styles.badgeText,
                                    { color: colors.warning },
                                  ]}
                                >
                                  Paused
                                </Text>
                              </View>
                            ) : null}
                            {job.alerts_muted ? (
                              <View
                                style={[
                                  styles.badge,
                                  {
                                    backgroundColor:
                                      colors.mutedForeground + "22",
                                  },
                                ]}
                              >
                                <Text
                                  style={[
                                    styles.badgeText,
                                    { color: colors.mutedForeground },
                                  ]}
                                >
                                  Alerts muted
                                </Text>
                              </View>
                            ) : null}
                            {job.overdue && !job.paused ? (
                              <View
                                style={[
                                  styles.badge,
                                  {
                                    backgroundColor:
                                      colors.destructive + "22",
                                  },
                                ]}
                              >
                                <Text
                                  style={[
                                    styles.badgeText,
                                    { color: colors.destructive },
                                  ]}
                                >
                                  Overdue
                                </Text>
                              </View>
                            ) : null}
                            {job.failing_repeatedly ? (
                              <View
                                style={[
                                  styles.badge,
                                  {
                                    backgroundColor:
                                      colors.destructive + "22",
                                  },
                                ]}
                              >
                                <Text
                                  style={[
                                    styles.badgeText,
                                    { color: colors.destructive },
                                  ]}
                                >
                                  Failing repeatedly ({job.failing_streak} in a
                                  row)
                                </Text>
                              </View>
                            ) : null}
                          </View>
                        </View>
                        <Feather
                          name="chevron-right"
                          size={18}
                          color={colors.mutedForeground}
                        />
                      </Pressable>
                    );
                  })}
                </View>
              </View>
            ))}
          </>
        ) : null}
      </ScrollView>

      {/* Job detail + run history sheet */}
      <Modal
        visible={!!selected}
        animationType="slide"
        transparent
        onRequestClose={closeSheet}
      >
        <View style={styles.sheetBackdrop}>
          <Pressable style={{ flex: 1 }} onPress={closeSheet} />
          <View
            style={[
              styles.sheet,
              { backgroundColor: colors.background, borderColor: colors.border },
            ]}
          >
            {selected ? (
              <ScrollView
                contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: 40 }}
              >
                <View style={styles.cardHead}>
                  <Text
                    style={[
                      styles.cardTitle,
                      { color: colors.foreground, flex: 1 },
                    ]}
                    numberOfLines={2}
                  >
                    {selected.command}
                  </Text>
                  <Pressable onPress={closeSheet} hitSlop={12}>
                    <Feather name="x" size={20} color={colors.mutedForeground} />
                  </Pressable>
                </View>

                <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
                  {selected.purpose}
                </Text>
                <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                  {selected.frequency} · cron {selected.expression}
                </Text>
                <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
                  Next run: {formatWhen(selected.next_run)} · Last run:{" "}
                  {formatWhen(selected.last_run)}
                  {selected.last_run_source
                    ? ` (${selected.last_run_source})`
                    : ""}
                </Text>
                {selected.last_run_error ? (
                  <Text style={{ color: colors.destructive, fontSize: 12 }}>
                    {selected.last_run_error}
                  </Text>
                ) : null}

                {selected.protected ? (
                  <View
                    style={[
                      styles.protectedNote,
                      {
                        backgroundColor: colors.mutedForeground + "14",
                        borderRadius: colors.radius,
                      },
                    ]}
                  >
                    <Feather
                      name="lock"
                      size={14}
                      color={colors.mutedForeground}
                    />
                    <Text
                      style={{
                        color: colors.mutedForeground,
                        fontSize: 12,
                        flex: 1,
                      }}
                    >
                      Protected job — pausing it could break billing, data
                      integrity or platform health, so it can't be paused.
                    </Text>
                  </View>
                ) : null}

                {/* Actions */}
                <View style={styles.actionRow}>
                  {selected.protected ? null : selected.paused ? (
                    <Pressable
                      disabled={busy}
                      onPress={() => resumeMut.mutate(selected.key)}
                      style={[
                        styles.actionBtn,
                        {
                          backgroundColor: colors.success + "22",
                          opacity: busy ? 0.5 : 1,
                        },
                      ]}
                    >
                      {resumeMut.isPending ? (
                        <ActivityIndicator size="small" color={colors.success} />
                      ) : (
                        <Feather name="play" size={15} color={colors.success} />
                      )}
                      <Text style={{ color: colors.success, fontWeight: "600" }}>
                        Resume
                      </Text>
                    </Pressable>
                  ) : (
                    <Pressable
                      disabled={busy}
                      onPress={() => pauseMut.mutate(selected.key)}
                      style={[
                        styles.actionBtn,
                        {
                          backgroundColor: colors.warning + "22",
                          opacity: busy ? 0.5 : 1,
                        },
                      ]}
                    >
                      {pauseMut.isPending ? (
                        <ActivityIndicator size="small" color={colors.warning} />
                      ) : (
                        <Feather name="pause" size={15} color={colors.warning} />
                      )}
                      <Text style={{ color: colors.warning, fontWeight: "600" }}>
                        Pause
                      </Text>
                    </Pressable>
                  )}

                  <Pressable
                    disabled={busy}
                    onPress={() => runMut.mutate(selected.key)}
                    style={[
                      styles.actionBtn,
                      {
                        backgroundColor: colors.primary + "22",
                        opacity: busy ? 0.5 : 1,
                      },
                    ]}
                  >
                    {runMut.isPending ? (
                      <ActivityIndicator size="small" color={colors.primary} />
                    ) : (
                      <Feather name="zap" size={15} color={colors.primary} />
                    )}
                    <Text style={{ color: colors.primary, fontWeight: "600" }}>
                      Run now
                    </Text>
                  </Pressable>

                  {selected.alerts_muted ? (
                    <Pressable
                      disabled={busy}
                      onPress={() => unmuteMut.mutate(selected.key)}
                      style={[
                        styles.actionBtn,
                        {
                          backgroundColor: colors.mutedForeground + "22",
                          opacity: busy ? 0.5 : 1,
                        },
                      ]}
                    >
                      {unmuteMut.isPending ? (
                        <ActivityIndicator
                          size="small"
                          color={colors.mutedForeground}
                        />
                      ) : (
                        <Feather
                          name="bell"
                          size={15}
                          color={colors.mutedForeground}
                        />
                      )}
                      <Text
                        style={{
                          color: colors.mutedForeground,
                          fontWeight: "600",
                        }}
                      >
                        Unmute alerts
                      </Text>
                    </Pressable>
                  ) : (
                    <Pressable
                      disabled={busy}
                      onPress={() => muteMut.mutate(selected.key)}
                      style={[
                        styles.actionBtn,
                        {
                          backgroundColor: colors.mutedForeground + "22",
                          opacity: busy ? 0.5 : 1,
                        },
                      ]}
                    >
                      {muteMut.isPending ? (
                        <ActivityIndicator
                          size="small"
                          color={colors.mutedForeground}
                        />
                      ) : (
                        <Feather
                          name="bell-off"
                          size={15}
                          color={colors.mutedForeground}
                        />
                      )}
                      <Text
                        style={{
                          color: colors.mutedForeground,
                          fontWeight: "600",
                        }}
                      >
                        Mute alerts
                      </Text>
                    </Pressable>
                  )}
                </View>

                {selected.alerts_muted ? (
                  <Text
                    style={{ color: colors.mutedForeground, fontSize: 12 }}
                  >
                    Failure alerts are muted for this job — it still runs on
                    schedule, but ops admins are not notified when it fails.
                  </Text>
                ) : null}

                {actionError ? (
                  <Text style={{ color: colors.destructive, fontSize: 13 }}>
                    {actionError}
                  </Text>
                ) : null}
                {runNotice ? (
                  <Text style={{ color: colors.mutedForeground, fontSize: 13 }}>
                    {runNotice}
                  </Text>
                ) : null}

                {/* Run history */}
                <Text
                  style={[
                    styles.groupLabel,
                    { color: colors.mutedForeground, marginTop: 8 },
                  ]}
                >
                  Recent runs
                </Text>
                {runsQuery.isLoading ? (
                  <ActivityIndicator color={colors.primary} />
                ) : runsQuery.isError ? (
                  <Text style={{ color: colors.destructive, fontSize: 13 }}>
                    Couldn't load run history.
                  </Text>
                ) : (runsQuery.data?.runs.length ?? 0) === 0 ? (
                  <Text
                    style={{ color: colors.mutedForeground, fontSize: 13 }}
                  >
                    No recorded runs yet.
                  </Text>
                ) : (
                  <View
                    style={[
                      styles.list,
                      {
                        backgroundColor: colors.card,
                        borderColor: colors.border,
                        borderRadius: colors.radius,
                      },
                    ]}
                  >
                    {runsQuery.data!.runs.map((run, i) => {
                      const ok = run.status === "ok";
                      const running = run.status === "running";
                      const color = running
                        ? colors.primary
                        : ok
                          ? colors.success
                          : colors.destructive;
                      return (
                        <View
                          key={run.id}
                          style={[
                            styles.runRow,
                            {
                              borderTopWidth:
                                i === 0 ? 0 : StyleSheet.hairlineWidth,
                              borderTopColor: colors.border,
                            },
                          ]}
                        >
                          <View
                            style={[
                              styles.badge,
                              { backgroundColor: color + "22" },
                            ]}
                          >
                            <Text style={[styles.badgeText, { color }]}>
                              {running ? "Running" : ok ? "OK" : "Failed"}
                            </Text>
                          </View>
                          <View style={{ flex: 1, gap: 2 }}>
                            <Text
                              style={{ color: colors.foreground, fontSize: 13 }}
                            >
                              {formatWhen(run.started_at)}
                              {run.runtime ? ` · ${run.runtime}` : ""}
                              {run.exit_code !== null && !ok && !running
                                ? ` · exit ${run.exit_code}`
                                : ""}
                              {run.source ? ` · ${run.source}` : ""}
                            </Text>
                            {run.error ? (
                              <Text
                                style={{
                                  color: colors.destructive,
                                  fontSize: 12,
                                }}
                                numberOfLines={3}
                              >
                                {run.error}
                              </Text>
                            ) : null}
                          </View>
                        </View>
                      );
                    })}
                  </View>
                )}
              </ScrollView>
            ) : null}
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { borderWidth: StyleSheet.hairlineWidth, borderRadius: 16, padding: 16 },
  cardHead: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 10,
  },
  cardTitle: { fontSize: 16, fontWeight: "700" },
  note: { marginTop: 8, fontSize: 13 },
  groupLabel: {
    fontSize: 12,
    fontWeight: "700",
    textTransform: "uppercase",
    letterSpacing: 0.6,
  },
  list: { borderWidth: StyleSheet.hairlineWidth, overflow: "hidden" },
  listItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
  },
  rowHead: { flexDirection: "row", alignItems: "center", gap: 6 },
  itemLabel: { fontSize: 14, fontWeight: "600", flexShrink: 1 },
  pillRow: { flexDirection: "row", gap: 6, marginTop: 2 },
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 999,
    alignSelf: "flex-start",
  },
  badgeText: { fontSize: 11, fontWeight: "700" },
  sheetBackdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.45)",
    justifyContent: "flex-end",
  },
  sheet: {
    maxHeight: "85%",
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    borderWidth: StyleSheet.hairlineWidth,
  },
  runRow: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 10,
    padding: 12,
  },
  protectedNote: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    padding: 10,
  },
  actionRow: { flexDirection: "row", flexWrap: "wrap", gap: 10, marginTop: 4 },
  staleRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    marginTop: 10,
  },
  staleInput: {
    borderWidth: StyleSheet.hairlineWidth,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    fontSize: 15,
    minWidth: 72,
    textAlign: "center",
  },
  actionBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 14,
    paddingVertical: 9,
    borderRadius: 999,
  },
});
