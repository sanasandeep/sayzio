import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { LinearGradient } from "expo-linear-gradient";
import Svg, {
  Circle,
  ClipPath,
  Defs,
  Ellipse,
  Image as SvgImage,
  Path,
  Polygon,
} from "react-native-svg";
import * as Linking from "expo-linking";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import * as React from "react";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import {
  AccessibilityInfo,
  ActivityIndicator,
  Animated,
  Dimensions,
  FlatList,
  Image,
  ImageBackground,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
  type ViewStyle,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AvatarFrame, isAvatarFrameKey, type AvatarFrameKey } from "@/components/AvatarFrame";
import {
  ListBlockView,
  PricingBlockView,
  normalizeListItems as normalizeListBlockItems,
  normalizePricingItems as normalizePricingBlockItems,
  visibleListItems,
  visiblePricingItems,
} from "@/components/BlockListPreview";
import { BrandWordmark } from "@/components/Brand";
import { EmbedModal } from "@/components/EmbedModal";
import { LinkTypePairings } from "@/components/LinkTypePairings";
import { ReviewsWall } from "@/components/ReviewsWall";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import { errorStatus, getBaseUrl } from "@/lib/api";
import { getEvent } from "@/lib/api/events";
import { getBgPresets } from "@/lib/api/bgPresets";
import { buyProduct, checkoutCart } from "@/lib/api/store";
import { variantOverlay } from "@/lib/blockVariants";
import { canonicalBlockType } from "@/lib/blockTypeRegistry";

// ── In-page Product storefront cart (Task #1763) ────────────────────
// The web storefront keeps the cart in the HTTP session; the Sanctum
// mobile path has no session, so the cart lives here in app state and is
// posted as line items to /store/{alias}/checkout. Prices are always
// re-read server-side from the block — these snapshots are display-only.

export type CartLine = {
  blockId: number;
  name: string;
  priceCents: number;
  currency: string;
  image: string | null;
  productType: "digital" | "physical";
  qty: number;
};

type CartContextValue = {
  lines: CartLine[];
  count: number;
  subtotalCents: number;
  currency: string | null;
  add: (line: Omit<CartLine, "qty">) => void;
  setQty: (blockId: number, qty: number) => void;
  remove: (blockId: number) => void;
  clear: () => void;
  open: () => void;
};

const CartContext = createContext<CartContextValue | null>(null);

function useCart(): CartContextValue {
  const ctx = useContext(CartContext);
  if (!ctx) {
    throw new Error("useCart must be used within a CartProvider");
  }
  return ctx;
}

// Build the card style override that should overlay any default
// `blockCardStyle(block, colors)` style.
// Centralizing this means every block container — including those rendered
// by sub-components like PollBlock/RsvpBlock — picks up the variant
// without having to thread a prop through. Spread AFTER the defaults.
function blockCardStyle(
  block: { type: string; settings: Record<string, unknown> | null },
  colors: ReturnType<typeof useColors>,
): {
  backgroundColor: string;
  borderColor: string;
  borderWidth?: number;
  borderRadius?: number;
  borderStyle?: "solid" | "dashed" | "dotted";
  borderTopLeftRadius?: number;
  borderTopRightRadius?: number;
  borderBottomLeftRadius?: number;
  borderBottomRightRadius?: number;
  borderTopWidth?: number;
  borderRightWidth?: number;
  borderBottomWidth?: number;
  borderLeftWidth?: number;
  borderTopColor?: string;
  borderRightColor?: string;
  borderBottomColor?: string;
  borderLeftColor?: string;
} {
  const o = variantOverlay(block.type, block.settings ?? null);
  return {
    backgroundColor: o?.backgroundColor ?? colors.card,
    borderColor: o?.borderColor ?? colors.border,
    ...(o?.borderWidth != null ? { borderWidth: o.borderWidth } : {}),
    ...(o?.borderRadius != null ? { borderRadius: o.borderRadius } : {}),
    ...(o?.borderStyle != null ? { borderStyle: o.borderStyle } : {}),
    // Advanced borders (Task #6038): per-corner radius + per-side
    // width/color computed by variantOverlay with field-by-field
    // fallback to the shorthand. Spread after the generic props so a
    // set corner/side always wins.
    ...(o?.borderTopLeftRadius != null ? { borderTopLeftRadius: o.borderTopLeftRadius } : {}),
    ...(o?.borderTopRightRadius != null ? { borderTopRightRadius: o.borderTopRightRadius } : {}),
    ...(o?.borderBottomLeftRadius != null ? { borderBottomLeftRadius: o.borderBottomLeftRadius } : {}),
    ...(o?.borderBottomRightRadius != null ? { borderBottomRightRadius: o.borderBottomRightRadius } : {}),
    ...(o?.borderTopWidth != null ? { borderTopWidth: o.borderTopWidth } : {}),
    ...(o?.borderRightWidth != null ? { borderRightWidth: o.borderRightWidth } : {}),
    ...(o?.borderBottomWidth != null ? { borderBottomWidth: o.borderBottomWidth } : {}),
    ...(o?.borderLeftWidth != null ? { borderLeftWidth: o.borderLeftWidth } : {}),
    ...(o?.borderTopColor != null ? { borderTopColor: o.borderTopColor } : {}),
    ...(o?.borderRightColor != null ? { borderRightColor: o.borderRightColor } : {}),
    ...(o?.borderBottomColor != null ? { borderBottomColor: o.borderBottomColor } : {}),
    ...(o?.borderLeftColor != null ? { borderLeftColor: o.borderLeftColor } : {}),
  };
}

// Returns the variant's chosen text color, or the theme default. Used for
// primary text in heading/text/badge/button blocks so a "Neon Outline"
// pick actually shows neon-violet copy on mobile.
function blockTextColor(
  block: { type: string; settings: Record<string, unknown> | null },
  fallback: string,
): string {
  const o = variantOverlay(block.type, block.settings ?? null);
  return o?.textColor ?? fallback;
}
import {
  type BiolinkBlock,
  type BiolinkPayload,
  forgetBlockResponse,
  getBiolink,
  getPollResults,
  getRememberedBlockResponse,
  type PollResults,
  rememberBlockResponse,
  type Slide,
  type SlidesPayload,
  submitPollVote,
  submitRsvp,
  trackBiolinkBlockTap,
  trackBiolinkVisit,
  trackSlideView,
} from "@/lib/api/biolinks";
import { showAlert } from "@/lib/webAlert";

function pickNum(s: Record<string, unknown> | null, ...keys: string[]): number | null {
  if (!s) return null;
  for (const k of keys) {
    const v = s[k];
    if (typeof v === "number" && Number.isFinite(v)) return v;
    if (typeof v === "string" && v.trim() !== "" && !Number.isNaN(Number(v))) return Number(v);
  }
  return null;
}
function pickBool(s: Record<string, unknown> | null, key: string, fallback = false): boolean {
  if (!s) return fallback;
  const v = s[key];
  if (typeof v === "boolean") return v;
  if (v === 1 || v === "1" || v === "true") return true;
  if (v === 0 || v === "0" || v === "false") return false;
  return fallback;
}
function publicBiolinkUrl(alias: string): string {
  // The mobile API base host is also the web host. Strip a trailing /api so
  // /<alias> resolves to the public biolink page.
  const base = getBaseUrl().replace(/\/?api\/?$/, "").replace(/\/+$/, "");
  return `${base}/${alias}`;
}

// Reduced-motion preference — sticker highlight animations must stay still
// when the visitor asked the OS to reduce motion (web parity with the
// prefers-reduced-motion CSS gate).
function useReduceMotion(): boolean {
  const [reduce, setReduce] = useState(false);
  useEffect(() => {
    let mounted = true;
    AccessibilityInfo.isReduceMotionEnabled().then((v) => {
      if (mounted) setReduce(!!v);
    });
    const sub = AccessibilityInfo.addEventListener("reduceMotionChanged", (v) =>
      setReduce(!!v),
    );
    return () => {
      mounted = false;
      sub.remove();
    };
  }, []);
  return reduce;
}

// Looping highlight animation wrapper for a single sticker. Mirrors the web
// CSS keyframes (pulse/bounce/wiggle/spin/float/glow) with the chosen loop
// count ('infinite' loops forever). No-op when the effect is 'none' or the
// visitor prefers reduced motion.
function AnimatedStickerInner({
  animation,
  loop,
  reduceMotion,
  children,
}: {
  animation?: string;
  loop?: string;
  reduceMotion: boolean;
  children: React.ReactNode;
}) {
  const v = useRef(new Animated.Value(0)).current;
  const effect = animation && animation !== "none" ? animation : null;

  useEffect(() => {
    if (!effect || reduceMotion) return;
    const durations: Record<string, number> = {
      pulse: 1400, bounce: 1100, wiggle: 900, spin: 2200, float: 2600, glow: 1600,
    };
    const dur = durations[effect] ?? 1400;
    const seq =
      effect === "spin"
        ? Animated.timing(v, { toValue: 1, duration: dur, useNativeDriver: true })
        : Animated.sequence([
            Animated.timing(v, { toValue: 1, duration: dur / 2, useNativeDriver: true }),
            Animated.timing(v, { toValue: 0, duration: dur / 2, useNativeDriver: true }),
          ]);
    const iterations = loop === "infinite" || !loop ? -1 : Math.max(1, parseInt(loop, 10) || 1);
    const anim = Animated.loop(
      effect === "spin"
        ? Animated.sequence([
            seq,
            Animated.timing(v, { toValue: 0, duration: 0, useNativeDriver: true }),
          ])
        : seq,
      { iterations },
    );
    anim.start();
    return () => {
      anim.stop();
      v.setValue(0);
    };
  }, [effect, loop, reduceMotion, v]);

  if (!effect || reduceMotion) return <>{children}</>;

  const style =
    effect === "pulse"
      ? { transform: [{ scale: v.interpolate({ inputRange: [0, 1], outputRange: [1, 1.18] }) }] }
      : effect === "bounce"
        ? { transform: [{ translateY: v.interpolate({ inputRange: [0, 1], outputRange: [0, -12] }) }] }
        : effect === "wiggle"
          ? { transform: [{ rotate: v.interpolate({ inputRange: [0, 1], outputRange: ["-9deg", "9deg"] }) }] }
          : effect === "spin"
            ? { transform: [{ rotate: v.interpolate({ inputRange: [0, 1], outputRange: ["0deg", "360deg"] }) }] }
            : effect === "float"
              ? { transform: [{ translateY: v.interpolate({ inputRange: [0, 1], outputRange: [0, -8] }) }] }
              : /* glow — approximated as a soft opacity shimmer */
                { opacity: v.interpolate({ inputRange: [0, 1], outputRange: [1, 0.55] }) };

  return <Animated.View style={style}>{children}</Animated.View>;
}

// Decorative page stickers (emoji/image overlays) — mirrors the web
// renderer: percent positioning on a full-screen pointer-events-none layer,
// "back" behind the content, "front" above it. Base sizes match web
// (36px emoji / 64px image, multiplied by scale). `mode` splits stickers by
// position_mode: "fixed" layers overlay the viewport, "scroll" layers are
// rendered inside the ScrollView so they move with the page content.
function StickerOverlay({
  stickers,
  layer,
  mode = "fixed",
}: {
  stickers?: import("@/lib/api/biolinks").PageSticker[];
  layer: "front" | "back";
  mode?: "fixed" | "scroll";
}) {
  const reduceMotion = useReduceMotion();
  const list = (stickers ?? []).filter(
    (s) => s.layer === layer && (s.position_mode ?? "fixed") === mode,
  );
  if (!list.length) return null;
  const host = getBaseUrl().replace(/\/?api\/?$/, "").replace(/\/+$/, "");
  return (
    <View pointerEvents="none" style={StyleSheet.absoluteFillObject}>
      {list.map((s, i) => {
        const wrap = {
          position: "absolute" as const,
          left: `${s.x}%` as const,
          top: `${s.y}%` as const,
          transform: [{ rotate: `${s.rotation}deg` }],
        };
        if (s.kind === "image") {
          const size = Math.round(64 * s.scale);
          const uri = s.value.startsWith("/") ? `${host}${s.value}` : s.value;
          return (
            <View key={i} style={wrap}>
              <AnimatedStickerInner animation={s.animation} loop={s.loop} reduceMotion={reduceMotion}>
                <Image
                  source={{ uri }}
                  style={{
                    width: size,
                    height: size,
                    marginLeft: -size / 2,
                    marginTop: -size / 2,
                  }}
                  resizeMode="contain"
                />
              </AnimatedStickerInner>
            </View>
          );
        }
        const fontSize = Math.round(36 * s.scale);
        return (
          <View key={i} style={wrap}>
            <AnimatedStickerInner animation={s.animation} loop={s.loop} reduceMotion={reduceMotion}>
              <Text
                style={{
                  fontSize,
                  lineHeight: fontSize * 1.2,
                  marginLeft: -fontSize / 2,
                  marginTop: -fontSize / 2,
                }}
              >
                {s.value}
              </Text>
            </AnimatedStickerInner>
          </View>
        );
      })}
    </View>
  );
}

const MASK_POLYGONS: Record<string, [number, number][]> = {
  diamond: [[50, 0], [100, 50], [50, 100], [0, 50]],
  hexagon: [[25, 0], [75, 0], [100, 50], [75, 100], [25, 100], [0, 50]],
  octagon: [[29.3, 0], [70.7, 0], [100, 29.3], [100, 70.7], [70.7, 100], [29.3, 100], [0, 70.7], [0, 29.3]],
  star: [[50, 0], [61, 35], [98, 35], [68, 57], [79, 91], [50, 70], [21, 91], [32, 57], [2, 35], [39, 35]],
  blob: [[30, 0], [70, 0], [100, 30], [100, 70], [70, 100], [30, 100], [0, 70], [0, 30]],
  arch: [[0, 100], [0, 30], [5, 15], [15, 5], [30, 0], [70, 0], [85, 5], [95, 15], [100, 30], [100, 100]],
  heart: [[50, 100], [8, 60], [0, 35], [5, 18], [18, 8], [32, 8], [42, 18], [50, 28], [58, 18], [68, 8], [82, 8], [95, 18], [100, 35], [92, 60]],
  torn: [[0, 4], [5, 0], [12, 5], [20, 1], [28, 6], [38, 0], [48, 4], [58, 0], [68, 5], [78, 1], [88, 5], [95, 0], [100, 4], [100, 96], [95, 100], [88, 95], [78, 99], [68, 95], [58, 100], [48, 96], [38, 100], [28, 94], [20, 99], [12, 95], [5, 100], [0, 96]],
  triangle: [[50, 0], [100, 100], [0, 100]],
  pentagon: [[50, 0], [100, 38], [81, 100], [19, 100], [0, 38]],
  semicircle: [[0, 0], [100, 0], [100, 70], [95, 85], [85, 95], [70, 100], [30, 100], [15, 95], [5, 85], [0, 70]],
  wave: [[0, 0], [100, 0], [100, 88], [88, 95], [75, 88], [62, 95], [50, 88], [38, 95], [25, 88], [12, 95], [0, 88]],
  shield: [[0, 0], [100, 0], [100, 65], [92, 80], [75, 92], [50, 100], [25, 92], [8, 80], [0, 65]],
  scallop: [[50, 0], [61.4, 7.5], [75, 6.7], [81.1, 18.9], [93.3, 25], [92.5, 38.6], [100, 50], [92.5, 61.4], [93.3, 75], [81.1, 81.1], [75, 93.3], [61.4, 92.5], [50, 100], [38.6, 92.5], [25, 93.3], [18.9, 81.1], [6.7, 75], [7.5, 61.4], [0, 50], [7.5, 38.6], [6.7, 25], [18.9, 18.9], [25, 6.7], [38.6, 7.5]],
  cross: [[35, 0], [65, 0], [65, 35], [100, 35], [100, 65], [65, 65], [65, 100], [35, 100], [35, 65], [0, 65], [0, 35], [35, 35]],
};
function pickStr(s: Record<string, unknown> | null, ...keys: string[]): string | null {
  if (!s) return null;
  for (const k of keys) {
    const v = s[k];
    if (typeof v === "string" && v.trim() !== "") return v.trim();
  }
  return null;
}

// Blank-aware variant for block CONTENT text (labels/titles/captions).
// Admin block defaults can be explicitly blanked to "" — those must render
// blank (web `??` parity), not fall back to sample text. Returns "" when a
// key exists but is blank, null only when all keys are truly absent.
function pickContentStr(s: Record<string, unknown> | null, ...keys: string[]): string | null {
  if (!s) return null;
  let sawBlank = false;
  for (const k of keys) {
    const v = s[k];
    if (typeof v === "string") {
      if (v.trim() !== "") return v.trim();
      sawBlank = true;
    }
  }
  return sawBlank ? "" : null;
}

// Block link URLs come from creator-defined block settings, which we treat as
// untrusted: only allow http/https and tel/mailto/sms so a malicious entry
// can't fire `javascript:` or `intent:` schemes from a tap.
function isSafeUrl(u: string): boolean {
  try {
    const url = new URL(u);
    return ["http:", "https:", "tel:", "mailto:", "sms:"].includes(url.protocol);
  } catch {
    return false;
  }
}
// Display-only money formatter for the storefront. The server is the source
// of truth for the amount actually charged — this only renders snapshots.
function fmtMoney(cents: number, currency: string): string {
  const amount = (cents / 100).toFixed(cents % 100 ? 2 : 0);
  const cur = (currency || "USD").toUpperCase();
  const symbol = cur === "USD" ? "$" : cur === "EUR" ? "€" : cur === "GBP" ? "£" : "";
  return symbol ? `${symbol}${amount}` : `${amount} ${cur}`;
}
function openSafe(u: string, router: ReturnType<typeof useRouter>) {
  if (!isSafeUrl(u)) return;
  // All safe schemes (including `tel:`) hand off to the OS default handler.
  // The in-app dialer moved to the standalone dialer app, so `tel:` blocks
  // open the device's native phone app.
  void Linking.openURL(u);
}

type OpenEmbed = (opts: { url: string; title?: string; sandboxed?: boolean }) => void;
type PaletteColors = ReturnType<typeof useColors>;

// Inline poll voter — submits the chosen option to the new poll-vote API
// and shows a "Thanks!" confirmation in place of bouncing to the WebView.
function PollBlock({
  block,
  alias,
  settings,
  colors,
}: {
  block: BiolinkBlock;
  alias: string;
  settings: Record<string, unknown>;
  colors: PaletteColors;
}) {
  const question = pickContentStr(settings, "question", "title", "text", "heading") ?? "Vote";
  const rawOptions: unknown =
    Array.isArray(settings.options) ? settings.options :
    Array.isArray(settings.choices) ? settings.choices :
    Array.isArray(settings.items) ? settings.items : [];
  const options: string[] = (rawOptions as unknown[])
    .map((o) => typeof o === "string" ? o : (typeof o === "object" && o ? (pickStr(o as Record<string, unknown>, "label", "text", "title", "name") ?? "") : ""))
    .filter((x) => x.length > 0)
    .slice(0, 8);

  // Pre-read the creator's "reveal results at" deadline from the block
  // settings so we can show "Results visible after <date>" before the
  // viewer ever votes (the API also enforces this server-side).
  const revealAtRaw = pickStr(settings, "reveal_results_at");
  const revealAtInitial = (() => {
    if (!revealAtRaw) return null;
    const d = new Date(revealAtRaw);
    if (Number.isNaN(d.getTime())) return null;
    return d.getTime() > Date.now() ? d : null;
  })();

  const [submitting, setSubmitting] = useState<number | null>(null);
  const [votedIndex, setVotedIndex] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [results, setResults] = useState<PollResults | null>(null);
  const [resultsLoading, setResultsLoading] = useState(false);
  const [resultsLockedUntil, setResultsLockedUntil] = useState<Date | null>(revealAtInitial);
  const remembered = useRememberedResponse(alias, block.id);

  // Fetch the live tally (best-effort: a failure just falls back to the
  // legacy "Thanks for voting!" message rather than blocking the UI).
  const [hiddenUntilVote, setHiddenUntilVote] = useState(false);
  const loadResults = useCallback(async () => {
    setResultsLoading(true);
    try {
      const r = await getPollResults(alias, block.id);
      setResults(r);
      setHiddenUntilVote(false);
      setResultsLockedUntil(null);
    } catch (e) {
      // Server returns 403 vote_required when the creator has hidden
      // tallies until this viewer has voted, or 403 results_locked
      // when a reveal-at deadline hasn't passed. Surface either case
      // explicitly so the viewer doesn't stare at a silent fallback.
      const status = (e && typeof e === "object" && "status" in e) ? Number((e as { status: unknown }).status) : 0;
      const code = (e && typeof e === "object" && "code" in e) ? String((e as { code: unknown }).code) : "";
      if (status === 403 && code === "results_locked") {
        const revealAt = (e as { reveal_at?: string }).reveal_at;
        if (revealAt) {
          const d = new Date(revealAt);
          if (!Number.isNaN(d.getTime())) setResultsLockedUntil(d);
        }
      } else if (status === 403) {
        setHiddenUntilVote(true);
      }
    } finally {
      setResultsLoading(false);
    }
  }, [alias, block.id]);

  const onVote = useCallback(async (i: number, label: string) => {
    if (submitting !== null) return;
    setSubmitting(i);
    setError(null);
    try {
      // The poll-vote endpoint already fires the same trackBlockClick the
      // tap endpoint would, so we don't double-count by also pinging /tap
      // here on success. We DO fall back to /tap on error so engagement
      // analytics still register even when the vote API itself fails.
      const res = await submitPollVote(alias, block.id, i, label);
      setVotedIndex(res.option_index);
      // Remember the picked label so a returning viewer sees the
      // results card instead of the prompt again.
      remembered.remember(label);
      void loadResults();
    } catch (e) {
      trackBiolinkBlockTap(alias, block.id, null);
      const msg = (e && typeof e === "object" && "message" in e) ? String((e as { message: unknown }).message) : "Could not save your vote";
      setError(msg);
    } finally {
      setSubmitting(null);
    }
  }, [alias, block.id, loadResults, remembered, submitting]);

  // For viewers who already voted in a previous session, kick off a
  // tally fetch immediately so they land on the results view.
  useEffect(() => {
    if (remembered.ready && remembered.value !== null && results === null && !resultsLoading) {
      void loadResults();
    }
  }, [loadResults, remembered.ready, remembered.value, results, resultsLoading]);

  // If the viewer already responded on a previous session, show the live
  // tallies (or the responded card while they load). Tapping "Change
  // response" clears the remembered value and brings the live options back.
  if (remembered.value !== null) {
    if (results) {
      return (
        <PollResultsCard
          question={question}
          results={results}
          pickedLabel={remembered.value}
          onChange={() => {
            remembered.forget();
            setResults(null);
            setVotedIndex(null);
          }}
          colors={colors}
        />
      );
    }
    return (
      <View>
        <RespondedCard
          icon="📊"
          title={question}
          responseLabel={remembered.value}
          onChange={() => {
            remembered.forget();
            setResults(null);
          }}
        />
        {resultsLockedUntil ? (
          <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 12, marginTop: 4, paddingHorizontal: 12 }]}>
            🔒 Results visible after {resultsLockedUntil.toLocaleString()}
          </Text>
        ) : null}
      </View>
    );
  }

  // Wait for AsyncStorage to report back so a remembered viewer doesn't
  // see the live options flash for a frame and double-submit.
  if (!remembered.ready) {
    return (
      <View style={[styles.cardContainer, blockCardStyle(block, colors)]}>
        <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left" }]}>📊 {question}</Text>
        <ActivityIndicator color={colors.primary} style={{ alignSelf: "flex-start", marginTop: 4 }} />
      </View>
    );
  }

  return (
    <View style={[styles.cardContainer, blockCardStyle(block, colors)]}>
      <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left" }]}>📊 {question}</Text>
      {options.length > 0 ? (
        options.map((opt, i) => {
          const isVoted = votedIndex === i;
          const isBusy = submitting === i;
          return (
            <Pressable
              key={i}
              disabled={submitting !== null || votedIndex !== null}
              onPress={() => { void onVote(i, opt); }}
              style={[
                styles.pollOption,
                {
                  borderColor: isVoted ? "#3d6bff" : colors.border,
                  backgroundColor: isVoted ? "#3d6bff22" : "transparent",
                  opacity: submitting !== null && !isBusy ? 0.5 : 1,
                },
              ]}
            >
              <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <Text style={[styles.body, { color: colors.foreground, textAlign: "left", fontSize: 14, flex: 1 }]}>
                  {opt}
                </Text>
                {isBusy ? <ActivityIndicator size="small" color={colors.foreground} /> : null}
                {isVoted ? <Feather name="check" size={16} color="#3d6bff" /> : null}
              </View>
            </Pressable>
          );
        })
      ) : (
        <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 12 }]}>
          No options configured.
        </Text>
      )}
      {resultsLockedUntil ? (
        <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 12, marginTop: 4 }]}>
          🔒 Results visible after {resultsLockedUntil.toLocaleString()}
        </Text>
      ) : null}
      {votedIndex !== null ? (
        <Text style={[styles.body, { color: colors.success, textAlign: "left", fontSize: 12, marginTop: 4 }]}>
          {resultsLockedUntil
            ? "Thanks for voting!"
            : (hiddenUntilVote ? "Thanks for voting! Results are hidden by the creator." : "Thanks for voting!")}
        </Text>
      ) : null}
      {error ? (
        <Text style={[styles.body, { color: colors.destructive, textAlign: "left", fontSize: 12, marginTop: 4 }]}>
          {error}
        </Text>
      ) : null}
    </View>
  );
}

// Inline RSVP form — collects the response + minimum required fields and
// posts to the native RSVP API instead of opening the WebView form.
function RsvpBlock({
  block,
  alias,
  settings,
  colors,
}: {
  block: BiolinkBlock;
  alias: string;
  settings: Record<string, unknown>;
  colors: PaletteColors;
}) {
  const title = pickContentStr(settings, "title", "heading", "event_title") ?? "RSVP";
  const date = pickStr(settings, "date", "event_date", "starts_at");
  const allowPlusOnes = pickBool(settings, "rsvp_allow_plus_ones", false)
    || pickBool(settings, "allow_plus_ones", false);
  const collectPhone = pickBool(settings, "rsvp_collect_phone", false)
    || pickBool(settings, "collect_phone", false);

  const responses: { key: "yes" | "maybe" | "no"; label: string; bg: string }[] = [
    { key: "yes", label: "Going", bg: colors.success },
    { key: "maybe", label: "Maybe", bg: "#ca8a04" },
    { key: "no", label: "Can't go", bg: "#64748b" },
  ];

  const [response, setResponse] = useState<"yes" | "maybe" | "no" | null>(null);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [plusOnes, setPlusOnes] = useState("0");
  const [message, setMessage] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const remembered = useRememberedResponse(alias, block.id);

  const onPickResponse = useCallback((key: "yes" | "maybe" | "no") => {
    setResponse(key);
    setError(null);
    // Fire the analytics ping when the user engages with a response so
    // taps still flow into creator analytics like before.
    trackBiolinkBlockTap(alias, block.id, null);
  }, [alias, block.id]);

  const onSubmit = useCallback(async () => {
    if (!response) {
      setError("Pick a response first.");
      return;
    }
    if (!name.trim()) {
      setError("Your name is required.");
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      await submitRsvp(alias, block.id, {
        name: name.trim(),
        email: email.trim() || null,
        phone: collectPhone ? (phone.trim() || null) : null,
        response,
        plus_ones: allowPlusOnes ? (Number.parseInt(plusOnes, 10) || 0) : 0,
        message: message.trim() || null,
      });
      // Persist the human-readable label so a returning viewer sees the
      // "Thanks for responding" card on next open instead of the form.
      const picked = responses.find((r) => r.key === response);
      remembered.remember(picked ? picked.label : response);
    } catch (e) {
      const msg = (e && typeof e === "object" && "message" in e) ? String((e as { message: unknown }).message) : "Could not submit RSVP";
      setError(msg);
    } finally {
      setSubmitting(false);
    }
  }, [alias, allowPlusOnes, block.id, collectPhone, email, message, name, phone, plusOnes, remembered, response, responses]);

  // If a previous session already RSVP'd (or we just submitted), show
  // the shared "Thanks for responding" card with a "Change response"
  // affordance instead of the form.
  if (remembered.value !== null) {
    return (
      <RespondedCard
        icon="📅"
        title={title}
        responseLabel={remembered.value}
        onChange={remembered.forget}
      />
    );
  }

  // Wait for AsyncStorage so a remembered viewer doesn't see the form
  // flash for a frame and accidentally double-submit.
  if (!remembered.ready) {
    return (
      <View style={[styles.cardContainer, blockCardStyle(block, colors)]}>
        <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left" }]}>📅 {title}</Text>
        <ActivityIndicator color={colors.primary} style={{ alignSelf: "flex-start", marginTop: 4 }} />
      </View>
    );
  }

  const inputStyle = {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 8,
    color: colors.foreground,
    fontSize: 14,
    marginTop: 6,
  };

  return (
    <View style={[styles.cardContainer, blockCardStyle(block, colors)]}>
      <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left" }]}>📅 {title}</Text>
      {date ? (
        <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 12 }]}>{date}</Text>
      ) : null}
      <View style={styles.rsvpRow}>
        {responses.map((r) => {
          const isPicked = response === r.key;
          return (
            <Pressable
              key={r.key}
              onPress={() => onPickResponse(r.key)}
              style={[
                styles.rsvpBtn,
                {
                  backgroundColor: isPicked ? r.bg : "transparent",
                  borderColor: r.bg,
                },
              ]}
            >
              <Text style={[styles.btnLabel, { color: isPicked ? "#fff" : r.bg, fontSize: 13 }]}>
                {r.label}
              </Text>
            </Pressable>
          );
        })}
      </View>
      {response ? (
        <View style={{ marginTop: 6 }}>
          <TextInput
            value={name}
            onChangeText={setName}
            placeholder="Your name"
            placeholderTextColor={colors.mutedForeground}
            style={inputStyle}
            autoCapitalize="words"
            editable={!submitting}
          />
          <TextInput
            value={email}
            onChangeText={setEmail}
            placeholder="Email (optional)"
            placeholderTextColor={colors.mutedForeground}
            style={inputStyle}
            keyboardType="email-address"
            autoCapitalize="none"
            editable={!submitting}
          />
          {collectPhone ? (
            <TextInput
              value={phone}
              onChangeText={setPhone}
              placeholder="Phone (optional)"
              placeholderTextColor={colors.mutedForeground}
              style={inputStyle}
              keyboardType="phone-pad"
              editable={!submitting}
            />
          ) : null}
          {allowPlusOnes ? (
            <TextInput
              value={plusOnes}
              onChangeText={(v) => setPlusOnes(v.replace(/[^0-9]/g, ""))}
              placeholder="Plus-ones"
              placeholderTextColor={colors.mutedForeground}
              style={inputStyle}
              keyboardType="number-pad"
              editable={!submitting}
            />
          ) : null}
          <TextInput
            value={message}
            onChangeText={setMessage}
            placeholder="Message (optional)"
            placeholderTextColor={colors.mutedForeground}
            style={[inputStyle, { minHeight: 60 }]}
            multiline
            editable={!submitting}
          />
          <Pressable
            onPress={() => { void onSubmit(); }}
            disabled={submitting}
            style={{
              marginTop: 8,
              backgroundColor: "#3d6bff",
              borderRadius: 10,
              paddingVertical: 10,
              alignItems: "center",
              opacity: submitting ? 0.6 : 1,
            }}
          >
            {submitting ? (
              <ActivityIndicator size="small" color="#fff" />
            ) : (
              <Text style={[styles.btnLabel, { color: "#fff", fontSize: 14 }]}>Send RSVP</Text>
            )}
          </Pressable>
        </View>
      ) : null}
      {error ? (
        <Text style={[styles.body, { color: colors.destructive, textAlign: "left", fontSize: 12, marginTop: 4 }]}>
          {error}
        </Text>
      ) : null}
    </View>
  );
}

// Render a small "Thanks for responding, you picked X" card that replaces
// the live poll/RSVP options once the viewer has answered. Tapping the
// "Change response" affordance clears the remembered choice so the original
// options come back. Used by both the poll and RSVP block branches.
function RespondedCard({
  icon,
  title,
  responseLabel,
  onChange,
}: {
  icon: string;
  title: string;
  responseLabel: string;
  onChange: () => void;
}) {
  const colors = useColors();
  return (
    <View style={[styles.cardContainer, { backgroundColor: colors.card, borderColor: colors.border }]}>
      <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left" }]}>
        {icon} {title}
      </Text>
      <Text style={[styles.body, { color: colors.foreground, textAlign: "left", fontSize: 14 }]}>
        Thanks for responding, you picked “{responseLabel}”.
      </Text>
      <Pressable onPress={onChange} hitSlop={8} style={{ alignSelf: "flex-start" }}>
        <Text style={[styles.body, { color: colors.primary, textAlign: "left", fontSize: 13 }]}>
          Change response
        </Text>
      </Pressable>
    </View>
  );
}

// Render the live tally bars after a viewer has voted. Highlights the
// option the viewer picked with a stronger fill so they can spot their
// own choice within the chart at a glance.
function PollResultsCard({
  question,
  results,
  pickedLabel,
  onChange,
  colors,
}: {
  question: string;
  results: PollResults;
  pickedLabel: string;
  onChange: () => void;
  colors: PaletteColors;
}) {
  const total = results.total_votes;
  return (
    <View style={[styles.cardContainer, { backgroundColor: colors.card, borderColor: colors.border }]}>
      <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left" }]}>📊 {question}</Text>
      <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 12 }]}>
        {total === 1 ? "1 vote" : `${total} votes`}
      </Text>
      {results.options.length > 0 ? (
        results.options.map((opt) => {
          const isPicked = opt.label === pickedLabel;
          return (
            <View
              key={opt.index}
              style={[
                styles.pollOption,
                {
                  borderColor: isPicked ? "#3d6bff" : colors.border,
                  backgroundColor: "transparent",
                  overflow: "hidden",
                  position: "relative",
                },
              ]}
            >
              <View
                style={{
                  position: "absolute",
                  left: 0, top: 0, bottom: 0,
                  width: `${Math.max(0, Math.min(100, opt.percent))}%`,
                  backgroundColor: isPicked ? "#3d6bff44" : "#3d6bff1f",
                }}
              />
              <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <Text style={[styles.body, { color: colors.foreground, textAlign: "left", fontSize: 14, flex: 1 }]} numberOfLines={2}>
                  {opt.label}
                </Text>
                {isPicked ? <Feather name="check" size={14} color="#3d6bff" /> : null}
                <Text style={[styles.body, { color: colors.foreground, textAlign: "right", fontSize: 13, fontWeight: "600" }]}>
                  {opt.percent}%
                </Text>
                <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "right", fontSize: 11, minWidth: 26 }]}>
                  {opt.count}
                </Text>
              </View>
            </View>
          );
        })
      ) : (
        <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 12 }]}>
          No options configured.
        </Text>
      )}
      <Pressable onPress={onChange} hitSlop={8} style={{ alignSelf: "flex-start", marginTop: 4 }}>
        <Text style={[styles.body, { color: colors.primary, textAlign: "left", fontSize: 13 }]}>
          Change response
        </Text>
      </Pressable>
    </View>
  );
}

// Loads any remembered response for (alias, blockId) from AsyncStorage and
// exposes setters/clearers that update both storage and local state in
// lockstep, so the poll/RSVP branches can flip to the "Thanks" card the
// moment a viewer taps a choice — and back to the live options if they
// hit "Change response".
function useRememberedResponse(alias: string, blockId: number) {
  const [value, setValue] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  useEffect(() => {
    let cancelled = false;
    setReady(false);
    getRememberedBlockResponse(alias, blockId)
      .then((v) => {
        if (!cancelled) {
          setValue(v);
          setReady(true);
        }
      })
      .catch(() => {
        if (!cancelled) setReady(true);
      });
    return () => {
      cancelled = true;
    };
  }, [alias, blockId]);
  const remember = useCallback(
    (v: string) => {
      setValue(v);
      void rememberBlockResponse(alias, blockId, v);
    },
    [alias, blockId],
  );
  const forget = useCallback(() => {
    setValue(null);
    void forgetBlockResponse(alias, blockId);
  }, [alias, blockId]);
  return { value, ready, remember, forget };
}


// Native-checkout Product block (Task #1763). Renders the storefront card
// with in-app Buy Now + Add to Cart. Buying opens the hosted-checkout URL in
// the system browser (provider rules) then routes to the order/thank-you
// screen which polls for the paid status and exposes digital downloads.
function NativeProductBlock({
  block,
  alias,
  colors,
}: {
  block: BiolinkBlock;
  alias: string;
  colors: ReturnType<typeof useColors>;
}) {
  const router = useRouter();
  const cart = useCart();
  const { user } = useAuth();
  const [busy, setBusy] = useState(false);

  const s = block.settings ?? {};
  const name = (pickContentStr(s, "name", "title") ?? "Product").trim();
  const desc = pickStr(s, "description", "subtitle");
  const priceCents = pickNum(s, "price_cents") ?? 0;
  const currency = (pickStr(s, "currency") ?? "USD").toUpperCase();
  const productType = pickStr(s, "product_type") === "physical" ? "physical" : "digital";
  const image = pickStr(s, "image", "thumbnail");

  const inCart = cart.lines.some((l) => l.blockId === block.id);

  const ensureAuthed = (): boolean => {
    if (user) return true;
    showAlert(
      "Sign in to buy",
      "Create a free account or sign in to complete your purchase.",
      [
        { text: "Not now", style: "cancel" },
        { text: "Sign in", onPress: () => router.push("/(auth)" as any) },
      ],
    );
    return false;
  };

  const handleBuyNow = async () => {
    if (busy) return;
    if (!ensureAuthed()) return;
    setBusy(true);
    try {
      const res = await buyProduct(alias, block.id);
      if (res.checkout_url) {
        try {
          await WebBrowser.openBrowserAsync(res.checkout_url);
        } catch {
          Linking.openURL(res.checkout_url);
        }
      }
      router.push(`/store/order/${res.order.id}` as any);
    } catch (e) {
      const err = e as { status?: number; message?: string };
      if (err.status === 401) {
        ensureAuthed();
      } else {
        showAlert("Couldn't start checkout", err.message || "Please try again.");
      }
    } finally {
      setBusy(false);
    }
  };

  const handleAddToCart = () => {
    cart.add({
      blockId: block.id,
      name,
      priceCents,
      currency,
      image: image ?? null,
      productType,
    });
  };

  return (
    <View style={[styles.cardContainer, blockCardStyle(block, colors)]}>
      {image ? (
        <Image
          source={{ uri: image }}
          style={[styles.image, { aspectRatio: 16 / 9, marginBottom: 10 }]}
        />
      ) : null}
      <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
        <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left", flex: 1 }]}>
          {name}
        </Text>
        <View
          style={{
            paddingHorizontal: 8,
            paddingVertical: 2,
            borderRadius: 999,
            backgroundColor: "rgba(61,107,255,0.15)",
          }}
        >
          <Text style={{ fontSize: 10, fontWeight: "700", color: "#7d9bff" }}>
            {productType === "physical" ? "Ships" : "Digital"}
          </Text>
        </View>
      </View>
      {desc ? (
        <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 13, marginTop: 2 }]}>
          {desc}
        </Text>
      ) : null}
      <Text style={[styles.heading, { color: colors.primary, textAlign: "left", fontSize: 20, marginTop: 6 }]}>
        {fmtMoney(priceCents, currency)}
      </Text>

      <View style={{ flexDirection: "row", gap: 8, marginTop: 12 }}>
        <Pressable
          onPress={handleBuyNow}
          disabled={busy}
          style={{
            flex: 1,
            backgroundColor: colors.primary,
            borderRadius: 12,
            paddingVertical: 12,
            alignItems: "center",
            justifyContent: "center",
            opacity: busy ? 0.6 : 1,
          }}
        >
          {busy ? (
            <ActivityIndicator color={colors.primaryForeground ?? "#fff"} />
          ) : (
            <Text style={{ color: colors.primaryForeground ?? "#fff", fontWeight: "700" }}>
              Buy now
            </Text>
          )}
        </Pressable>
        <Pressable
          onPress={handleAddToCart}
          disabled={inCart}
          style={{
            paddingHorizontal: 16,
            borderRadius: 12,
            paddingVertical: 12,
            alignItems: "center",
            justifyContent: "center",
            borderWidth: 1,
            borderColor: colors.border,
            backgroundColor: colors.card,
            opacity: inCart ? 0.6 : 1,
          }}
        >
          <Feather
            name={inCart ? "check" : "shopping-cart"}
            size={18}
            color={inCart ? colors.primary : colors.foreground}
          />
        </Pressable>
      </View>
    </View>
  );
}

// Block-level catalog preset background (Task #5970). The web renderer
// paints the preset's raw CSS on an absolutely-positioned layer behind the
// block; RN can't render CSS strings, so we approximate with the preset's
// `colors` LinearGradient (instant paint) covered by the pre-rendered PNG
// swatch of the REAL texture when the server advertises one — the same
// approximation the Appearance preset picker/preview already uses. The
// layer honours `bg_preset_opacity` (0–100, default 100).
// ── Per-block horizontal margins (Task #6114) ────────────────────────
// The public page no longer has horizontal page padding; the default
// side spacing lives on each top-level block instead so a creator can
// set a block's Left/Right margin to 0 for a truly full-width block.
// Mirrors the web renderer: an explicit 0 is a real value, not "unset".
const BLOCK_DEFAULT_SIDE_MARGIN = 24;

function styleMarginNum(st: Record<string, unknown>, key: string): number | null {
  const v = st[key];
  const n =
    typeof v === "number" ? v : typeof v === "string" && v.trim() !== "" ? Number(v) : NaN;
  return Number.isFinite(n) ? n : null;
}

export function blockWrapMargins(block: BiolinkBlock): ViewStyle {
  const st = (block.settings?._style as Record<string, unknown> | undefined) ?? {};
  const ml = styleMarginNum(st, "margin_left");
  const mr = styleMarginNum(st, "margin_right");
  const mt = styleMarginNum(st, "margin_top");
  const mb = styleMarginNum(st, "margin_bottom");
  return {
    marginLeft: ml ?? BLOCK_DEFAULT_SIDE_MARGIN,
    marginRight: mr ?? BLOCK_DEFAULT_SIDE_MARGIN,
    ...(mt != null ? { marginTop: mt } : {}),
    ...(mb != null ? { marginBottom: mb } : {}),
  };
}

// ── Retro browser-window chrome (Task #6568) ──────────────────────────
// Mirrors the web renderer: when `_style._window_chrome` is set on a
// heading/link-family block, the block renders inside a retro OS window —
// title bar with three decorative control dots (× + −), thick border,
// sharp corners, and a hard offset shadow (drawn as an offset backing
// View so it stays hard-edged on Android too). Other block types carrying
// the token are ignored gracefully.
const WINDOW_CHROME_TYPES = new Set([
  "heading",
  "heading_logo",
  "link",
  "link_big",
  "cta_button",
  "button",
  "featured_pin",
]);

function WindowChromeFrame(props: { st: Record<string, unknown>; children: React.ReactNode }) {
  const { st } = props;
  const rawBg = typeof st.bg_color === "string" ? st.bg_color.trim() : "";
  const bg =
    rawBg !== "" && rawBg !== "transparent" && !/gradient\(/i.test(rawBg) ? rawBg : "#f6f4ef";
  const rawBorder = typeof st.border_color === "string" ? st.border_color.trim() : "";
  const border = /^#[0-9a-fA-F]{3,8}$/.test(rawBorder) ? rawBorder : "#111111";
  return (
    <View style={{ marginBottom: 6, marginRight: 6 }}>
      {/* Hard offset shadow layer */}
      <View
        pointerEvents="none"
        style={{ position: "absolute", top: 6, left: 6, right: -6, bottom: -6, backgroundColor: border }}
      />
      <View style={{ backgroundColor: bg, borderWidth: 3, borderColor: border, borderRadius: 0 }}>
        <View
          accessibilityElementsHidden
          style={{
            flexDirection: "row",
            alignItems: "center",
            gap: 6,
            paddingVertical: 6,
            paddingHorizontal: 12,
            borderBottomWidth: 3,
            borderBottomColor: border,
          }}
        >
          {["×", "+", "−"].map((g) => (
            <View
              key={g}
              style={{
                width: 15,
                height: 15,
                borderRadius: 999,
                borderWidth: 1.5,
                borderColor: border,
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <Text style={{ fontSize: 9, lineHeight: 12, fontWeight: "700", color: border }}>{g}</Text>
            </View>
          ))}
        </View>
        <View style={{ paddingVertical: 12, paddingHorizontal: 14 }}>{props.children}</View>
      </View>
    </View>
  );
}

// --- Structural link_layout renderers (Task #6605) -----------------------
// Native mirrors of the four web link.blade.php branches added in Task
// #6602: sparkle_pill, notched_bar, speech_bubble, riveted_plaque. Each
// reads the same `_style` keys as the web renderer and falls back to the
// same reference colors. Unknown tokens still fall through to the plain
// button path in BlockViewInner.

type LlStyle = Record<string, unknown> | null;
const llStr = (st: LlStyle, k: string): string =>
  typeof st?.[k] === "string" ? (st[k] as string) : "";
// Mirrors Blade's `intval(...) ?: fallback` — 0/blank/invalid → fallback.
const llInt = (st: LlStyle, k: string, fallback: number): number => {
  const n = parseInt(String(st?.[k] ?? ""), 10);
  return Number.isFinite(n) && n > 0 ? n : fallback;
};
const llColor = (st: LlStyle, k: string, fallback: string): string => {
  const v = llStr(st, k);
  return v !== "" && v !== "transparent" ? v : fallback;
};
const LL_SERIF = Platform.select({
  ios: "Georgia",
  android: "serif",
  default: "Georgia, 'Times New Roman', serif",
});
// Four-point sparkle glyph (same path as the web renderer's inline SVG).
const LL_SPARKLE_D =
  "M12 0 C13.2 7.4 16.6 10.8 24 12 C16.6 13.2 13.2 16.6 12 24 C10.8 16.6 7.4 13.2 0 12 C7.4 10.8 10.8 7.4 12 0 Z";

function SparklePillLink({ st, label, onPress }: { st: LlStyle; label: string; onPress: () => void }) {
  const ink = llStr(st, "text_color") !== "" ? llStr(st, "text_color") : "#2c2a26";
  const line = llColor(st, "border_color", ink);
  const bgPick = llStr(st, "bg_color");
  const bg = bgPick !== "" ? bgPick : "transparent";
  return (
    <Pressable onPress={onPress} style={{ width: "100%", paddingVertical: 9, paddingHorizontal: 12 }}>
      <View
        style={{
          width: "100%",
          backgroundColor: bg,
          borderWidth: llInt(st, "border_width", 1),
          borderColor: line,
          borderRadius: 999,
          paddingVertical: llInt(st, "padding", 14),
          paddingHorizontal: 24,
        }}
      >
        <Text
          style={{
            color: ink,
            textAlign: "center",
            fontSize: llInt(st, "font_size", 18),
            fontWeight: (llStr(st, "font_weight") || "500") as "500",
            letterSpacing: 1,
            fontFamily: LL_SERIF,
          }}
          numberOfLines={2}
        >
          {label}
        </Text>
      </View>
      <Svg
        pointerEvents="none"
        viewBox="0 0 24 24"
        width={19}
        height={19}
        style={{ position: "absolute", top: 0, right: "6%" }}
      >
        <Path d={LL_SPARKLE_D} fill={line} />
      </Svg>
      <Svg
        pointerEvents="none"
        viewBox="0 0 24 24"
        width={15}
        height={15}
        style={{ position: "absolute", bottom: 0, left: "8%" }}
      >
        <Path d={LL_SPARKLE_D} fill={line} />
      </Svg>
    </Pressable>
  );
}

function NotchedBarLink({ st, label, onPress }: { st: LlStyle; label: string; onPress: () => void }) {
  // The bar's silhouette (45°-clipped corners) is drawn as an SVG polygon
  // sized from the measured layout, so the notches stay a fixed 16px at
  // any width (RN has no CSS clip-path).
  const [dims, setDims] = useState<{ w: number; h: number }>({ w: 0, h: 0 });
  const bg = llColor(st, "bg_color", "#191512");
  const ink = llStr(st, "text_color") !== "" ? llStr(st, "text_color") : "#ffffff";
  const { w, h } = dims;
  const notch = 16;
  const points =
    w > 0 && h > 0
      ? `${notch},0 ${w - notch},0 ${w},${0.34 * h} ${w},${0.66 * h} ${w - notch},${h} ${notch},${h} 0,${0.66 * h} 0,${0.34 * h}`
      : "";
  return (
    <Pressable
      onPress={onPress}
      onLayout={(e) =>
        setDims({ w: e.nativeEvent.layout.width, h: e.nativeEvent.layout.height })
      }
      style={{ width: "100%", marginBottom: 4 }}
    >
      {points !== "" ? (
        <Svg
          pointerEvents="none"
          width={w}
          height={h}
          viewBox={`0 0 ${w} ${h}`}
          style={StyleSheet.absoluteFill}
        >
          <Polygon points={points} fill={bg} />
        </Svg>
      ) : null}
      <View style={{ paddingVertical: 16, paddingHorizontal: 32 }}>
        <Text
          style={{
            color: ink,
            textAlign: "center",
            textTransform: "uppercase",
            fontWeight: "700",
            fontSize: llInt(st, "font_size", 16),
            letterSpacing: 1.3,
          }}
          numberOfLines={2}
        >
          {label}
        </Text>
      </View>
    </Pressable>
  );
}

function SpeechBubbleLink({ st, label, onPress }: { st: LlStyle; label: string; onPress: () => void }) {
  const bg = llColor(st, "bg_color", "#6b4a2f");
  const ink = llStr(st, "text_color") !== "" ? llStr(st, "text_color") : "#f7ead3";
  const borderStyle = llStr(st, "border_style") || "none";
  const borderW = borderStyle !== "none" ? llInt(st, "border_width", 0) : 0;
  const borderColor = borderW > 0 ? llColor(st, "border_color", ink) : "transparent";
  return (
    <Pressable onPress={onPress} style={{ width: "100%", paddingBottom: 12, marginBottom: 4 }}>
      <View
        style={{
          width: "100%",
          backgroundColor: bg,
          borderRadius: llInt(st, "border_radius", 26),
          borderWidth: borderW,
          borderColor,
          paddingVertical: llInt(st, "padding", 22),
          paddingHorizontal: 28,
        }}
      >
        <Text
          style={{
            color: ink,
            textAlign: "left",
            textTransform: "uppercase",
            fontWeight: (llStr(st, "font_weight") || "800") as "800",
            fontSize: llInt(st, "font_size", 19),
            letterSpacing: 0.8,
          }}
          numberOfLines={2}
        >
          {label}
        </Text>
      </View>
      {/* Tail poking out of the bottom-right corner, same silhouette as the
          web renderer's clip-path polygon(0 0, 100% 0, 100% 100%, 55% 30%). */}
      <Svg
        pointerEvents="none"
        width={26}
        height={16}
        viewBox="0 0 26 16"
        style={{ position: "absolute", bottom: 0, right: 22 }}
      >
        <Polygon points="0,0 26,0 26,16 14.3,4.8" fill={bg} />
      </Svg>
    </Pressable>
  );
}

function RivetedPlaqueLink({ st, label, onPress }: { st: LlStyle; label: string; onPress: () => void }) {
  const bg = llColor(st, "bg_color", "#17161a");
  const ink = llStr(st, "text_color") !== "" ? llStr(st, "text_color") : "#f3ede0";
  const metal = llColor(st, "border_color", "#c9a35c");
  const radius = llInt(st, "border_radius", 10);
  const rivets: Array<Record<string, number>> = [
    { top: 4, left: 4 },
    { top: 4, right: 4 },
    { bottom: 4, left: 4 },
    { bottom: 4, right: 4 },
  ];
  return (
    <Pressable onPress={onPress} style={{ width: "100%", marginBottom: 4 }}>
      <View
        style={{
          width: "100%",
          backgroundColor: bg,
          borderWidth: llInt(st, "border_width", 2),
          borderColor: metal,
          borderRadius: radius,
          paddingVertical: llInt(st, "padding", 18),
          paddingHorizontal: 24,
          shadowColor: "#000",
          shadowOpacity: 0.35,
          shadowRadius: 14,
          shadowOffset: { width: 0, height: 4 },
          elevation: 4,
        }}
      >
        {/* Inner metallic frame inset inside the outer border. */}
        <View
          pointerEvents="none"
          style={{
            position: "absolute",
            top: 9,
            left: 9,
            right: 9,
            bottom: 9,
            borderWidth: 1,
            borderColor: metal,
            borderRadius: Math.max(radius - 6, 2),
          }}
        />
        {rivets.map((pos, i) => (
          <View
            key={`rivet-${i}`}
            pointerEvents="none"
            style={{
              position: "absolute",
              width: 5,
              height: 5,
              borderRadius: 999,
              backgroundColor: metal,
              borderTopWidth: 1,
              borderLeftWidth: 1,
              borderColor: "rgba(255,255,255,0.7)",
              ...pos,
            }}
          />
        ))}
        <Text
          style={{
            color: ink,
            textAlign: "center",
            fontWeight: (llStr(st, "font_weight") || "500") as "500",
            fontSize: llInt(st, "font_size", 17),
            letterSpacing: 0.9,
            fontFamily: LL_SERIF,
          }}
          numberOfLines={2}
        >
          {label}
        </Text>
      </View>
    </Pressable>
  );
}

export function BlockView(props: { block: BiolinkBlock; alias: string; allBlocks: BiolinkBlock[]; openEmbed: OpenEmbed }) {
  const st = (props.block.settings?._style as Record<string, unknown> | undefined) ?? {};
  const presetKey = typeof st.bg_preset_key === "string" ? st.bg_preset_key.trim() : "";
  const rawOpacity = Number(st.bg_preset_opacity);
  const presetOpacity = Number.isFinite(rawOpacity)
    ? Math.max(0, Math.min(100, Math.round(rawOpacity)))
    : 100;

  // Hook is unconditional (React rules); it only fires when a preset key
  // is present. Query key/staleTime match the pickers' so caches share.
  const catalogQ = useQuery({
    queryKey: ["bg-presets"],
    queryFn: getBgPresets,
    staleTime: 60 * 60 * 1000,
    enabled: presetKey !== "",
  });
  const preset = presetKey
    ? catalogQ.data?.presets.find((p) => p.key === presetKey && !p.paper)
    : undefined;

  // Custom gradient / image backgrounds (Task #6044): a gradient string in
  // `_style.bg_color` is approximated with its color stops on a
  // LinearGradient layer (RN can't render CSS strings); `_style.bg_image`
  // paints a cover image layer — root-relative /f/ vault paths resolve
  // against the API base. Preset wins when both are somehow present
  // (mirrors the web layer stacking order).
  const bgColorStr = typeof st.bg_color === "string" ? st.bg_color.trim() : "";
  const gradientColors = /^(linear|radial|conic)-gradient\(/i.test(bgColorStr)
    ? (bgColorStr.match(/#[0-9a-fA-F]{3,8}|rgba?\([^)]*\)/g) ?? [])
    : [];
  const bgImageRaw = typeof st.bg_image === "string" ? st.bg_image.trim() : "";
  const bgImageUri = bgImageRaw
    ? /^https?:\/\//i.test(bgImageRaw)
      ? bgImageRaw
      : bgImageRaw.startsWith("/f/")
        ? `${getBaseUrl()}${bgImageRaw}`
        : ""
    : "";

  const inner = <BlockViewInner {...props} />;

  // Retro browser-window chrome (Task #6568): the frame owns the block's
  // background/border/shadow, so it takes precedence over the generic
  // preset/gradient/image background layers below.
  const wcToken = typeof st._window_chrome === "string" ? st._window_chrome.trim() : "";
  if (wcToken !== "" && WINDOW_CHROME_TYPES.has(props.block.type)) {
    return <WindowChromeFrame st={st}>{inner}</WindowChromeFrame>;
  }

  if (!preset && gradientColors.length < 2 && !bgImageUri) return inner;

  let layer: React.ReactNode = null;
  let layerOpacity = 1;
  if (preset) {
    const stops =
      preset.colors.length >= 2
        ? (preset.colors as [string, string, ...string[]])
        : ([preset.colors[0] ?? "#3d3654", preset.colors[0] ?? "#3d3654"] as [string, string]);
    layerOpacity = presetOpacity / 100;
    layer = (
      <>
        <LinearGradient
          colors={stops}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={StyleSheet.absoluteFill}
        />
        {preset.swatch ? (
          <ImageBackground
            source={{ uri: `${getBaseUrl()}${preset.swatch}` }}
            style={StyleSheet.absoluteFill}
            resizeMode="cover"
          />
        ) : null}
      </>
    );
  } else if (bgImageUri) {
    layer = (
      <ImageBackground
        source={{ uri: bgImageUri }}
        style={StyleSheet.absoluteFill}
        resizeMode="cover"
      />
    );
  } else {
    layer = (
      <LinearGradient
        colors={gradientColors as [string, string, ...string[]]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={StyleSheet.absoluteFill}
      />
    );
  }

  return (
    <View style={{ borderRadius: 14, overflow: "hidden" }}>
      <View style={[StyleSheet.absoluteFill, { opacity: layerOpacity }]} pointerEvents="none">
        {layer}
      </View>
      <View style={{ padding: layerOpacity > 0 ? 8 : 0 }}>{inner}</View>
    </View>
  );
}

/**
 * Rich countdown block (mobile parity with the web renderer). Consumes the
 * new configurable settings — unit toggles, label style, subtitle, expired
 * behaviour, optional CTA — plus the countdown-specific `_style` color
 * overrides (`_countdown_digit_color` / `_countdown_label_color` /
 * `_countdown_box_bg` / `_countdown_cta_bg` / `_countdown_cta_text`). It's a standalone component so the 1s ticker can use
 * hooks without breaking the render function's hook order. Styles are
 * approximated (RN can't render CSS gradient strings); the component never
 * crashes on any variant. */
function CountdownBlock({
  settings,
  colors,
  router,
}: {
  settings: Record<string, unknown>;
  colors: PaletteColors;
  router: ReturnType<typeof useRouter>;
}) {
  const [now, setNow] = useState(() => Date.now());
  const st = ((settings._style as Record<string, unknown> | undefined) ?? {}) as Record<string, unknown>;

  const target = pickStr(settings, "target_date", "date", "ends_at");
  const tsMs = target ? Date.parse(target.replace(" ", "T")) : NaN;
  const expiredAction = pickStr(settings, "expired_action") === "hide_block" ? "hide_block" : "message";
  const remaining = Number.isFinite(tsMs) ? Math.max(0, tsMs - now) : 0;
  const expired = Number.isFinite(tsMs) && remaining <= 0;

  useEffect(() => {
    if (expired) return;
    const id = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(id);
  }, [expired]);

  // Hidden block once expired — render nothing.
  if (expired && expiredAction === "hide_block") return null;

  const title = pickContentStr(settings, "title", "text");
  const subtitle = pickContentStr(settings, "subtitle", "description");
  const expiredMessage = pickStr(settings, "expired_message") ?? "Time's up!";

  const labelStyle = ((): "full" | "short" | "hidden" => {
    const v = pickStr(settings, "label_style");
    return v === "short" || v === "hidden" ? v : "full";
  })();

  const showDays = pickBool(settings, "show_days", true);
  const showHours = pickBool(settings, "show_hours", true);
  const showMinutes = pickBool(settings, "show_minutes", true);
  const showSeconds = pickBool(settings, "show_seconds", true);

  // Countdown-specific colors with graceful fallbacks to the palette.
  const isColor = (v: unknown): v is string =>
    typeof v === "string" && v.trim() !== "" && v.trim() !== "transparent" && !/gradient\(/i.test(v);
  const digitColor = isColor(st._countdown_digit_color)
    ? (st._countdown_digit_color as string)
    : isColor(st.text_color)
      ? (st.text_color as string)
      : colors.primary;
  const labelColor = isColor(st._countdown_label_color)
    ? (st._countdown_label_color as string)
    : colors.mutedForeground;
  const boxBgRaw = st._countdown_box_bg;
  const boxBg = isColor(boxBgRaw) ? (boxBgRaw as string) : null;
  const isInline = st.display_mode === "content";

  const s = Math.floor(remaining / 1000);
  const parts: { key: string; val: number; full: string; short: string }[] = [];
  if (showDays) parts.push({ key: "d", val: Math.floor(s / 86400), full: "Days", short: "D" });
  if (showHours) parts.push({ key: "h", val: Math.floor((s % 86400) / 3600), full: "Hours", short: "H" });
  if (showMinutes) parts.push({ key: "m", val: Math.floor((s % 3600) / 60), full: "Min", short: "M" });
  if (showSeconds) parts.push({ key: "s", val: s % 60, full: "Sec", short: "S" });
  if (parts.length === 0) {
    parts.push(
      { key: "d", val: Math.floor(s / 86400), full: "Days", short: "D" },
      { key: "h", val: Math.floor((s % 86400) / 3600), full: "Hours", short: "H" },
      { key: "m", val: Math.floor((s % 3600) / 60), full: "Min", short: "M" },
      { key: "s", val: s % 60, full: "Sec", short: "S" },
    );
  }
  const pad = (n: number) => (n < 10 ? `0${n}` : String(n));

  const buttonText = pickStr(settings, "button_text");
  const buttonUrl = pickStr(settings, "button_url");
  const hasCta = !!buttonText && !!buttonUrl && isSafeUrl(buttonUrl);

  // CTA colors. Variants ship explicit high-contrast pairs
  // (_countdown_cta_bg / _countdown_cta_text) so the button is never an
  // invisible "white pill, white text" (glass/gradient variants). Fall back
  // to the digit color + a luminance-picked ink when a variant omits them.
  const ctaBg = isColor(st._countdown_cta_bg)
    ? (st._countdown_cta_bg as string)
    : isColor(digitColor)
      ? digitColor
      : colors.primary;
  const ctaText = ((): string => {
    if (isColor(st._countdown_cta_text)) return st._countdown_cta_text as string;
    const hex = ctaBg.replace("#", "");
    if (/^[0-9a-fA-F]{6}$/.test(hex)) {
      const lum =
        0.299 * parseInt(hex.slice(0, 2), 16) +
        0.587 * parseInt(hex.slice(2, 4), 16) +
        0.114 * parseInt(hex.slice(4, 6), 16);
      return lum > 150 ? "#111827" : "#ffffff";
    }
    return "#ffffff";
  })();

  // Card background from the variant's `_style.bg_color`. The outer BlockView
  // wrapper only paints a gradient layer (and only for gradient strings), and
  // this card sits on top of it — so we must draw the card bg ourselves or
  // the variant would render on the default light card (invisible white
  // digits on glass/gradient variants). Mirror the wrapper's gradient parse
  // (color-stop regex) and, for gradients, paint our own LinearGradient layer.
  const bgColorStr = typeof st.bg_color === "string" ? st.bg_color.trim() : "";
  const isGradientBg = /^(linear|radial|conic)-gradient\(/i.test(bgColorStr);
  const gradientStops = isGradientBg ? (bgColorStr.match(/#[0-9a-fA-F]{3,8}|rgba?\([^)]*\)/g) ?? []) : [];
  const hasGradient = gradientStops.length >= 2;
  const solidCardBg =
    !isGradientBg && bgColorStr !== "" && bgColorStr !== "transparent"
      ? bgColorStr
      : bgColorStr === "transparent"
        ? "transparent"
        : null;

  // Radius / border from `_style` (parity with web + other mobile blocks).
  const radiusNum = Number.parseFloat(String(st.border_radius ?? ""));
  const cardRadius = Number.isFinite(radiusNum) ? Math.min(radiusNum, 40) : 14;
  const borderWidthNum = Number.parseFloat(String(st.border_width ?? ""));
  const hasBorder = (st.border_style ?? "") !== "none" && isColor(st.border_color) && Number.isFinite(borderWidthNum) && borderWidthNum > 0;

  // Luminance of the digit color decides whether the card needs a dark or
  // light backdrop (used to composite translucent card bgs).
  const digitLum = ((): number => {
    const hex = digitColor.replace("#", "");
    if (/^[0-9a-fA-F]{6}$/.test(hex)) {
      return 0.299 * parseInt(hex.slice(0, 2), 16) + 0.587 * parseInt(hex.slice(2, 4), 16) + 0.114 * parseInt(hex.slice(4, 6), 16);
    }
    return 128;
  })();

  // A translucent solid card bg (e.g. glass_cards `rgba(255,255,255,0.08)`)
  // would be near-invisible on the default light app card and hide light
  // digits. Web composites glass over the page background; on mobile we back
  // it with a solid base contrasting the digit color, then overlay the
  // translucent tint, so the frosted panel always reads.
  const isTranslucentSolid =
    !isGradientBg && /^rgba?\(/i.test(solidCardBg ?? "") && /,\s*0?\.\d+\s*\)/.test(solidCardBg ?? "");
  const translucentBase = digitLum > 150 ? "#1e293b" : "#ffffff";

  // Base card style. Gradient => transparent (LinearGradient layer paints it).
  // Translucent solid => a contrasting base with the tint overlaid.
  // Opaque solid => used directly. Otherwise the theme card.
  const cardBg = hasGradient
    ? "transparent"
    : isTranslucentSolid
      ? translucentBase
      : (solidCardBg ?? colors.card);

  return (
    <View
      style={[
        styles.cardContainer,
        {
          backgroundColor: cardBg,
          borderColor: hasBorder ? (st.border_color as string) : colors.border,
          borderWidth: hasBorder ? borderWidthNum : solidCardBg || hasGradient ? 0 : StyleSheet.hairlineWidth,
          borderRadius: cardRadius,
          alignItems: "center",
          overflow: "hidden",
        },
      ]}
    >
      {hasGradient ? (
        <LinearGradient
          colors={gradientStops as [string, string, ...string[]]}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={StyleSheet.absoluteFill}
        />
      ) : null}
      {isTranslucentSolid ? (
        <View style={[StyleSheet.absoluteFill, { backgroundColor: solidCardBg as string }]} pointerEvents="none" />
      ) : null}
      {title ? <Text style={[styles.btnLabel, { color: labelColor }]}>{title}</Text> : null}
      {subtitle ? (
        <Text style={[styles.body, { color: labelColor, opacity: 0.7, fontSize: 12, marginTop: 2 }]}>{subtitle}</Text>
      ) : null}

      {expired && expiredAction === "message" ? (
        <Text style={[styles.heading, { color: digitColor, fontSize: 18, marginTop: 6 }]}>{expiredMessage}</Text>
      ) : (
        <View
          style={{
            flexDirection: "row",
            flexWrap: "wrap",
            justifyContent: "center",
            alignItems: isInline ? "baseline" : "flex-start",
            gap: isInline ? 4 : 10,
            marginTop: 8,
          }}
        >
          {parts.map((p, i) => (
            <React.Fragment key={p.key}>
              {isInline && i > 0 ? (
                <Text style={{ color: digitColor, fontSize: 22, opacity: 0.5, fontFamily: "SpaceGrotesk_700Bold" }}>:</Text>
              ) : null}
              <View
                style={{
                  alignItems: "center",
                  ...(boxBg && !isInline
                    ? { backgroundColor: boxBg, borderRadius: 12, paddingVertical: 8, paddingHorizontal: 8, minWidth: 52 }
                    : {}),
                }}
              >
                <Text style={{ color: digitColor, fontSize: isInline ? 22 : 26, fontFamily: "SpaceGrotesk_700Bold" }}>
                  {pad(p.val)}
                </Text>
                {labelStyle !== "hidden" ? (
                  <Text
                    style={{
                      color: labelColor,
                      fontSize: 10,
                      marginTop: isInline ? 0 : 4,
                      textTransform: "uppercase",
                      letterSpacing: 1,
                      fontFamily: "SpaceGrotesk_500Medium",
                    }}
                  >
                    {labelStyle === "short" ? p.short : p.full}
                  </Text>
                ) : null}
              </View>
            </React.Fragment>
          ))}
        </View>
      )}

      {hasCta ? (
        <Pressable
          onPress={() => openSafe(buttonUrl as string, router)}
          style={{
            marginTop: 14,
            backgroundColor: ctaBg,
            borderRadius: 10,
            paddingVertical: 10,
            paddingHorizontal: 22,
          }}
        >
          <Text style={{ color: ctaText, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 }}>
            {buttonText}
          </Text>
        </Pressable>
      ) : null}
    </View>
  );
}

function BlockViewInner({ block, alias, allBlocks, openEmbed }: { block: BiolinkBlock; alias: string; allBlocks: BiolinkBlock[]; openEmbed: OpenEmbed }) {
  const colors = useColors();
  const router = useRouter();
  const s = block.settings ?? {};
  const t = block.type;

  // Pull the design-variant overlay (bg/border/radius/text color) so a
  // creator's "Designs" pick from the editor is reflected on the public
  // mobile page. Returns null when the block is using its theme defaults
  // — callers should spread it after their own card style so it wins.
  const overlay = variantOverlay(t, s as Record<string, unknown>);
  const cardOverlay = overlay
    ? {
        ...(overlay.backgroundColor != null ? { backgroundColor: overlay.backgroundColor } : {}),
        ...(overlay.borderColor != null ? { borderColor: overlay.borderColor } : {}),
        ...(overlay.borderWidth != null ? { borderWidth: overlay.borderWidth } : {}),
        ...(overlay.borderRadius != null ? { borderRadius: overlay.borderRadius } : {}),
        ...(overlay.borderStyle != null ? { borderStyle: overlay.borderStyle } : {}),
        // Advanced borders (Task #6038): per-corner radius + per-side
        // width/color, spread after the generic props so a set
        // corner/side always wins (mirrors blockCardStyle()).
        ...(overlay.borderTopLeftRadius != null ? { borderTopLeftRadius: overlay.borderTopLeftRadius } : {}),
        ...(overlay.borderTopRightRadius != null ? { borderTopRightRadius: overlay.borderTopRightRadius } : {}),
        ...(overlay.borderBottomLeftRadius != null ? { borderBottomLeftRadius: overlay.borderBottomLeftRadius } : {}),
        ...(overlay.borderBottomRightRadius != null ? { borderBottomRightRadius: overlay.borderBottomRightRadius } : {}),
        ...(overlay.borderTopWidth != null ? { borderTopWidth: overlay.borderTopWidth } : {}),
        ...(overlay.borderRightWidth != null ? { borderRightWidth: overlay.borderRightWidth } : {}),
        ...(overlay.borderBottomWidth != null ? { borderBottomWidth: overlay.borderBottomWidth } : {}),
        ...(overlay.borderLeftWidth != null ? { borderLeftWidth: overlay.borderLeftWidth } : {}),
        ...(overlay.borderTopColor != null ? { borderTopColor: overlay.borderTopColor } : {}),
        ...(overlay.borderRightColor != null ? { borderRightColor: overlay.borderRightColor } : {}),
        ...(overlay.borderBottomColor != null ? { borderBottomColor: overlay.borderBottomColor } : {}),
        ...(overlay.borderLeftColor != null ? { borderLeftColor: overlay.borderLeftColor } : {}),
      }
    : null;

  // Layout containers (card / grid / grid_auto) nest other blocks via
  // parent_id. Render their direct children inline so the visual grouping
  // survives on mobile. The styled "card" keeps its background/border chrome;
  // the plain "grid"/"grid_auto" containers render their children with no
  // chrome (only spacing), matching the web's plain grid behaviour.
  if (t === "card" || t === "grid" || t === "grid_auto") {
    const children = allBlocks.filter((b) => b.parent_id === block.id);
    const title = pickStr(s, "title");
    const isCard = t === "card";
    const pad = pickNum(s, "padding");
    // Card container's OWN background settings (Task #6044): honour the
    // web card builder's bg_type/bg_color/bg_gradient/bg_image so the
    // mobile grouping matches the public web page. Gradient strings are
    // approximated with their color stops; /f/ vault paths resolve
    // against the API base.
    const cardBgType = isCard ? (pickStr(s, "bg_type") ?? "glass") : "glass";
    const cardBgColor = isCard && cardBgType === "color" ? pickStr(s, "bg_color") : undefined;
    const cardGradientStr = isCard && cardBgType === "gradient" ? (pickStr(s, "bg_gradient") ?? "") : "";
    const cardGradientColors = cardGradientStr
      ? (cardGradientStr.match(/#[0-9a-fA-F]{3,8}|rgba?\([^)]*\)/g) ?? [])
      : [];
    const cardBgImgRaw = isCard && cardBgType === "image" ? (pickStr(s, "bg_image") ?? "") : "";
    const cardBgImgUri = cardBgImgRaw
      ? /^https?:\/\//i.test(cardBgImgRaw)
        ? cardBgImgRaw
        : cardBgImgRaw.startsWith("/f/")
          ? `${getBaseUrl()}${cardBgImgRaw}`
          : ""
      : "";
    const cardBgOverride =
      cardBgType === "transparent"
        ? { backgroundColor: "transparent" }
        : cardBgColor
          ? { backgroundColor: cardBgColor }
          : cardGradientColors.length >= 2 || cardBgImgUri
            ? { backgroundColor: "transparent" }
            : null;
    // Unified `_style` parity for card containers (Task #6173): the web
    // editor's Block Styling picker now styles cards too. `_style` values
    // override the legacy bg_type mapping property-by-property (borders /
    // corners already flow in via blockCardStyle → variantOverlay; this
    // block adds backgrounds, glass and per-side padding/margins).
    const _cs = isCard ? (((s as Record<string, unknown>)._style ?? null) as Record<string, unknown> | null) : null;
    const csStr = (k: string): string => {
      const v = _cs?.[k];
      return typeof v === "string" ? v.trim() : typeof v === "number" ? String(v) : "";
    };
    const csNum = (k: string): number | null => {
      const v = csStr(k);
      const n = Number(v);
      return v !== "" && Number.isFinite(n) ? n : null;
    };
    const uBgRaw = csStr("bg_color");
    const uIsGrad = /^(linear|radial|conic)-gradient\(/i.test(uBgRaw);
    const uGradColors = uIsGrad ? (uBgRaw.match(/#[0-9a-fA-F]{3,8}|rgba?\([^)]*\)/g) ?? []) : [];
    const uSolidBg = !uIsGrad ? uBgRaw : "";
    const uImgRaw = csStr("bg_image");
    const uImgUri = uImgRaw
      ? /^https?:\/\//i.test(uImgRaw)
        ? uImgRaw
        : uImgRaw.startsWith("/f/")
          ? `${getBaseUrl()}${uImgRaw}`
          : ""
      : "";
    const uGlass = csStr("effect") === "glass" && uBgRaw === "" && uImgRaw === "";
    // Any unified background pick supersedes the legacy bg layers entirely
    // (web CSS last-wins equivalent).
    const unifiedBgSet = uBgRaw !== "" || uImgRaw !== "" || uGlass;
    const gradColors = unifiedBgSet ? uGradColors : cardGradientColors;
    const bgImgUri = unifiedBgSet ? uImgUri : cardBgImgUri;
    const uPad = csNum("padding");
    const unifiedOverride = _cs
      ? {
          ...(uSolidBg !== "" && uSolidBg !== "transparent"
            ? { backgroundColor: uSolidBg }
            : uGlass
              ? { backgroundColor: "rgba(255,255,255,0.08)" }
              : unifiedBgSet || uSolidBg === "transparent"
                ? { backgroundColor: "transparent" }
                : {}),
          ...(uPad != null ? { padding: uPad } : {}),
          ...(csNum("padding_top") != null ? { paddingTop: csNum("padding_top")! } : {}),
          ...(csNum("padding_right") != null ? { paddingRight: csNum("padding_right")! } : {}),
          ...(csNum("padding_bottom") != null ? { paddingBottom: csNum("padding_bottom")! } : {}),
          ...(csNum("padding_left") != null ? { paddingLeft: csNum("padding_left")! } : {}),
          ...(csNum("margin_top") != null ? { marginTop: csNum("margin_top")! } : {}),
          ...(csNum("margin_bottom") != null ? { marginBottom: csNum("margin_bottom")! } : {}),
          ...(csNum("margin_left") != null ? { marginLeft: csNum("margin_left")! } : {}),
          ...(csNum("margin_right") != null ? { marginRight: csNum("margin_right")! } : {}),
        }
      : null;
    const containerStyle = isCard
      ? [styles.cardContainer, blockCardStyle(block, colors), cardBgOverride, unifiedOverride, { overflow: "hidden" as const }]
      : [styles.gridContainer, pad != null ? { padding: pad } : null];
    return (
      <View style={containerStyle}>
        {isCard && gradColors.length >= 2 ? (
          <LinearGradient
            colors={gradColors as [string, string, ...string[]]}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={StyleSheet.absoluteFill}
            pointerEvents="none"
          />
        ) : null}
        {isCard && bgImgUri ? (
          <View style={StyleSheet.absoluteFill} pointerEvents="none">
            <ImageBackground
              source={{ uri: bgImgUri }}
              style={StyleSheet.absoluteFill}
              resizeMode="cover"
            />
          </View>
        ) : null}
        {title ? <Text style={[styles.heading, { color: colors.foreground, fontSize: 16, marginTop: 0 }]}>{title}</Text> : null}
        {children.map((c) => (
          <BlockView key={c.id} block={c} alias={alias} allBlocks={allBlocks} openEmbed={openEmbed} />
        ))}
      </View>
    );
  }

  // Fire the analytics ping first (best-effort, non-blocking) so the tap
  // is counted even on slow networks where Linking.openURL hands off
  // immediately.
  const handleTap = (url: string) => {
    trackBiolinkBlockTap(alias, block.id, url);
    openSafe(url, router);
  };

  // Generic link/button block. The link block now also supports a Linktree
  // style "Featured" flag (`is_featured`) which gives it a pinned-style
  // accent treatment.
  if (
    t === "link" ||
    t === "link_big" ||
    t === "button" ||
    t === "url" ||
    t === "social" ||
    t === "cta"
  ) {
    const url = pickStr(s, "url", "link", "destination_url", "href");
    const label =
      pickStr(s, "title", "label", "text", "button_text", "name") ??
      url ??
      "Open";
    if (!url) return null;
    if (!isSafeUrl(url)) return null;
    const featured = pickBool(s, "is_featured", false);
    const accent = pickStr(s, "accent_color") ?? colors.primary;
    const desc = pickStr(s, "description");
    const thumb = pickStr(s, "thumbnail");
    // "Text list / divider" layout (web `_style.link_layout=text_divider`):
    // plain left-aligned text row with a thin hairline divider below it —
    // no button chrome. Divider derives from the row's text color so it
    // stays legible on both dark and light page themes.
    const _st = (s?.["_style"] ?? null) as Record<string, unknown> | null;
    const _linkLayout = typeof _st?.["link_layout"] === "string" ? (_st["link_layout"] as string) : "";
    if (!featured && _linkLayout === "text_divider") {
      const _tdColor =
        (typeof _st?.["text_color"] === "string" && (_st["text_color"] as string) !== ""
          ? (_st["text_color"] as string)
          : null) ?? blockTextColor(block, colors.foreground);
      return (
        <Pressable
          onPress={() => handleTap(url)}
          style={{
            width: "100%",
            paddingVertical: 14,
            borderBottomWidth: StyleSheet.hairlineWidth,
            borderBottomColor: _tdColor + "40",
          }}
        >
          <Text
            style={{ color: _tdColor, fontSize: 15, fontWeight: "500", textAlign: "left" }}
            numberOfLines={2}
          >
            {label}
          </Text>
        </Pressable>
      );
    }
    // "Taped Notes" layout (web `_style.link_layout=taped_note`): muted
    // pastel paper card with a washi-tape strip at the top and a centered
    // serif label. Per-card paper tint rotates through the same pastel
    // palette as the web renderer (keyed by sort_order) unless the block
    // carries its own bg_color/text_color override. Paper + ink colors
    // are explicit so the card reads identically in dark and light themes.
    if (!featured && _linkLayout === "taped_note") {
      const TN_PALETTE: [string, string][] = [
        ["#f7e9ed", "#6d4c3d"],
        ["#a98a7d", "#f9f2ec"],
        ["#bdb3aa", "#4a3d31"],
        ["#f2e3e6", "#6d4c3d"],
        ["#8d7466", "#f6ede5"],
        ["#cfc3b8", "#4a3d31"],
      ];
      const tnIdx = Math.abs(Math.trunc(block.sort_order ?? block.id ?? 0)) % TN_PALETTE.length;
      const [tnBgDefault, tnInkDefault] = TN_PALETTE[tnIdx];
      const tnBgPick = typeof _st?.["bg_color"] === "string" ? (_st["bg_color"] as string) : "";
      const tnBg = tnBgPick !== "" && tnBgPick !== "transparent" ? tnBgPick : tnBgDefault;
      const tnInkPick = typeof _st?.["text_color"] === "string" ? (_st["text_color"] as string) : "";
      const tnInk = tnInkPick !== "" ? tnInkPick : tnInkDefault;
      const tnTilt = tnIdx % 2 === 0 ? "-2.5deg" : "2deg";
      return (
        <Pressable onPress={() => handleTap(url)} style={{ width: "100%", paddingTop: 12, marginBottom: 4 }}>
          <View
            style={{
              width: "100%",
              backgroundColor: tnBg,
              borderRadius: 3,
              paddingVertical: 30,
              paddingHorizontal: 16,
              alignItems: "center",
              shadowColor: "#4c3c32",
              shadowOpacity: 0.16,
              shadowRadius: 9,
              shadowOffset: { width: 0, height: 4 },
              elevation: 3,
            }}
          >
            <Text
              style={{
                color: tnInk,
                fontSize: 16,
                fontWeight: "500",
                textAlign: "center",
                letterSpacing: 0.3,
                fontFamily: Platform.select({ ios: "Georgia", android: "serif", default: "Georgia, 'Times New Roman', serif" }),
              }}
              numberOfLines={2}
            >
              {label}
            </Text>
          </View>
          <View
            pointerEvents="none"
            style={{
              position: "absolute",
              top: 0,
              left: "50%",
              width: 86,
              height: 24,
              marginLeft: -43,
              transform: [{ rotate: tnTilt }],
              backgroundColor: "rgba(235,227,208,0.82)",
              borderLeftWidth: 1,
              borderRightWidth: 1,
              borderLeftColor: "rgba(120,105,85,0.28)",
              borderRightColor: "rgba(120,105,85,0.28)",
              shadowColor: "#4c3c32",
              shadowOpacity: 0.18,
              shadowRadius: 2,
              shadowOffset: { width: 0, height: 1 },
            }}
          />
        </Pressable>
      );
    }
    // Structural button layouts from Task #6602 (web link.blade.php):
    // rendered natively so the mobile page matches the web page instead
    // of degrading to the plain colored button. Unknown/other tokens
    // still fall through to the plain button below.
    if (!featured && _linkLayout === "sparkle_pill") {
      return <SparklePillLink st={_st} label={label} onPress={() => handleTap(url)} />;
    }
    if (!featured && _linkLayout === "notched_bar") {
      return <NotchedBarLink st={_st} label={label} onPress={() => handleTap(url)} />;
    }
    if (!featured && _linkLayout === "speech_bubble") {
      return <SpeechBubbleLink st={_st} label={label} onPress={() => handleTap(url)} />;
    }
    if (!featured && _linkLayout === "riveted_plaque") {
      return <RivetedPlaqueLink st={_st} label={label} onPress={() => handleTap(url)} />;
    }
    if (featured) {
      return (
        <Pressable
          onPress={() => handleTap(url)}
          style={[styles.btn, { backgroundColor: accent, borderColor: accent, alignItems: "flex-start" }]}
        >
          <Text style={[styles.btnLabel, { color: blockTextColor(block, "#fff"), textAlign: "left" }]} numberOfLines={2}>
            ★ {label}
          </Text>
          {desc ? (
            <Text style={[styles.body, { color: "#ffffffcc", textAlign: "left", fontSize: 12, marginTop: 2 }]}>
              {desc}
            </Text>
          ) : null}
        </Pressable>
      );
    }
    return (
      <Pressable
        onPress={() => handleTap(url)}
        style={[styles.btn, { backgroundColor: colors.primary, borderColor: colors.primary, flexDirection: thumb ? "row" : "column", gap: thumb ? 10 : 0 }]}
      >
        {thumb ? <Image source={{ uri: thumb }} style={{ width: 28, height: 28, borderRadius: 6 }} /> : null}
        <Text style={[styles.btnLabel, { color: "#fff" }]} numberOfLines={2}>
          {label}
        </Text>
      </Pressable>
    );
  }

  if (
    t === "heading" ||
    t === "title" ||
    t === "heading_logo"
  ) {
    const text = pickStr(s, "text", "title", "heading");
    if (!text) return null;

    // Decorative shape accents behind heading blocks (web
    // `_style._heading_*`, Task #5938). Mirrors AccentShapeCatalog.
    const haSt = (s._style && typeof s._style === "object" ? s._style : {}) as Record<
      string,
      unknown
    >;
    const haStr = (k: string): string => (typeof haSt[k] === "string" ? (haSt[k] as string) : "");
    const haKnown = ["starburst", "dots", "squiggle", "ring", "blob"];
    const haAccents =
      t === "heading"
        ? haStr("_heading_accents")
            .split(",")
            .map((a) => a.trim().toLowerCase())
            .filter((a, i, arr) => a !== "" && haKnown.includes(a) && arr.indexOf(a) === i)
        : [];

    // Text tilt (web `_style._tilt`, Task #5954): rotate the whole heading
    // up to ±30° for poster / scrapbook looks.
    const haTiltRaw = Number(haSt._tilt ?? 0);
    const haTilt = Number.isFinite(haTiltRaw) ? Math.max(-30, Math.min(30, haTiltRaw)) : 0;
    const tiltWrap = (el: React.ReactElement) =>
      haTilt !== 0 ? (
        <View style={{ transform: [{ rotate: `${haTilt}deg` }] }}>{el}</View>
      ) : (
        el
      );

    const headingEl = (
      <Text style={[styles.heading, { color: blockTextColor(block, colors.foreground), zIndex: 1 }]}>{text}</Text>
    );
    if (haAccents.length === 0) return tiltWrap(headingEl);

    const haColor = haStr("_heading_accent_color") || "#ec4899";
    const haPlacementRaw = haStr("_heading_accent_placement");
    const haPlacement = ["behind_left", "behind_right", "top_left", "top_right"].includes(
      haPlacementRaw
    )
      ? haPlacementRaw
      : "behind_left";
    const haScale = ({ sm: 0.7, md: 1.0, lg: 1.5 } as Record<string, number>)[
      haStr("_heading_accent_size")
    ] ?? 1.0;
    // Base dims per shape (matches AccentShapeCatalog::SHAPES).
    const haDims: Record<string, [string, number, number]> = {
      starburst: ["0 0 100 100", 54, 54],
      dots: ["0 0 90 90", 76, 76],
      squiggle: ["0 0 120 40", 84, 28],
      ring: ["0 0 60 60", 46, 46],
      blob: ["0 0 100 100", 58, 58],
    };
    // Up to three anchor slots per placement (primary first), mirroring
    // the web renderer with fixed-pixel offsets (RN has no % transforms).
    const haSlots: Record<string, Array<Record<string, number>>> = {
      behind_left: [
        { left: -10, top: -8 },
        { right: -10, top: -8 },
        { left: 60, top: -16 },
      ],
      behind_right: [
        { right: -10, top: -8 },
        { left: -10, top: -8 },
        { left: 60, top: -16 },
      ],
      top_left: [
        { left: -12, top: -16 },
        { right: -12, bottom: -10 },
        { right: -12, top: -16 },
      ],
      top_right: [
        { right: -12, top: -16 },
        { left: -12, bottom: -10 },
        { left: -12, top: -16 },
      ],
    };
    const slots = haSlots[haPlacement];

    return tiltWrap(
      <View style={{ position: "relative" }}>
        {haAccents.map((shape, idx) => {
          const [viewBox, bw, bh] = haDims[shape];
          const slotScale = haScale * (idx === 0 ? 1.0 : 0.72);
          const w = Math.round(bw * slotScale);
          const h = Math.round(bh * slotScale);
          const pos = slots[idx % slots.length];
          return (
            <Svg
              key={`ha-${shape}`}
              pointerEvents="none"
              viewBox={viewBox}
              width={w}
              height={h}
              style={{ position: "absolute", zIndex: 0, ...pos }}
            >
              {shape === "starburst" ? (
                <Path
                  d="M50 0 L56 33 L75 7 L63 38 L96 22 L67 44 L100 50 L67 56 L96 78 L63 62 L75 93 L56 67 L50 100 L44 67 L25 93 L37 62 L4 78 L33 56 L0 50 L33 44 L4 22 L37 38 L25 7 L44 33 Z"
                  fill={haColor}
                />
              ) : shape === "dots" ? (
                <>
                  {[
                    [78, 10, 6], [58, 18, 4.5], [76, 30, 4], [44, 10, 3.5], [62, 38, 3.2],
                    [82, 46, 3], [48, 28, 2.6], [70, 54, 2.4], [34, 20, 2.2], [56, 50, 2],
                    [84, 62, 2], [42, 42, 1.8], [66, 68, 1.6], [78, 76, 1.4],
                  ].map(([cx, cy, r], i) => (
                    <Circle key={`had-${i}`} cx={cx} cy={cy} r={r} fill={haColor} />
                  ))}
                </>
              ) : shape === "squiggle" ? (
                <Path
                  d="M5 30 Q20 5 35 25 T65 22 T95 24 T115 15"
                  fill="none"
                  stroke={haColor}
                  strokeWidth={5}
                  strokeLinecap="round"
                />
              ) : shape === "ring" ? (
                <Circle cx={30} cy={30} r={24} fill="none" stroke={haColor} strokeWidth={6} />
              ) : (
                <Path
                  d="M83 45 C90 62 78 84 58 88 C38 92 16 82 12 62 C8 42 22 20 44 14 C66 8 76 28 83 45 Z"
                  fill={haColor}
                />
              )}
            </Svg>
          );
        })}
        {headingEl}
      </View>
    );
  }

  if (
    t === "text" ||
    t === "paragraph" ||
    t === "paragraph_rich" ||
    t === "bio" ||
    t === "markdown"
  ) {
    const text =
      pickStr(s, "text", "content", "body", "bio") ??
      // paragraph_rich stores raw HTML; strip tags so we don't show markup.
      (pickStr(s, "html") ?? "").replace(/<[^>]+>/g, "").trim();
    if (!text) return null;
    // Text tilt (web `_style._tilt`, Task #5954), paragraph parity.
    const pSt = (s._style && typeof s._style === "object" ? s._style : {}) as Record<
      string,
      unknown
    >;
    const pTiltRaw = Number(pSt._tilt ?? 0);
    const pTilt =
      t === "paragraph" && Number.isFinite(pTiltRaw) ? Math.max(-30, Math.min(30, pTiltRaw)) : 0;
    const paragraphEl = (
      <Text style={[styles.body, { color: blockTextColor(block, colors.foreground) }]}>{text}</Text>
    );
    return pTilt !== 0 ? (
      <View style={{ transform: [{ rotate: `${pTilt}deg` }] }}>{paragraphEl}</View>
    ) : (
      paragraphEl
    );
  }

  if (
    t === "image" ||
    t === "photo" ||
    t === "banner" ||
    t === "header_image" ||
    t === "avatar"
  ) {
    const url = pickStr(s, "url", "image", "image_url", "src", "thumbnail");
    if (!url) return null;
    const isAvatar = t === "avatar";

    // Hero-photo decorations (web `_style._photo_*`, Task #5922): concentric
    // arch frame, half-overlapping title banner, torn-edge collage + accents.
    const phSt = (s._style && typeof s._style === "object" ? s._style : {}) as Record<
      string,
      unknown
    >;
    const phStr = (k: string): string => (typeof phSt[k] === "string" ? (phSt[k] as string) : "");
    const phFrame = !isAvatar && phStr("_photo_frame") === "concentric_arch";
    const phMask = !isAvatar ? phStr("_photo_mask") : "";
    const phBanner = !isAvatar ? phStr("_photo_banner_text").trim() : "";
    const phAccents = !isAvatar
      ? phStr("_photo_accents")
          .split(",")
          .map((a) => a.trim())
          .filter(Boolean)
      : [];
    // Custom sticker overlays (Task #5939): sanitized `{file_id,url,pos,...}`
    // entries persisted in _style. `url` is a server-derived relative
    // `/f/{id}/{name}` path — absolutize against the API origin here.
    type PhSticker = { url: string; pos: string; size: number; rotate: number; dx: number; dy: number };
    const phStickers: PhSticker[] = !isAvatar && Array.isArray(phSt._photo_stickers)
      ? (phSt._photo_stickers as unknown[])
          .filter((e): e is Record<string, unknown> => !!e && typeof e === "object")
          .map((e) => {
            const raw = typeof e.url === "string" ? e.url : "";
            return {
              url: raw.startsWith("/") ? `${getBaseUrl()}${raw}` : raw,
              pos: typeof e.pos === "string" ? e.pos : "top_right",
              size: Math.max(24, Math.min(160, Number(e.size) || 64)),
              rotate: Math.max(-180, Math.min(180, Number(e.rotate) || 0)),
              dx: Math.max(-80, Math.min(80, Number(e.dx) || 0)),
              dy: Math.max(-80, Math.min(80, Number(e.dy) || 0)),
            };
          })
          .filter((e) => e.url !== "")
          .slice(0, 4)
      : [];
    // Text-on-photo overlays (web `_style._photo_text_stickers`, Task #5954):
    // short captions anchored like stickers, draggable via dx/dy offsets.
    type PhTextSticker = {
      text: string;
      font: string;
      color: string;
      size: number;
      pos: string;
      dx: number;
      dy: number;
      rotate: number;
    };
    const phTexts: PhTextSticker[] = !isAvatar && Array.isArray(phSt._photo_text_stickers)
      ? (phSt._photo_text_stickers as unknown[])
          .filter((e): e is Record<string, unknown> => !!e && typeof e === "object")
          .map((e) => ({
            text: typeof e.text === "string" ? e.text.trim() : "",
            font:
              typeof e.font === "string" ? e.font.replace(/^custom:/, "") : "",
            color:
              typeof e.color === "string" && /^#[0-9a-fA-F]{3,8}$/.test(e.color)
                ? e.color
                : "#ffffff",
            size: Math.max(10, Math.min(64, Number(e.size) || 20)),
            pos: typeof e.pos === "string" ? e.pos : "top_left",
            dx: Math.max(-80, Math.min(80, Number(e.dx) || 0)),
            dy: Math.max(-80, Math.min(80, Number(e.dy) || 0)),
            rotate: Math.max(-180, Math.min(180, Number(e.rotate) || 0)),
          }))
          .filter((e) => e.text !== "")
          .slice(0, 4)
      : [];
    const phDecorated =
      phFrame ||
      phMask !== "" ||
      phBanner !== "" ||
      phAccents.length > 0 ||
      phStickers.length > 0 ||
      phTexts.length > 0;

    if (phDecorated) {
      const phFrameColor = phStr("_photo_frame_color") || "#57534e";
      const phStrokesRaw = Number(phSt._photo_frame_strokes ?? 0);
      const phStrokes = Math.max(2, Math.min(5, phStrokesRaw || 3));
      const phGap = 9;
      const phPad = phFrame ? phStrokes * phGap + 6 : 0;
      const phBannerBg = phStr("_photo_banner_bg") || "#2a201c";
      const phBannerColor = phStr("_photo_banner_text_color") || "#ffffff";
      const phAccentColor = phStr("_photo_accent_color") || "#3f4e63";
      const phArch = phFrame || phMask === "arch";
      const phTorn = !phArch && phMask === "torn";
      // Torn edges approximated with page-background zigzag overlays.
      const tornBg = colors.background;
      const tornZig = "M0 12 L10 2 L20 12 L30 3 L40 11 L50 2 L60 12 L70 4 L80 11 L90 2 L100 12 L100 0 L0 0 Z";
      return (
        <View
          style={{
            padding: phPad,
            marginBottom: phBanner !== "" ? 40 : 4,
            position: "relative",
          }}
        >
          {phFrame
            ? Array.from({ length: phStrokes }).map((_, i) => (
                <View
                  key={`fs-${i}`}
                  pointerEvents="none"
                  style={{
                    position: "absolute",
                    top: i * phGap,
                    left: i * phGap,
                    right: i * phGap,
                    bottom: 0,
                    borderWidth: 1.5,
                    borderBottomWidth: 0,
                    borderColor: phFrameColor,
                    borderTopLeftRadius: 999,
                    borderTopRightRadius: 999,
                    opacity: 1 - i * 0.12,
                  }}
                />
              ))
            : null}
          <View
            style={{
              overflow: "hidden",
              borderTopLeftRadius: phArch ? 999 : 0,
              borderTopRightRadius: phArch ? 999 : 0,
            }}
          >
            <Image
              source={{ uri: url }}
              style={{ width: "100%", aspectRatio: phArch ? 3 / 4 : 4 / 5 }}
              resizeMode="cover"
            />
            {phTorn ? (
              <>
                <Svg
                  pointerEvents="none"
                  viewBox="0 0 100 12"
                  preserveAspectRatio="none"
                  style={{ position: "absolute", top: 0, left: 0, right: 0, height: 12 }}
                >
                  <Path d={tornZig} fill={tornBg} />
                </Svg>
                <Svg
                  pointerEvents="none"
                  viewBox="0 0 100 12"
                  preserveAspectRatio="none"
                  style={{
                    position: "absolute",
                    bottom: 0,
                    left: 0,
                    right: 0,
                    height: 12,
                    transform: [{ scaleY: -1 }],
                  }}
                >
                  <Path d={tornZig} fill={tornBg} />
                </Svg>
              </>
            ) : null}
          </View>
          {phBanner !== "" ? (
            <View
              pointerEvents="none"
              style={{
                position: "absolute",
                bottom: -18,
                left: 16,
                right: 16,
                alignItems: "center",
                zIndex: 10,
              }}
            >
              <View
                style={{
                  backgroundColor: phBannerBg,
                  paddingVertical: 11,
                  paddingHorizontal: 26,
                  maxWidth: "92%",
                }}
              >
                <Text
                  numberOfLines={1}
                  style={{
                    color: phBannerColor,
                    fontFamily: "SpaceGrotesk_700Bold",
                    fontSize: 14,
                    letterSpacing: 2,
                    textTransform: "uppercase",
                    textAlign: "center",
                  }}
                >
                  {phBanner}
                </Text>
              </View>
            </View>
          ) : null}
          {phAccents.includes("starburst") ? (
            <Svg
              pointerEvents="none"
              viewBox="0 0 100 100"
              width={54}
              height={54}
              style={{ position: "absolute", left: -8, top: "42%", zIndex: 10 }}
            >
              <Path
                d="M50 0 L56 33 L75 7 L63 38 L96 22 L67 44 L100 50 L67 56 L96 78 L63 62 L75 93 L56 67 L50 100 L44 67 L25 93 L37 62 L4 78 L33 56 L0 50 L33 44 L4 22 L37 38 L25 7 L44 33 Z"
                fill={phAccentColor}
              />
            </Svg>
          ) : null}
          {phAccents.includes("dots") ? (
            <Svg
              pointerEvents="none"
              viewBox="0 0 90 90"
              width={76}
              height={76}
              style={{ position: "absolute", right: -6, top: -10, zIndex: 10 }}
            >
              {[
                [78, 10, 6], [58, 18, 4.5], [76, 30, 4], [44, 10, 3.5], [62, 38, 3.2],
                [82, 46, 3], [48, 28, 2.6], [70, 54, 2.4], [34, 20, 2.2], [56, 50, 2],
                [84, 62, 2], [42, 42, 1.8], [66, 68, 1.6], [78, 76, 1.4],
              ].map(([cx, cy, r], i) => (
                <Circle key={`d-${i}`} cx={cx} cy={cy} r={r} fill={phAccentColor} />
              ))}
            </Svg>
          ) : null}
          {phAccents.includes("squiggle") ? (
            <Svg
              pointerEvents="none"
              viewBox="0 0 120 40"
              width={84}
              height={28}
              style={{ position: "absolute", left: -4, bottom: -8, zIndex: 10 }}
            >
              <Path
                d="M5 30 Q20 5 35 25 T65 22 T95 24 T115 15"
                fill="none"
                stroke={phAccentColor}
                strokeWidth={5}
                strokeLinecap="round"
              />
            </Svg>
          ) : null}
          {phAccents.includes("ring") ? (
            <Svg
              pointerEvents="none"
              viewBox="0 0 60 60"
              width={46}
              height={46}
              style={{ position: "absolute", left: -10, top: -8, zIndex: 10 }}
            >
              <Circle cx={30} cy={30} r={24} fill="none" stroke={phAccentColor} strokeWidth={6} />
            </Svg>
          ) : null}
          {phAccents.includes("blob") ? (
            <Svg
              pointerEvents="none"
              viewBox="0 0 100 100"
              width={58}
              height={58}
              style={{ position: "absolute", right: -10, bottom: -6, zIndex: 10 }}
            >
              <Path
                d="M83 45 C90 62 78 84 58 88 C38 92 16 82 12 62 C8 42 22 20 44 14 C66 8 76 28 83 45 Z"
                fill={phAccentColor}
              />
            </Svg>
          ) : null}
          {phStickers.map((stk, i) => {
            const anchor: Record<string, number | string> =
              stk.pos === "top_left"
                ? { left: -10, top: -10 }
                : stk.pos === "bottom_left"
                  ? { left: -10, bottom: -10 }
                  : stk.pos === "bottom_right"
                    ? { right: -10, bottom: -10 }
                    : stk.pos === "center_left"
                      ? { left: -12, top: "50%" }
                      : stk.pos === "center_right"
                        ? { right: -12, top: "50%" }
                        : { right: -10, top: -10 };
            const centered = stk.pos === "center_left" || stk.pos === "center_right";
            return (
              <View
                key={`stk-${i}`}
                pointerEvents="none"
                style={{
                  position: "absolute",
                  zIndex: 11,
                  width: stk.size,
                  height: stk.size,
                  ...anchor,
                  transform: [
                    ...(centered ? [{ translateY: -stk.size / 2 }] : []),
                    { translateX: stk.dx },
                    { translateY: stk.dy },
                    { rotate: `${stk.rotate}deg` },
                  ],
                }}
              >
                <Image
                  source={{ uri: stk.url }}
                  resizeMode="contain"
                  style={{ width: "100%", height: "100%" }}
                />
              </View>
            );
          })}
          {phTexts.map((tk, i) => {
            const anchor: Record<string, number | string> =
              tk.pos === "top_left"
                ? { left: -10, top: -10 }
                : tk.pos === "bottom_left"
                  ? { left: -10, bottom: -10 }
                  : tk.pos === "bottom_right"
                    ? { right: -10, bottom: -10 }
                    : tk.pos === "center_left"
                      ? { left: -12, top: "50%" }
                      : tk.pos === "center_right"
                        ? { right: -12, top: "50%" }
                        : { right: -10, top: -10 };
            const centered = tk.pos === "center_left" || tk.pos === "center_right";
            return (
              <View
                key={`ptk-${i}`}
                pointerEvents="none"
                style={{
                  position: "absolute",
                  zIndex: 12,
                  ...anchor,
                  transform: [
                    ...(centered ? [{ translateY: -(tk.size * 0.6) }] : []),
                    { translateX: tk.dx },
                    { translateY: tk.dy },
                    { rotate: `${tk.rotate}deg` },
                  ],
                }}
              >
                <Text
                  style={{
                    color: tk.color,
                    fontSize: tk.size,
                    fontWeight: "700",
                    textShadowColor: "rgba(0,0,0,0.45)",
                    textShadowOffset: { width: 0, height: 1 },
                    textShadowRadius: 6,
                  }}
                >
                  {tk.text}
                </Text>
              </View>
            );
          })}
        </View>
      );
    }

    // ── Mask shapes + tappable link (Task #6575) ────────────────────
    // Web parity: `_image_style.mask_shape` clips the image and a `_link`
    // URL makes the whole shape tappable. circle/rounded/square/pill map
    // to border-radius; the polygon/oval shapes go through the SVG clip.
    const imgStyleObj = (s._image_style && typeof s._image_style === "object"
      ? s._image_style
      : {}) as Record<string, unknown>;
    const maskShape =
      !isAvatar && typeof imgStyleObj.mask_shape === "string" ? imgStyleObj.mask_shape : "none";
    const linkObj = (s._link && typeof s._link === "object" ? s._link : {}) as Record<
      string,
      unknown
    >;
    const imgLinkUrl = !isAvatar ? (pickStr(linkObj, "url") ?? pickStr(s, "link")) : null;

    let imageEl: React.ReactElement;
    if (isAvatar) {
      imageEl = (
        <Image
          source={{ uri: url }}
          style={[
            styles.image,
            { width: 96, height: 96, aspectRatio: undefined, borderRadius: pickBool(s, "rounded", true) ? 999 : 16 },
          ]}
          resizeMode="cover"
        />
      );
    } else if (maskShape === "circle") {
      imageEl = (
        <Image
          source={{ uri: url }}
          style={{ width: "100%", aspectRatio: 1, borderRadius: 9999 }}
          resizeMode="cover"
        />
      );
    } else if (maskShape === "rounded" || maskShape === "square" || maskShape === "pill") {
      imageEl = (
        <Image
          source={{ uri: url }}
          style={[
            styles.image,
            { borderRadius: maskShape === "square" ? 0 : maskShape === "pill" ? 9999 : 20 },
          ]}
          resizeMode="cover"
        />
      );
    } else if (maskShape === "oval" || MASK_POLYGONS[maskShape]) {
      imageEl = <MaskedBlockImage uri={url} shape={maskShape} />;
    } else {
      imageEl = <Image source={{ uri: url }} style={styles.image} resizeMode="cover" />;
    }

    if (imgLinkUrl && isSafeUrl(imgLinkUrl)) {
      return (
        <Pressable
          onPress={() => handleTap(imgLinkUrl)}
          accessibilityRole="link"
          style={({ pressed }) => ({ width: "100%", opacity: pressed ? 0.85 : 1 })}
        >
          {imageEl}
        </Pressable>
      );
    }
    return imageEl;
  }

  if (t === "spacer") {
    // Match web: an empty gap, not a colored band.
    const h = Math.max(4, Math.min(200, pickNum(s, "height") ?? 12));
    return <View style={{ height: h }} />;
  }

  if (t === "divider") {
    // Richer divider (Task #6581) — mirrors the web renderer's knobs:
    // line style presets, thickness, width %, alignment and an optional
    // centered icon/text ornament. Untouched legacy blocks fall through
    // to the plain hairline they always had.
    const dvStyleRaw = pickStr(s, "style") ?? "solid";
    const dvStyle = ["solid", "dashed", "dotted", "double", "gradient", "dots", "zigzag", "wave"].includes(dvStyleRaw)
      ? dvStyleRaw
      : "solid";
    const thick = Math.max(1, Math.min(12, pickNum(s, "thickness") ?? 1));
    const widthPct = Math.max(10, Math.min(100, pickNum(s, "width") ?? 100));
    const alignRaw = pickStr(s, "align") ?? "center";
    const alignSelf = alignRaw === "left" ? "flex-start" : alignRaw === "right" ? "flex-end" : "center";
    const dvColor = pickStr(s, "color") ?? colors.border;
    const ornIcon = (pickStr(s, "ornament_icon") ?? "").trim();
    const ornText = (pickStr(s, "ornament_text") ?? "").trim();
    const hasOrn = ornIcon !== "" || ornText !== "";
    const ornColor = pickStr(s, "ornament_color") ?? dvColor;
    const ornSize = Math.max(10, Math.min(40, pickNum(s, "ornament_size") ?? 16));
    // Feather has no FontAwesome catalog — map the common ornament icons
    // to glyphs so web and mobile pages still match visually.
    const ornGlyph = (() => {
      const k = ornIcon.toLowerCase();
      if (k.includes("star")) return "★";
      if (k.includes("heart")) return "♥";
      if (k.includes("circle")) return "●";
      if (k.includes("diamond") || k.includes("gem")) return "◆";
      if (k.includes("bolt")) return "⚡";
      if (k.includes("moon")) return "☾";
      if (k.includes("sun")) return "☀";
      if (k.includes("music")) return "♪";
      if (k.includes("leaf")) return "❧";
      return "✦";
    })();

    const seg = (key: string) => {
      const flex = { flex: 1, minWidth: 0 } as const;
      switch (dvStyle) {
        case "gradient":
          return (
            <LinearGradient
              key={key}
              colors={["transparent", dvColor, "transparent"]}
              start={{ x: 0, y: 0.5 }}
              end={{ x: 1, y: 0.5 }}
              style={[flex, { height: thick }]}
            />
          );
        case "dots": {
          const d = Math.max(4, thick * 3);
          return (
            <View key={key} style={[flex, { flexDirection: "row", justifyContent: "space-between", overflow: "hidden", height: d, alignItems: "center" }]}>
              {Array.from({ length: 40 }).map((_, i) => (
                <View key={i} style={{ width: d, height: d, borderRadius: d / 2, backgroundColor: dvColor, marginRight: d * 2 }} />
              ))}
            </View>
          );
        }
        case "zigzag": {
          const h = Math.max(6, thick * 3);
          const segs: string[] = [`M0 ${h}`];
          for (let x = 0; x < 240; x += h * 2) segs.push(`L${x + h} 0 L${x + h * 2} ${h}`);
          return (
            <View key={key} style={[flex, { height: h }]}>
              <Svg width="100%" height={h} viewBox={`0 0 240 ${h}`} preserveAspectRatio="none">
                <Path d={segs.join(" ")} fill="none" stroke={dvColor} strokeWidth={thick} />
              </Svg>
            </View>
          );
        }
        case "wave": {
          const h = thick + 8;
          const mid = h / 2;
          const parts: string[] = [`M0 ${mid}`, `Q6 0 12 ${mid}`];
          for (let x = 24; x <= 240; x += 12) parts.push(`T${x} ${mid}`);
          return (
            <View key={key} style={[flex, { height: h }]}>
              <Svg width="100%" height={h} viewBox={`0 0 240 ${h}`} preserveAspectRatio="none">
                <Path d={parts.join(" ")} fill="none" stroke={dvColor} strokeWidth={thick} />
              </Svg>
            </View>
          );
        }
        case "double": {
          const bw = Math.max(1, Math.round(thick / 3));
          return (
            <View key={key} style={flex}>
              <View style={{ height: bw, backgroundColor: dvColor }} />
              <View style={{ height: bw, marginTop: bw, backgroundColor: dvColor }} />
            </View>
          );
        }
        case "dashed":
        case "dotted":
          return (
            <View
              key={key}
              style={[flex, { height: 0, borderTopWidth: thick, borderColor: dvColor, borderStyle: dvStyle }]}
            />
          );
        default:
          return <View key={key} style={[flex, { height: thick, backgroundColor: dvColor }]} />;
      }
    };

    return (
      <View style={{ marginVertical: 6, width: `${widthPct}%`, alignSelf }}>
        {hasOrn ? (
          <View style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
            {seg("l")}
            <Text style={{ color: ornColor, fontSize: ornSize, lineHeight: ornSize + 2 }} numberOfLines={1}>
              {ornIcon !== "" ? ornGlyph : ornText.slice(0, 30)}
            </Text>
            {seg("r")}
          </View>
        ) : (
          seg("full")
        )}
      </View>
    );
  }

  if (t === "badge" || t === "alert" || t === "notification" || t === "ticker") {
    const text = pickStr(s, "text", "title", "message");
    if (!text) return null;
    return (
      <View style={[styles.badge, blockCardStyle(block, colors)]}>
        <Text style={[styles.badgeText, { color: blockTextColor(block, colors.foreground) }]}>{text}</Text>
      </View>
    );
  }

  if (t === "cta_button") {
    const url = pickStr(s, "url", "link");
    const label = pickContentStr(s, "text", "label", "title") ?? "Get started";
    if (!url || !isSafeUrl(url)) return null;
    return (
      <Pressable onPress={() => handleTap(url)} style={[styles.btn, { backgroundColor: colors.primary, borderColor: colors.primary }]}>
        <Text style={[styles.btnLabel, { color: "#fff" }]}>{label}</Text>
      </Pressable>
    );
  }

  if (t === "socials" || t === "socials_multi" || t === "socials_custom") {
    const items = Array.isArray(s.items)
      ? (s.items as Record<string, unknown>[])
      : Array.isArray(s.socials)
        ? (s.socials as Record<string, unknown>[])
        : [];
    const links = items
      .map((it) => ({
        label: pickContentStr(it, "label", "name", "platform", "title") ?? "Open",
        url: pickStr(it, "url", "link", "href"),
      }))
      .filter((x): x is { label: string; url: string } => !!x.url && isSafeUrl(x.url));
    if (links.length === 0) return null;
    return (
      <View style={styles.socialsRow}>
        {links.slice(0, 8).map((l, i) => (
          <Pressable
            key={i}
            onPress={() => handleTap(l.url)}
            style={[styles.socialIcon, blockCardStyle(block, colors)]}
          >
            <Feather name="external-link" size={18} color={colors.foreground} />
          </Pressable>
        ))}
      </View>
    );
  }

  if (t === "youtube" || t === "video" || t === "latest_youtube" || t === "vimeo" || t === "twitch") {
    const url =
      pickStr(s, "url", "video_url") ??
      (pickStr(s, "video_id") ? `https://youtube.com/watch?v=${pickStr(s, "video_id")}` : null) ??
      (pickStr(s, "channel") ? `https://youtube.com/${pickStr(s, "channel")!.replace(/^@?\/?/, "@")}` : null);
    const thumb = pickStr(s, "thumbnail", "image");
    const label = pickContentStr(s, "title", "text") ?? "Watch video";
    if (!url || !isSafeUrl(url)) return null;
    return (
      <Pressable onPress={() => handleTap(url)} style={[styles.mediaCard, blockCardStyle(block, colors)]}>
        {thumb ? <Image source={{ uri: thumb }} style={styles.mediaThumb} /> : null}
        <View style={styles.mediaBody}>
          <Feather name="play-circle" size={20} color={colors.primary} />
          <Text style={[styles.mediaLabel, { color: colors.foreground }]} numberOfLines={2}>{label}</Text>
        </View>
      </Pressable>
    );
  }

  if (t === "spotify" || t === "audio" || t === "soundcloud") {
    const url = pickStr(s, "url", "audio_url", "track_url");
    const label = pickContentStr(s, "title", "text") ?? "Listen";
    if (!url || !isSafeUrl(url)) return null;
    // Spotify / SoundCloud have first-party web players that work in an
    // in-app WebView, so keep the user inside the app rather than handing
    // off to the browser. Native audio SDKs aren't available in Expo Go.
    return (
      <Pressable
        onPress={() => {
          trackBiolinkBlockTap(alias, block.id, url);
          openEmbed({ url, title: label });
        }}
        style={[styles.mediaCard, blockCardStyle(block, colors)]}
      >
        <View style={styles.mediaBody}>
          <Feather name="headphones" size={20} color={colors.primary} />
          <Text style={[styles.mediaLabel, { color: colors.foreground }]} numberOfLines={2}>{label}</Text>
          <Feather name="play" size={16} color={colors.mutedForeground} />
        </View>
      </Pressable>
    );
  }

  if (t === "latest_instagram" || t === "instagram_media") {
    const url =
      pickStr(s, "post_url", "url") ??
      (pickStr(s, "handle") ? `https://instagram.com/${pickStr(s, "handle")!.replace(/^@?\/?/, "")}` : null);
    const thumb = pickStr(s, "thumbnail", "image");
    if (!url || !isSafeUrl(url)) return null;
    return (
      <Pressable onPress={() => handleTap(url)} style={[styles.mediaCard, blockCardStyle(block, colors)]}>
        {thumb ? <Image source={{ uri: thumb }} style={[styles.mediaThumb, { aspectRatio: 1 }]} /> : null}
        <View style={styles.mediaBody}>
          <Feather name="instagram" size={20} color={colors.primary} />
          <Text style={[styles.mediaLabel, { color: colors.foreground }]} numberOfLines={2}>
            {pickContentStr(s, "caption") ?? "View on Instagram"}
          </Text>
        </View>
      </Pressable>
    );
  }

  if (t === "tip_jar") {
    const title = pickContentStr(s, "title") ?? "Send me a tip";
    const message = pickStr(s, "message");
    const btnText = pickContentStr(s, "button_text") ?? "Send Tip";
    const rawAmounts = Array.isArray(s.amounts) ? (s.amounts as unknown[]) : [];
    const parsedAmounts = rawAmounts
      .map((n) => (typeof n === "number" ? n : typeof n === "string" ? parseInt(n, 10) : 0))
      .filter((n) => n > 0)
      .slice(0, 6);
    const presets = parsedAmounts.length > 0 ? parsedAmounts : [3, 5, 10, 25];
    const tipUrl = `${publicBiolinkUrl(alias)}/tip-jar`;
    return (
      <View style={[styles.cardContainer, blockCardStyle(block, colors), { padding: 16 }]}>
        <View style={{ flexDirection: "row", alignItems: "center", gap: 8, marginBottom: message ? 4 : 12 }}>
          <Text style={{ fontSize: 18 }}>🫙</Text>
          <Text style={[styles.heading, { color: blockTextColor(block, colors.foreground), marginBottom: 0, fontSize: 16, flex: 1 }]}>
            {title}
          </Text>
        </View>
        {message ? (
          <Text style={[styles.body, { color: blockTextColor(block, colors.foreground) + "99", marginBottom: 12, fontSize: 12 }]}>
            {message}
          </Text>
        ) : null}
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8, marginBottom: 12 }}>
          {presets.map((amt, i) => (
            <Pressable
              key={i}
              onPress={() => {
                trackBiolinkBlockTap(alias, block.id, tipUrl);
                openEmbed({ url: tipUrl, title: btnText });
              }}
              style={[
                styles.socialIcon,
                blockCardStyle(block, colors),
                { paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20, minWidth: 52, alignItems: "center" },
              ]}
            >
              <Text style={[styles.btnLabel, { color: blockTextColor(block, colors.foreground), fontSize: 13 }]}>
                ${amt}
              </Text>
            </Pressable>
          ))}
        </View>
        <Pressable
          onPress={() => {
            trackBiolinkBlockTap(alias, block.id, tipUrl);
            openEmbed({ url: tipUrl, title: btnText });
          }}
          style={[styles.btn, { backgroundColor: "#f59e0b", borderColor: "#f59e0b" }]}
        >
          <Text style={[styles.btnLabel, { color: "#0D0C22" }]}>🫙 {btnText}</Text>
        </Pressable>
        <Text style={{ fontSize: 10, textAlign: "center", marginTop: 6, color: blockTextColor(block, colors.foreground) + "55" }}>
          0% platform fee · goes directly to the creator
        </Text>
      </View>
    );
  }

  if (t === "buy_me_coffee" || t === "patreon" || t === "ko_fi") {
    const username = pickStr(s, "username") ?? "";
    const base =
      t === "buy_me_coffee"
        ? "https://www.buymeacoffee.com/"
        : t === "patreon"
          ? "https://www.patreon.com/"
          : "https://ko-fi.com/";
    const url = username ? `${base}${username.replace(/^@?\/?/, "")}` : "";
    const label = pickContentStr(s, "text") ?? "Support me";
    if (!url || !isSafeUrl(url)) return null;
    const bg =
      t === "buy_me_coffee" ? "#FFDD00" : t === "patreon" ? "#F96854" : "#FF5E5B";
    const fg = t === "buy_me_coffee" ? "#0D0C22" : "#fff";
    return (
      <Pressable onPress={() => handleTap(url)} style={[styles.btn, { backgroundColor: bg, borderColor: bg }]}>
        <Text style={[styles.btnLabel, { color: fg }]}>{label}</Text>
      </Pressable>
    );
  }

  if (t === "featured_pin") {
    const url = pickStr(s, "url");
    const text = pickContentStr(s, "text") ?? "Featured";
    const desc = pickStr(s, "description");
    const accent = pickStr(s, "accent_color") ?? "#f59e0b";
    if (!url || !isSafeUrl(url)) return null;
    return (
      <Pressable onPress={() => handleTap(url)} style={[styles.btn, { backgroundColor: accent, borderColor: accent, alignItems: "flex-start" }]}>
        <Text style={[styles.btnLabel, { color: "#fff", textAlign: "left" }]}>★ {text}</Text>
        {desc ? <Text style={[styles.body, { color: "#ffffffcc", textAlign: "left", fontSize: 12 }]}>{desc}</Text> : null}
      </Pressable>
    );
  }

  if (t === "map" || t === "map_location" || t === "yandex_maps") {
    const addr = pickStr(s, "address");
    const latRaw = s.lat;
    const lngRaw = s.lng;
    const lat = typeof latRaw === "number" ? latRaw : (typeof latRaw === "string" && latRaw.trim() !== "" && !Number.isNaN(parseFloat(latRaw)) ? parseFloat(latRaw) : null);
    const lng = typeof lngRaw === "number" ? lngRaw : (typeof lngRaw === "string" && lngRaw.trim() !== "" && !Number.isNaN(parseFloat(lngRaw)) ? parseFloat(lngRaw) : null);
    const query = lat !== null && lng !== null ? `${lat},${lng}` : addr;
    if (!query) return null;
    const url = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
    return (
      <Pressable onPress={() => handleTap(url)} style={[styles.btn, { backgroundColor: colors.card, borderColor: colors.border, alignItems: "flex-start" }]}>
        <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left" }]}>📍 {pickStr(s, "label") ?? addr ?? query}</Text>
        <Text style={[styles.body, { color: colors.mutedForeground, fontSize: 12, textAlign: "left" }]}>Open in Maps</Text>
      </Pressable>
    );
  }

  if (t === "file") {
    const url = pickStr(s, "url");
    const name = pickContentStr(s, "name", "title") ?? "Download file";
    if (!url || !isSafeUrl(url)) return null;
    return (
      <Pressable onPress={() => handleTap(url)} style={[styles.btn, blockCardStyle(block, colors)]}>
        <Text style={[styles.btnLabel, { color: colors.foreground }]}>⬇ {name}</Text>
      </Pressable>
    );
  }

  if (t === "donation" || t === "paypal" || t === "price" || t === "coupon" || t === "one_time_offer") {
    const url = pickStr(s, "url");
    const label = pickContentStr(s, "title", "text", "code") ?? "View offer";
    if (!url || !isSafeUrl(url)) return null;
    return (
      <Pressable onPress={() => handleTap(url)} style={[styles.btn, { backgroundColor: colors.primary, borderColor: colors.primary }]}>
        <Text style={[styles.btnLabel, { color: "#fff" }]}>{label}</Text>
      </Pressable>
    );
  }

  if (t === "list" || t === "list_numbered") {
    // Render through the shared list view used by the editor preview, so
    // visitors see exactly what the creator previewed: per-item icons
    // (with fallback to the block's default bullet), checklist/timeline
    // treatments, numbered pill/badge/outlined variants, etc.
    const items = normalizeListBlockItems(s.items).slice(0, 30);
    if (visibleListItems(items).length === 0) return null;
    const styleKey = typeof s.style === "string" && s.style ? s.style : "clean";
    const defaultIcon = typeof s.icon === "string" && s.icon ? s.icon : "fas fa-check";
    return (
      <View style={[styles.cardContainer, blockCardStyle(block, colors)]}>
        <ListBlockView
          kind={t === "list_numbered" ? "numbered" : "list"}
          styleKey={styleKey}
          defaultIcon={defaultIcon}
          items={items}
          colors={colors}
        />
      </View>
    );
  }

  if (t === "list_pricing") {
    // Pricing rows used to fall through to the generic "Open on web"
    // fallback. Render them inline through the shared view so visitors
    // get the same presentation as the editor preview and the public
    // web page (style variants: classic / menu / cards / comparison /
    // featured), including picked icons and included/featured flags.
    const items = normalizePricingBlockItems(s.items).slice(0, 30);
    if (visiblePricingItems(items).length === 0) return null;
    const styleKey = typeof s.style === "string" && s.style ? s.style : "classic";
    return (
      <View style={[styles.cardContainer, blockCardStyle(block, colors)]}>
        <PricingBlockView styleKey={styleKey} items={items} colors={colors} />
      </View>
    );
  }

  if (t === "image_grid" || t === "image_slider" || t === "gallery") {
    const items = Array.isArray(s.items)
      ? (s.items as Record<string, unknown>[])
      : Array.isArray(s.images)
        ? (s.images as Record<string, unknown>[])
        : [];
    const urls = items
      .map((it) => pickStr(it, "url", "src", "image", "image_url"))
      .filter((u): u is string => !!u);
    if (urls.length === 0) return null;
    return (
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ width: "100%" }} contentContainerStyle={{ gap: 8 }}>
        {urls.slice(0, 16).map((u, i) => (
          <Image key={i} source={{ uri: u }} style={[styles.image, { width: 220, aspectRatio: 1 }]} />
        ))}
      </ScrollView>
    );
  }

  if (t === "faq" || t === "faq_v2") {
    const items = Array.isArray(s.items) ? (s.items as Record<string, unknown>[]) : [];
    if (items.length === 0) return null;
    return (
      <View style={[styles.cardContainer, blockCardStyle(block, colors)]}>
        {items.slice(0, 12).map((it, i) => {
          const q = pickStr(it, "question", "q", "title");
          const a = pickStr(it, "answer", "a", "text");
          if (!q) return null;
          return (
            <View key={i} style={{ marginBottom: 8 }}>
              <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left", marginBottom: 4 }]}>{q}</Text>
              {a ? <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 13 }]}>{a}</Text> : null}
            </View>
          );
        })}
      </View>
    );
  }

  if (t === "countdown") {
    return <CountdownBlock settings={s as Record<string, unknown>} colors={colors} router={router} />;
  }

  if (t === "product" || t === "service") {
    // Native-checkout products (in-page storefront) get the full Buy Now /
    // Add to Cart UI; everything else falls back to the simple link card.
    const nativeCheckout = !!(s as Record<string, unknown>).native_checkout;
    const priceCents = pickNum(s, "price_cents") ?? 0;
    if (t === "product" && nativeCheckout && priceCents > 0) {
      return <NativeProductBlock block={block} alias={alias} colors={colors} />;
    }
    const title = pickStr(s, "title", "name") ?? (t === "product" ? "Product" : "Service");
    const desc = pickStr(s, "description", "subtitle");
    const price = pickStr(s, "price");
    const url = pickStr(s, "url", "buy_url", "link");
    const thumb = pickStr(s, "image", "thumbnail");
    const inner = (
      <View style={[styles.cardContainer, blockCardStyle(block, colors)]}>
        {thumb ? <Image source={{ uri: thumb }} style={[styles.image, { aspectRatio: 16 / 9, marginBottom: 8 }]} /> : null}
        <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left" }]}>{title}</Text>
        {desc ? <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 13 }]}>{desc}</Text> : null}
        {price ? <Text style={[styles.btnLabel, { color: colors.primary, textAlign: "left", marginTop: 4 }]}>{price}</Text> : null}
      </View>
    );
    if (url && isSafeUrl(url)) {
      return <Pressable onPress={() => handleTap(url)}>{inner}</Pressable>;
    }
    return inner;
  }

  if (t === "email_collector" || t === "email_subscribe" || t === "phone_collector") {
    const title = pickStr(s, "title", "heading") ?? (t === "phone_collector" ? "Send me your number" : "Subscribe");
    return (
      <Pressable
        onPress={() => handleTap(publicBiolinkUrl(alias))}
        style={[styles.cardContainer, blockCardStyle(block, colors)]}
      >
        <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left" }]}>{title}</Text>
        <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 12, marginTop: 4 }]}>
          Tap to fill out the form on the web
        </Text>
      </Pressable>
    );
  }

  // Typeform: open the form directly in an in-app WebView so the user can
  // fill it out without leaving the app.
  if (t === "typeform") {
    const url = pickStr(s, "url", "form_url");
    const label = pickContentStr(s, "title", "heading", "text") ?? "Open form";
    if (!url || !isSafeUrl(url)) return null;
    return (
      <Pressable
        onPress={() => {
          trackBiolinkBlockTap(alias, block.id, url);
          openEmbed({ url, title: label });
        }}
        style={[styles.cardContainer, blockCardStyle(block, colors)]}
      >
        <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left" }]}>📝 {label}</Text>
        <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 12, marginTop: 4 }]}>
          Tap to fill out in-app
        </Text>
      </Pressable>
    );
  }

  // Native poll: render the question and tappable options. Selecting an
  // option submits the vote directly to the API and shows an inline
  // "Thanks!" confirmation — no WebView bounce.
  if (t === "poll") {
    return (
      <PollBlock block={block} alias={alias} settings={s} colors={colors} />
    );
  }

  // Native RSVP: render an inline form (Yes/Maybe/No + name/email) that
  // posts directly to the API and shows a "Thanks!" confirmation, instead
  // of bouncing the user out to the WebView event form.
  if (t === "rsvp") {
    return (
      <RsvpBlock block={block} alias={alias} settings={s} colors={colors} />
    );
  }

  // Reviews wall: render the rating summary, the unified review feed, and an
  // inline no-login submission form natively instead of bouncing to the web.
  if (t === "reviews_wall") {
    const title = pickStr(s, "title", "heading");
    return (
      <View style={[styles.cardContainer, blockCardStyle(block, colors)]}>
        <ReviewsWall alias={alias} colors={colors} title={title} />
      </View>
    );
  }

  if (t === "contact_form" || t === "form" || t === "quiz" || t === "review") {
    const title = pickStr(s, "title", "heading") ?? (t === "quiz" ? "Take quiz" : "Open form");
    // For `form` blocks the API resolves the priced public form URL so we can
    // open the form itself (price tags, live order total and the per-field
    // payment flow all render inside the in-app WebView) rather than bouncing
    // to the whole biolink page. Other form-like blocks open the biolink page.
    const fm = block.form ?? null;
    const url = t === "form" && fm?.public_url ? fm.public_url : publicBiolinkUrl(alias);
    const pay = fm?.payment ?? null;
    const priceHint =
      fm?.is_paid && pay
        ? pay.mode === "per_field"
          ? pay.amount_cents > 0
            ? `Paid form · from ${fmtMoney(pay.amount_cents, pay.currency)}`
            : "Paid form · priced by your answers"
          : `Paid form · ${fmtMoney(pay.amount_cents, pay.currency)}`
        : null;
    return (
      <Pressable
        onPress={() => {
          trackBiolinkBlockTap(alias, block.id, url);
          openEmbed({ url, title });
        }}
        style={[styles.cardContainer, blockCardStyle(block, colors)]}
      >
        <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left" }]}>{title}</Text>
        <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 12, marginTop: 4 }]}>
          {priceHint ?? "Tap to open in-app"}
        </Text>
      </Pressable>
    );
  }

  if (t === "whatsapp_widget" || t === "whatsapp_number_subscribe" || t === "whatsapp_channel_subscribe" || t === "whatsapp_item") {
    const phone = pickStr(s, "phone", "number");
    const channel = pickStr(s, "channel", "url");
    const url = phone
      ? `https://wa.me/${phone.replace(/[^0-9]/g, "")}`
      : channel ?? null;
    if (!url || !isSafeUrl(url)) return null;
    const label = pickContentStr(s, "button_text", "text", "title", "name") ?? "Chat on WhatsApp";
    return (
      <Pressable onPress={() => handleTap(url)} style={[styles.btn, { backgroundColor: "#25D366", borderColor: "#25D366" }]}>
        <Text style={[styles.btnLabel, { color: "#fff" }]}>💬 {label}</Text>
      </Pressable>
    );
  }

  if (t === "calendly" || t === "calendly_embed") {
    const url = pickStr(s, "url");
    const label = pickContentStr(s, "text", "title") ?? "Book a time";
    if (!url || !isSafeUrl(url)) return null;
    // Calendly's standalone scheduling page works fine inside an in-app
    // WebView; keep the booking flow inside the app.
    return (
      <Pressable
        onPress={() => {
          trackBiolinkBlockTap(alias, block.id, url);
          openEmbed({ url, title: label });
        }}
        style={[styles.btn, blockCardStyle(block, colors)]}
      >
        <Text style={[styles.btnLabel, { color: colors.foreground }]}>📅 {label}</Text>
      </Pressable>
    );
  }

  // Custom HTML / iframe / generic embed blocks: render a clearly labelled
  // card and open the embedded URL in a sandboxed in-app WebView so the
  // viewer never hands off to the system browser for these.
  if (
    t === "custom_html" ||
    t === "html" ||
    t === "iframe" ||
    t === "iframe_embed" ||
    t === "embed" ||
    t === "widget"
  ) {
    const url =
      pickStr(s, "url", "src", "iframe_url", "embed_url") ??
      // Try to extract src="..." from a raw HTML/iframe snippet.
      (() => {
        const html = pickStr(s, "html", "code", "content");
        if (!html) return null;
        const m = html.match(/src=["']([^"']+)["']/i);
        return m ? m[1] : null;
      })();
    const label = pickContentStr(s, "title", "text", "label") ?? "Open embed";
    if (!url || !isSafeUrl(url)) {
      // Without a safe URL we can't render anything trustworthy — show a
      // disabled-style notice instead of a tappable button.
      return (
        <View style={[styles.cardContainer, blockCardStyle(block, colors)]}>
          <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left" }]}>{label}</Text>
          <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 12 }]}>
            This embed isn&apos;t available on mobile.
          </Text>
        </View>
      );
    }
    return (
      <Pressable
        onPress={() => {
          trackBiolinkBlockTap(alias, block.id, url);
          openEmbed({ url, title: label, sandboxed: true });
        }}
        style={[styles.cardContainer, blockCardStyle(block, colors)]}
      >
        <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left" }]}>🔗 {label}</Text>
        <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 12, marginTop: 4 }]}>
          Third-party embed: tap to open in-app
        </Text>
      </Pressable>
    );
  }

  if (t === "qr_code") {
    const url = pickStr(s, "url");
    if (!url) return null;
    return (
      <View style={[styles.badge, blockCardStyle(block, colors)]}>
        <Text style={[styles.badgeText, { color: blockTextColor(block, colors.foreground) }]}>QR: {url}</Text>
      </View>
    );
  }

  // Link Group (link_tree_group) — Task #6576. Mirrors the web renderer's
  // three layouts (list, grid, text_divider) with per-item tap tracking.
  // Layout can come from a curated variant (stamped into the opaque
  // `_style._ltg_layout` hook) or the block's own `layout` setting.
  if (t === "link_tree_group") {
    const items = (Array.isArray((s as Record<string, unknown>).items)
      ? ((s as Record<string, unknown>).items as unknown[])
      : []
    ).filter((it): it is Record<string, unknown> => !!it && typeof it === "object");
    const ltgStyle = ((s as Record<string, unknown>)._style &&
    typeof (s as Record<string, unknown>)._style === "object"
      ? (s as Record<string, unknown>)._style
      : {}) as Record<string, unknown>;
    const rawLayout =
      (typeof ltgStyle._ltg_layout === "string" && ltgStyle._ltg_layout) ||
      pickStr(s, "layout") ||
      "list";
    const layout = ["list", "grid", "text_divider"].includes(rawLayout) ? rawLayout : "list";
    const rawAlign =
      (typeof ltgStyle._ltg_align === "string" && ltgStyle._ltg_align) ||
      pickStr(s, "align") ||
      "left";
    const align = (["left", "center", "right"].includes(rawAlign) ? rawAlign : "left") as
      | "left"
      | "center"
      | "right";
    const title = pickContentStr(s, "title");
    const txtColor = blockTextColor(block, colors.foreground);
    const itemLabel = (it: Record<string, unknown>) =>
      typeof it.text === "string" && it.text.trim() !== "" ? it.text : "Link";
    const itemUrl = (it: Record<string, unknown>) =>
      typeof it.url === "string" ? it.url : "";
    const tapItem = (it: Record<string, unknown>) => {
      const url = itemUrl(it);
      if (!url) return;
      // Per-item attribution: pass the stable item id so the tap row can be
      // told apart from siblings even when they share a destination URL.
      const itemId = typeof it.id === "string" && it.id !== "" ? it.id : undefined;
      trackBiolinkBlockTap(alias, block.id, url, itemId);
      openSafe(url, router);
    };

    if (layout === "text_divider") {
      return (
        <View style={{ width: "100%" }}>
          {title ? (
            <Text
              style={[
                styles.btnLabel,
                { color: txtColor, textAlign: align, marginBottom: 6 },
              ]}
            >
              {title}
            </Text>
          ) : null}
          {items.map((it, i) => (
            <Pressable
              key={typeof it.id === "string" && it.id ? it.id : String(i)}
              onPress={() => tapItem(it)}
              style={{
                width: "100%",
                paddingVertical: 14,
                borderBottomWidth: StyleSheet.hairlineWidth,
                borderBottomColor: `${txtColor}40`,
              }}
            >
              <Text
                style={{
                  color: txtColor,
                  fontSize: 15,
                  fontWeight: "500",
                  textAlign: align,
                }}
              >
                {itemLabel(it)}
              </Text>
            </Pressable>
          ))}
        </View>
      );
    }

    if (layout === "grid") {
      return (
        <View style={{ width: "100%" }}>
          {title ? (
            <Text style={[styles.btnLabel, { color: txtColor, marginBottom: 6 }]}>{title}</Text>
          ) : null}
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
            {items.map((it, i) => (
              <Pressable
                key={typeof it.id === "string" && it.id ? it.id : String(i)}
                onPress={() => tapItem(it)}
                style={[
                  {
                    flexBasis: "48%",
                    flexGrow: 1,
                    borderRadius: 12,
                    paddingVertical: 12,
                    paddingHorizontal: 10,
                    borderWidth: StyleSheet.hairlineWidth,
                    borderColor: `${txtColor}30`,
                    backgroundColor: `${txtColor}10`,
                  },
                ]}
              >
                <Text
                  numberOfLines={1}
                  style={{ color: txtColor, fontSize: 14, fontWeight: "500", textAlign: "center" }}
                >
                  {itemLabel(it)}
                </Text>
              </Pressable>
            ))}
          </View>
        </View>
      );
    }

    return (
      <View style={{ width: "100%" }}>
        {title ? (
          <Text style={[styles.btnLabel, { color: txtColor, marginBottom: 6 }]}>{title}</Text>
        ) : null}
        {items.map((it, i) => (
          <Pressable
            key={typeof it.id === "string" && it.id ? it.id : String(i)}
            onPress={() => tapItem(it)}
            style={{
              width: "100%",
              borderRadius: 12,
              paddingVertical: 12,
              paddingHorizontal: 14,
              marginBottom: 8,
              borderWidth: StyleSheet.hairlineWidth,
              borderColor: `${txtColor}30`,
              backgroundColor: `${txtColor}10`,
            }}
          >
            <Text numberOfLines={1} style={{ color: txtColor, fontSize: 14, fontWeight: "500" }}>
              {itemLabel(it)}
            </Text>
            {typeof it.description === "string" && it.description.trim() !== "" ? (
              <Text
                numberOfLines={1}
                style={{ color: txtColor, opacity: 0.6, fontSize: 12, marginTop: 2 }}
              >
                {it.description}
              </Text>
            ) : null}
          </Pressable>
        ))}
      </View>
    );
  }

  // Event list (Task #6615 — Smart Calendar biolink block). Renders the
  // block's events natively — for calendar-sourced blocks the API has
  // already resolved live upcoming events plus calendar/subscribe URLs
  // (Api\BiolinkController::decorateEventListBlock); manual blocks carry
  // their hand-entered events array. Without this branch the block would
  // silently degrade to the generic "Open on web" fallback.
  if (t === "event_list") {
    const rawEvents = Array.isArray(s?.events) ? (s!.events as unknown[]) : (Array.isArray(s?.items) ? (s!.items as unknown[]) : []);
    const events = rawEvents
      .filter((e): e is Record<string, unknown> => !!e && typeof e === "object")
      .slice(0, 20);
    const accent = pickStr(s, "accent_color") ?? "#3d6bff";
    const evTitle = pickContentStr(s, "title") ?? pickStr(s, "calendar_title");
    const calendarUrl = pickStr(s, "calendar_url");
    const subscribeUrl = pickStr(s, "subscribe_url");
    const showSubscribe = pickBool(s ?? {}, "show_subscribe", true) && !!calendarUrl;
    const fmtDate = (v: unknown): string | null => {
      if (typeof v !== "string" || !v) return null;
      const d = new Date(v);
      if (isNaN(d.getTime())) return v;
      return d.toLocaleDateString(undefined, { weekday: "short", month: "short", day: "numeric" })
        + (v.length > 10 ? ` · ${d.toLocaleTimeString(undefined, { hour: "numeric", minute: "2-digit" })}` : "");
    };
    return (
      <View style={[styles.cardContainer, blockCardStyle(block, colors)]}>
        {evTitle ? (
          <Text style={[styles.heading, { color: colors.foreground, fontSize: 15, textAlign: "left", marginBottom: 6 }]}>{evTitle}</Text>
        ) : null}
        {events.length === 0 ? (
          <Text style={[styles.body, { color: colors.mutedForeground, fontSize: 12, textAlign: "center", paddingVertical: 10 }]}>
            {calendarUrl ? "No upcoming events" : "No events yet"}
          </Text>
        ) : (
          events.map((ev, i) => {
            const evUrl = pickStr(ev, "url", "link");
            const dateLabel = fmtDate(ev.date ?? ev.start_at ?? ev.starts_at);
            const location = pickStr(ev, "location");
            const inner = (
              <View style={{ flexDirection: "row", gap: 10, paddingVertical: 7, borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth, borderTopColor: colors.border }}>
                <View style={{ width: 3, borderRadius: 2, backgroundColor: accent }} />
                <View style={{ flex: 1 }}>
                  <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left", fontSize: 13 }]} numberOfLines={2}>
                    {pickStr(ev, "title", "name") ?? "Event"}
                  </Text>
                  {dateLabel ? (
                    <Text style={[styles.body, { color: accent, textAlign: "left", fontSize: 11, marginTop: 1 }]}>{dateLabel}</Text>
                  ) : null}
                  {location ? (
                    <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 11, marginTop: 1 }]} numberOfLines={1}>{location}</Text>
                  ) : null}
                </View>
              </View>
            );
            return evUrl && isSafeUrl(evUrl) ? (
              <Pressable key={i} onPress={() => handleTap(evUrl)}>{inner}</Pressable>
            ) : (
              <View key={i}>{inner}</View>
            );
          })
        )}
        {showSubscribe ? (
          <View style={{ flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginTop: 10, paddingTop: 10, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.border }}>
            <Pressable onPress={() => handleTap(calendarUrl!)}>
              <Text style={[styles.body, { color: accent, fontSize: 12, fontWeight: "600" }]}>View full calendar</Text>
            </Pressable>
            {subscribeUrl ? (
              <Pressable
                onPress={() => handleTap(subscribeUrl)}
                style={{ backgroundColor: `${accent}22`, borderRadius: 999, paddingHorizontal: 12, paddingVertical: 6 }}
              >
                <Text style={[styles.body, { color: accent, fontSize: 12, fontWeight: "600" }]}>Subscribe</Text>
              </Pressable>
            ) : null}
          </View>
        ) : null}
      </View>
    );
  }

  // Profile / identity card family (profile_card_v1..v4). Dispatches on the
  // `_profile_layout` token carried in _style (set when a curated
  // `profile_identity` design is applied), falling back to the historical
  // per-type layout for older blocks.
  if (canonicalBlockType(t) === "profile_card") {
    return (
      <ProfileCardView
        block={block}
        s={s as Record<string, unknown>}
        colors={colors}
        cardOverlay={cardOverlay}
        onTap={handleTap}
      />
    );
  }

  // Generic URL fallback: many block types share a settings.url.
  const fallbackUrl = pickStr(s, "url", "link", "href");
  if (fallbackUrl && isSafeUrl(fallbackUrl)) {
    return (
      <Pressable
        onPress={() => handleTap(fallbackUrl)}
        style={[styles.btn, blockCardStyle(block, colors)]}
      >
        <Text style={[styles.btnLabel, { color: colors.foreground }]}>
          {pickStr(s, "title", "label", "text") ?? fallbackUrl}
        </Text>
      </Pressable>
    );
  }

  // Unhandled block type — show a generic "Open on web" card so the user can
  // still reach this block's content from the public biolink page rather
  // than seeing nothing at all.
  const webUrl = publicBiolinkUrl(alias);
  return (
    <Pressable
      onPress={() => handleTap(webUrl)}
      style={[styles.btn, blockCardStyle(block, colors)]}
    >
      <Text style={[styles.btnLabel, { color: colors.foreground }]}>
        {pickContentStr(s, "title", "text", "label") ?? "Open on web"}
      </Text>
      <Text style={[styles.body, { color: colors.mutedForeground, fontSize: 11, marginTop: 2 }]}>
        Tap to view this block in your browser
      </Text>
    </Pressable>
  );
}

// ───────────────────────────────────────────────────────────────────
// Profile / identity card renderer — the mobile counterpart of
// common/biolink-profile-card.blade.php. Ten layouts dispatched on the
// `_profile_layout` token (with a per-type fallback for older blocks).
// ───────────────────────────────────────────────────────────────────

type ProfileSocialRow = { name: string; url: string };

// Map a social platform slug to a Feather brand icon, falling back to the
// generic "link" glyph for platforms Feather doesn't ship (tiktok, twitch,
// etc.). The web uses FontAwesome's far richer brand set; mobile only has
// Feather, so unmapped platforms still render a recognisable chip.
function profileSocialIcon(name: string): React.ComponentProps<typeof Feather>["name"] {
  switch (name.trim().toLowerCase()) {
    case "instagram":
      return "instagram";
    case "twitter":
    case "x":
      return "twitter";
    case "facebook":
      return "facebook";
    case "youtube":
      return "youtube";
    case "linkedin":
      return "linkedin";
    case "github":
      return "github";
    default:
      return "link";
  }
}

function normalizeProfileSocialRows(raw: unknown): ProfileSocialRow[] {
  if (!Array.isArray(raw)) return [];
  return raw
    .map((i) => {
      const o = (i && typeof i === "object" ? i : {}) as Record<string, unknown>;
      const name =
        typeof o.name === "string"
          ? o.name
          : typeof o.platform === "string"
            ? o.platform
            : "";
      const url = typeof o.url === "string" ? o.url : "";
      return { name, url };
    })
    .filter((s) => s.name !== "" || s.url !== "");
}

function profileLayout(block: BiolinkBlock, s: Record<string, unknown>): string {
  const style = (s._style && typeof s._style === "object" ? s._style : {}) as Record<
    string,
    unknown
  >;
  const tok = typeof style._profile_layout === "string" ? style._profile_layout : "";
  if (tok) return tok;
  switch (block.type) {
    case "profile_card_v2":
      return "cover_hero";
    case "profile_card_v3":
      return "stats";
    case "profile_card_v4":
      return "badges";
    default:
      return "classic_creator";
  }
}

function profileAccent(layout: string): string {
  switch (layout) {
    case "founder":
      return "#d4af37";
    case "social_profile":
      return "#3b82f6";
    case "gradient":
      return "#ffffff";
    case "glass":
      return "#c4b5fd";
    case "paper_collage":
      return "#5f6f52";
    case "minimal_dark":
    case "cover_hero":
      return "#7d9bff";
    default:
      return "#3d6bff";
  }
}

const PROFILE_AVATAR_BG = "rgba(61,107,255,0.20)";

function ProfileAvatar({
  avatar,
  initial,
  size,
  border,
  textColor,
  frame,
}: {
  avatar: string;
  initial: string;
  size: number;
  border?: { borderWidth?: number; borderColor?: string; borderRadius?: number };
  textColor?: string;
  // Decorative frame (Task #5910) rendered behind the avatar. Only applied
  // to circular avatars — call sites that override borderRadius (square /
  // rounded-rect looks) skip the frame automatically, mirroring the web
  // renderer which only wraps rounded-full avatars.
  frame?: { shape: AvatarFrameKey; color: string } | null;
}) {
  const core =
    avatar && isSafeUrl(avatar) ? (
      <Image
        source={{ uri: avatar }}
        style={[{ width: size, height: size, borderRadius: size / 2 }, border]}
      />
    ) : (
      <View
        style={[
          {
            width: size,
            height: size,
            borderRadius: size / 2,
            alignItems: "center",
            justifyContent: "center",
            backgroundColor: PROFILE_AVATAR_BG,
          },
          border,
        ]}
      >
        <Text style={{ fontSize: size * 0.4, fontWeight: "700", color: textColor ?? "#fff" }}>
          {initial}
        </Text>
      </View>
    );

  const circular = !border || border.borderRadius === undefined;
  if (!frame || !circular) return core;

  // Frame sits at ~1.36x the avatar (matches the web wrapper's -18% inset).
  const frameSize = size * 1.36;
  return (
    <View style={{ width: size, height: size, alignItems: "center", justifyContent: "center" }}>
      <View
        pointerEvents="none"
        style={{
          position: "absolute",
          left: (size - frameSize) / 2,
          top: (size - frameSize) / 2,
          width: frameSize,
          height: frameSize,
        }}
      >
        <AvatarFrame shape={frame.shape} color={frame.color} size={frameSize} />
      </View>
      {core}
    </View>
  );
}

function ProfileSocialsRow({
  socials,
  accent,
  onTap,
  chip = "glass",
}: {
  socials: ProfileSocialRow[];
  accent: string;
  onTap: (url: string) => void;
  chip?: "glass" | "accent_outline";
}) {
  if (socials.length === 0) return null;
  return (
    <View
      style={{
        flexDirection: "row",
        flexWrap: "wrap",
        justifyContent: "center",
        gap: 10,
        marginTop: 16,
      }}
    >
      {socials.map((soc, i) => {
        const chipStyle: ViewStyle =
          chip === "accent_outline"
            ? { borderWidth: 1.5, borderColor: `${accent}66` }
            : {
                backgroundColor: "rgba(255,255,255,0.10)",
                borderWidth: 1,
                borderColor: "rgba(255,255,255,0.20)",
              };
        return (
          <Pressable
            key={i}
            onPress={() => (soc.url && isSafeUrl(soc.url) ? onTap(soc.url) : undefined)}
            style={[
              {
                width: 36,
                height: 36,
                borderRadius: 18,
                alignItems: "center",
                justifyContent: "center",
              },
              chipStyle,
            ]}
          >
            <Feather name={profileSocialIcon(soc.name)} size={16} color={accent} />
          </Pressable>
        );
      })}
    </View>
  );
}

function ProfileCardView({
  block,
  s,
  colors,
  cardOverlay,
  onTap,
}: {
  block: BiolinkBlock;
  s: Record<string, unknown>;
  colors: ReturnType<typeof useColors>;
  cardOverlay: ViewStyle | null;
  onTap: (url: string) => void;
}) {
  const avatar = pickStr(s, "avatar") ?? "";
  const name = (pickStr(s, "name") ?? "").trim();
  const title = (pickStr(s, "title") ?? "").trim();
  const bio = (pickStr(s, "bio") ?? "").trim();
  const cover = pickStr(s, "cover") ?? "";
  const verified = pickBool(s, "verified");
  const location = (pickStr(s, "location") ?? "").trim();
  const website = (pickStr(s, "website") ?? "").trim();
  const ctaLabel = (pickStr(s, "cta_label") ?? "").trim();
  const ctaUrl = (pickStr(s, "cta_url") ?? "").trim();
  const socials = normalizeProfileSocialRows(s.socials);
  const stats = Array.isArray(s.stats) ? (s.stats as Record<string, unknown>[]) : [];
  const badges = Array.isArray(s.badges) ? (s.badges as unknown[]) : [];

  const layout = profileLayout(block, s);
  const accent = profileAccent(layout);

  // Decorative avatar frame (Task #5910) — key + optional tint live in
  // _style, mirroring the web renderer. Unknown keys render no frame.
  const pcStyle = (s._style && typeof s._style === "object" ? s._style : {}) as Record<
    string,
    unknown
  >;
  const pcFrameColor =
    typeof pcStyle._avatar_frame_color === "string" && pcStyle._avatar_frame_color !== ""
      ? pcStyle._avatar_frame_color
      : accent;
  const pcFrame = isAvatarFrameKey(pcStyle._avatar_frame)
    ? { shape: pcStyle._avatar_frame, color: pcFrameColor }
    : null;
  const initial = (name !== "" ? name : "U").charAt(0).toUpperCase();
  const hasCover = cover !== "" && isSafeUrl(cover);

  // Cover-image effects (Task #6585) — blur via the native Image
  // blurRadius prop, tint via an absolute overlay layered over the cover
  // and UNDER arch/avatar/text (mirrors the web renderer's blur+tint
  // layers). Unset keys = 0/empty so existing pages keep today's look.
  const cvBlurRaw = Number(pcStyle._cover_blur);
  const coverBlur = Number.isFinite(cvBlurRaw) ? Math.max(0, Math.min(40, cvBlurRaw)) : 0;
  const cvOpRaw = Number(pcStyle._cover_overlay_opacity);
  const cvOp = Number.isFinite(cvOpRaw) ? Math.max(0, Math.min(100, cvOpRaw)) : 0;
  const cvColor =
    typeof pcStyle._cover_overlay_color === "string" ? pcStyle._cover_overlay_color : "";
  const hasCoverTint = cvColor !== "" && cvOp > 0;
  const coverTintView = hasCoverTint ? (
    <View
      pointerEvents="none"
      style={[StyleSheet.absoluteFillObject, { backgroundColor: cvColor, opacity: cvOp / 100 }]}
    />
  ) : null;

  // Outer card surface. The design's bg/border/radius arrive via cardOverlay;
  // with no design we keep the page's translucent card look.
  const surface: ViewStyle = {
    borderRadius: 18,
    overflow: "hidden",
    marginBottom: 16,
    backgroundColor: colors.card,
    ...(cardOverlay ?? {}),
  };
  const themeText = colors.foreground;

  // ───────────── CLASSIC CREATOR ─────────────
  if (layout === "classic_creator") {
    return (
      <View style={surface}>
        {hasCover ? (
          <View style={{ position: "relative" }}>
            <Image source={{ uri: cover }} blurRadius={coverBlur} style={{ height: 112, width: "100%" }} />
            {coverTintView}
          </View>
        ) : null}
        <View
          style={{
            paddingHorizontal: 20,
            paddingBottom: 24,
            alignItems: "center",
            marginTop: hasCover ? -48 : 0,
            paddingTop: hasCover ? 0 : 24,
          }}
        >
          <ProfileAvatar frame={pcFrame}
            avatar={avatar}
            initial={initial}
            size={96}
            border={{ borderWidth: 4, borderColor: "#fff" }}
          />
          {name ? (
            <Text style={{ marginTop: 12, fontSize: 18, fontWeight: "700", color: themeText }}>
              {name}
            </Text>
          ) : null}
          {title ? (
            <Text style={{ fontSize: 13, fontWeight: "600", color: accent }}>{title}</Text>
          ) : null}
          {bio ? (
            <Text
              style={{ fontSize: 13, marginTop: 12, color: themeText, opacity: 0.72, textAlign: "center" }}
            >
              {bio}
            </Text>
          ) : null}
        </View>
      </View>
    );
  }

  // ───────────── MODERN GLASSMORPHISM ─────────────
  if (layout === "glass") {
    return (
      <View style={surface}>
        {hasCover ? (
          <Image
            source={{ uri: cover }}
            blurRadius={coverBlur}
            style={{ ...StyleSheet.absoluteFillObject, opacity: 0.3 }}
          />
        ) : null}
        {/* Translucent tint over a cover; an opaque brand gradient when there's
            no cover, so the white glass text stays legible on any page theme
            (mirrors the floating/social_profile fallback). A user cover
            overlay (Task #6585) overrides the built-in wash. */}
        {hasCover && hasCoverTint ? (
          coverTintView
        ) : (
          <LinearGradient
            colors={
              hasCover
                ? ["rgba(61,107,255,0.40)", "rgba(236,72,153,0.28)"]
                : ["#3d6bff", "#d76dff"]
            }
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={StyleSheet.absoluteFillObject}
          />
        )}
        <View style={{ paddingHorizontal: 20, paddingVertical: 28, alignItems: "center" }}>
          <ProfileAvatar frame={pcFrame}
            avatar={avatar}
            initial={initial}
            size={80}
            border={{ borderWidth: 3, borderColor: "rgba(255,255,255,0.55)" }}
          />
          {name ? (
            <Text style={{ marginTop: 12, fontSize: 18, fontWeight: "700", color: "#fff" }}>
              {name}
            </Text>
          ) : null}
          {title ? <Text style={{ fontSize: 13, color: accent }}>{title}</Text> : null}
          {bio ? (
            <Text style={{ fontSize: 13, marginTop: 12, color: "rgba(255,255,255,0.8)", textAlign: "center" }}>
              {bio}
            </Text>
          ) : null}
          <ProfileSocialsRow socials={socials} accent="#ffffff" onTap={onTap} />
        </View>
      </View>
    );
  }

  // ───────────── COVER OVERLAY HERO ─────────────
  if (layout === "cover_hero") {
    const inner = (
      <View style={{ minHeight: 300, justifyContent: "flex-end" }}>
        {hasCover ? coverTintView : null}
        <LinearGradient
          colors={["rgba(0,0,0,0.15)", "rgba(0,0,0,0.88)"]}
          style={StyleSheet.absoluteFillObject}
        />
        <View style={{ padding: 20 }}>
          <View style={{ flexDirection: "row", alignItems: "flex-end", gap: 12 }}>
            <ProfileAvatar frame={pcFrame}
              avatar={avatar}
              initial={initial}
              size={64}
              border={{ borderWidth: 3, borderColor: "rgba(255,255,255,0.85)" }}
            />
            <View style={{ flex: 1 }}>
              {name ? (
                <Text style={{ fontSize: 18, fontWeight: "700", color: "#fff" }}>{name}</Text>
              ) : null}
              {title ? <Text style={{ fontSize: 13, color: accent }}>{title}</Text> : null}
            </View>
          </View>
          {bio ? (
            <Text style={{ fontSize: 13, marginTop: 12, color: "rgba(255,255,255,0.8)" }}>
              {bio}
            </Text>
          ) : null}
        </View>
      </View>
    );
    return (
      <View style={surface}>
        {hasCover ? (
          <ImageBackground source={{ uri: cover }} blurRadius={coverBlur} style={{ width: "100%" }}>
            {inner}
          </ImageBackground>
        ) : (
          <View style={{ backgroundColor: "#0b0b0f" }}>{inner}</View>
        )}
      </View>
    );
  }

  // ───────────── SPLIT HERO ─────────────
  // Task #5885 (parity backfill): photo-first column — a large ringed
  // circular avatar with the social-icon row beneath it, nothing else.
  // Name/title live in sibling blocks; transparent surface so the page
  // background shows through (mirrors the web blade branch).
  if (layout === "split_hero") {
    return (
      <View style={{ marginBottom: 16, alignItems: "center", paddingVertical: 16 }}>
        <ProfileAvatar frame={pcFrame}
          avatar={avatar}
          initial={initial}
          size={192}
          border={{ borderWidth: 3, borderColor: "rgba(255,255,255,0.35)" }}
        />
        <ProfileSocialsRow socials={socials} accent="#ffffff" onTap={onTap} />
      </View>
    );
  }

  // ───────────── PORTRAIT POSTER ─────────────
  // Task #5906: full-bleed portrait cover filling the card, a large ringed
  // circular avatar centered on the photo, and name + thin divider +
  // letter-spaced uppercase title over a bottom dark gradient. Gradient
  // background when there's no cover (mirrors the web blade branch).
  if (layout === "portrait_poster") {
    const inner = (
      <View style={{ minHeight: 420, alignItems: "center" }}>
        {hasCover ? coverTintView : null}
        <LinearGradient
          colors={["rgba(0,0,0,0.05)", "rgba(0,0,0,0.35)", "rgba(0,0,0,0.82)"]}
          locations={[0.4, 0.66, 1]}
          style={StyleSheet.absoluteFillObject}
        />
        <View style={{ marginTop: 88 }}>
          <ProfileAvatar frame={pcFrame}
            avatar={avatar}
            initial={initial}
            size={128}
            border={{ borderWidth: 4, borderColor: "rgba(255,255,255,0.85)" }}
          />
        </View>
        <View
          style={{
            marginTop: "auto",
            width: "100%",
            paddingHorizontal: 24,
            paddingBottom: 32,
            paddingTop: 40,
            alignItems: "center",
          }}
        >
          {name ? (
            <Text
              style={{ fontSize: 22, fontWeight: "700", color: "#fff", letterSpacing: 1, textAlign: "center" }}
            >
              {name}
            </Text>
          ) : null}
          {name && title ? (
            <View
              style={{
                marginTop: 12,
                width: 200,
                maxWidth: "70%",
                height: 1,
                backgroundColor: "rgba(255,255,255,0.75)",
              }}
            />
          ) : null}
          {title ? (
            <Text
              style={{
                marginTop: 12,
                fontSize: 12,
                fontWeight: "600",
                color: "#fff",
                letterSpacing: 4,
                textTransform: "uppercase",
                textAlign: "center",
              }}
            >
              {title}
            </Text>
          ) : null}
          {bio ? (
            <Text
              style={{ fontSize: 13, marginTop: 12, color: "rgba(255,255,255,0.8)", textAlign: "center" }}
            >
              {bio}
            </Text>
          ) : null}
        </View>
      </View>
    );
    return (
      <View style={surface}>
        {hasCover ? (
          <ImageBackground source={{ uri: cover }} blurRadius={coverBlur} style={{ width: "100%" }}>
            {inner}
          </ImageBackground>
        ) : (
          <LinearGradient
            colors={["#64748b", "#334155", "#1e293b"]}
            start={{ x: 0, y: 0 }}
            end={{ x: 0.4, y: 1 }}
          >
            {inner}
          </LinearGradient>
        )}
      </View>
    );
  }

  // ───────────── SPLIT CARD ─────────────
  if (layout === "split") {
    return (
      <View style={surface}>
        <View style={{ flexDirection: "row", alignItems: "center", gap: 20, padding: 20 }}>
          <ProfileAvatar frame={pcFrame} avatar={avatar} initial={initial} size={96} border={{ borderRadius: 16 }} />
          <View style={{ flex: 1 }}>
            {name ? (
              <Text style={{ fontSize: 18, fontWeight: "700", color: themeText }}>{name}</Text>
            ) : null}
            {title ? (
              <Text style={{ fontSize: 13, fontWeight: "600", color: accent }}>{title}</Text>
            ) : null}
            {bio ? (
              <Text style={{ fontSize: 13, marginTop: 8, color: themeText, opacity: 0.72 }}>
                {bio}
              </Text>
            ) : null}
          </View>
        </View>
      </View>
    );
  }

  // ───────────── FLOATING AVATAR ─────────────
  if (layout === "floating") {
    return (
      <View style={surface}>
        {hasCover ? (
          <View style={{ position: "relative" }}>
            <Image source={{ uri: cover }} blurRadius={coverBlur} style={{ height: 96, width: "100%" }} />
            {coverTintView}
          </View>
        ) : (
          <LinearGradient
            colors={["#3d6bff", "#d76dff"]}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={{ height: 96, width: "100%" }}
          />
        )}
        <View
          style={{
            paddingHorizontal: 20,
            paddingBottom: 24,
            marginTop: -48,
            alignItems: "center",
          }}
        >
          <ProfileAvatar frame={pcFrame}
            avatar={avatar}
            initial={initial}
            size={96}
            border={{ borderWidth: 5, borderColor: "#fff" }}
          />
          {name ? (
            <Text style={{ marginTop: 12, fontSize: 18, fontWeight: "700", color: themeText }}>
              {name}
            </Text>
          ) : null}
          {title ? (
            <Text style={{ fontSize: 13, fontWeight: "600", color: accent }}>{title}</Text>
          ) : null}
          {bio ? (
            <Text style={{ fontSize: 13, marginTop: 12, color: themeText, opacity: 0.72, textAlign: "center" }}>
              {bio}
            </Text>
          ) : null}
        </View>
      </View>
    );
  }

  // ───────────── ARCH BAND ─────────────
  // Task #5922: cover photo with a semi-circular arch band at its bottom
  // edge; the circular avatar sits inside the arch. The band and avatar
  // ring share ONE color/width — the block's border_color/border_width
  // (mirrors the web `arch_band` blade branch).
  if (layout === "arch_band") {
    const abColor =
      typeof pcStyle.border_color === "string" && pcStyle.border_color !== ""
        ? pcStyle.border_color
        : "#b98a5e";
    const abWidthRaw = Number(pcStyle.border_width);
    const abWidth = Math.max(2, Math.min(10, Number.isFinite(abWidthRaw) && abWidthRaw > 0 ? abWidthRaw : 6));
    const abAv = 120; // avatar diameter
    const abBand = 12 + abWidth * 3; // band thickness
    const abOut = abAv + 2 * abBand; // arch outer diameter
    return (
      <View style={[surface, { backgroundColor: "#ffffff" }]}>
        <View style={{ position: "relative" }}>
          {hasCover ? (
            <View style={{ position: "relative" }}>
              <Image source={{ uri: cover }} blurRadius={coverBlur} style={{ height: 176, width: "100%" }} />
              {coverTintView}
            </View>
          ) : (
            <LinearGradient
              colors={["#e7dccf", "#cdb9a0"]}
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 1 }}
              style={{ height: 176, width: "100%" }}
            />
          )}
          {/* Thin rule along the cover's bottom edge, same band color */}
          <View
            style={{
              position: "absolute",
              left: 0,
              right: 0,
              bottom: 0,
              height: 3,
              backgroundColor: abColor,
            }}
          />
          {/* Filled semi-circular arch band, bottom-aligned with the cover */}
          <View
            style={{
              position: "absolute",
              bottom: 0,
              alignSelf: "center",
              width: abOut,
              height: abOut / 2 + 10,
              backgroundColor: abColor,
              borderTopLeftRadius: abOut,
              borderTopRightRadius: abOut,
            }}
          />
        </View>
        <View
          style={{
            paddingHorizontal: 20,
            paddingBottom: 24,
            paddingTop: abAv / 2 + 14,
            alignItems: "center",
          }}
        >
          <View style={{ position: "absolute", top: -(abAv / 2), alignSelf: "center" }}>
            <ProfileAvatar
              frame={pcFrame}
              avatar={avatar}
              initial={initial}
              size={abAv}
              border={{ borderWidth: abWidth, borderColor: abColor }}
            />
          </View>
          {name ? (
            <Text style={{ fontSize: 19, fontWeight: "700", color: "#1c1917" }}>
              {name}
              {verified ? <Feather name="check-circle" size={15} color={abColor} /> : null}
            </Text>
          ) : null}
          {title ? (
            <Text
              style={{
                marginTop: 4,
                fontSize: 11,
                fontWeight: "600",
                letterSpacing: 3,
                textTransform: "uppercase",
                color: abColor,
              }}
            >
              {title}
            </Text>
          ) : null}
          {bio ? (
            <Text style={{ fontSize: 13, marginTop: 12, color: "#57534e", textAlign: "center" }}>
              {bio}
            </Text>
          ) : null}
          <ProfileSocialsRow socials={socials} accent={abColor} onTap={onTap} chip="accent_outline" />
        </View>
      </View>
    );
  }

  // ───────────── OVERLAP HERO ─────────────
  // Tall cover with the white card pulled up over it; the avatar
  // straddles the card's top edge. The block surface stays transparent —
  // the layout paints its own white card internally (mirrors the web
  // `overlap_hero` blade branch).
  if (layout === "overlap_hero") {
    return (
      <View style={{ marginBottom: 16 }}>
        {hasCover ? (
          <View style={{ position: "relative", borderRadius: 16, overflow: "hidden" }}>
            <Image
              source={{ uri: cover }}
              blurRadius={coverBlur}
              style={{ height: 176, width: "100%" }}
            />
            {coverTintView}
          </View>
        ) : (
          <LinearGradient
            colors={["#3d6bff", "#6ea8ff"]}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={{ height: 176, width: "100%", borderRadius: 16 }}
          />
        )}
        <View
          style={{
            marginHorizontal: 16,
            marginTop: -56,
            backgroundColor: "#ffffff",
            borderRadius: 24,
            paddingHorizontal: 20,
            paddingBottom: 24,
            paddingTop: 60,
            alignItems: "center",
            shadowColor: "#0f172a",
            shadowOpacity: 0.16,
            shadowRadius: 17,
            shadowOffset: { width: 0, height: 7 },
            elevation: 6,
          }}
        >
          <View style={{ position: "absolute", top: -48, alignSelf: "center" }}>
            <ProfileAvatar frame={pcFrame}
              avatar={avatar}
              initial={initial}
              size={96}
              border={{ borderWidth: 4, borderColor: "#fff" }}
            />
          </View>
          {name ? (
            <Text style={{ fontSize: 18, fontWeight: "700", color: "#0f172a" }}>{name}</Text>
          ) : null}
          {title ? (
            <Text style={{ fontSize: 13, fontWeight: "600", color: accent }}>{title}</Text>
          ) : null}
          {bio ? (
            <Text style={{ fontSize: 13, marginTop: 12, color: "#475569", textAlign: "center" }}>
              {bio}
            </Text>
          ) : null}
          <ProfileSocialsRow socials={socials} accent={accent} onTap={onTap} chip="accent_outline" />
        </View>
      </View>
    );
  }

  // ───────────── GRADIENT IDENTITY ─────────────
  if (layout === "gradient") {
    const grad = (
      <View style={{ paddingHorizontal: 20, paddingVertical: 28, alignItems: "center" }}>
        <ProfileAvatar frame={pcFrame}
          avatar={avatar}
          initial={initial}
          size={80}
          border={{ borderWidth: 3, borderColor: "rgba(255,255,255,0.65)" }}
        />
        {name ? (
          <Text style={{ marginTop: 12, fontSize: 18, fontWeight: "700", color: "#fff" }}>
            {name}
          </Text>
        ) : null}
        {title ? (
          <Text style={{ fontSize: 13, color: "rgba(255,255,255,0.85)" }}>{title}</Text>
        ) : null}
        {bio ? (
          <Text style={{ fontSize: 13, marginTop: 12, color: "rgba(255,255,255,0.8)", textAlign: "center" }}>
            {bio}
          </Text>
        ) : null}
        <ProfileSocialsRow socials={socials} accent="#ffffff" onTap={onTap} />
      </View>
    );
    // Honour a flat bg from the design; otherwise paint our own gradient
    // (RN can't take a CSS linear-gradient string as a backgroundColor).
    const hasFlatBg = cardOverlay?.backgroundColor != null;
    return (
      <View style={surface}>
        {hasFlatBg ? (
          grad
        ) : (
          <LinearGradient
            colors={["#3d6bff", "#d76dff"]}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
          >
            {grad}
          </LinearGradient>
        )}
      </View>
    );
  }

  // ───────────── PREMIUM FOUNDER ─────────────
  if (layout === "founder") {
    const inner = (
      <View style={{ paddingHorizontal: 20, paddingVertical: 28, alignItems: "center" }}>
        <ProfileAvatar frame={pcFrame}
          avatar={avatar}
          initial={initial}
          size={80}
          border={{ borderWidth: 3, borderColor: "#d4af37" }}
        />
        {name ? (
          <View style={{ flexDirection: "row", alignItems: "center", marginTop: 12, gap: 6 }}>
            <Text style={{ fontSize: 18, fontWeight: "700", color: accent }}>{name}</Text>
            {verified ? <Feather name="check-circle" size={16} color={accent} /> : null}
          </View>
        ) : null}
        {title ? <Text style={{ fontSize: 13, color: "rgba(255,255,255,0.7)" }}>{title}</Text> : null}
        {bio ? (
          <Text style={{ fontSize: 13, marginTop: 12, color: "rgba(255,255,255,0.75)", textAlign: "center" }}>
            {bio}
          </Text>
        ) : null}
        {ctaLabel ? (
          <Pressable
            onPress={() => (ctaUrl && isSafeUrl(ctaUrl) ? onTap(ctaUrl) : undefined)}
            style={{
              flexDirection: "row",
              alignItems: "center",
              gap: 8,
              marginTop: 20,
              paddingHorizontal: 24,
              paddingVertical: 10,
              borderRadius: 999,
              borderWidth: 1,
              borderColor: "#d4af37",
              backgroundColor: "rgba(212,175,55,0.06)",
            }}
          >
            <Feather name="award" size={14} color="#d4af37" />
            <Text style={{ color: "#d4af37", fontSize: 13, fontWeight: "600" }}>{ctaLabel}</Text>
          </Pressable>
        ) : null}
      </View>
    );
    return (
      <View style={surface}>
        {hasCover ? (
          <ImageBackground source={{ uri: cover }} blurRadius={coverBlur} imageStyle={{ opacity: 0.35 }}>
            {/* Built-in dark wash; a user cover overlay overrides it (Task #6585) */}
            {hasCoverTint ? (
              coverTintView
            ) : (
              <LinearGradient
                colors={["rgba(0,0,0,0.75)", "rgba(0,0,0,0.92)"]}
                style={StyleSheet.absoluteFillObject}
              />
            )}
            {inner}
          </ImageBackground>
        ) : (
          <View style={{ backgroundColor: "#0b0b0f" }}>{inner}</View>
        )}
      </View>
    );
  }

  // ───────────── MINIMAL DARK ─────────────
  if (layout === "minimal_dark") {
    return (
      <View style={[surface, cardOverlay?.backgroundColor == null ? { backgroundColor: "#0b0b0f" } : null]}>
        <View style={{ paddingHorizontal: 20, paddingVertical: 32, alignItems: "center" }}>
          <ProfileAvatar frame={pcFrame}
            avatar={avatar}
            initial={initial}
            size={80}
            border={{ borderWidth: 1, borderColor: "rgba(255,255,255,0.25)" }}
          />
          {name ? (
            <Text style={{ marginTop: 16, fontSize: 20, fontWeight: "700", color: "#fff" }}>
              {name}
            </Text>
          ) : null}
          {title ? <Text style={{ fontSize: 13, color: accent }}>{title}</Text> : null}
          {bio ? (
            <Text style={{ fontSize: 13, marginTop: 12, color: "rgba(255,255,255,0.65)", textAlign: "center" }}>
              {bio}
            </Text>
          ) : null}
          <ProfileSocialsRow socials={socials} accent="#ffffff" onTap={onTap} />
        </View>
      </View>
    );
  }

  // ───────────── MAGAZINE LAYOUT ─────────────
  if (layout === "magazine") {
    return (
      <View style={[surface, { borderRadius: 12 }]}>
        {hasCover ? (
          <View style={{ position: "relative" }}>
            <Image source={{ uri: cover }} blurRadius={coverBlur} style={{ height: 128, width: "100%" }} />
            {coverTintView}
          </View>
        ) : null}
        <View style={{ padding: 20 }}>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
            <ProfileAvatar frame={pcFrame} avatar={avatar} initial={initial} size={56} />
            <View style={{ flex: 1 }}>
              {title ? (
                <Text
                  style={{
                    fontSize: 11,
                    textTransform: "uppercase",
                    letterSpacing: 2,
                    fontWeight: "600",
                    color: accent,
                  }}
                >
                  {title}
                </Text>
              ) : null}
              {name ? (
                <Text style={{ fontSize: 20, fontWeight: "700", color: themeText }}>{name}</Text>
              ) : null}
            </View>
          </View>
          {bio ? (
            <Text style={{ fontSize: 13, marginTop: 16, color: themeText, opacity: 0.78, lineHeight: 20 }}>
              {bio}
            </Text>
          ) : null}
        </View>
      </View>
    );
  }

  // ───────────── SOCIAL PROFILE STYLE ─────────────
  if (layout === "social_profile") {
    const cleanWeb = website.replace(/^https?:\/\/(www\.)?/, "");
    return (
      <View style={surface}>
        {hasCover ? (
          <View style={{ position: "relative" }}>
            <Image source={{ uri: cover }} blurRadius={coverBlur} style={{ height: 96, width: "100%" }} />
            {coverTintView}
          </View>
        ) : (
          <LinearGradient
            colors={["#3b82f6", "#06b6d4"]}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={{ height: 96, width: "100%" }}
          />
        )}
        <View style={{ paddingHorizontal: 20, paddingBottom: 24, marginTop: -44, alignItems: "center" }}>
          <ProfileAvatar frame={pcFrame}
            avatar={avatar}
            initial={initial}
            size={88}
            border={{ borderWidth: 4, borderColor: "#fff" }}
          />
          {name ? (
            <View style={{ flexDirection: "row", alignItems: "center", marginTop: 12, gap: 6 }}>
              <Text style={{ fontSize: 18, fontWeight: "700", color: themeText }}>{name}</Text>
              {verified ? <Feather name="check-circle" size={16} color={accent} /> : null}
            </View>
          ) : null}
          {title ? (
            <Text style={{ fontSize: 13, fontWeight: "600", color: accent }}>{title}</Text>
          ) : null}
          {location || website ? (
            <View
              style={{
                flexDirection: "row",
                flexWrap: "wrap",
                justifyContent: "center",
                alignItems: "center",
                gap: 12,
                marginTop: 8,
              }}
            >
              {location ? (
                <View style={{ flexDirection: "row", alignItems: "center", gap: 4 }}>
                  <Feather name="map-pin" size={12} color={themeText} />
                  <Text style={{ fontSize: 12, color: themeText, opacity: 0.7 }}>{location}</Text>
                </View>
              ) : null}
              {website ? (
                <Pressable
                  onPress={() => (isSafeUrl(website) ? onTap(website) : undefined)}
                  style={{ flexDirection: "row", alignItems: "center", gap: 4 }}
                >
                  <Feather name="link" size={12} color={accent} />
                  <Text style={{ fontSize: 12, color: accent }}>{cleanWeb}</Text>
                </Pressable>
              ) : null}
            </View>
          ) : null}
          {bio ? (
            <Text style={{ fontSize: 13, marginTop: 12, color: themeText, opacity: 0.72, textAlign: "center" }}>
              {bio}
            </Text>
          ) : null}
          <ProfileSocialsRow socials={socials} accent={accent} onTap={onTap} chip="accent_outline" />
        </View>
      </View>
    );
  }

  // ───────────── BUSINESS CARD ─────────────
  if (layout === "business_card") {
    const cleanWeb = website.replace(/^https?:\/\/(www\.)?/, "");
    return (
      <View style={surface}>
        <View style={{ flexDirection: "row", alignItems: "center", gap: 16, padding: 20 }}>
          <ProfileAvatar frame={pcFrame} avatar={avatar} initial={initial} size={80} border={{ borderRadius: 12 }} />
          <View
            style={{
              flex: 1,
              minWidth: 0,
              borderLeftWidth: 2,
              borderLeftColor: `${accent}33`,
              paddingLeft: 16,
            }}
          >
            {name ? (
              <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
                <Text style={{ fontSize: 17, fontWeight: "700", color: themeText }}>{name}</Text>
                {verified ? <Feather name="check-circle" size={15} color={accent} /> : null}
              </View>
            ) : null}
            {title ? (
              <Text
                style={{
                  fontSize: 11,
                  fontWeight: "600",
                  textTransform: "uppercase",
                  letterSpacing: 2,
                  color: accent,
                }}
              >
                {title}
              </Text>
            ) : null}
            {bio ? (
              <Text style={{ fontSize: 13, marginTop: 8, color: themeText, opacity: 0.72 }}>
                {bio}
              </Text>
            ) : null}
            {location || website ? (
              <View
                style={{
                  flexDirection: "row",
                  flexWrap: "wrap",
                  alignItems: "center",
                  gap: 12,
                  marginTop: 8,
                }}
              >
                {location ? (
                  <View style={{ flexDirection: "row", alignItems: "center", gap: 4 }}>
                    <Feather name="map-pin" size={12} color={accent} />
                    <Text style={{ fontSize: 12, color: themeText, opacity: 0.7 }}>{location}</Text>
                  </View>
                ) : null}
                {website ? (
                  <Pressable
                    onPress={() => (isSafeUrl(website) ? onTap(website) : undefined)}
                    style={{ flexDirection: "row", alignItems: "center", gap: 4 }}
                  >
                    <Feather name="link" size={12} color={accent} />
                    <Text style={{ fontSize: 12, color: accent }}>{cleanWeb}</Text>
                  </Pressable>
                ) : null}
              </View>
            ) : null}
            <ProfileSocialsRow socials={socials} accent={accent} onTap={onTap} chip="accent_outline" />
          </View>
        </View>
      </View>
    );
  }

  // ───────────── SIDEBAR ACCENT ─────────────
  if (layout === "sidebar_accent") {
    return (
      <View style={surface}>
        <View style={{ flexDirection: "row", alignItems: "stretch" }}>
          <LinearGradient
            colors={[accent, `${accent}99`]}
            start={{ x: 0, y: 0 }}
            end={{ x: 0, y: 1 }}
            style={{ width: 10 }}
          />
          <View style={{ flex: 1, padding: 20 }}>
            <View style={{ flexDirection: "row", alignItems: "center", gap: 16 }}>
              <ProfileAvatar frame={pcFrame}
                avatar={avatar}
                initial={initial}
                size={64}
                border={{ borderWidth: 2, borderColor: `${accent}33` }}
              />
              <View style={{ minWidth: 0, flex: 1 }}>
                {name ? (
                  <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
                    <Text style={{ fontSize: 17, fontWeight: "700", color: themeText }}>{name}</Text>
                    {verified ? <Feather name="check-circle" size={15} color={accent} /> : null}
                  </View>
                ) : null}
                {title ? (
                  <Text style={{ fontSize: 13, fontWeight: "600", color: accent }}>{title}</Text>
                ) : null}
              </View>
            </View>
            {bio ? (
              <Text style={{ fontSize: 13, marginTop: 12, color: themeText, opacity: 0.72 }}>
                {bio}
              </Text>
            ) : null}
            <ProfileSocialsRow socials={socials} accent={accent} onTap={onTap} chip="accent_outline" />
          </View>
        </View>
      </View>
    );
  }

  // ───────────── ID BADGE / LANYARD ─────────────
  if (layout === "id_badge") {
    return (
      <View style={{ marginBottom: 16, alignItems: "center" }}>
        {/* Lanyard strap + clip */}
        <View style={{ alignItems: "center" }}>
          <View
            style={{
              width: 8,
              height: 20,
              backgroundColor: accent,
              borderTopLeftRadius: 3,
              borderTopRightRadius: 3,
              opacity: 0.85,
            }}
          />
          <View
            style={{
              width: 36,
              height: 11,
              borderWidth: 2,
              borderColor: accent,
              borderRadius: 6,
              marginTop: -2,
            }}
          />
        </View>
        <View style={[surface, { width: "100%", marginBottom: 0 }]}>
          {/* Punch hole */}
          <View style={{ alignItems: "center", paddingTop: 12 }}>
            <View
              style={{
                width: 46,
                height: 8,
                borderRadius: 999,
                backgroundColor: "rgba(15,23,42,0.14)",
              }}
            />
          </View>
          {/* Accent header band */}
          <View
            style={{
              marginTop: 12,
              paddingHorizontal: 20,
              paddingVertical: 10,
              backgroundColor: accent,
              alignItems: "center",
            }}
          >
            <Text
              style={{
                fontSize: 10,
                fontWeight: "700",
                textTransform: "uppercase",
                letterSpacing: 4,
                color: "#fff",
              }}
            >
              Identification
            </Text>
          </View>
          <View style={{ paddingHorizontal: 20, paddingVertical: 20, alignItems: "center" }}>
            <ProfileAvatar frame={pcFrame}
              avatar={avatar}
              initial={initial}
              size={80}
              border={{ borderWidth: 3, borderColor: accent, borderRadius: 10 }}
              textColor={accent}
            />
            {name ? (
              <View style={{ flexDirection: "row", alignItems: "center", marginTop: 12, gap: 6 }}>
                <Text style={{ fontSize: 18, fontWeight: "700", color: themeText }}>{name}</Text>
                {verified ? <Feather name="check-circle" size={16} color={accent} /> : null}
              </View>
            ) : null}
            {title ? (
              <View
                style={{
                  marginTop: 6,
                  paddingHorizontal: 12,
                  paddingVertical: 3,
                  borderRadius: 999,
                  backgroundColor: `${accent}1a`,
                }}
              >
                <Text
                  style={{
                    fontSize: 11,
                    fontWeight: "700",
                    textTransform: "uppercase",
                    letterSpacing: 1,
                    color: accent,
                  }}
                >
                  {title}
                </Text>
              </View>
            ) : null}
            {bio ? (
              <Text
                style={{ fontSize: 13, marginTop: 12, color: themeText, opacity: 0.7, textAlign: "center" }}
              >
                {bio}
              </Text>
            ) : null}
            {/* Barcode footer */}
            <View
              style={{
                flexDirection: "row",
                alignItems: "flex-end",
                justifyContent: "center",
                gap: 3,
                marginTop: 16,
                opacity: 0.55,
              }}
            >
              {[3, 1, 2, 1, 3, 1, 1, 2, 1, 3, 2, 1, 1, 3, 1, 2].map((bw, i) => (
                <View key={i} style={{ width: bw, height: 22, backgroundColor: themeText }} />
              ))}
            </View>
          </View>
        </View>
      </View>
    );
  }

  // ───────────── TICKET STUB ─────────────
  if (layout === "ticket_stub") {
    return (
      <View style={surface}>
        <View style={{ flexDirection: "row", alignItems: "stretch" }}>
          <View style={{ flex: 1, padding: 20, alignItems: "center" }}>
            <Text
              style={{
                fontSize: 10,
                fontWeight: "700",
                textTransform: "uppercase",
                letterSpacing: 3,
                color: accent,
                opacity: 0.85,
              }}
            >
              Admit One
            </Text>
            <View style={{ marginTop: 12 }}>
              <ProfileAvatar frame={pcFrame}
                avatar={avatar}
                initial={initial}
                size={64}
                border={{ borderWidth: 2, borderColor: accent }}
                textColor={accent}
              />
            </View>
            {name ? (
              <Text style={{ marginTop: 12, fontSize: 20, fontWeight: "700", color: themeText }}>
                {name}
              </Text>
            ) : null}
            {bio ? (
              <Text
                style={{ fontSize: 13, marginTop: 8, color: themeText, opacity: 0.7, textAlign: "center" }}
              >
                {bio}
              </Text>
            ) : null}
          </View>
          {/* Perforated divider */}
          <View
            style={{
              borderLeftWidth: 2,
              borderLeftColor: `${accent}66`,
              borderStyle: "dashed",
            }}
          />
          <View
            style={{
              width: 96,
              alignItems: "center",
              justifyContent: "center",
              padding: 12,
            }}
          >
            <Text
              style={{
                fontSize: 9,
                fontWeight: "700",
                textTransform: "uppercase",
                letterSpacing: 1,
                color: themeText,
                opacity: 0.55,
              }}
            >
              Section
            </Text>
            <Text style={{ fontSize: 13, fontWeight: "700", marginTop: 4, color: accent }}>
              {title !== "" ? title : "GA"}
            </Text>
            <View
              style={{
                flexDirection: "row",
                alignItems: "flex-end",
                gap: 2,
                marginTop: 8,
                opacity: 0.5,
              }}
            >
              {[2, 1, 3, 1, 2, 1, 3, 1].map((bw, i) => (
                <View key={i} style={{ width: bw, height: 28, backgroundColor: accent }} />
              ))}
            </View>
          </View>
        </View>
      </View>
    );
  }

  // ───────────── POLAROID ─────────────
  if (layout === "polaroid") {
    return (
      <View style={{ marginBottom: 24, alignItems: "center" }}>
        <View
          style={[
            surface,
            {
              marginBottom: 0,
              maxWidth: 288,
              width: "100%",
              transform: [{ rotate: "-2.5deg" }],
            },
            cardOverlay?.backgroundColor == null ? { backgroundColor: "#ffffff" } : null,
          ]}
        >
          <View style={{ padding: 12, paddingBottom: 4 }}>
            <View
              style={{
                width: "100%",
                aspectRatio: 1,
                overflow: "hidden",
                backgroundColor: PROFILE_AVATAR_BG,
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              {avatar && isSafeUrl(avatar) ? (
                <Image source={{ uri: avatar }} style={{ width: "100%", height: "100%" }} />
              ) : (
                <Text style={{ fontSize: 60, fontWeight: "700", color: accent }}>{initial}</Text>
              )}
            </View>
          </View>
          <View style={{ paddingHorizontal: 16, paddingBottom: 20, paddingTop: 8, alignItems: "center" }}>
            {name ? (
              <Text style={{ fontSize: 22, fontStyle: "italic", color: "#1f2937" }}>{name}</Text>
            ) : null}
            {title ? (
              <Text style={{ fontSize: 15, fontStyle: "italic", opacity: 0.75, color: "#374151" }}>
                {title}
              </Text>
            ) : null}
            {bio ? (
              <Text
                style={{
                  fontSize: 15,
                  fontStyle: "italic",
                  marginTop: 4,
                  opacity: 0.6,
                  color: "#374151",
                  textAlign: "center",
                }}
              >
                {bio}
              </Text>
            ) : null}
          </View>
        </View>
      </View>
    );
  }

  // ───────────── TERMINAL / CODE ─────────────
  if (layout === "terminal") {
    const mono = Platform.OS === "ios" ? "Menlo" : "monospace";
    const cleanWeb = website.replace(/^https?:\/\/(www\.)?/, "");
    const termText = cardOverlay?.backgroundColor == null ? "#e2e8f0" : themeText;
    return (
      <View
        style={[
          surface,
          { borderRadius: 12 },
          cardOverlay?.backgroundColor == null ? { backgroundColor: "#0d1117" } : null,
        ]}
      >
        {/* Title bar with traffic lights */}
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            gap: 6,
            paddingHorizontal: 16,
            paddingVertical: 8,
            backgroundColor: "rgba(255,255,255,0.06)",
            borderBottomWidth: 1,
            borderBottomColor: "rgba(255,255,255,0.08)",
          }}
        >
          <View style={{ width: 11, height: 11, borderRadius: 999, backgroundColor: "#ff5f56" }} />
          <View style={{ width: 11, height: 11, borderRadius: 999, backgroundColor: "#ffbd2e" }} />
          <View style={{ width: 11, height: 11, borderRadius: 999, backgroundColor: "#27c93f" }} />
          <Text style={{ marginLeft: 8, fontSize: 10, fontFamily: mono, color: termText, opacity: 0.6 }}>
            ~ /profile
          </Text>
        </View>
        <View style={{ padding: 16 }}>
          <Text style={{ fontFamily: mono, fontSize: 13, lineHeight: 22, color: termText }}>
            <Text style={{ opacity: 0.6 }}>$ </Text>whoami
          </Text>
          {avatar || name ? (
            <View style={{ flexDirection: "row", alignItems: "center", gap: 12, marginTop: 8, marginBottom: 4 }}>
              {avatar && isSafeUrl(avatar) ? (
                <Image
                  source={{ uri: avatar }}
                  style={{
                    width: 48,
                    height: 48,
                    borderRadius: 4,
                    borderWidth: 1,
                    borderColor: `${accent}55`,
                  }}
                />
              ) : null}
              {name ? (
                <Text style={{ fontFamily: mono, fontSize: 15, fontWeight: "700", color: termText }}>
                  {name}
                </Text>
              ) : null}
            </View>
          ) : null}
          {title ? (
            <Text style={{ fontFamily: mono, fontSize: 13, lineHeight: 22, color: termText }}>
              <Text style={{ opacity: 0.6 }}>role: </Text>
              <Text style={{ color: accent }}>{title}</Text>
            </Text>
          ) : null}
          {bio ? (
            <Text
              style={{ fontFamily: mono, fontSize: 13, lineHeight: 22, marginTop: 4, color: termText, opacity: 0.85 }}
            >
              <Text style={{ opacity: 0.6 }}>bio: </Text>
              {bio}
            </Text>
          ) : null}
          {location ? (
            <Text style={{ fontFamily: mono, fontSize: 13, lineHeight: 22, color: termText, opacity: 0.85 }}>
              <Text style={{ opacity: 0.6 }}>loc: </Text>
              {location}
            </Text>
          ) : null}
          {website && isSafeUrl(website) ? (
            <Pressable onPress={() => onTap(website)}>
              <Text style={{ fontFamily: mono, fontSize: 13, lineHeight: 22, color: termText }}>
                <Text style={{ opacity: 0.6 }}>url: </Text>
                <Text style={{ color: accent, textDecorationLine: "underline" }}>{cleanWeb}</Text>
              </Text>
            </Pressable>
          ) : null}
          <View style={{ flexDirection: "row", alignItems: "center", gap: 8, marginTop: 8 }}>
            <Text style={{ fontFamily: mono, fontSize: 13, color: termText, opacity: 0.6 }}>$</Text>
            <View style={{ width: 8, height: 16, backgroundColor: accent }} />
          </View>
        </View>
      </View>
    );
  }

  // ───────────── PAPER COLLAGE ─────────────
  // Task #5929: scrapbook brand intro — offset sage grid-paper panel with a
  // slightly-rotated white paper card (script-styled name + serif tagline)
  // and a simple pressed-leaf accent built from rotated leaf-shaped Views
  // (RN has no clip-path, so the torn edge is approximated with small
  // uneven "torn scrap" strips along the card's top and bottom edges).
  // Colours are intrinsic to the collage (paper is always light), matching
  // the web renderer, so both app themes stay legible.
  if (layout === "paper_collage") {
    const serif = Platform.OS === "ios" ? "Georgia" : "serif";
    const leaf = (
      rotate: string,
      top: number,
      left: number,
      w: number,
      h: number,
      color: string,
    ) => (
      <View
        style={{
          position: "absolute",
          top,
          left,
          width: w,
          height: h,
          backgroundColor: color,
          borderTopLeftRadius: w,
          borderBottomRightRadius: w,
          borderTopRightRadius: 3,
          borderBottomLeftRadius: 3,
          transform: [{ rotate }],
        }}
      />
    );
    // Uneven "torn" strips along the paper edges.
    const tornStrip = (edge: "top" | "bottom") => (
      <View
        style={{
          flexDirection: "row",
          alignItems: edge === "top" ? "flex-end" : "flex-start",
          height: 7,
          overflow: "hidden",
        }}
      >
        {[6, 4, 7, 3, 6, 5, 7, 4, 6, 3, 7, 5, 6, 4].map((h, i) => (
          <View
            key={i}
            style={{
              flex: 1,
              height: h,
              backgroundColor: "#fcfbf7",
              transform: [{ rotate: i % 2 === 0 ? "1.5deg" : "-1.5deg" }],
            }}
          />
        ))}
      </View>
    );
    return (
      <View
        style={[
          surface,
          cardOverlay?.backgroundColor == null ? { backgroundColor: "#f0eee7" } : null,
        ]}
      >
        <View style={{ minHeight: 220, paddingVertical: 26, paddingHorizontal: 14 }}>
          {/* Offset grid-paper panel */}
          <View
            style={{
              position: "absolute",
              top: 14,
              right: 14,
              left: "21%",
              bottom: 20,
              backgroundColor: "#c6d0c3",
              overflow: "hidden",
            }}
          >
            {Array.from({ length: 12 }).map((_, i) => (
              <View
                key={`h${i}`}
                style={{
                  position: "absolute",
                  top: i * 17,
                  left: 0,
                  right: 0,
                  height: 1,
                  backgroundColor: "rgba(255,255,255,0.55)",
                }}
              />
            ))}
            {Array.from({ length: 18 }).map((_, i) => (
              <View
                key={`v${i}`}
                style={{
                  position: "absolute",
                  left: i * 17,
                  top: 0,
                  bottom: 0,
                  width: 1,
                  backgroundColor: "rgba(255,255,255,0.55)",
                }}
              />
            ))}
          </View>
          {/* Pressed botanical sprig */}
          <View style={{ position: "absolute", left: 6, top: 12, width: 96, height: 168 }}>
            <View
              style={{
                position: "absolute",
                left: 40,
                top: 8,
                width: 2.5,
                height: 150,
                borderRadius: 2,
                backgroundColor: "#6d7f5e",
                transform: [{ rotate: "14deg" }],
              }}
            />
            {leaf("-35deg", 26, 6, 34, 18, "#93a37e")}
            {leaf("-15deg", 58, 2, 38, 20, "#788a68")}
            {leaf("20deg", 84, 44, 36, 18, "#7f9070")}
            {leaf("-25deg", 104, 8, 34, 18, "#87977a")}
            {leaf("30deg", 126, 40, 32, 16, "#9aa887")}
            <View style={{ position: "absolute", left: 12, top: 0, width: 8, height: 8, borderRadius: 4, backgroundColor: "#b9b2a4" }} />
            <View style={{ position: "absolute", left: 28, top: -4, width: 6, height: 6, borderRadius: 3, backgroundColor: "#c8c2b4" }} />
          </View>
          {/* Torn paper card */}
          <View
            style={{
              marginTop: 18,
              marginLeft: "14%",
              marginRight: "5%",
              transform: [{ rotate: "-1.2deg" }],
              shadowColor: "#42402f",
              shadowOpacity: 0.22,
              shadowRadius: 12,
              shadowOffset: { width: 0, height: 8 },
              elevation: 6,
            }}
          >
            {tornStrip("top")}
            <View
              style={{
                backgroundColor: "#fcfbf7",
                paddingHorizontal: 24,
                paddingVertical: 30,
                alignItems: "center",
              }}
            >
              {name ? (
                <Text
                  style={{
                    fontSize: 30,
                    fontStyle: "italic",
                    fontWeight: "600",
                    color: "#5b4636",
                    textAlign: "center",
                    transform: [{ rotate: "-1.5deg" }],
                  }}
                >
                  {name}
                </Text>
              ) : null}
              {title ? (
                <Text
                  style={{
                    marginTop: 10,
                    fontSize: 13,
                    lineHeight: 20,
                    fontFamily: serif,
                    color: "#57534e",
                    textAlign: "center",
                  }}
                >
                  {title}
                </Text>
              ) : null}
              {bio ? (
                <Text
                  style={{
                    marginTop: 6,
                    fontSize: 12,
                    lineHeight: 19,
                    fontFamily: serif,
                    color: "#78716c",
                    textAlign: "center",
                  }}
                >
                  {bio}
                </Text>
              ) : null}
            </View>
            {tornStrip("bottom")}
          </View>
        </View>
      </View>
    );
  }

  // ───────────── BRAND RAIL ─────────────
  // Task #5934: solid brand-color panel — brand name in an outlined
  // ellipse top-right, a large offset rectangular portrait, and a
  // vertical rail of social icons down the right edge. Mirrors the web
  // `brand_rail` blade branch: bg_color paints the surface, text_color
  // drives the outlines and copy.
  if (layout === "brand_rail") {
    const brInk =
      typeof pcStyle.text_color === "string" && pcStyle.text_color !== ""
        ? pcStyle.text_color
        : "#f3efe6";
    return (
      <View
        style={[
          surface,
          cardOverlay?.backgroundColor == null ? { backgroundColor: "#2f7f72" } : null,
        ]}
      >
        <View style={{ paddingHorizontal: 20, paddingTop: 20, paddingBottom: 24 }}>
          {name ? (
            <View style={{ alignItems: "flex-end" }}>
              <View
                style={{
                  borderWidth: 1.5,
                  borderColor: brInk,
                  borderRadius: 999,
                  paddingVertical: 14,
                  paddingHorizontal: 26,
                  maxWidth: "78%",
                }}
              >
                <Text
                  style={{
                    fontSize: 15,
                    fontWeight: "600",
                    color: brInk,
                    textAlign: "center",
                    letterSpacing: 0.3,
                  }}
                >
                  {name}
                </Text>
              </View>
            </View>
          ) : null}
          <View style={{ flexDirection: "row", gap: 16, marginTop: 16 }}>
            <View style={{ flex: 1, marginRight: "4%" }}>
              {avatar && isSafeUrl(avatar) ? (
                <Image
                  source={{ uri: avatar }}
                  style={{ width: "100%", height: 250, borderRadius: 6 }}
                />
              ) : (
                <View
                  style={{
                    width: "100%",
                    height: 250,
                    borderRadius: 6,
                    alignItems: "center",
                    justifyContent: "center",
                    backgroundColor: "rgba(255,255,255,0.14)",
                  }}
                >
                  <Text style={{ fontSize: 56, fontWeight: "700", color: brInk }}>{initial}</Text>
                </View>
              )}
            </View>
            {socials.length > 0 ? (
              <View style={{ alignItems: "center", justifyContent: "center", gap: 12 }}>
                {socials.map((soc, i) => (
                  <Pressable
                    key={i}
                    onPress={() => (soc.url && isSafeUrl(soc.url) ? onTap(soc.url) : undefined)}
                    style={{
                      width: 36,
                      height: 36,
                      borderRadius: 18,
                      alignItems: "center",
                      justifyContent: "center",
                      borderWidth: 1.5,
                      borderColor: `${brInk}66`,
                    }}
                  >
                    <Feather name={profileSocialIcon(soc.name)} size={16} color={brInk} />
                  </Pressable>
                ))}
              </View>
            ) : null}
          </View>
          {title ? (
            <Text
              style={{
                marginTop: 16,
                fontSize: 11,
                fontWeight: "700",
                letterSpacing: 3.5,
                textTransform: "uppercase",
                color: brInk,
                opacity: 0.9,
              }}
            >
              {title}
            </Text>
          ) : null}
          {bio ? (
            <Text style={{ fontSize: 13, marginTop: 8, color: brInk, opacity: 0.8 }}>{bio}</Text>
          ) : null}
        </View>
      </View>
    );
  }

  // ───────────── SPLIT PILL ─────────────
  // Task #5934: large serif display name up top on a two-tone
  // horizontally split background, stadium-pill portrait straddling the
  // boundary. Top zone = bg_color (the surface), bottom zone =
  // border_color (mirrors the web `split_pill` blade branch).
  if (layout === "split_pill") {
    const spBottom =
      typeof pcStyle.border_color === "string" && pcStyle.border_color !== ""
        ? pcStyle.border_color
        : "#8a5a3b";
    const spInk =
      typeof pcStyle.text_color === "string" && pcStyle.text_color !== ""
        ? pcStyle.text_color
        : "#2f2a24";
    const serif = Platform.OS === "ios" ? "Georgia" : "serif";
    const pillH = 260;
    const split = 130; // how much of the pill sits in the bottom zone
    return (
      <View
        style={[
          surface,
          cardOverlay?.backgroundColor == null ? { backgroundColor: "#f3ede3" } : null,
        ]}
      >
        <View style={{ paddingHorizontal: 24, paddingTop: 28, paddingBottom: 6 }}>
          {name ? (
            <Text
              style={{
                fontSize: 34,
                fontFamily: serif,
                fontWeight: "500",
                letterSpacing: 2.5,
                color: spInk,
                textAlign: "center",
              }}
            >
              {name}
            </Text>
          ) : null}
        </View>
        <View style={{ position: "relative" }}>
          {/* Bottom color zone starts where the pill's midpoint sits */}
          <View
            style={{
              position: "absolute",
              left: 0,
              right: 0,
              bottom: 0,
              top: pillH - split + 6,
              backgroundColor: spBottom,
            }}
          />
          {/* Decorative dots at the boundary */}
          <View
            style={{
              position: "absolute",
              right: "8%",
              top: pillH - split + 26,
              flexDirection: "row",
              gap: 7,
            }}
          >
            <View style={{ width: 7, height: 7, borderRadius: 4, backgroundColor: "rgba(255,255,255,0.75)" }} />
            <View style={{ width: 7, height: 7, borderRadius: 4, backgroundColor: "rgba(255,255,255,0.55)" }} />
            <View style={{ width: 7, height: 7, borderRadius: 4, backgroundColor: "rgba(255,255,255,0.35)" }} />
          </View>
          <View style={{ alignItems: "center", paddingTop: 6 }}>
            {avatar && isSafeUrl(avatar) ? (
              <Image
                source={{ uri: avatar }}
                style={{ width: 180, height: pillH, borderRadius: pillH / 2 }}
              />
            ) : (
              <View
                style={{
                  width: 180,
                  height: pillH,
                  borderRadius: pillH / 2,
                  alignItems: "center",
                  justifyContent: "center",
                  backgroundColor: PROFILE_AVATAR_BG,
                }}
              >
                <Text style={{ fontSize: 56, fontWeight: "700", color: spInk }}>{initial}</Text>
              </View>
            )}
          </View>
          <View
            style={{
              backgroundColor: spBottom,
              paddingHorizontal: 24,
              paddingTop: 20,
              paddingBottom: 30,
              alignItems: "center",
            }}
          >
            {title ? (
              <Text
                style={{
                  fontSize: 11,
                  fontWeight: "700",
                  letterSpacing: 3.5,
                  textTransform: "uppercase",
                  color: "#ffffff",
                  opacity: 0.92,
                  textAlign: "center",
                }}
              >
                {title}
              </Text>
            ) : null}
            {bio ? (
              <Text
                style={{
                  fontSize: 13,
                  marginTop: 8,
                  color: "#ffffff",
                  opacity: 0.85,
                  textAlign: "center",
                }}
              >
                {bio}
              </Text>
            ) : null}
          </View>
        </View>
      </View>
    );
  }

  // ───────────── BADGE CARD ─────────────
  // Task #5934: full-bleed cover photo behind everything, a small
  // @handle pill badge up top, a tall light rounded card at the bottom
  // whose top edge is straddled by a ringed circular avatar; script-feel
  // name + divider + uppercase letter-spaced subtitle (mirrors the web
  // `badge_card` blade branch — the light card is intrinsic).
  if (layout === "badge_card") {
    const bcHandle =
      website !== ""
        ? website.replace(/^https?:\/\/(www\.)?/, "").replace(/\/$/, "")
        : name !== ""
          ? "@" + name.toLowerCase().replace(/[^a-z0-9]+/g, "")
          : "";
    return (
      <View style={surface}>
        <View style={{ position: "relative", minHeight: 440 }}>
          {hasCover ? (
            <>
              <Image
                source={{ uri: cover }}
                blurRadius={coverBlur}
                style={{ position: "absolute", top: 0, left: 0, right: 0, bottom: 0, width: "100%", height: "100%" }}
              />
              {coverTintView}
            </>
          ) : (
            <LinearGradient
              colors={["#a39a8b", "#7c7466", "#5f594e"]}
              start={{ x: 0.2, y: 0 }}
              end={{ x: 0.8, y: 1 }}
              style={{ position: "absolute", top: 0, left: 0, right: 0, bottom: 0 }}
            />
          )}
          {bcHandle !== "" ? (
            <View style={{ alignItems: "center", paddingTop: 20 }}>
              <View
                style={{
                  backgroundColor: "rgba(252,251,247,0.92)",
                  borderRadius: 999,
                  paddingHorizontal: 16,
                  paddingVertical: 6,
                }}
              >
                <Text style={{ fontSize: 12, fontWeight: "600", color: "#3f3a33", letterSpacing: 0.8 }}>
                  {bcHandle}
                </Text>
              </View>
            </View>
          ) : null}
          <View style={{ marginTop: "auto", paddingHorizontal: 16, paddingBottom: 16, paddingTop: 170 }}>
            <View
              style={{
                backgroundColor: "#fcfbf7",
                borderRadius: 26,
                paddingHorizontal: 20,
                paddingBottom: 28,
                paddingTop: 74,
                alignItems: "center",
              }}
            >
              <View style={{ position: "absolute", top: -62, alignSelf: "center" }}>
                <ProfileAvatar
                  frame={pcFrame}
                  avatar={avatar}
                  initial={initial}
                  size={124}
                  border={{ borderWidth: 5, borderColor: "#fcfbf7" }}
                  textColor="#3f3a33"
                />
              </View>
              {name ? (
                <Text
                  style={{
                    fontSize: 30,
                    fontStyle: "italic",
                    fontWeight: "600",
                    color: "#3f3a33",
                    textAlign: "center",
                  }}
                >
                  {name}
                </Text>
              ) : null}
              {name && title ? (
                <View
                  style={{
                    marginTop: 12,
                    width: 150,
                    maxWidth: "65%",
                    height: 1,
                    backgroundColor: "rgba(63,58,51,0.45)",
                  }}
                />
              ) : null}
              {title ? (
                <Text
                  style={{
                    marginTop: 12,
                    fontSize: 12,
                    fontWeight: "600",
                    letterSpacing: 3.5,
                    textTransform: "uppercase",
                    color: "#57534e",
                    textAlign: "center",
                  }}
                >
                  {title}
                </Text>
              ) : null}
              {bio ? (
                <Text style={{ fontSize: 13, marginTop: 12, color: "#78716c", textAlign: "center" }}>
                  {bio}
                </Text>
              ) : null}
            </View>
          </View>
        </View>
      </View>
    );
  }

  // ───────────── LEGACY: STATS (v3 default) ─────────────
  if (layout === "stats") {
    return (
      <View style={surface}>
        <View style={{ padding: 20, alignItems: "center" }}>
          <ProfileAvatar frame={pcFrame}
            avatar={avatar}
            initial={initial}
            size={64}
            border={{ borderWidth: 2, borderColor: "rgba(255,255,255,0.12)" }}
          />
          {name ? (
            <Text style={{ marginTop: 12, fontWeight: "600", color: themeText }}>{name}</Text>
          ) : null}
          {title ? <Text style={{ fontSize: 12, color: accent }}>{title}</Text> : null}
          {bio ? (
            <Text style={{ fontSize: 13, marginTop: 12, color: themeText, opacity: 0.6, textAlign: "center" }}>
              {bio}
            </Text>
          ) : null}
          {stats.length > 0 ? (
            <View style={{ flexDirection: "row", justifyContent: "center", gap: 24, marginTop: 16 }}>
              {stats.map((stat, i) => (
                <View key={i} style={{ alignItems: "center" }}>
                  <Text style={{ fontWeight: "700", color: themeText }}>
                    {typeof stat.value === "string" || typeof stat.value === "number"
                      ? String(stat.value)
                      : "0"}
                  </Text>
                  <Text style={{ fontSize: 10, color: themeText, opacity: 0.45 }}>
                    {typeof stat.label === "string" ? stat.label : ""}
                  </Text>
                </View>
              ))}
            </View>
          ) : null}
        </View>
      </View>
    );
  }

  // ───────────── LEGACY: BADGES (v4 default) & FALLBACK ─────────────
  return (
    <View style={surface}>
      <View style={{ padding: 20, alignItems: "center" }}>
        <ProfileAvatar frame={pcFrame}
          avatar={avatar}
          initial={initial}
          size={64}
          border={{ borderWidth: 2, borderColor: "rgba(255,255,255,0.12)" }}
        />
        {name ? (
          <Text style={{ marginTop: 12, fontWeight: "600", color: themeText }}>{name}</Text>
        ) : null}
        {title ? <Text style={{ fontSize: 12, color: accent }}>{title}</Text> : null}
        {bio ? (
          <Text style={{ fontSize: 13, marginTop: 12, color: themeText, opacity: 0.6, textAlign: "center" }}>
            {bio}
          </Text>
        ) : null}
        {badges.length > 0 ? (
          <View
            style={{
              flexDirection: "row",
              flexWrap: "wrap",
              justifyContent: "center",
              gap: 8,
              marginTop: 16,
            }}
          >
            {badges.map((badge, i) => {
              const label =
                badge && typeof badge === "object"
                  ? typeof (badge as Record<string, unknown>).label === "string"
                    ? ((badge as Record<string, unknown>).label as string)
                    : ""
                  : typeof badge === "string"
                    ? badge
                    : "";
              if (label === "") return null;
              return (
                <View
                  key={i}
                  style={{
                    paddingHorizontal: 12,
                    paddingVertical: 4,
                    borderRadius: 999,
                    backgroundColor: "rgba(61,107,255,0.18)",
                  }}
                >
                  <Text style={{ fontSize: 12, color: accent }}>{label}</Text>
                </View>
              );
            })}
          </View>
        ) : null}
      </View>
    </View>
  );
}

// ───────────────────────────────────────────────────────────────────
// Slides mode: full-screen, swipeable vertical deck. Each slide hosts
// one or more existing biolink blocks rendered with the same BlockView
// renderer used by list mode. Mirrors common.biolink-slides.blade.php.
// ───────────────────────────────────────────────────────────────────

// Resolve the slide's flat background color. For image backgrounds the
// caller separately renders an <ImageBackground>, so we just return a
// safe solid color (used when the image is loading or fails to fetch).
// For gradients we return the start color as a fallback so the FlatList
// item still has a sensible solid backdrop while the gradient layer
// composites on top.
function slideBgColor(bg: Slide["background"], fallback: string): string {
  const t = bg?.type ?? "color";
  if (t === "image")    return bg?.color ?? fallback;
  if (t === "gradient") return bg?.from_color ?? bg?.color ?? fallback;
  return bg?.color ?? fallback;
}

function SlideBlockReveal({
  active,
  enter,
  delayMs,
  durationMs,
  align,
  children,
}: {
  active: boolean;
  enter: string;
  delayMs: number;
  durationMs: number;
  align: string;
  children: React.ReactNode;
}) {
  const progress = useRef(new Animated.Value(0)).current;
  useEffect(() => {
    if (!active) {
      progress.setValue(0);
      return;
    }
    const a = Animated.timing(progress, {
      toValue: 1,
      duration: Math.max(0, durationMs),
      delay: Math.max(0, delayMs),
      useNativeDriver: true,
    });
    a.start();
    return () => a.stop();
  }, [active, delayMs, durationMs, progress]);

  const offset = enter === "slide_up" ? 16
    : enter === "slide_down" ? -16
    : enter === "slide_left" ? 16
    : enter === "slide_right" ? -16
    : 0;
  const isVertical   = enter === "slide_up" || enter === "slide_down";
  const isHorizontal = enter === "slide_left" || enter === "slide_right";
  const wantsScale   = enter === "zoom";

  const translateY = isVertical
    ? progress.interpolate({ inputRange: [0, 1], outputRange: [offset, 0] })
    : 0;
  const translateX = isHorizontal
    ? progress.interpolate({ inputRange: [0, 1], outputRange: [offset, 0] })
    : 0;
  const scale = wantsScale
    ? progress.interpolate({ inputRange: [0, 1], outputRange: [0.92, 1] })
    : 1;

  const alignSelf: "flex-start" | "flex-end" | "stretch" | "center" =
    align === "left" ? "flex-start"
      : align === "right" ? "flex-end"
        : align === "stretch" ? "stretch"
          : "center";

  return (
    <Animated.View
      style={{
        opacity: progress,
        transform: [{ translateX }, { translateY }, { scale }],
        alignSelf,
        width: align === "stretch" ? "100%" : undefined,
        maxWidth: "100%",
      }}
    >
      {children}
    </Animated.View>
  );
}

function SlidesViewer({
  alias,
  payload,
  ownerBlocks,
  openEmbed,
}: {
  alias: string;
  payload: SlidesPayload;
  ownerBlocks: BiolinkBlock[];
  openEmbed: OpenEmbed;
}) {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const { height: winH } = Dimensions.get("window");
  const slideHeight = Math.max(420, winH);

  const themeBg = payload.settings?.theme?.background ?? colors.background;
  const themeText = payload.settings?.theme?.text ?? colors.foreground;
  const themeAccent = payload.settings?.theme?.accent ?? colors.primary;
  const autoAdvance = Math.max(0, Number(payload.settings?.auto_advance ?? 0));
  const loop = !!payload.settings?.loop;

  const slides = (payload.slides ?? []).filter((s) => s && Array.isArray(s.blocks));
  const listRef = useRef<FlatList<Slide> | null>(null);
  const [active, setActive] = useState(0);

  // Per-mount session id so analytics can group views from one viewer.
  const sessionRef = useRef<string>(`m_${Math.random().toString(36).slice(2, 12)}`);
  const trackedRef = useRef<Set<number>>(new Set());
  const completedRef = useRef<boolean>(false);
  // Track when the active slide first became visible so we can fire a
  // follow-up "exit" ping with dwell_ms on slide change / unmount. The
  // server stores entry rows with dwell_ms = NULL and exit rows with
  // dwell_ms set, so per-slide averages don't double-count impressions.
  const dwellStartRef = useRef<number>(0);
  const dwellSlideRef = useRef<number>(-1);

  // Build a quick lookup so slides can render owner blocks even if the
  // server-stripped snapshot only carried minimal fields.
  const blockById = new Map<number, BiolinkBlock>();
  for (const b of ownerBlocks) blockById.set(b.id, b);

  useEffect(() => {
    // Flush dwell for the slide we are leaving (if any) before logging
    // the new one, so the exit ping is attributed to the right slide.
    if (dwellSlideRef.current !== -1 && dwellSlideRef.current !== active && dwellStartRef.current > 0) {
      const elapsed = Date.now() - dwellStartRef.current;
      if (elapsed > 0) {
        trackSlideView(alias, dwellSlideRef.current, sessionRef.current, false, elapsed);
      }
    }
    if (!trackedRef.current.has(active)) {
      trackedRef.current.add(active);
      trackSlideView(alias, active, sessionRef.current);
    }
    dwellSlideRef.current = active;
    dwellStartRef.current = Date.now();
    // Fire a one-shot deck-completion event when the viewer reaches
    // the last slide so analytics can report deck-completion rate.
    if (!completedRef.current && slides.length > 0 && active >= slides.length - 1) {
      completedRef.current = true;
      trackSlideView(alias, active, sessionRef.current, true);
    }
  }, [active, alias, slides.length]);

  // On unmount, flush dwell for whichever slide is still active so the
  // last slide in a session still contributes an avg-time sample.
  useEffect(() => {
    return () => {
      if (dwellSlideRef.current !== -1 && dwellStartRef.current > 0) {
        const elapsed = Date.now() - dwellStartRef.current;
        if (elapsed > 0) {
          trackSlideView(alias, dwellSlideRef.current, sessionRef.current, false, elapsed);
        }
      }
    };
  }, [alias]);

  // Optional auto-advance ticker.
  useEffect(() => {
    if (!autoAdvance || slides.length < 2) return;
    const t = setTimeout(() => {
      const next = active + 1;
      if (next >= slides.length) {
        if (loop) listRef.current?.scrollToIndex({ index: 0, animated: true });
        return;
      }
      listRef.current?.scrollToIndex({ index: next, animated: true });
    }, autoAdvance);
    return () => clearTimeout(t);
  }, [active, autoAdvance, loop, slides.length]);

  if (slides.length === 0) {
    return (
      <View style={[styles.center, { backgroundColor: themeBg }]}>
        <Feather name="layers" size={36} color={themeText} />
        <Text style={[styles.note, { color: themeText }]}>This deck has no slides yet.</Text>
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: themeBg }}>
      {/* Progress dots / segments at the top — mirrors web viewer. */}
      <View
        style={{
          position: "absolute",
          top: insets.top + 12,
          left: 16,
          right: 16,
          flexDirection: "row",
          gap: 4,
          zIndex: 5,
        }}
        pointerEvents="none"
      >
        {slides.map((_, i) => (
          <View
            key={i}
            style={{
              flex: 1,
              height: 3,
              borderRadius: 2,
              backgroundColor: i <= active ? themeAccent : "rgba(255,255,255,0.18)",
            }}
          />
        ))}
      </View>

      <FlatList
        ref={listRef}
        data={slides}
        keyExtractor={(s, i) => `sl-${s.id ?? i}`}
        pagingEnabled
        showsVerticalScrollIndicator={false}
        snapToInterval={slideHeight}
        decelerationRate="fast"
        getItemLayout={(_, i) => ({ length: slideHeight, offset: slideHeight * i, index: i })}
        onMomentumScrollEnd={(e) => {
          const idx = Math.round(e.nativeEvent.contentOffset.y / slideHeight);
          if (idx !== active) setActive(idx);
        }}
        renderItem={({ item, index }) => {
          const bgColor = slideBgColor(item.background, themeBg);
          const isImage = item.background?.type === "image" && !!item.background?.image_url;
          const isGradient = item.background?.type === "gradient";
          // Mirror the web viewer's `linear-gradient(135deg, from, to)`:
          // 135deg in CSS goes top-left → bottom-right, which maps to
          // expo-linear-gradient start={0,0} end={1,1}.
          const gradFrom = item.background?.from_color ?? "#1e293b";
          const gradTo = item.background?.to_color ?? item.background?.color ?? "#0f172a";
          // Render strictly from the published snapshot so mobile and
          // web show the same frozen content for a published deck.
          // Owner blocks are only consulted to fill optional fields
          // (e.g. parent_id) that the stripped snapshot omits.
          const blocks: BiolinkBlock[] = item.blocks.map((b) => {
            const live = blockById.get(b.id);
            return {
              id: b.id,
              type: b.type,
              parent_id: live?.parent_id ?? null,
              settings: b.settings,
            } as BiolinkBlock;
          });

          const slideBody = (
            <View
              style={{
                flex: 1,
                paddingTop: insets.top + 36,
                paddingBottom: insets.bottom + 24,
                paddingHorizontal: 24,
                justifyContent: "center",
              }}
            >
              {item.title ? (
                <Text style={[styles.heading, { color: themeText, marginBottom: 16, fontSize: 22 }]}>
                  {item.title}
                </Text>
              ) : null}
              <View style={{ gap: 12, alignItems: "center", maxWidth: 480, width: "100%", alignSelf: "center" }}>
                {blocks.map((b, bi) => {
                  const ov = (item.blocks[bi] as { animation?: { enter?: string; delay_ms?: number; duration_ms?: number; align?: string } | null })?.animation ?? null;
                  return (
                    <SlideBlockReveal
                      key={`${index}-${b.id}`}
                      active={index === active}
                      enter={ov?.enter ?? "fade"}
                      delayMs={ov?.delay_ms ?? 0}
                      durationMs={ov?.duration_ms ?? 400}
                      align={ov?.align ?? "center"}
                    >
                      <BlockView block={b} alias={alias} allBlocks={blocks} openEmbed={openEmbed} />
                    </SlideBlockReveal>
                  );
                })}
              </View>
            </View>
          );

          if (isImage) {
            return (
              <ImageBackground
                source={{ uri: item.background!.image_url! }}
                resizeMode="cover"
                style={{ width: "100%", height: slideHeight, backgroundColor: bgColor }}
              >
                {/* Subtle scrim so foreground content stays legible. */}
                <View style={{ ...StyleSheet.absoluteFillObject, backgroundColor: "rgba(0,0,0,0.35)" }} />
                {slideBody}
              </ImageBackground>
            );
          }

          if (isGradient) {
            return (
              <LinearGradient
                colors={[gradFrom, gradTo]}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 1 }}
                style={{ width: "100%", height: slideHeight }}
              >
                {slideBody}
              </LinearGradient>
            );
          }

          return (
            <View style={{ width: "100%", height: slideHeight, backgroundColor: bgColor }}>
              {slideBody}
            </View>
          );
        }}
      />
    </View>
  );
}

// Provides the in-page storefront cart and renders the floating cart bar +
// checkout drawer. Wraps the biolink content so any nested Product block
// (even inside card containers) can add to the cart via context.
export function StoreCartProvider({ alias, children }: { alias: string; children: React.ReactNode }) {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { user } = useAuth();
  const [lines, setLines] = useState<CartLine[]>([]);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [busy, setBusy] = useState(false);

  const add = useCallback((line: Omit<CartLine, "qty">) => {
    setLines((prev) => {
      const existing = prev.find((l) => l.blockId === line.blockId);
      if (existing) {
        return prev.map((l) =>
          l.blockId === line.blockId ? { ...l, qty: Math.min(99, l.qty + 1) } : l,
        );
      }
      // Single-currency cart: ignore items in a different currency than
      // what's already there (mirrors the server's single-currency order).
      if (prev.length > 0 && prev[0].currency !== line.currency) {
        showAlert(
          "Different currency",
          "Your cart already has items in another currency. Check out first, then start a new cart.",
        );
        return prev;
      }
      return [...prev, { ...line, qty: 1 }];
    });
  }, []);

  const setQty = useCallback((blockId: number, qty: number) => {
    setLines((prev) =>
      qty <= 0
        ? prev.filter((l) => l.blockId !== blockId)
        : prev.map((l) => (l.blockId === blockId ? { ...l, qty: Math.min(99, qty) } : l)),
    );
  }, []);

  const remove = useCallback((blockId: number) => {
    setLines((prev) => prev.filter((l) => l.blockId !== blockId));
  }, []);

  const clear = useCallback(() => setLines([]), []);
  const open = useCallback(() => setDrawerOpen(true), []);

  const subtotalCents = lines.reduce((sum, l) => sum + l.priceCents * l.qty, 0);
  const count = lines.reduce((sum, l) => sum + l.qty, 0);
  const currency = lines[0]?.currency ?? null;

  const value = useMemo<CartContextValue>(
    () => ({ lines, count, subtotalCents, currency, add, setQty, remove, clear, open }),
    [lines, count, subtotalCents, currency, add, setQty, remove, clear, open],
  );

  const handleCheckout = async () => {
    if (busy || lines.length === 0) return;
    if (!user) {
      showAlert(
        "Sign in to check out",
        "Create a free account or sign in to complete your purchase.",
        [
          { text: "Not now", style: "cancel" },
          { text: "Sign in", onPress: () => router.push("/(auth)" as any) },
        ],
      );
      return;
    }
    setBusy(true);
    try {
      const res = await checkoutCart(
        alias,
        lines.map((l) => ({ block_id: l.blockId, quantity: l.qty })),
      );
      if (res.checkout_url) {
        try {
          await WebBrowser.openBrowserAsync(res.checkout_url);
        } catch {
          Linking.openURL(res.checkout_url);
        }
      }
      setDrawerOpen(false);
      clear();
      router.push(`/store/order/${res.order.id}` as any);
    } catch (e) {
      const err = e as { message?: string };
      showAlert("Couldn't check out", err.message || "Please try again.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <CartContext.Provider value={value}>
      {children}

      {count > 0 ? (
        <Pressable
          onPress={open}
          style={{
            position: "absolute",
            left: 20,
            right: 20,
            bottom: insets.bottom + 16,
            backgroundColor: colors.primary,
            borderRadius: 16,
            paddingVertical: 14,
            paddingHorizontal: 18,
            flexDirection: "row",
            alignItems: "center",
            gap: 10,
            shadowColor: "#000",
            shadowOpacity: 0.25,
            shadowRadius: 12,
            shadowOffset: { width: 0, height: 4 },
            elevation: 6,
          }}
        >
          <Feather name="shopping-cart" size={18} color={colors.primaryForeground ?? "#fff"} />
          <Text style={{ color: colors.primaryForeground ?? "#fff", fontWeight: "700", flex: 1 }}>
            View cart ({count})
          </Text>
          <Text style={{ color: colors.primaryForeground ?? "#fff", fontWeight: "800" }}>
            {fmtMoney(subtotalCents, currency ?? "USD")}
          </Text>
        </Pressable>
      ) : null}

      <Modal
        visible={drawerOpen}
        transparent
        animationType="slide"
        onRequestClose={() => setDrawerOpen(false)}
      >
        <Pressable
          style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.5)" }}
          onPress={() => setDrawerOpen(false)}
        />
        <View
          style={{
            backgroundColor: colors.background,
            borderTopLeftRadius: 24,
            borderTopRightRadius: 24,
            paddingTop: 16,
            paddingHorizontal: 20,
            paddingBottom: insets.bottom + 20,
            maxHeight: "80%",
          }}
        >
          <View style={{ flexDirection: "row", alignItems: "center", marginBottom: 12 }}>
            <Text style={[styles.heading, { color: colors.foreground, fontSize: 20, flex: 1, marginTop: 0 }]}>
              Your cart
            </Text>
            <Pressable onPress={() => setDrawerOpen(false)} hitSlop={12}>
              <Feather name="x" size={24} color={colors.foreground} />
            </Pressable>
          </View>

          <ScrollView style={{ maxHeight: 360 }}>
            {lines.map((l) => (
              <View
                key={l.blockId}
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  gap: 12,
                  paddingVertical: 10,
                  borderBottomWidth: StyleSheet.hairlineWidth,
                  borderBottomColor: colors.border,
                }}
              >
                {l.image ? (
                  <Image source={{ uri: l.image }} style={{ width: 48, height: 48, borderRadius: 8 }} />
                ) : (
                  <View
                    style={{
                      width: 48,
                      height: 48,
                      borderRadius: 8,
                      backgroundColor: colors.card,
                      alignItems: "center",
                      justifyContent: "center",
                    }}
                  >
                    <Feather name="box" size={20} color={colors.mutedForeground} />
                  </View>
                )}
                <View style={{ flex: 1 }}>
                  <Text style={[styles.btnLabel, { color: colors.foreground, textAlign: "left" }]} numberOfLines={1}>
                    {l.name}
                  </Text>
                  <Text style={[styles.body, { color: colors.mutedForeground, textAlign: "left", fontSize: 12 }]}>
                    {fmtMoney(l.priceCents, l.currency)}
                  </Text>
                </View>
                <View style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
                  <Pressable onPress={() => setQty(l.blockId, l.qty - 1)} hitSlop={8}>
                    <Feather name="minus-circle" size={22} color={colors.mutedForeground} />
                  </Pressable>
                  <Text style={{ color: colors.foreground, fontWeight: "700", minWidth: 18, textAlign: "center" }}>
                    {l.qty}
                  </Text>
                  <Pressable onPress={() => setQty(l.blockId, l.qty + 1)} hitSlop={8}>
                    <Feather name="plus-circle" size={22} color={colors.primary} />
                  </Pressable>
                </View>
              </View>
            ))}
          </ScrollView>

          <View style={{ flexDirection: "row", alignItems: "center", marginTop: 16, marginBottom: 12 }}>
            <Text style={[styles.btnLabel, { color: colors.mutedForeground, flex: 1, textAlign: "left" }]}>
              Subtotal
            </Text>
            <Text style={[styles.heading, { color: colors.foreground, fontSize: 20, marginTop: 0 }]}>
              {fmtMoney(subtotalCents, currency ?? "USD")}
            </Text>
          </View>

          <Pressable
            onPress={handleCheckout}
            disabled={busy}
            style={{
              backgroundColor: colors.primary,
              borderRadius: 14,
              paddingVertical: 15,
              alignItems: "center",
              opacity: busy ? 0.6 : 1,
            }}
          >
            {busy ? (
              <ActivityIndicator color={colors.primaryForeground ?? "#fff"} />
            ) : (
              <Text style={{ color: colors.primaryForeground ?? "#fff", fontWeight: "800", fontSize: 16 }}>
                Checkout
              </Text>
            )}
          </Pressable>
        </View>
      </Modal>
    </CartContext.Provider>
  );
}

export default function BiolinkViewer() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { handle, t, src } = useLocalSearchParams<{ handle: string; t?: string; src?: string }>();
  const alias = String(handle ?? "");
  const tableCode = t ? String(t) : "";
  const srcTag = src ? String(src) : "";
  const webTop = Platform.OS === "web" ? 0 : 0;

  const [embed, setEmbed] = useState<{ url: string; title?: string; sandboxed?: boolean } | null>(null);
  const openEmbed = useCallback<OpenEmbed>((opts) => {
    if (!opts.url || !isSafeUrl(opts.url)) return;
    setEmbed(opts);
  }, []);
  const closeEmbed = useCallback(() => setEmbed(null), []);

  const q = useQuery<BiolinkPayload>({
    queryKey: ["biolink", alias],
    queryFn: () => getBiolink(alias),
    enabled: !!alias,
  });

  // Fire one page-visit ping per successful biolink load, mirroring the
  // web's RedirectController::track() so in-app opens are counted in the
  // creator's visit analytics. Best-effort and non-blocking; we only ping
  // once per (alias, mount) pair to avoid double-counting on re-renders.
  const visitedRef = useRef<string | null>(null);
  useEffect(() => {
    if (!q.data || !alias) return;
    if (visitedRef.current === alias) return;
    visitedRef.current = alias;
    trackBiolinkVisit(alias);
  }, [q.data, alias]);

  // Restaurant menus are a distinct biolink-family type with their own
  // viewer (menu + cart + order-at-table). When a handle resolves to one,
  // hand off to the dedicated screen so the in-app experience matches web.
  const redirectedRef = useRef(false);
  useEffect(() => {
    if (!alias || redirectedRef.current) return;
    const type = q.data?.biolink.type;
    if (type === "restaurant_menu") {
      redirectedRef.current = true;
      const suffix = tableCode ? `?t=${encodeURIComponent(tableCode)}` : "";
      router.replace(`/restaurant/${alias}${suffix}` as any);
    } else if (type === "store_menu") {
      redirectedRef.current = true;
      router.replace(`/store/${alias}` as any);
    } else if (type === "service_booking") {
      redirectedRef.current = true;
      router.replace(`/service-booking/${alias}` as any);
    }
  }, [q.data, alias, tableCode, router]);

  // Task #6687: event (ics) links are NOT biolink-family, so the biolink
  // payload 404s for them. When that happens, try resolving the alias as a
  // public event and hand off to the native event screen — preserving the
  // Connect QR attribution tag (?src=connect_qr) so the one-tap
  // "RSVP & Connect" prompt shows there.
  useEffect(() => {
    if (!q.isError || !alias || redirectedRef.current) return;
    if (errorStatus(q.error) !== 404) return;
    let stale = false;
    getEvent(alias)
      .then(() => {
        if (stale || redirectedRef.current) return;
        redirectedRef.current = true;
        const suffix = srcTag ? `?src=${encodeURIComponent(srcTag)}` : "";
        router.replace(`/events/${alias}${suffix}` as any);
      })
      .catch(() => {});
    return () => {
      stale = true;
    };
  }, [q.isError, q.error, alias, srcTag, router]);

  return (
    <StoreCartProvider alias={alias}>
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: false }} />
      <View
        style={[
          styles.topBar,
          { paddingTop: insets.top + 12 + webTop, paddingHorizontal: 20 },
        ]}
      >
        <Pressable onPress={() => router.back()} hitSlop={12}>
          <Feather name="x" size={26} color={colors.foreground} />
        </Pressable>
        <BrandWordmark size={22} />
        <View style={{ width: 26 }} />
      </View>

      {q.isLoading && (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      )}

      {q.error && (
        <View style={styles.center}>
          <Feather name="alert-circle" size={36} color={colors.mutedForeground} />
          <Text style={[styles.note, { color: colors.foreground }]}>
            We couldn&apos;t open this profile.
          </Text>
          <Text style={[styles.note, { color: colors.mutedForeground }]}>
            {(q.error as Error).message}
          </Text>
        </View>
      )}

      {q.data && q.data.biolink.mode === "slides" && q.data.slides ? (
        <SlidesViewer
          alias={alias}
          payload={q.data.slides}
          ownerBlocks={q.data.blocks}
          openEmbed={openEmbed}
        />
      ) : null}

      {q.data && (q.data.biolink.mode !== "slides" || !q.data.slides) && (
        <StickerOverlay stickers={q.data.biolink.stickers} layer="back" mode="fixed" />
      )}

      {q.data && (q.data.biolink.mode !== "slides" || !q.data.slides) && (
        <ScrollView contentContainerStyle={styles.content}>
          {/* Scroll-mode stickers live inside the ScrollView so they move
              with the page content (web parity: .page-stickers--scroll). */}
          <StickerOverlay stickers={q.data.biolink.stickers} layer="back" mode="scroll" />
          {/* Task #6114: the scroll content has no horizontal padding any
              more (blocks manage their own side margins), so the profile
              header keeps its old 24px inset via this wrapper. */}
          <View style={styles.headerPad}>
          {q.data.owner.avatar ? (
            <Image
              source={{ uri: q.data.owner.avatar }}
              style={[styles.avatar, { borderColor: colors.border, borderRadius: 999 }]}
            />
          ) : (
            <View
              style={[
                styles.avatar,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: 999,
                  alignItems: "center",
                  justifyContent: "center",
                },
              ]}
            >
              <Feather name="user" size={48} color={colors.mutedForeground} />
            </View>
          )}
          <Text style={[styles.handle, { color: colors.foreground }]}>
            {q.data.owner.name ?? `@${q.data.owner.handle ?? alias}`}
          </Text>
          {q.data.owner.handle && q.data.owner.name ? (
            <Text style={[styles.subhandle, { color: colors.mutedForeground }]}>
              @{q.data.owner.handle}
            </Text>
          ) : null}
          {q.data.owner.bio ? (
            <Text style={[styles.bio, { color: colors.foreground }]}>
              {q.data.owner.bio}
            </Text>
          ) : null}
          {q.data.ab_test ? (
            // Surfaces the active A/B test status to the viewer. The
            // server already picked the variant for this visitor (sticky
            // via the X-1INME-Visitor-Id header) so we just render which
            // bucket they're in. Useful for the creator previewing the
            // page on their phone, harmless for casual visitors.
            <View
              style={{
                alignSelf: "center",
                paddingHorizontal: 10,
                paddingVertical: 4,
                borderRadius: 999,
                backgroundColor: "rgba(61,107,255,0.15)",
                borderWidth: 1,
                borderColor: "rgba(61,107,255,0.4)",
                marginBottom: 12,
              }}
            >
              <Text style={{ fontSize: 11, fontWeight: "700", color: "#7d9bff" }}>
                A/B test live · Showing Variant {q.data.ab_test.variant.toUpperCase()}
              </Text>
            </View>
          ) : null}
          </View>
          <View style={styles.blocks}>
            {q.data.blocks
              .filter((b) => !b.parent_id)
              .map((b) => (
                // Task #6114: default side spacing lives on each top-level
                // block (overridable via _style margins — 0 = full width);
                // card children rendered inside BlockView are unaffected.
                <View key={b.id} style={blockWrapMargins(b)}>
                  <BlockView block={b} alias={alias} allBlocks={q.data.blocks} openEmbed={openEmbed} />
                </View>
              ))}
            {/* Free-floating page text overlays (Task #5954). Percent x/y are
                relative to the blocks column; pointerEvents none so they
                never block taps on the blocks underneath. */}
            {(q.data.biolink.text_overlays ?? []).map((ov, i) => (
              <View
                key={`pov-${i}`}
                pointerEvents="none"
                style={{
                  position: "absolute",
                  left: `${Math.max(0, Math.min(100, ov.x))}%`,
                  top: `${Math.max(0, Math.min(100, ov.y))}%`,
                  zIndex: 30,
                  transform: [
                    { translateX: "-50%" as unknown as number },
                    { translateY: "-50%" as unknown as number },
                    { rotate: `${Math.max(-180, Math.min(180, ov.rotate))}deg` },
                  ],
                }}
              >
                <Text
                  style={{
                    color: /^#[0-9a-fA-F]{3,8}$/.test(ov.color) ? ov.color : "#ffffff",
                    fontSize: Math.max(10, Math.min(72, ov.size)),
                    fontWeight: "700",
                    textShadowColor: "rgba(0,0,0,0.45)",
                    textShadowOffset: { width: 0, height: 1 },
                    textShadowRadius: 6,
                  }}
                >
                  {ov.text}
                </Text>
              </View>
            ))}
          </View>
          <View style={styles.headerPad}>
            <LinkTypePairings
              pairings={q.data.pairings}
              theme="biolink"
              fontColor={colors.foreground}
            />
          </View>
          <StickerOverlay stickers={q.data.biolink.stickers} layer="front" mode="scroll" />
        </ScrollView>
      )}

      {q.data && (q.data.biolink.mode !== "slides" || !q.data.slides) && (
        <StickerOverlay stickers={q.data.biolink.stickers} layer="front" mode="fixed" />
      )}

      <EmbedModal
        visible={!!embed}
        url={embed?.url ?? null}
        title={embed?.title}
        sandboxed={embed?.sandboxed}
        onClose={closeEmbed}
      />
    </View>
    </StoreCartProvider>
  );
}

const styles = StyleSheet.create({
  topBar: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  center: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    padding: 32,
  },
  content: {
    alignItems: "center",
    // Task #6114: no horizontal padding — each top-level block carries its
    // own side margin (default 24, creator-overridable down to 0 for true
    // full-width blocks). The profile header keeps its inset via headerPad.
    paddingBottom: 64,
    gap: 14,
  },
  avatar: {
    width: 112,
    height: 112,
    borderWidth: 1,
    marginTop: 12,
  },
  handle: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 24,
    textAlign: "center",
  },
  subhandle: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 15,
    marginTop: -8,
  },
  bio: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    textAlign: "center",
    lineHeight: 22,
    maxWidth: 360,
    marginBottom: 8,
  },
  note: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    textAlign: "center",
  },
  headerPad: { alignSelf: "stretch", alignItems: "center", paddingHorizontal: 24 },
  blocks: { width: "100%", maxWidth: 480, gap: 10, marginTop: 12 },
  btn: {
    width: "100%",
    paddingVertical: 16,
    paddingHorizontal: 16,
    borderRadius: 14,
    borderWidth: 1,
    alignItems: "center",
  },
  btnLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
    textAlign: "center",
  },
  heading: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 18,
    textAlign: "center",
    marginTop: 8,
    width: "100%",
  },
  body: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    lineHeight: 22,
    width: "100%",
    textAlign: "center",
  },
  image: {
    width: "100%",
    aspectRatio: 16 / 9,
    borderRadius: 14,
  },
  badge: {
    width: "100%",
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: "center",
  },
  badgeText: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    textAlign: "center",
  },
  socialsRow: {
    width: "100%",
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 10,
    justifyContent: "center",
  },
  socialIcon: {
    width: 44,
    height: 44,
    borderRadius: 22,
    borderWidth: 1,
    alignItems: "center",
    justifyContent: "center",
  },
  mediaCard: {
    width: "100%",
    borderRadius: 14,
    borderWidth: 1,
    overflow: "hidden",
  },
  mediaThumb: {
    width: "100%",
    aspectRatio: 16 / 9,
  },
  mediaBody: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    padding: 12,
  },
  mediaLabel: {
    flex: 1,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
  },
  cardContainer: {
    width: "100%",
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
    gap: 8,
  },
  gridContainer: {
    width: "100%",
    gap: 8,
  },
  listRow: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 8,
    paddingVertical: 4,
  },
  pollOption: {
    width: "100%",
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 10,
    borderWidth: 1,
    marginTop: 4,
  },
  rsvpRow: {
    flexDirection: "row",
    gap: 8,
    marginTop: 4,
  },
  rsvpBtn: {
    flex: 1,
    paddingVertical: 10,
    paddingHorizontal: 8,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: "center",
  },
});

function maskAspectRatio(shape: string): number {
  if (shape === "arch" || shape === "semicircle") return 3 / 4;
  if (shape === "torn" || shape === "wave") return 4 / 5;
  return 1;
}

function MaskedBlockImage({ uri, shape }: { uri: string; shape: string }) {
  const [w, setW] = useState(0);
  const clipId = useRef(`blkmask${++__maskClipSeq}`).current;
  const ratio = maskAspectRatio(shape);
  const h = w / ratio;
  const pts = MASK_POLYGONS[shape];
  return (
    <View
      style={{ width: "100%", aspectRatio: ratio }}
      onLayout={(e) => setW(e.nativeEvent.layout.width)}
    >
      {w > 0 ? (
        <Svg width={w} height={h}>
          <Defs>
            <ClipPath id={clipId}>
              {shape === "oval" || !pts ? (
                <Ellipse cx={w / 2} cy={h / 2} rx={w / 2} ry={h / 2} />
              ) : (
                <Polygon
                  points={pts.map(([x, y]) => `${(x / 100) * w},${(y / 100) * h}`).join(" ")}
                />
              )}
            </ClipPath>
          </Defs>
          <SvgImage
            href={{ uri }}
            x={0}
            y={0}
            width={w}
            height={h}
            preserveAspectRatio="xMidYMid slice"
            clipPath={`url(#${clipId})`}
          />
        </Svg>
      ) : null}
    </View>
  );
}

let __maskClipSeq = 0;
