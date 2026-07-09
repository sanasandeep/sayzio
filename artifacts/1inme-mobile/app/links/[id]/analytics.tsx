import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from "react-native";

import {
  ClickHeatmap,
  type ClickHeatmapHandle,
} from "@/components/ClickHeatmap";
import { CoinCostHint, insufficientCoins } from "@/components/CoinCostHint";
import { NfcWriteTrigger } from "@/components/NfcWriteTrigger";
import { StatTile } from "@/components/StatTile";
import { useColors } from "@/hooks/useColors";
import { useForegroundRefresh } from "@/hooks/useForegroundRefresh";
import {
  type AudienceEstimate,
  type AudienceEstimateRow,
  type BlockAnalytics,
  type RateLimitConfig,
  type VisitorType,
  getAnalytics,
  getBlockAnalytics,
  getHeatmap,
  getLiveHeatmap,
  getNfcCount,
  getRateLimit,
  runAudienceEstimate,
  updateRateLimit,
} from "@/lib/api/analytics";
import { getLink } from "@/lib/api/links";
import {
  handlePlanLockedError,
  upgradeHintFromError,
} from "@/lib/upgradePrompt";

const VISITOR_LABEL: Record<VisitorType, string> = {
  anonymous: "Anonymous",
  registered: "Registered",
  follower: "Followers",
  subscriber: "Subscribers",
};

function personaLabel(type: string): string {
  return type
    .split(/[_-]/)
    .map((w) => (w ? w[0].toUpperCase() + w.slice(1) : w))
    .join(" ");
}

const ESTIMATE_STALE_MS = 30 * 24 * 60 * 60 * 1000;

function estimateDateLabel(generatedAt?: string | null): string | null {
  if (!generatedAt) return null;
  const t = Date.parse(generatedAt);
  if (isNaN(t)) return null;
  return new Date(t).toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function estimateIsStale(generatedAt?: string | null): boolean {
  if (!generatedAt) return false;
  const t = Date.parse(generatedAt);
  return !isNaN(t) && Date.now() - t > ESTIMATE_STALE_MS;
}

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
  const linkQ = useQuery({
    queryKey: ["link", id],
    queryFn: () => getLink(id),
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
        {linkQ.data?.short_url ? (
          <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
            <NfcWriteTrigger
              linkId={id}
              url={linkQ.data.short_url}
              variant="button"
              onWritten={() => {
                void n.refetch();
              }}
            />
          </View>
        ) : null}

        <HeatmapSection linkId={id} />

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

        <AudienceInsightsSection
          linkId={id}
          selfIdentified={data.by_visitor_type ?? []}
          cachedEstimate={data.audience_estimate ?? null}
          estimateCoins={data.audience_estimate_coins ?? 0}
          coinBalance={data.coin_balance ?? null}
        />

        {(data.audience_estimate?.data ?? []).length > 0 ? (
          <Section
            title="AI audience estimate"
            subtitle={
              estimateDateLabel(data.audience_estimate?.generated_at)
                ? `Estimated on ${estimateDateLabel(data.audience_estimate?.generated_at)}`
                : "Inferred from aggregate session signals"
            }
          >
            <View style={{ gap: 8 }}>
              {(data.audience_estimate?.data ?? []).map((r) => (
                <View key={r.type} style={styles.barRow}>
                  <Text
                    style={[styles.barLabel, { color: colors.mutedForeground }]}
                    numberOfLines={1}
                  >
                    {r.label || personaLabel(r.type)}
                  </Text>
                  <View style={styles.barTrack}>
                    <View
                      style={{
                        height: 8,
                        width: `${Math.max(2, Math.min(100, r.pct))}%`,
                        backgroundColor: colors.primary,
                        borderRadius: 4,
                        opacity: 0.75,
                      }}
                    />
                  </View>
                  <Text style={[styles.barValue, { color: colors.foreground }]}>
                    ~{r.pct}%
                  </Text>
                </View>
              ))}
            </View>
            {estimateIsStale(data.audience_estimate?.generated_at) ? (
              <Text
                style={{ color: "#fbbf24", fontSize: 12, marginTop: 10 }}
                testID="text-estimate-stale"
              >
                This estimate is over 30 days old — re-run it from the web
                dashboard for a fresh read.
              </Text>
            ) : null}
          </Section>
        ) : null}

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

function AudienceInsightsSection({
  linkId,
  selfIdentified,
  cachedEstimate,
  estimateCoins,
  coinBalance,
}: {
  linkId: number;
  selfIdentified: { type: string; count: number; pct: number }[];
  cachedEstimate: AudienceEstimate | null;
  estimateCoins: number;
  coinBalance: number | null;
}) {
  const colors = useColors();
  const qc = useQueryClient();
  const [rows, setRows] = useState<AudienceEstimateRow[]>(
    Array.isArray(cachedEstimate?.data) ? cachedEstimate.data : [],
  );
  const [error, setError] = useState<string | null>(null);
  const [upgradeMsg, setUpgradeMsg] = useState<string | null>(null);
  const [freshNote, setFreshNote] = useState<string | null>(null);

  const estimate = useMutation({
    mutationFn: (force: boolean) => runAudienceEstimate(linkId, force),
    onSuccess: (res) => {
      setRows(res.estimated);
      setError(null);
      setUpgradeMsg(null);
      if (res.cached) {
        const t = Date.parse(res.generated_at ?? "");
        const mins = isNaN(t)
          ? null
          : Math.max(1, Math.round((Date.now() - t) / 60000));
        setFreshNote(
          mins !== null
            ? `Estimate is fresh — generated ${mins} minute${mins === 1 ? "" : "s"} ago. No coins were charged.`
            : "Estimate is fresh — no coins were charged.",
        );
      } else {
        setFreshNote(null);
      }
      qc.invalidateQueries({ queryKey: ["analytics", linkId] });
    },
    onError: (e) => {
      // Check insufficient coins FIRST: handlePlanLockedError treats all
      // plan-lock codes (including insufficient_credits) as upgrade prompts,
      // but running out of coins is a wallet top-up problem, not a plan one.
      if ((e as { code?: string })?.code === "insufficient_credits") {
        setError(
          "Not enough coins to run this estimate. Top up your wallet and try again.",
        );
        qc.invalidateQueries({ queryKey: ["analytics", linkId] });
        return;
      }
      if (handlePlanLockedError(e)) {
        // Also keep an inline hint so the section explains the lock after
        // the alert is dismissed (free plans; 402 plan_upgrade_required).
        const hint = upgradeHintFromError(e);
        setUpgradeMsg(
          hint?.planName
            ? `AI Audience Estimation is available on paid plans. Upgrade to ${hint.planName} to unlock it.`
            : "AI Audience Estimation is available on paid plans.",
        );
        return;
      }
      setError(
        (e as { message?: string })?.message ??
          "Estimation failed. Please try again.",
      );
    },
  });

  const hasEstimate = rows.length > 0;
  // Pre-run affordability check: only meaningful when both the cost hint
  // and the wallet balance came back from the analytics payload.
  const shortOnCoins = insufficientCoins(estimateCoins, coinBalance);

  return (
    <Section
      title="Audience insights"
      subtitle={
        selfIdentified.length > 0
          ? "Self-identified visitor personas"
          : "Who visits this link"
      }
    >
      <View style={{ gap: 14 }}>
        {selfIdentified.length > 0 ? (
          <View style={{ gap: 8 }}>
            {selfIdentified.map((r) => (
              <View key={r.type} style={styles.barRow}>
                <Text
                  style={[styles.barLabel, { color: colors.mutedForeground }]}
                  numberOfLines={1}
                >
                  {personaLabel(r.type)}
                </Text>
                <View style={styles.barTrack}>
                  <View
                    style={{
                      height: 8,
                      width: `${Math.max(2, Math.min(100, r.pct))}%`,
                      backgroundColor: colors.primary,
                      borderRadius: 4,
                    }}
                  />
                </View>
                <Text style={[styles.barValue, { color: colors.foreground }]}>
                  {r.count} · {r.pct}%
                </Text>
              </View>
            ))}
          </View>
        ) : null}

        {hasEstimate ? (
          <View style={{ gap: 8 }}>
            <View
              style={{ flexDirection: "row", alignItems: "center", gap: 8 }}
            >
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontFamily: "SpaceGrotesk_600SemiBold",
                  fontSize: 12,
                }}
              >
                AI Estimate
              </Text>
              <View
                style={{
                  paddingHorizontal: 7,
                  paddingVertical: 2,
                  borderRadius: 999,
                  backgroundColor: "rgba(99,102,241,0.18)",
                }}
              >
                <Text
                  style={{
                    color: "#a5b4fc",
                    fontSize: 10,
                    fontFamily: "SpaceGrotesk_600SemiBold",
                  }}
                >
                  estimated · last 30 days
                </Text>
              </View>
            </View>
            <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
              Probabilistic breakdown inferred from aggregate, anonymous
              session signals. Not based on any individual visitor data.
            </Text>
            {rows.map((r) => (
              <View key={r.type} style={styles.barRow}>
                <Text
                  style={[styles.barLabel, { color: colors.mutedForeground }]}
                  numberOfLines={1}
                >
                  {r.label || personaLabel(r.type)}
                </Text>
                <View style={styles.barTrack}>
                  <View
                    style={{
                      height: 8,
                      width: `${Math.max(2, Math.min(100, r.pct))}%`,
                      backgroundColor: "#818cf8",
                      borderRadius: 4,
                    }}
                  />
                </View>
                <Text style={[styles.barValue, { color: colors.foreground }]}>
                  ~{r.pct}%
                </Text>
              </View>
            ))}
          </View>
        ) : null}

        {!hasEstimate && selfIdentified.length === 0 ? (
          <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
            No visitors have self-identified yet. Use AI to estimate your
            audience mix from aggregate traffic signals.
          </Text>
        ) : null}

        <View style={{ gap: 8 }}>
          <Pressable
            onPress={() => estimate.mutate(false)}
            disabled={estimate.isPending || shortOnCoins}
            accessibilityRole="button"
            accessibilityState={{
              disabled: estimate.isPending || shortOnCoins,
            }}
            accessibilityLabel={
              hasEstimate ? "Re-estimate with AI" : "Get AI Estimate"
            }
            testID="button-run-audience-estimate"
            style={({ pressed }) => [
              {
                flexDirection: "row",
                alignItems: "center",
                justifyContent: "center",
                gap: 8,
                paddingHorizontal: 16,
                paddingVertical: 10,
                borderRadius: 10,
                alignSelf: "flex-start",
                backgroundColor: "rgba(99,102,241,0.15)",
                borderWidth: 1,
                borderColor: "rgba(99,102,241,0.3)",
                opacity:
                  pressed || estimate.isPending || shortOnCoins ? 0.5 : 1,
              },
            ]}
          >
            {estimate.isPending ? (
              <ActivityIndicator size="small" color="#a5b4fc" />
            ) : (
              <Feather name="zap" size={13} color="#a5b4fc" />
            )}
            <Text
              style={{
                color: "#a5b4fc",
                fontFamily: "SpaceGrotesk_600SemiBold",
                fontSize: 13,
              }}
            >
              {estimate.isPending
                ? "Estimating…"
                : hasEstimate
                  ? "Re-estimate with AI"
                  : "Get AI Estimate"}
            </Text>
          </Pressable>

          <CoinCostHint
            cost={estimateCoins}
            balance={coinBalance}
            actionLabel="this estimate"
          />
          {upgradeMsg ? (
            <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
              {upgradeMsg}
            </Text>
          ) : null}
          {error ? (
            <Text style={{ color: colors.destructive, fontSize: 12 }}>
              {error}
            </Text>
          ) : null}
          {freshNote ? (
            <Text
              testID="text-estimate-fresh"
              style={{ color: "#34d399", fontSize: 12 }}
            >
              {freshNote}
            </Text>
          ) : null}
          {freshNote ? (
            <Pressable
              testID="button-estimate-force"
              accessibilityRole="button"
              accessibilityLabel="Run a fresh estimate anyway"
              disabled={estimate.isPending}
              onPress={() =>
                Alert.alert(
                  "Run a fresh estimate?",
                  estimateCoins > 0
                    ? `This will charge up to ${estimateCoins} coin${estimateCoins === 1 ? "" : "s"} — run anyway?`
                    : "This will charge coins for a fresh run — run anyway?",
                  [
                    { text: "Cancel", style: "cancel" },
                    {
                      text: "Run anyway",
                      style: "destructive",
                      onPress: () => estimate.mutate(true),
                    },
                  ],
                )
              }
              style={({ pressed }) => [
                {
                  flexDirection: "row",
                  alignItems: "center",
                  gap: 6,
                  paddingHorizontal: 12,
                  paddingVertical: 7,
                  borderRadius: 8,
                  alignSelf: "flex-start",
                  backgroundColor: "rgba(251,191,36,0.12)",
                  borderWidth: 1,
                  borderColor: "rgba(251,191,36,0.3)",
                  opacity: pressed || estimate.isPending ? 0.6 : 1,
                },
              ]}
            >
              <Feather name="zap" size={12} color="#fbbf24" />
              <Text
                style={{
                  color: "#fbbf24",
                  fontFamily: "SpaceGrotesk_600SemiBold",
                  fontSize: 12,
                }}
              >
                Run fresh anyway
              </Text>
            </Pressable>
          ) : null}
        </View>
      </View>
    </Section>
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

function HeatmapSection({ linkId }: { linkId: number }) {
  const colors = useColors();
  const mapRef = useRef<ClickHeatmapHandle>(null);
  const lastIdRef = useRef(0);
  const [liveMode, setLiveMode] = useState(false);
  const [liveMeta, setLiveMeta] = useState<{
    unique_visitors: number;
    count: number;
  } | null>(null);

  const h = useQuery({
    queryKey: ["heatmap", linkId],
    queryFn: () => getHeatmap(linkId),
    enabled: Number.isFinite(linkId),
  });

  // Live polling: every 10s fetch clicks newer than the last cursor and pulse
  // them onto the map. Replaces the web SSE stream with a poll loop.
  const pollRef = useRef<(() => void) | null>(null);
  useEffect(() => {
    if (!liveMode) return;
    let cancelled = false;

    const poll = async () => {
      try {
        const live = await getLiveHeatmap(linkId, {
          lastId: lastIdRef.current || undefined,
        });
        if (cancelled) return;
        if (live.meta.last_id > lastIdRef.current) {
          lastIdRef.current = live.meta.last_id;
        }
        setLiveMeta({
          unique_visitors: live.meta.unique_visitors,
          count: live.points.length,
        });
        if (live.points.length) {
          mapRef.current?.addLivePoints(
            live.points.map((p) => ({ id: p.id, lat: p.lat, lng: p.lng })),
          );
        }
      } catch {
        // transient network/poll error — keep the interval running
      }
    };

    poll();
    pollRef.current = poll;
    const t = setInterval(poll, 10000);
    return () => {
      cancelled = true;
      pollRef.current = null;
      clearInterval(t);
    };
  }, [liveMode, linkId]);

  // Timers pause while backgrounded — pull missed clicks as soon as the app
  // resumes (only when live mode is on; pollRef is null otherwise).
  useForegroundRefresh(() => {
    pollRef.current?.();
  });

  const points = h.data?.points ?? [];
  const maxWeight = h.data?.meta.max_weight ?? 1;
  const totalGeo = h.data?.meta.total_clicks ?? 0;

  return (
    <Section
      title="Click map"
      subtitle="Where your clicks come from over the last 30 days"
    >
      <View style={{ gap: 12 }}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            justifyContent: "space-between",
          }}
        >
          <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
            <View
              style={{
                width: 8,
                height: 8,
                borderRadius: 4,
                backgroundColor: liveMode ? "#22c55e" : colors.mutedForeground,
              }}
            />
            <Text
              style={{
                color: colors.foreground,
                fontFamily: "SpaceGrotesk_600SemiBold",
                fontSize: 12,
              }}
            >
              {liveMode
                ? liveMeta
                  ? `Live · ${liveMeta.unique_visitors} active`
                  : "Live · listening…"
                : "Live mode"}
            </Text>
          </View>
          <Switch value={liveMode} onValueChange={setLiveMode} />
        </View>

        {h.isLoading ? (
          <View style={{ paddingVertical: 28, alignItems: "center" }}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : h.error ? (
          <Text style={{ color: colors.destructive, fontSize: 12 }}>
            Couldn't load the click map.
          </Text>
        ) : points.length === 0 ? (
          <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
            No located clicks yet. Once visitors with a known location tap this
            link, they'll appear on the map.
            {liveMode
              ? " Live pulses will still show new clicks as they arrive."
              : ""}
          </Text>
        ) : (
          <>
            <ClickHeatmap
              ref={mapRef}
              points={points}
              maxWeight={maxWeight}
            />
            <Text
              style={{
                color: colors.mutedForeground,
                fontFamily: "SpaceGrotesk_500Medium",
                fontSize: 11,
              }}
            >
              {totalGeo.toLocaleString()} located click
              {totalGeo === 1 ? "" : "s"} across {points.length} location
              {points.length === 1 ? "" : "s"}.
            </Text>
          </>
        )}

        {liveMode && points.length === 0 ? (
          <View
            style={{ height: 200, borderRadius: 12, overflow: "hidden" }}
          >
            <ClickHeatmap ref={mapRef} points={[]} maxWeight={1} height={200} />
          </View>
        ) : null}
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
