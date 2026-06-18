import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { LinearGradient } from "expo-linear-gradient";
import * as Linking from "expo-linking";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Animated,
  Dimensions,
  FlatList,
  Image,
  ImageBackground,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
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
import { ReviewsWall } from "@/components/ReviewsWall";
import { useColors } from "@/hooks/useColors";
import { getBaseUrl } from "@/lib/api";
import { variantOverlay } from "@/lib/blockVariants";

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
function openSafe(u: string, router: ReturnType<typeof useRouter>) {
  if (!isSafeUrl(u)) return;
  // `tel:` links open the in-app dialer (and onward to the active-call
  // screen) instead of handing off to the device's native phone app —
  // see task #395. Other safe schemes keep their default OS behaviour.
  if (u.toLowerCase().startsWith("tel:")) {
    let raw = u.slice(4);
    try {
      raw = decodeURIComponent(raw);
    } catch {
      /* leave as-is — the dialer accepts any string */
    }
    const number = raw.trim();
    if (!number) return;
    router.push({
      pathname: "/dialer",
      params: { prefill: number, autoDial: "1" },
    });
    return;
  }
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
                  borderColor: isVoted ? "#7c3aed" : colors.border,
                  backgroundColor: isVoted ? "#7c3aed22" : "transparent",
                  opacity: submitting !== null && !isBusy ? 0.5 : 1,
                },
              ]}
            >
              <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <Text style={[styles.body, { color: colors.foreground, textAlign: "left", fontSize: 14, flex: 1 }]}>
                  {opt}
                </Text>
                {isBusy ? <ActivityIndicator size="small" color={colors.foreground} /> : null}
                {isVoted ? <Feather name="check" size={16} color="#7c3aed" /> : null}
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
        <Text style={[styles.body, { color: "#16a34a", textAlign: "left", fontSize: 12, marginTop: 4 }]}>
          {resultsLockedUntil
            ? "Thanks for voting!"
            : (hiddenUntilVote ? "Thanks for voting! Results are hidden by the creator." : "Thanks for voting!")}
        </Text>
      ) : null}
      {error ? (
        <Text style={[styles.body, { color: "#dc2626", textAlign: "left", fontSize: 12, marginTop: 4 }]}>
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
    { key: "yes", label: "Going", bg: "#16a34a" },
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
              backgroundColor: "#7c3aed",
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
        <Text style={[styles.body, { color: "#dc2626", textAlign: "left", fontSize: 12, marginTop: 4 }]}>
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
                  borderColor: isPicked ? "#7c3aed" : colors.border,
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
                  backgroundColor: isPicked ? "#7c3aed44" : "#7c3aed1f",
                }}
              />
              <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <Text style={[styles.body, { color: colors.foreground, textAlign: "left", fontSize: 14, flex: 1 }]} numberOfLines={2}>
                  {opt.label}
                </Text>
                {isPicked ? <Feather name="check" size={14} color="#7c3aed" /> : null}
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


function BlockView({ block, alias, allBlocks, openEmbed }: { block: BiolinkBlock; alias: string; allBlocks: BiolinkBlock[]; openEmbed: OpenEmbed }) {
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
    const url = publicBiolinkUrl(alias);
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
          Tap to open in-app
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

export default function BiolinkViewer() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { handle, t } = useLocalSearchParams<{ handle: string; t?: string }>();
  const alias = String(handle ?? "");
  const tableCode = t ? String(t) : "";
  const webTop = Platform.OS === "web" ? 67 : 0;

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
    if (q.data?.biolink.type === "restaurant_menu" && alias && !redirectedRef.current) {
      redirectedRef.current = true;
      const suffix = tableCode ? `?t=${encodeURIComponent(tableCode)}` : "";
      router.replace(`/restaurant/${alias}${suffix}` as any);
    }
  }, [q.data, alias, tableCode, router]);

  return (
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
                backgroundColor: "rgba(124,58,237,0.15)",
                borderWidth: 1,
                borderColor: "rgba(124,58,237,0.4)",
                marginBottom: 12,
              }}
            >
              <Text style={{ fontSize: 11, fontWeight: "700", color: "#a78bfa" }}>
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
