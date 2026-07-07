import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import * as Clipboard from "expo-clipboard";
import { Stack } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import { getCronJobs, type CronJob } from "@/lib/api/cronJobs";

// Super-admin parity for the web admin "Cron Jobs" reference page. Read-only:
// shows the single master crontab line an operator must add to the server (with
// a one-tap copy) plus the derived list of every scheduled job — command,
// plain-English frequency, raw cron expression, purpose and next run — so an
// operator can check what cron entries are required without opening a laptop.

function formatNextRun(iso: string | null): string {
  if (!iso) return "—";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "—";
  return d.toLocaleString();
}

export default function CronJobsScreen() {
  const colors = useColors();
  const [copied, setCopied] = useState(false);

  const query = useQuery({
    queryKey: ["cron-jobs"],
    queryFn: getCronJobs,
  });

  const data = query.data;

  const copyMaster = async () => {
    if (!data?.master_cron_line) return;
    await Clipboard.setStringAsync(data.master_cron_line);
    setCopied(true);
    setTimeout(() => setCopied(false), 1800);
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Cron jobs", headerBackTitle: "Back" }} />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 48 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 403
                ? "You need admin access to view cron jobs."
                : "Couldn't load cron jobs."}
            </Text>
          </View>
        ) : data ? (
          <>
            {/* Master crontab line */}
            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <View style={styles.cardHead}>
                <Text style={[styles.cardTitle, { color: colors.foreground }]}>
                  Master crontab line
                </Text>
                <View style={[styles.badge, { backgroundColor: colors.primary + "22" }]}>
                  <Text style={[styles.badgeText, { color: colors.primary }]}>
                    {data.jobs.length} job{data.jobs.length === 1 ? "" : "s"}
                  </Text>
                </View>
              </View>
              <Text style={[styles.note, { color: colors.mutedForeground }]}>
                Add this single entry to the server crontab — it drives every
                scheduled job below.
              </Text>
              <View style={[styles.codeBox, { backgroundColor: colors.background, borderColor: colors.border }]}>
                <Text style={[styles.codeText, { color: colors.foreground }]} selectable>
                  {data.master_cron_line}
                </Text>
              </View>
              <Pressable
                onPress={copyMaster}
                style={({ pressed }) => [
                  styles.copyBtn,
                  { borderColor: colors.border, opacity: pressed ? 0.7 : 1 },
                ]}
              >
                <Feather
                  name={copied ? "check" : "copy"}
                  size={15}
                  color={copied ? colors.success : colors.primary}
                />
                <Text style={[styles.copyText, { color: copied ? colors.success : colors.primary }]}>
                  {copied ? "Copied" : "Copy line"}
                </Text>
              </Pressable>
            </View>

            {/* Job list */}
            <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
              Required scheduled jobs
            </Text>
            {data.jobs.map((job, i) => (
              <CronJobCard key={`${job.command}-${i}`} job={job} colors={colors} />
            ))}
          </>
        ) : null}
      </ScrollView>
    </View>
  );
}

function CronJobCard({
  job,
  colors,
}: {
  job: CronJob;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
      <View style={styles.cardHead}>
        <Text style={[styles.mono, { color: colors.foreground, flex: 1 }]} selectable>
          {job.command}
        </Text>
        {job.is_callback ? (
          <View style={[styles.badge, { backgroundColor: colors.mutedForeground + "22" }]}>
            <Text style={[styles.badgeText, { color: colors.mutedForeground }]}>Closure</Text>
          </View>
        ) : null}
      </View>

      {job.purpose && job.purpose !== "—" ? (
        <Text style={[styles.note, { color: colors.mutedForeground }]}>{job.purpose}</Text>
      ) : null}

      <View style={styles.metaGrid}>
        <MetaRow icon="clock" label="Frequency" value={job.frequency} colors={colors} />
        <MetaRow icon="terminal" label="Cron" value={job.expression} mono colors={colors} />
        <MetaRow
          icon="calendar"
          label="Next run"
          value={formatNextRun(job.next_run)}
          colors={colors}
        />
      </View>

      {job.without_overlapping ||
      job.on_one_server ||
      job.running_now ||
      job.failing_repeatedly ? (
        <View style={styles.flags}>
          {job.failing_repeatedly ? (
            <Flag
              label={`Failing repeatedly (${job.failing_streak} in a row)`}
              color={colors.destructive}
              colors={colors}
            />
          ) : null}
          {job.running_now ? (
            <Flag label="Running now" color={colors.success} colors={colors} />
          ) : null}
          {job.without_overlapping ? (
            <Flag label="No overlap" color={colors.primary} colors={colors} />
          ) : null}
          {job.on_one_server ? (
            <Flag label="One server" color={colors.primary} colors={colors} />
          ) : null}
        </View>
      ) : null}
    </View>
  );
}

function MetaRow({
  icon,
  label,
  value,
  mono,
  colors,
}: {
  icon: keyof typeof Feather.glyphMap;
  label: string;
  value: string;
  mono?: boolean;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View style={styles.metaRow}>
      <Feather name={icon} size={13} color={colors.mutedForeground} />
      <Text style={[styles.metaLabel, { color: colors.mutedForeground }]}>{label}</Text>
      <Text
        style={[
          styles.metaValue,
          mono ? styles.mono : null,
          { color: colors.foreground },
        ]}
        selectable
      >
        {value}
      </Text>
    </View>
  );
}

function Flag({
  label,
  color,
  colors,
}: {
  label: string;
  color: string;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View style={[styles.flag, { backgroundColor: color + "1a", borderColor: color + "44" }]}>
      <Text style={[styles.flagText, { color }]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { padding: 14, borderWidth: 1, borderRadius: 12, gap: 8 },
  cardHead: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 8,
  },
  cardTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 15 },
  sectionLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.3,
    textTransform: "uppercase",
    marginTop: 4,
  },
  note: { fontSize: 12, fontFamily: "SpaceGrotesk_500Medium", lineHeight: 17 },
  badge: { paddingHorizontal: 10, paddingVertical: 3, borderRadius: 999 },
  badgeText: { fontSize: 11, fontFamily: "SpaceGrotesk_700Bold" },
  mono: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  codeBox: { padding: 10, borderWidth: 1, borderRadius: 8 },
  codeText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  copyBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    paddingVertical: 9,
    borderWidth: 1,
    borderRadius: 9,
  },
  copyText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  metaGrid: { gap: 6, marginTop: 2 },
  metaRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  metaLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    width: 72,
  },
  metaValue: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12, flex: 1 },
  flags: { flexDirection: "row", flexWrap: "wrap", gap: 6, marginTop: 2 },
  flag: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6, borderWidth: 1 },
  flagText: { fontSize: 10, fontFamily: "SpaceGrotesk_700Bold" },
});
