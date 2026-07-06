import { Feather } from "@expo/vector-icons";
import { useEffect, useRef, useState } from "react";
import {
  AccessibilityInfo,
  Animated,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import type { DashboardPreset } from "@/lib/api/dashboard";

/**
 * Mobile parity for the web "how it works" AI Dashboard demo (Task #3714 /
 * originally #3705). A lightweight, looping "describe → AI arranges →
 * dashboard updates" explainer driven by the SAME real DashboardPresets the
 * web demo uses (passed in from the /dashboard/layout API), so no invented
 * metrics or widget names. Cycles through each preset: types out its
 * description in a prompt console, shows the AI "arranging", then reveals the
 * preset's real widget tiles.
 *
 * Respects the OS reduce-motion setting: when enabled, there is no typing,
 * tile stagger, or auto-advance — the first (or tapped) preset renders fully
 * and statically. Tab dots stay tappable in both modes.
 */

type WidgetMeta = { icon: keyof typeof Feather.glyphMap; label: string };

// Mirrors the web demo's $__aiddWidgetMeta, mapped to Feather glyphs (the
// mobile app's icon set) since FontAwesome names don't exist here.
const WIDGET_META: Record<string, WidgetMeta> = {
  stat_total_clicks: { icon: "bar-chart-2", label: "Total Clicks" },
  stat_today: { icon: "calendar", label: "Today" },
  stat_plan: { icon: "award", label: "Plan" },
  stat_links: { icon: "link", label: "Links" },
  stat_projects: { icon: "folder", label: "Projects" },
  recent_links: { icon: "clock", label: "Recent Links" },
  quick_actions: { icon: "zap", label: "Quick Actions" },
  plan_detail: { icon: "info", label: "Plan Detail" },
  traffic_channels: { icon: "share-2", label: "Traffic Channels" },
  backlinks: { icon: "trending-up", label: "Backlinks" },
  coin_balance: { icon: "dollar-sign", label: "Coin Balance" },
};

// Preset icons come through the API as FontAwesome names (DashboardPresets
// PHP constant); map the handful we ship to Feather glyphs.
const PRESET_ICONS: Record<string, keyof typeof Feather.glyphMap> = {
  "fa-gauge-high": "activity",
  "fa-arrow-trend-up": "trending-up",
  "fa-folder-open": "folder",
  "fa-gem": "award",
  "fa-users": "users",
};

const MAX_TILES = 4;
const TYPE_MS = 24;
const ARRANGE_MS = 650;
const DWELL_MS = 6200;

function widgetMeta(key: string): WidgetMeta {
  return (
    WIDGET_META[key] ?? {
      icon: "grid",
      label: key
        .replace(/_/g, " ")
        .replace(/\b\w/g, (c) => c.toUpperCase()),
    }
  );
}

function presetIcon(icon?: string): keyof typeof Feather.glyphMap {
  return (icon && PRESET_ICONS[icon]) || "activity";
}

export function AiDashboardDemo({ presets }: { presets: DashboardPreset[] }) {
  const colors = useColors();

  const [reduceMotion, setReduceMotion] = useState(false);
  useEffect(() => {
    let mounted = true;
    AccessibilityInfo.isReduceMotionEnabled().then((on) => {
      if (mounted) setReduceMotion(on);
    });
    const sub = AccessibilityInfo.addEventListener(
      "reduceMotionChanged",
      (on) => setReduceMotion(on),
    );
    return () => {
      mounted = false;
      sub.remove();
    };
  }, []);

  const [index, setIndex] = useState(0);
  const [typed, setTyped] = useState("");
  const [status, setStatus] = useState<"listening" | "thinking" | "ready">(
    "ready",
  );

  const tileAnims = useRef(
    Array.from({ length: MAX_TILES }, () => new Animated.Value(0)),
  ).current;

  const active = presets[index] ?? presets[0];
  const prompt = active?.description || active?.label || "";
  const widgets = (active?.widgets ?? []).slice(0, MAX_TILES);

  useEffect(() => {
    if (!active) return;

    const timers: ReturnType<typeof setTimeout>[] = [];
    let typeTimer: ReturnType<typeof setInterval> | null = null;

    const playTiles = () => {
      tileAnims.forEach((v) => v.setValue(0));
      Animated.stagger(
        90,
        tileAnims
          .slice(0, widgets.length)
          .map((v) =>
            Animated.timing(v, {
              toValue: 1,
              duration: 320,
              useNativeDriver: true,
            }),
          ),
      ).start();
    };

    if (reduceMotion) {
      setTyped(prompt);
      setStatus("ready");
      tileAnims.forEach((v) => v.setValue(1));
      return () => {};
    }

    // 1) Type out the prompt char by char.
    setTyped("");
    setStatus("listening");
    let pos = 0;
    typeTimer = setInterval(() => {
      pos += 1;
      setTyped(prompt.slice(0, pos));
      if (pos >= prompt.length && typeTimer) {
        clearInterval(typeTimer);
        typeTimer = null;
        // 2) AI "arranges" the dashboard.
        setStatus("thinking");
        timers.push(
          setTimeout(() => {
            setStatus("ready");
            playTiles();
          }, ARRANGE_MS),
        );
      }
    }, TYPE_MS);

    // 3) Auto-advance to the next preset.
    if (presets.length > 1) {
      timers.push(
        setTimeout(
          () => setIndex((i) => (i + 1) % presets.length),
          DWELL_MS,
        ),
      );
    }

    return () => {
      if (typeTimer) clearInterval(typeTimer);
      timers.forEach(clearTimeout);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [index, reduceMotion, presets.length]);

  if (!active) return null;

  const statusText =
    status === "thinking"
      ? "Arranging your dashboard…"
      : status === "listening"
        ? "Listening"
        : "Ready";

  return (
    <View
      style={styles.wrap}
      accessibilityRole="summary"
      accessibilityLabel="Demo: describe what you want and AI arranges your dashboard"
    >
      {/* Prompt console */}
      <View
        style={[
          styles.console,
          { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
        ]}
      >
        <View style={styles.consoleHead}>
          <View style={[styles.badge, { backgroundColor: colors.primary + "1f" }]}>
            <Feather name="zap" size={11} color={colors.primary} />
            <Text style={[styles.badgeText, { color: colors.primary }]}>
              AI DESIGNER
            </Text>
          </View>
          <View style={styles.statusRow}>
            <View
              style={[
                styles.statusDot,
                {
                  backgroundColor:
                    status === "thinking" ? colors.warning : colors.primary,
                },
              ]}
            />
            <Text style={[styles.statusText, { color: colors.mutedForeground }]}>
              {statusText}
            </Text>
          </View>
        </View>
        <View style={styles.promptRow}>
          <Feather
            name="message-square"
            size={13}
            color={colors.primary}
            style={{ marginTop: 2 }}
          />
          <Text style={[styles.promptText, { color: colors.foreground }]}>
            {typed}
            {!reduceMotion && status !== "ready" ? (
              <Text style={{ color: colors.primary }}>▍</Text>
            ) : null}
          </Text>
        </View>
      </View>

      <Feather
        name="arrow-down"
        size={16}
        color={colors.mutedForeground}
        style={styles.arrow}
      />

      {/* Dashboard board */}
      <View
        style={[
          styles.board,
          { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
        ]}
      >
        <View style={styles.boardHead}>
          <View style={[styles.boardIcon, { backgroundColor: colors.primary }]}>
            <Feather
              name={presetIcon(active.icon)}
              size={13}
              color={colors.primaryForeground}
            />
          </View>
          <Text style={[styles.boardTitle, { color: colors.foreground }]}>
            {active.label}
          </Text>
        </View>
        <View style={styles.tiles}>
          {widgets.map((key, j) => {
            const meta = widgetMeta(key);
            return (
              <Animated.View
                key={`${index}-${key}-${j}`}
                style={[
                  styles.tile,
                  {
                    borderColor: colors.border,
                    backgroundColor: colors.background,
                    opacity: tileAnims[j],
                    transform: [
                      {
                        translateY: tileAnims[j].interpolate({
                          inputRange: [0, 1],
                          outputRange: [8, 0],
                        }),
                      },
                    ],
                  },
                ]}
              >
                <Feather name={meta.icon} size={13} color={colors.primary} />
                <Text
                  style={[styles.tileText, { color: colors.foreground }]}
                  numberOfLines={1}
                >
                  {meta.label}
                </Text>
              </Animated.View>
            );
          })}
        </View>
      </View>

      {/* Tab dots */}
      {presets.length > 1 ? (
        <View style={styles.tabs}>
          {presets.map((p, i) => {
            const on = i === index;
            return (
              <Pressable
                key={p.key}
                hitSlop={8}
                onPress={() => setIndex(i)}
                accessibilityRole="button"
                accessibilityLabel={`Show the ${p.label} layout`}
              >
                <View
                  style={[
                    styles.tab,
                    {
                      width: on ? 22 : 14,
                      backgroundColor: on ? colors.primary : colors.border,
                    },
                  ]}
                />
              </Pressable>
            );
          })}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { gap: 10 },
  console: {
    borderWidth: 1,
    padding: 14,
    minHeight: 92,
  },
  consoleHead: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 10,
  },
  badge: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
  },
  badgeText: { fontSize: 10, fontWeight: "800", letterSpacing: 0.5 },
  statusRow: { flexDirection: "row", alignItems: "center", gap: 5 },
  statusDot: { width: 6, height: 6, borderRadius: 999 },
  statusText: { fontSize: 11, fontWeight: "600" },
  promptRow: { flexDirection: "row", gap: 8 },
  promptText: { flex: 1, fontSize: 13, lineHeight: 19 },
  arrow: { alignSelf: "center" },
  board: {
    borderWidth: 1,
    padding: 14,
  },
  boardHead: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    marginBottom: 12,
  },
  boardIcon: {
    width: 28,
    height: 28,
    borderRadius: 8,
    alignItems: "center",
    justifyContent: "center",
  },
  boardTitle: { fontSize: 14, fontWeight: "700" },
  tiles: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  tile: {
    flexDirection: "row",
    alignItems: "center",
    gap: 7,
    borderWidth: 1,
    borderRadius: 10,
    paddingVertical: 9,
    paddingHorizontal: 10,
    width: "47%",
  },
  tileText: { flex: 1, fontSize: 12, fontWeight: "600" },
  tabs: {
    flexDirection: "row",
    justifyContent: "center",
    alignItems: "center",
    gap: 6,
    marginTop: 2,
  },
  tab: { height: 3, borderRadius: 999 },
});
