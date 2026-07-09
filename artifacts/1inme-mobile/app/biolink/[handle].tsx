import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { LinearGradient } from "expo-linear-gradient";
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
  ActivityIndicator,
  Alert,
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
import { getBaseUrl } from "@/lib/api";
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
} {
  const o = variantOverlay(block.type, block.settings ?? null);
  return {
    backgroundColor: o?.backgroundColor ?? colors.card,
    borderColor: o?.borderColor ?? colors.border,
    ...(o?.borderWidth != null ? { borderWidth: o.borderWidth } : {}),
    ...(o?.borderRadius != null ? { borderRadius: o.borderRadius } : {}),
    ...(o?.borderStyle != null ? { borderStyle: o.borderStyle } : {}),
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

function pickStr(s: Record<string, unknown> | null, ...keys: string[]): string | null {
  if (!s) return null;
  for (const k of keys) {
    const v = s[k];
    if (typeof v === "string" && v.trim() !== "") return v.trim();
  }
  return null;
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
  const question = pickStr(settings, "question", "title", "text", "heading") ?? "Vote";
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
  const title = pickStr(settings, "title", "heading", "event_title") ?? "RSVP";
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

// Render a small "Thanks for responding — you picked X" card that replaces
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
        Thanks for responding — you picked “{responseLabel}”.
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
  const name = (pickStr(s, "name", "title") ?? "Product").trim() || "Product";
  const desc = pickStr(s, "description", "subtitle");
  const priceCents = pickNum(s, "price_cents") ?? 0;
  const currency = (pickStr(s, "currency") ?? "USD").toUpperCase();
  const productType = pickStr(s, "product_type") === "physical" ? "physical" : "digital";
  const image = pickStr(s, "image", "thumbnail");

  const inCart = cart.lines.some((l) => l.blockId === block.id);

  const ensureAuthed = (): boolean => {
    if (user) return true;
    Alert.alert(
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
        Alert.alert("Couldn't start checkout", err.message || "Please try again.");
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

export function BlockView({ block, alias, allBlocks, openEmbed }: { block: BiolinkBlock; alias: string; allBlocks: BiolinkBlock[]; openEmbed: OpenEmbed }) {
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
    const containerStyle = isCard
      ? [styles.cardContainer, blockCardStyle(block, colors)]
      : [styles.gridContainer, pad != null ? { padding: pad } : null];
    return (
      <View style={containerStyle}>
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
    return (
      <Text style={[styles.heading, { color: blockTextColor(block, colors.foreground) }]}>{text}</Text>
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
    return (
      <Text style={[styles.body, { color: blockTextColor(block, colors.foreground) }]}>{text}</Text>
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
    return (
      <Image
        source={{ uri: url }}
        style={[
          styles.image,
          isAvatar
            ? { width: 96, height: 96, aspectRatio: undefined, borderRadius: pickBool(s, "rounded", true) ? 999 : 16 }
            : null,
        ]}
        resizeMode="cover"
      />
    );
  }

  if (t === "spacer" || t === "divider") {
    return (
      <View
        style={{
          height: t === "spacer" ? (pickNum(s, "height") ?? 12) : 1,
          backgroundColor: colors.border,
          marginVertical: 6,
        }}
      />
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
    const label = pickStr(s, "text", "label", "title") ?? "Get started";
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
        label: pickStr(it, "label", "name", "platform", "title") ?? "Open",
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
    const label = pickStr(s, "title", "text") ?? "Watch video";
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
    const label = pickStr(s, "title", "text") ?? "Listen";
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
            {pickStr(s, "caption") ?? "View on Instagram"}
          </Text>
        </View>
      </Pressable>
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
    const label = pickStr(s, "text") ?? "Support me";
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
    const text = pickStr(s, "text") ?? "Featured";
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
    const name = pickStr(s, "name", "title") ?? "Download file";
    if (!url || !isSafeUrl(url)) return null;
    return (
      <Pressable onPress={() => handleTap(url)} style={[styles.btn, blockCardStyle(block, colors)]}>
        <Text style={[styles.btnLabel, { color: colors.foreground }]}>⬇ {name}</Text>
      </Pressable>
    );
  }

  if (t === "donation" || t === "paypal" || t === "price" || t === "coupon" || t === "one_time_offer") {
    const url = pickStr(s, "url");
    const label = pickStr(s, "title", "text", "code") ?? "View offer";
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
    const target = pickStr(s, "target_date", "date", "ends_at");
    const title = pickStr(s, "title", "text") ?? "Coming soon";
    const tsMs = target ? Date.parse(target) : NaN;
    const remaining = Number.isFinite(tsMs) ? Math.max(0, tsMs - Date.now()) : 0;
    const days = Math.floor(remaining / 86400000);
    const hours = Math.floor((remaining % 86400000) / 3600000);
    return (
      <View style={[styles.cardContainer, { backgroundColor: colors.card, borderColor: colors.border, alignItems: "center" }]}>
        <Text style={[styles.btnLabel, { color: colors.foreground }]}>{title}</Text>
        {Number.isFinite(tsMs) ? (
          <Text style={[styles.heading, { color: colors.primary, fontSize: 22, marginTop: 6 }]}>
            {days}d {hours}h
          </Text>
        ) : null}
      </View>
    );
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
    const label = pickStr(s, "title", "heading", "text") ?? "Open form";
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
    const label = pickStr(s, "button_text", "text", "title", "name") ?? "Chat on WhatsApp";
    return (
      <Pressable onPress={() => handleTap(url)} style={[styles.btn, { backgroundColor: "#25D366", borderColor: "#25D366" }]}>
        <Text style={[styles.btnLabel, { color: "#fff" }]}>💬 {label}</Text>
      </Pressable>
    );
  }

  if (t === "calendly" || t === "calendly_embed") {
    const url = pickStr(s, "url");
    const label = pickStr(s, "text", "title") ?? "Book a time";
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
    const label = pickStr(s, "title", "text", "label") ?? "Open embed";
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
          Third-party embed — tap to open in-app
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
        {pickStr(s, "title", "text", "label") ?? "Open on web"}
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
}: {
  avatar: string;
  initial: string;
  size: number;
  border?: { borderWidth?: number; borderColor?: string; borderRadius?: number };
  textColor?: string;
}) {
  if (avatar && isSafeUrl(avatar)) {
    return (
      <Image
        source={{ uri: avatar }}
        style={[{ width: size, height: size, borderRadius: size / 2 }, border]}
      />
    );
  }
  return (
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
  const initial = (name !== "" ? name : "U").charAt(0).toUpperCase();
  const hasCover = cover !== "" && isSafeUrl(cover);

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
          <Image source={{ uri: cover }} style={{ height: 112, width: "100%" }} />
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
          <ProfileAvatar
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
            style={{ ...StyleSheet.absoluteFillObject, opacity: 0.3 }}
          />
        ) : null}
        {/* Translucent tint over a cover; an opaque brand gradient when there's
            no cover, so the white glass text stays legible on any page theme
            (mirrors the floating/social_profile fallback). */}
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
        <View style={{ paddingHorizontal: 20, paddingVertical: 28, alignItems: "center" }}>
          <ProfileAvatar
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
        <LinearGradient
          colors={["rgba(0,0,0,0.15)", "rgba(0,0,0,0.88)"]}
          style={StyleSheet.absoluteFillObject}
        />
        <View style={{ padding: 20 }}>
          <View style={{ flexDirection: "row", alignItems: "flex-end", gap: 12 }}>
            <ProfileAvatar
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
          <ImageBackground source={{ uri: cover }} style={{ width: "100%" }}>
            {inner}
          </ImageBackground>
        ) : (
          <View style={{ backgroundColor: "#0b0b0f" }}>{inner}</View>
        )}
      </View>
    );
  }

  // ───────────── SPLIT CARD ─────────────
  if (layout === "split") {
    return (
      <View style={surface}>
        <View style={{ flexDirection: "row", alignItems: "center", gap: 20, padding: 20 }}>
          <ProfileAvatar avatar={avatar} initial={initial} size={96} border={{ borderRadius: 16 }} />
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
          <Image source={{ uri: cover }} style={{ height: 96, width: "100%" }} />
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
          <ProfileAvatar
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

  // ───────────── GRADIENT IDENTITY ─────────────
  if (layout === "gradient") {
    const grad = (
      <View style={{ paddingHorizontal: 20, paddingVertical: 28, alignItems: "center" }}>
        <ProfileAvatar
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
        <ProfileAvatar
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
          <ImageBackground source={{ uri: cover }} imageStyle={{ opacity: 0.35 }}>
            <LinearGradient
              colors={["rgba(0,0,0,0.75)", "rgba(0,0,0,0.92)"]}
              style={StyleSheet.absoluteFillObject}
            />
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
          <ProfileAvatar
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
        {hasCover ? <Image source={{ uri: cover }} style={{ height: 128, width: "100%" }} /> : null}
        <View style={{ padding: 20 }}>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
            <ProfileAvatar avatar={avatar} initial={initial} size={56} />
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
          <Image source={{ uri: cover }} style={{ height: 96, width: "100%" }} />
        ) : (
          <LinearGradient
            colors={["#3b82f6", "#06b6d4"]}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={{ height: 96, width: "100%" }}
          />
        )}
        <View style={{ paddingHorizontal: 20, paddingBottom: 24, marginTop: -44, alignItems: "center" }}>
          <ProfileAvatar
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

  // ───────────── LEGACY: STATS (v3 default) ─────────────
  if (layout === "stats") {
    return (
      <View style={surface}>
        <View style={{ padding: 20, alignItems: "center" }}>
          <ProfileAvatar
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
        <ProfileAvatar
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
        Alert.alert(
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
      Alert.alert(
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
      Alert.alert("Couldn't check out", err.message || "Please try again.");
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
  const { handle, t } = useLocalSearchParams<{ handle: string; t?: string }>();
  const alias = String(handle ?? "");
  const tableCode = t ? String(t) : "";
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
        <ScrollView contentContainerStyle={styles.content}>
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
          <View style={styles.blocks}>
            {q.data.blocks
              .filter((b) => !b.parent_id)
              .map((b) => (
                <BlockView key={b.id} block={b} alias={alias} allBlocks={q.data.blocks} openEmbed={openEmbed} />
              ))}
          </View>
          <LinkTypePairings
            pairings={q.data.pairings}
            theme="biolink"
            fontColor={colors.foreground}
          />
        </ScrollView>
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
    paddingHorizontal: 24,
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
