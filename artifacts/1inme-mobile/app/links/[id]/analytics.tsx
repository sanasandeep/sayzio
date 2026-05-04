import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from "react-native";

import { StatTile } from "@/components/StatTile";
import { useColors } from "@/hooks/useColors";
import {
  type BlockAnalytics,
  type RateLimitConfig,
  type VisitorType,
  getAnalytics,
  getBlockAnalytics,
  getNfcCount,
  getRateLimit,
  updateRateLimit,
} from "@/lib/api/analytics";

const VISITOR_LABEL: Record<VisitorType, string> = {
  anonymous: "Anonymous",
  registered: "Registered",
  follower: "Followers",
  subscriber: "Subscribers",
};

export default function LinkAnalyticsScreen() {
  const colors = useColors();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const a = useQuery({
    queryKey: ["analytics", id],
    queryFn: () => getAnalytics(id),
    enabled: Number.isFinite(id),
  });
  const n = useQuery({
    queryKey: ["nfc-count", id],
    queryFn: () => getNfcCount(id),
    enabled: Number.isFinite(id),
  });

  if (a.isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }
  if (a.error || !a.data) {
    return (
      <View style={styles.center}>
        <Text style={{ color: colors.destructive }}>
          Couldn't load analytics.
        </Text>
      </View>
    );
  }

  const data = a.data;
  const maxDay = Math.max(1, ...data.by_day.map((d) => d.clicks));
  const blocks = data.by_block ?? [];

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: "Analytics" }} />
      <ScrollView contentContainerStyle={styles.body}>
        <View style={styles.tileRow}>
          <StatTile
            label="Total clicks"
            value={data.total_clicks}
            icon="bar-chart-2"
          />
          <StatTile label="Unique" value={data.unique_clicks} icon="users" />
        </View>
        <View style={styles.tileRow}>
          <StatTile
            label="NFC writes"
            value={n.data ?? 0}
            icon="wifi"
            hint="Tag programmings"
          />
          <StatTile
            label="Bots blocked / wk"
            value={data.blocked_this_week ?? 0}
            icon="shield"
            hint="Excluded from totals"
          />
        </View>

        {data.blocked_by_day && data.blocked_by_day.length > 0 ? (
          <Section
            title="Blocked attempts"
            subtitle="Bot + throttled hits the rate limiter dropped over time"
          >
            <BlockedChart rows={data.blocked_by_day} />
          </Section>
        ) : null}

        <RateLimitSection linkId={id} initial={data.rate_limit} />

        <Section title="Clicks by day">
          {data.by_day.length === 0 ? (
            <Text style={{ color: colors.mutedForeground }}>
              No clicks in the selected window.
            </Text>
          ) : (
            <View style={{ gap: 6 }}>
              {data.by_day.map((d) => (
                <View key={d.day} style={styles.barRow}>
                  <Text
                    style={[styles.barLabel, { color: colors.mutedForeground }]}
                  >
                    {d.day}
                  </Text>
                  <View style={styles.barTrack}>
                    <View
                      style={{
                        height: 8,
                        width: `${(d.clicks / maxDay) * 100}%`,
                        backgroundColor: colors.primary,
                        borderRadius: 4,
                      }}
                    />
                  </View>
                  <Text style={[styles.barValue, { color: colors.foreground }]}>
                    {d.clicks}
                  </Text>
                </View>
              ))}
            </View>
          )}
        </Section>

        <Section
          title="Blocks"
          subtitle="Tap a block to see clicks/day, referrers, devices, and visitor types"
        >
          {blocks.length === 0 ? (
            <Text style={{ color: colors.mutedForeground }}>
              No block clicks yet. Once visitors tap your blocks, they'll show
              up here.
            </Text>
          ) : (
            <View style={{ gap: 6 }}>
              {blocks.map((b) => (
                <BlockRow key={b.block_id} linkId={id} block={b} />
              ))}
            </View>
          )}
        </Section>

        <Section title="Top countries">
          <Breakdown
            rows={data.by_country.map((r) => ({
              label: r.country || "Unknown",
              clicks: r.clicks,
            }))}
          />
        </Section>

        <Section title="Top referrers">
          <Breakdown
            rows={data.by_referrer.map((r) => ({
              label: r.referrer_host || "Direct",
              clicks: r.clicks,
            }))}
          />
        </Section>

        <Section title="Devices">
          <Breakdown
            rows={data.by_device.map((r) => ({
              label: r.device_type || "Unknown",
              clicks: r.clicks,
            }))}
          />
        </Section>

        <Section title="Mobile app vs web">
          <Breakdown
            rows={(data.by_source ?? []).map((r) => ({
              label:
                r.source === "mobile_app"
                  ? "Mobile app"
                  : r.source === "web"
                    ? "Web"
                    : "Unknown",
              clicks: r.clicks,
            }))}
          />
        </Section>
      </ScrollView>
    </View>
  );
}

function BlockedChart({ rows }: { rows: { day: string; clicks: number }[] }) {
  const colors = useColors();
  const max = Math.max(1, ...rows.map((r) => r.clicks));
  return (
    <View
      style={{
        flexDirection: "row",
        alignItems: "flex-end",
        gap: 2,
        height: 60,
      }}
    >
      {rows.map((d) => {
        const h = Math.max(2, (d.clicks / max) * 100);
        return (
          <View
            key={d.day}
            style={{
              flex: 1,
              height: `${h}%`,
              backgroundColor: colors.destructive,
              opacity: 0.7,
              borderTopLeftRadius: 2,
              borderTopRightRadius: 2,
              minHeight: 2,
            }}
          />
        );
      })}
    </View>
  );
}

function BlockRow({
  linkId,
  block,
}: {
  linkId: number;
  block: NonNullable<
    Awaited<ReturnType<typeof getAnalytics>>["by_block"]
  >[number];
}) {
  const colors = useColors();
  const [open, setOpen] = useState(false);
  const [days, setDays] = useState<7 | 30 | 90>(30);

  const drill = useQuery({
    queryKey: ["block-analytics", linkId, block.block_id, days],
    queryFn: () => getBlockAnalytics(linkId, block.block_id, days),
    enabled: open,
  });

  const title = block.title || `Block #${block.block_id}`;

  return (
    <View
      style={[
        styles.blockCard,
        { backgroundColor: colors.background, borderColor: colors.border },
      ]}
    >
      <Pressable
        onPress={() => setOpen((v) => !v)}
        style={styles.blockHeader}
        accessibilityRole="button"
        accessibilityLabel={`Show drill-down for ${title}`}
      >
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text
            style={{
              color: colors.foreground,
              fontFamily: "SpaceGrotesk_600SemiBold",
              fontSize: 13,
            }}
            numberOfLines={1}
          >
            {title}
          </Text>
          <Text
            style={{
              color: colors.mutedForeground,
              fontFamily: "SpaceGrotesk_500Medium",
              fontSize: 11,
              marginTop: 2,
            }}
            numberOfLines={1}
          >
            {block.type ?? "block"}
            {block.destination_url ? ` · ${block.destination_url}` : ""}
          </Text>
        </View>
        <View style={{ alignItems: "flex-end" }}>
          <Text
            style={{
              color: colors.foreground,
              fontFamily: "SpaceGrotesk_700Bold",
              fontSize: 13,
            }}
          >
            {block.clicks}
          </Text>
          <Text
            style={{
              color: colors.mutedForeground,
              fontFamily: "SpaceGrotesk_500Medium",
              fontSize: 10,
            }}
          >
            {block.unique_clicks} unique
          </Text>
        </View>
        <Feather
          name={open ? "chevron-up" : "chevron-down"}
          size={16}
          color={colors.mutedForeground}
          style={{ marginLeft: 8 }}
        />
      </Pressable>

      {open ? (
        <View style={styles.drillBody}>
          <View style={styles.rangeRow}>
            {([7, 30, 90] as const).map((opt) => {
              const active = days === opt;
              return (
                <Pressable
                  key={opt}
                  onPress={() => setDays(opt)}
                  style={[
                    styles.rangeChip,
                    {
                      backgroundColor: active ? colors.primary : colors.card,
                      borderColor: colors.border,
                    },
                  ]}
                >
                  <Text
                    style={{
                      color: active
                        ? colors.primaryForeground
                        : colors.foreground,
                      fontFamily: "SpaceGrotesk_600SemiBold",
                      fontSize: 11,
                    }}
                  >
                    {opt}d
                  </Text>
                </Pressable>
              );
            })}
          </View>

          {drill.isLoading ? (
            <View style={{ paddingVertical: 16, alignItems: "center" }}>
              <ActivityIndicator color={colors.primary} />
            </View>
          ) : drill.error || !drill.data ? (
            <Text style={{ color: colors.destructive, fontSize: 12 }}>
              Couldn't load drill-down.
            </Text>
          ) : (
            <BlockDrill data={drill.data} />
          )}
        </View>
      ) : null}
    </View>
  );
}

function BlockDrill({ data }: { data: BlockAnalytics }) {
  const colors = useColors();
  const dayMax = Math.max(1, ...data.by_day.map((d) => d.clicks));

  return (
    <View style={{ gap: 14 }}>
      <View style={styles.tileRow}>
        <StatTile
          label="Clicks"
          value={data.total_clicks}
          icon="bar-chart-2"
        />
        <StatTile label="Unique" value={data.unique_clicks} icon="users" />
      </View>

      <SubSection title="Clicks per day">
        {data.by_day.length === 0 ? (
          <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
            No clicks in this range.
          </Text>
        ) : (
          <View style={{ flexDirection: "row", alignItems: "flex-end", gap: 2, height: 60 }}>
            {data.by_day.map((d) => {
              const h = Math.max(2, (d.clicks / dayMax) * 100);
              return (
                <View
                  key={d.day}
                  style={{
                    flex: 1,
                    height: `${h}%`,
                    backgroundColor: colors.primary,
                    borderTopLeftRadius: 2,
                    borderTopRightRadius: 2,
                    minHeight: 2,
                  }}
                />
              );
            })}
          </View>
        )}
      </SubSection>

      <SubSection title="Top referrers">
        <Breakdown
          rows={data.by_referrer.map((r) => ({
            label: r.referrer_host || "Direct",
            clicks: r.clicks,
          }))}
        />
      </SubSection>

      <SubSection title="Devices">
        <Breakdown
          rows={data.by_device.map((r) => ({
            label: r.device_type || "Unknown",
            clicks: r.clicks,
          }))}
        />
      </SubSection>

      <SubSection title="By visitor type">
        <Breakdown
          rows={data.by_visitor_type.map((r) => ({
            label: VISITOR_LABEL[r.visitor_type] ?? r.visitor_type,
            clicks: r.clicks,
          }))}
        />
      </SubSection>
    </View>
  );
}

function RateLimitSection({
  linkId,
  initial,
}: {
  linkId: number;
  initial?: RateLimitConfig;
}) {
  const colors = useColors();
  const qc = useQueryClient();
  const cfg = useQuery({
    queryKey: ["rate-limit", linkId],
    queryFn: () => getRateLimit(linkId),
    initialData: initial,
    enabled: Number.isFinite(linkId),
  });

  const [enabled, setEnabled] = useState<boolean>(initial?.enabled ?? true);
  const [ip, setIp] = useState<string>(String(initial?.ip_per_min ?? 30));
  const [fp, setFp] = useState<string>(String(initial?.fp_per_min ?? 60));
  const [savedMsg, setSavedMsg] = useState<string | null>(null);

  useEffect(() => {
    if (cfg.data) {
      setEnabled(cfg.data.enabled);
      setIp(String(cfg.data.ip_per_min));
      setFp(String(cfg.data.fp_per_min));
    }
  }, [cfg.data]);

  const save = useMutation({
    mutationFn: (patch: Partial<RateLimitConfig>) =>
      updateRateLimit(linkId, patch),
    onSuccess: (next) => {
      qc.setQueryData(["rate-limit", linkId], next);
      qc.invalidateQueries({ queryKey: ["analytics", linkId] });
      setSavedMsg("Saved");
      setTimeout(() => setSavedMsg(null), 1800);
    },
  });

  const onSave = () => {
    const ipN = Math.max(1, Math.min(10000, parseInt(ip, 10) || 30));
    const fpN = Math.max(1, Math.min(10000, parseInt(fp, 10) || 60));
    setIp(String(ipN));
    setFp(String(fpN));
    save.mutate({ enabled, ip_per_min: ipN, fp_per_min: fpN });
  };

  return (
    <Section
      title="Visitor protection"
      subtitle="Throttle floods and bot traffic on this link"
    >
      <View style={{ gap: 12 }}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            justifyContent: "space-between",
          }}
        >
          <View style={{ flex: 1, paddingRight: 12 }}>
            <Text style={{ color: colors.foreground, fontSize: 13 }}>
              Rate limiting enabled
            </Text>
            <Text
              style={{
                color: colors.mutedForeground,
                fontSize: 11,
                marginTop: 2,
              }}
            >
              When off, every visitor is recorded — even obvious bots.
            </Text>
          </View>
          <Switch value={enabled} onValueChange={setEnabled} />
        </View>

        <View style={{ flexDirection: "row", gap: 12 }}>
          <View style={{ flex: 1 }}>
            <Text
              style={{
                color: colors.mutedForeground,
                fontSize: 11,
                marginBottom: 4,
              }}
            >
              IP hits / minute
            </Text>
            <TextInput
              value={ip}
              onChangeText={setIp}
              keyboardType="number-pad"
              editable={enabled}
              style={[
                styles.input,
                {
                  borderColor: colors.border,
                  color: colors.foreground,
                  opacity: enabled ? 1 : 0.5,
                },
              ]}
            />
          </View>
          <View style={{ flex: 1 }}>
            <Text
              style={{
                color: colors.mutedForeground,
                fontSize: 11,
                marginBottom: 4,
              }}
            >
              Fingerprint hits / minute
            </Text>
            <TextInput
              value={fp}
              onChangeText={setFp}
              keyboardType="number-pad"
              editable={enabled}
              style={[
                styles.input,
                {
                  borderColor: colors.border,
                  color: colors.foreground,
                  opacity: enabled ? 1 : 0.5,
                },
              ]}
            />
          </View>
        </View>

        <View
          style={{
            flexDirection: "row",
            justifyContent: "flex-end",
            alignItems: "center",
            gap: 12,
          }}
        >
          {savedMsg ? (
            <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
              {savedMsg}
            </Text>
          ) : null}
          <Pressable
            onPress={onSave}
            disabled={save.isPending}
            style={({ pressed }) => [
              {
                paddingHorizontal: 16,
                paddingVertical: 10,
                borderRadius: 10,
                backgroundColor: colors.primary,
                opacity: pressed || save.isPending ? 0.7 : 1,
              },
            ]}
          >
            <Text
              style={{
                color: colors.primaryForeground,
                fontFamily: "SpaceGrotesk_600SemiBold",
                fontSize: 13,
              }}
            >
              {save.isPending ? "Saving…" : "Save limits"}
            </Text>
          </Pressable>
        </View>
      </View>
    </Section>
  );
}

function Section({
  title,
  subtitle,
  children,
}: {
  title: string;
  subtitle?: string;
  children: React.ReactNode;
}) {
  const colors = useColors();
  return (
    <View
      style={[
        styles.section,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
        {title}
      </Text>
      {subtitle ? (
        <Text
          style={{
            color: colors.mutedForeground,
            fontFamily: "SpaceGrotesk_500Medium",
            fontSize: 11,
            marginTop: -6,
          }}
        >
          {subtitle}
        </Text>
      ) : null}
      {children}
    </View>
  );
}

function SubSection({
  title,
  children,
}: {
  title: string;
  children: React.ReactNode;
}) {
  const colors = useColors();
  return (
    <View style={{ gap: 8 }}>
      <Text
        style={{
          color: colors.mutedForeground,
          fontFamily: "SpaceGrotesk_600SemiBold",
          fontSize: 11,
          textTransform: "uppercase",
          letterSpacing: 0.5,
        }}
      >
        {title}
      </Text>
      {children}
    </View>
  );
}

function Breakdown({ rows }: { rows: { label: string; clicks: number }[] }) {
  const colors = useColors();
  if (rows.length === 0) {
    return <Text style={{ color: colors.mutedForeground }}>No data yet.</Text>;
  }
  const max = Math.max(1, ...rows.map((r) => r.clicks));
  return (
    <View style={{ gap: 6 }}>
      {rows.slice(0, 8).map((r, i) => (
        <View key={`${r.label}-${i}`} style={styles.barRow}>
          <Text
            style={[styles.barLabel, { color: colors.foreground, flex: 1.4 }]}
            numberOfLines={1}
          >
            {r.label}
          </Text>
          <View style={styles.barTrack}>
            <View
              style={{
                height: 8,
                width: `${(r.clicks / max) * 100}%`,
                backgroundColor: colors.primary,
                borderRadius: 4,
              }}
            />
          </View>
          <Text style={[styles.barValue, { color: colors.foreground }]}>
            {r.clicks}
          </Text>
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  body: { padding: 20, gap: 16, paddingBottom: 40 },
  tileRow: { flexDirection: "row", gap: 12 },
  section: { borderWidth: 1, padding: 16, gap: 12 },
  sectionTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15 },
  barRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  barLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    flex: 1,
  },
  barTrack: { flex: 2, height: 8, borderRadius: 4, backgroundColor: "rgba(0,0,0,0.06)" },
  barValue: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 12,
    minWidth: 36,
    textAlign: "right",
  },
  blockCard: { borderWidth: 1, borderRadius: 10, padding: 10, gap: 10 },
  blockHeader: { flexDirection: "row", alignItems: "center", gap: 10 },
  drillBody: { gap: 12, paddingTop: 6, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: "rgba(0,0,0,0.08)" },
  rangeRow: { flexDirection: "row", gap: 6 },
  rangeChip: {
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 999,
    borderWidth: 1,
  },
  input: {
    borderWidth: 1,
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 8,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
  },
});
