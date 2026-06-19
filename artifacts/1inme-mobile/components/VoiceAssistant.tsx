import { Feather } from "@expo/vector-icons";
import {
  AudioModule,
  RecordingPresets,
  setAudioModeAsync,
  useAudioPlayer,
  useAudioRecorder,
  useAudioRecorderState,
} from "expo-audio";
import * as FileSystem from "expo-file-system/legacy";
import * as Haptics from "expo-haptics";
import { useRouter } from "expo-router";
import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import {
  ActivityIndicator,
  Alert,
  AppState,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { Button } from "@/components/Button";
import { NfcWriteSheet } from "@/components/NfcWriteSheet";
import { useColors } from "@/hooks/useColors";
import {
  type VoiceCapabilities,
  type VoiceMessage,
  type VoicePendingConfirmation,
  type VoiceTurnResponse,
  fetchCapabilities,
  runTurn,
  wakeCheck,
} from "@/lib/api/voice";
import {
  getVoiceWakeWordEnabled,
  onVoiceWakeWordEnabledChange,
} from "@/lib/secure";

type Phase = "idle" | "listening" | "processing" | "speaking";
type View_ = "session" | "help";

type NfcRequest = { linkId: number; url: string } | null;

/**
 * Maps the web's `navigate_to` URLs (which are full https:// links to
 * the marketing site / user dashboard) to the mobile app's
 * expo-router paths. We do a simple substring match; anything that
 * doesn't match a known path is ignored to avoid jumping the user
 * out to a browser unexpectedly.
 */
function mapNavTarget(url: string | undefined): string | null {
  if (!url) return null;
  const u = url.toLowerCase();
  if (u.includes("/user/dashboard")) return "/(tabs)";
  if (u.includes("/user/links")) return "/(tabs)/links";
  if (u.includes("/user/inbox")) return "/(tabs)/inbox";
  if (u.includes("/user/notifications")) return "/(tabs)/notifications";
  if (u.includes("/user/wallet")) return "/wallet";
  if (u.includes("/user/ai/credits")) return "/wallet";
  if (u.includes("/user/ai/companion")) return "/ai-coach";
  if (u.includes("/user/ai/ask-coach") || u.includes("/user/ai/coach"))
    return "/ask-coach";
  if (u.includes("/user/ai/personas")) return "/ai-persona";
  if (u.includes("/user/upgrade") || u.includes("/user/plans"))
    return "/upgrade";
  if (u.includes("/user/profile") || u.includes("/user/workspaces"))
    return "/(tabs)/profile";
  return null;
}

/**
 * Friendly labels for the per-stage credit metering rows. Mirrors the
 * server's three feature codes (voice_stt / voice_llm / voice_tts) so
 * the user can see exactly where each credit went on this turn.
 */
/**
 * Number of bars in the live waveform under the mic. A small fixed
 * window keeps the layout stable and the visual readable on phones.
 */
const WAVEFORM_BARS = 28;

const STAGE_LABEL: Record<"stt" | "llm" | "tts", string> = {
  stt: "Voice transcription",
  llm: "Voice thinking",
  tts: "Voice speech",
};

export function VoiceAssistant() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();

  const [open, setOpen] = useState(false);
  const [view, setView] = useState<View_>("session");
  const [phase, setPhase] = useState<Phase>("idle");
  const [transcript, setTranscript] = useState<string>("");
  const [reply, setReply] = useState<string>("");
  const [error, setError] = useState<string | null>(null);
  const [history, setHistory] = useState<VoiceMessage[]>([]);
  const [pending, setPending] = useState<VoicePendingConfirmation[]>([]);
  const [lastCredits, setLastCredits] =
    useState<VoiceTurnResponse["credits"] | null>(null);
  const [balance, setBalance] = useState<number | null>(null);
  const [nfcReq, setNfcReq] = useState<NfcRequest>(null);
  const [capabilities, setCapabilities] = useState<VoiceCapabilities | null>(
    null,
  );
  const [capError, setCapError] = useState<string | null>(null);
  const [audioPath, setAudioPath] = useState<string | null>(null);

  // Used to resend the same audio after the user confirms a destructive
  // tool. We only need the latest URI/mime/filename, not full history.
  const lastClipRef = useRef<{
    uri: string;
    mime: string;
    filename: string;
  } | null>(null);

  const recordingOptions = useMemo(
    () => ({ ...RecordingPresets.HIGH_QUALITY, isMeteringEnabled: true }),
    [],
  );
  const recorder = useAudioRecorder(recordingOptions);
  const recorderState = useAudioRecorderState(recorder, 80);
  // Separate, lower-quality recorder dedicated to wake-phrase snippets
  // so it never collides with the in-session recorder above.
  const wakeRecorder = useAudioRecorder(RecordingPresets.LOW_QUALITY);
  const player = useAudioPlayer(audioPath ? { uri: audioPath } : null);

  // Rolling buffer of recent normalised mic levels (0..1) used to draw
  // the live waveform while the user is speaking. We keep a fixed number
  // of samples so the visualiser shifts left as new audio arrives.
  const [levels, setLevels] = useState<number[]>(() =>
    new Array(WAVEFORM_BARS).fill(0),
  );

  useEffect(() => {
    if (phase !== "listening") return;
    const m = recorderState?.metering;
    if (typeof m !== "number" || !Number.isFinite(m)) return;
    // expo-audio reports metering in dBFS (~ -160..0). Map -60..0 dB to
    // 0..1 with a small floor so even silence shows a faint baseline.
    const norm = Math.max(0.04, Math.min(1, (m + 60) / 60));
    setLevels((prev) => {
      const next = prev.slice(1);
      next.push(norm);
      return next;
    });
  }, [recorderState?.metering, phase]);

  useEffect(() => {
    if (phase === "idle" || phase === "processing") {
      setLevels((prev) =>
        prev.some((v) => v !== 0) ? new Array(WAVEFORM_BARS).fill(0) : prev,
      );
    }
  }, [phase]);

  const currentLevel = levels[levels.length - 1] ?? 0;

  const [wakeWordEnabled, setWakeWordEnabled] = useState(false);
  // Mirror in a ref for the long-running wake loop so it can read the
  // latest values without being torn down on every state change.
  const wakeEnabledRef = useRef(false);
  const sheetOpenRef = useRef(false);
  // When the wake loop matches, it sets this flag and opens the sheet.
  // A separate effect watches `open` flipping true and starts a real
  // turn — doing it inline would race with the wake-effect cleanup
  // that fires when `open` flips, swallowing the auto-start.
  const pendingAutoStartRef = useRef(false);
  useEffect(() => { wakeEnabledRef.current = wakeWordEnabled; }, [wakeWordEnabled]);
  useEffect(() => { sheetOpenRef.current = open; }, [open]);

  // Keep the in-memory wake-word toggle in sync with persisted state.
  // We must NOT depend on `open` here — that would tear down the
  // subscription whenever the sheet opens/closes. Instead we:
  //   1. Read once on mount.
  //   2. Subscribe to in-process changes (e.g. flipped from Settings).
  //   3. Re-read whenever the app foregrounds, in case storage was
  //      mutated while we were backgrounded.
  useEffect(() => {
    let cancelled = false;
    void getVoiceWakeWordEnabled().then((v) => {
      if (!cancelled) setWakeWordEnabled(v);
    });
    const off = onVoiceWakeWordEnabledChange((v) => {
      if (!cancelled) setWakeWordEnabled(v);
    });
    const sub = AppState.addEventListener("change", (state) => {
      if (state !== "active") return;
      void getVoiceWakeWordEnabled().then((v) => {
        if (!cancelled) setWakeWordEnabled(v);
      });
    });
    return () => {
      cancelled = true;
      off();
      sub.remove();
    };
  }, []);

  // Auto-play the latest TTS clip whenever the path changes.
  useEffect(() => {
    if (!audioPath) return;
    try {
      player.seekTo(0);
      player.play();
      setPhase("speaking");
      const sub = player.addListener("playbackStatusUpdate", (s) => {
        if (s.didJustFinish) {
          setPhase((p) => (p === "speaking" ? "idle" : p));
        }
      });
      return () => sub.remove();
    } catch {
      /* noop */
    }
  }, [audioPath, player]);

  const closeSheet = useCallback(() => {
    setOpen(false);
    setView("session");
    try {
      player.pause();
    } catch {
      /* noop */
    }
    if (recorder.isRecording) {
      void recorder.stop().catch(() => undefined);
    }
    setPhase("idle");
  }, [player, recorder]);

  /**
   * Decode the base64 mp3 from the server to a temp file and trigger
   * playback. Using legacy expo-file-system because its `writeAsStringAsync`
   * with EncodingType.Base64 is the most compatible across SDK 54+.
   */
  const playBase64 = useCallback(async (b64: string) => {
    try {
      const dir = (FileSystem as unknown as { cacheDirectory?: string })
        .cacheDirectory;
      const target = `${dir ?? ""}voice-reply-${Date.now()}.mp3`;
      await FileSystem.writeAsStringAsync(target, b64, {
        encoding: FileSystem.EncodingType.Base64,
      });
      setAudioPath(target);
    } catch (e) {
      // TTS is a nice-to-have — silent text reply is still useful.
      console.warn("[voice] failed to play TTS audio", e);
    }
  }, []);

  /** Drive a full turn: upload audio, render result, kick off TTS. */
  const sendClip = useCallback(
    async (
      clip: { uri: string; mime: string; filename: string },
      confirmedTools: Record<string, boolean> = {},
    ) => {
      setPhase("processing");
      setError(null);
      try {
        const out = await runTurn({
          ...clip,
          history,
          confirmedTools,
        });
        setTranscript(out.transcript);
        setReply(out.reply);
        setPending(out.pending_confirmations);
        setLastCredits(out.credits);
        setBalance(out.balance);
        setHistory(out.messages);

        // Drain tool side effects.
        for (const tr of out.tool_results) {
          if (tr.result.nfc_write) {
            setNfcReq({
              linkId: tr.result.nfc_write.link_id,
              url: tr.result.nfc_write.url,
            });
          }
          const target = mapNavTarget(tr.result.navigate_to);
          if (target) {
            // Defer so the modal stays visible briefly with the
            // "Opening …" reply before navigation.
            setTimeout(() => {
              try {
                router.push(target as never);
              } catch {
                /* noop */
              }
            }, 600);
          }
        }

        if (out.audio_base64) {
          void playBase64(out.audio_base64);
        } else {
          setPhase("idle");
        }
      } catch (e: unknown) {
        const err = e as { status?: number; message?: string } | undefined;
        if (err?.status === 402) {
          setError(
            err.message ?? "You're out of coins — top up to keep going.",
          );
        } else {
          setError(err?.message ?? "Something went wrong. Try again.");
        }
        setPhase("idle");
      }
    },
    [history, playBase64, router],
  );

  /** Begin recording. Requests permission + audio mode lazily. */
  const startListening = useCallback(async () => {
    setError(null);
    if (Platform.OS === "web") {
      setError("Voice isn't supported in the web preview yet.");
      return;
    }
    try {
      const perm = await AudioModule.requestRecordingPermissionsAsync();
      if (!perm.granted) {
        setError("Microphone permission is required for the Voice Assistant.");
        return;
      }
      await setAudioModeAsync({
        allowsRecording: true,
        playsInSilentMode: true,
      });
      await recorder.prepareToRecordAsync();
      recorder.record();
      setPhase("listening");
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } catch (e) {
      const msg = e instanceof Error ? e.message : String(e);
      setError(`Could not start recording: ${msg}`);
      setPhase("idle");
    }
  }, [recorder]);

  /** Stop recording and ship the clip to the server. */
  const stopAndSend = useCallback(async () => {
    if (!recorder.isRecording && phase !== "listening") return;
    try {
      await recorder.stop();
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      const uri = recorder.uri;
      if (!uri) {
        setError("Could not capture audio.");
        setPhase("idle");
        return;
      }
      const ext = uri.split(".").pop()?.toLowerCase() ?? "m4a";
      const mime = ext === "caf"
        ? "audio/x-caf"
        : ext === "wav"
        ? "audio/wav"
        : ext === "webm"
        ? "audio/webm"
        : ext === "mp3"
        ? "audio/mpeg"
        : "audio/mp4";
      const clip = { uri, mime, filename: `voice-${Date.now()}.${ext}` };
      lastClipRef.current = clip;
      await sendClip(clip);
    } catch (e) {
      const msg = e instanceof Error ? e.message : String(e);
      setError(`Recording failed: ${msg}`);
      setPhase("idle");
    }
  }, [phase, recorder, sendClip]);

  /**
   * Wake-word listener loop. While enabled and the sheet is closed,
   * we continuously record short ~2s audio snippets and ship them to
   * the server-side wake-phrase detector. The detector runs Whisper
   * but does NOT bill the user's credit ledger — only a real turn
   * (started after a wake match) costs voice_stt credits.
   *
   * The loop intentionally runs in the foreground only — it is torn
   * down whenever wake-word listening is disabled, the user opens the
   * sheet, or the component unmounts.
   */
  useEffect(() => {
    if (!wakeWordEnabled) return;
    if (Platform.OS === "web") return; // recording not supported in preview
    if (open) return;

    let cancelled = false;
    const SNIPPET_MS = 2000;
    const COOLDOWN_MS = 350;
    const FAILURE_BACKOFF_MS = 4000;

    const sleep = (ms: number) =>
      new Promise<void>((res) => setTimeout(res, ms));

    const loop = async () => {
      // Bail early if the OS denies the mic — we don't want to keep
      // poking permissions on every iteration.
      try {
        const perm = await AudioModule.requestRecordingPermissionsAsync();
        if (!perm.granted) return;
        await setAudioModeAsync({
          allowsRecording: true,
          playsInSilentMode: true,
        });
      } catch {
        return;
      }

      while (!cancelled && wakeEnabledRef.current && !sheetOpenRef.current) {
        try {
          await wakeRecorder.prepareToRecordAsync();
          wakeRecorder.record();
          await sleep(SNIPPET_MS);
          if (cancelled) break;
          await wakeRecorder.stop();
          const uri = wakeRecorder.uri;
          if (!uri) {
            await sleep(FAILURE_BACKOFF_MS);
            continue;
          }
          const ext = uri.split(".").pop()?.toLowerCase() ?? "m4a";
          const mime = ext === "caf"
            ? "audio/x-caf"
            : ext === "wav"
            ? "audio/wav"
            : ext === "webm"
            ? "audio/webm"
            : ext === "mp3"
            ? "audio/mpeg"
            : "audio/mp4";

          const out = await wakeCheck({
            uri,
            mime,
            filename: `wake-${Date.now()}.${ext}`,
          });
          // Best-effort cleanup — old snippets are throwaway.
          try { await FileSystem.deleteAsync(uri, { idempotent: true }); } catch { /* noop */ }

          if (cancelled || sheetOpenRef.current) break;
          if (out.matched) {
            void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
            // Hand off to the auto-start effect below — opening the
            // sheet here would otherwise tear down this effect and
            // cancel any inline startListening() call.
            pendingAutoStartRef.current = true;
            setView("session");
            setOpen(true);
            break;
          }
          await sleep(COOLDOWN_MS);
        } catch {
          // Recorder hiccup — back off briefly so we don't spin.
          try { await wakeRecorder.stop().catch(() => undefined); } catch { /* noop */ }
          await sleep(FAILURE_BACKOFF_MS);
        }
      }
    };

    void loop();

    return () => {
      cancelled = true;
      try {
        if (wakeRecorder.isRecording) {
          void wakeRecorder.stop().catch(() => undefined);
        }
      } catch { /* noop */ }
    };
  }, [wakeWordEnabled, open, wakeRecorder, startListening]);

  /**
   * Auto-start a session turn when a wake match opened the sheet.
   * Runs in its own effect so the wake loop's cleanup (triggered by
   * `open` flipping true) doesn't race with — and cancel — the call
   * to `startListening()`.
   */
  useEffect(() => {
    if (!open) return;
    if (!pendingAutoStartRef.current) return;
    pendingAutoStartRef.current = false;
    // Slight defer so the modal mount/animation completes before iOS
    // is asked to switch audio routes for recording.
    const t = setTimeout(() => { void startListening(); }, 250);
    return () => clearTimeout(t);
  }, [open, startListening]);

  /** Tap-to-toggle handler. Long-press uses startListening/stopAndSend. */
  const onMicTap = useCallback(() => {
    if (phase === "listening") void stopAndSend();
    else if (phase === "idle") void startListening();
  }, [phase, startListening, stopAndSend]);

  /** Approve a pending destructive tool by re-sending the same clip. */
  const approveAll = useCallback(async () => {
    if (!lastClipRef.current) return;
    const confirmed: Record<string, boolean> = {};
    for (const p of pending) confirmed[p.tool] = true;
    setPending([]);
    await sendClip(lastClipRef.current, confirmed);
  }, [pending, sendClip]);

  const declineAll = useCallback(() => {
    setPending([]);
    setReply("Okay, cancelled.");
  }, []);

  /** Lazy-load the help-view payload the first time it's opened. */
  const openHelp = useCallback(async () => {
    setView("help");
    if (capabilities) return;
    setCapError(null);
    try {
      const caps = await fetchCapabilities();
      setCapabilities(caps);
    } catch (e: unknown) {
      const err = e as { message?: string };
      setCapError(err.message ?? "Couldn't load voice capabilities.");
    }
  }, [capabilities]);

  const phaseLabel = useMemo(() => {
    switch (phase) {
      case "listening":
        return "Listening… tap or release to send";
      case "processing":
        return "Thinking…";
      case "speaking":
        return "Speaking…";
      default:
        return "Tap the mic to talk, or press and hold to push-to-talk";
    }
  }, [phase]);

  return (
    <>
      {/* ── Floating mic button ────────────────────────────────── */}
      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Open Voice Assistant"
        onPress={() => setOpen(true)}
        style={({ pressed }) => [
          styles.fab,
          {
            backgroundColor: colors.primary,
            bottom: insets.bottom + 84,
            opacity: pressed ? 0.85 : 1,
            shadowColor: colors.foreground,
          },
        ]}
      >
        <Feather name="mic" size={22} color={colors.primaryForeground} />
      </Pressable>

      {/* ── Voice sheet ────────────────────────────────────────── */}
      <Modal
        visible={open}
        transparent
        animationType="slide"
        onRequestClose={closeSheet}
      >
        <View style={styles.backdrop}>
          <View
            style={[
              styles.sheet,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                paddingBottom: insets.bottom + 16,
              },
            ]}
          >
            <View style={styles.header}>
              <Text style={[styles.title, { color: colors.foreground }]}>
                Voice Assistant
              </Text>
              <View style={styles.headerActions}>
                <Pressable
                  onPress={() => (view === "help" ? setView("session") : openHelp())}
                  hitSlop={12}
                  accessibilityLabel={
                    view === "help" ? "Back to session" : "What can I do?"
                  }
                >
                  <Feather
                    name={view === "help" ? "arrow-left" : "help-circle"}
                    size={20}
                    color={colors.mutedForeground}
                  />
                </Pressable>
                <Pressable onPress={closeSheet} hitSlop={12}>
                  <Feather name="x" size={22} color={colors.mutedForeground} />
                </Pressable>
              </View>
            </View>

            {view === "session" ? (
              <SessionView
                colors={colors}
                phase={phase}
                phaseLabel={phaseLabel}
                transcript={transcript}
                reply={reply}
                error={error}
                pending={pending}
                lastCredits={lastCredits}
                balance={balance}
                levels={levels}
                currentLevel={currentLevel}
                onMicTap={onMicTap}
                onMicLongPressIn={startListening}
                onMicLongPressOut={stopAndSend}
                onApprove={approveAll}
                onDecline={declineAll}
              />
            ) : (
              <HelpView
                colors={colors}
                capabilities={capabilities}
                error={capError}
              />
            )}
          </View>
        </View>
      </Modal>

      {/* ── NFC follow-up sheet (stacks on top) ───────────────── */}
      {nfcReq ? (
        <NfcWriteSheet
          visible
          url={nfcReq.url}
          linkId={nfcReq.linkId}
          onClose={() => setNfcReq(null)}
        />
      ) : null}
    </>
  );
}

/* ── Session view ──────────────────────────────────────────────── */

type ColorsT = ReturnType<typeof useColors>;

function SessionView(props: {
  colors: ColorsT;
  phase: Phase;
  phaseLabel: string;
  transcript: string;
  reply: string;
  error: string | null;
  pending: VoicePendingConfirmation[];
  lastCredits: VoiceTurnResponse["credits"] | null;
  balance: number | null;
  levels: number[];
  currentLevel: number;
  onMicTap: () => void;
  onMicLongPressIn: () => void;
  onMicLongPressOut: () => void;
  onApprove: () => void;
  onDecline: () => void;
}) {
  const {
    colors,
    phase,
    phaseLabel,
    transcript,
    reply,
    error,
    pending,
    lastCredits,
    balance,
    levels,
    currentLevel,
    onMicTap,
    onMicLongPressIn,
    onMicLongPressOut,
    onApprove,
    onDecline,
  } = props;

  const micColor =
    phase === "listening"
      ? "#dc2626"
      : phase === "processing"
      ? colors.mutedForeground
      : colors.primary;

  return (
    <ScrollView contentContainerStyle={{ gap: 14 }}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Talk"
        onPress={onMicTap}
        onLongPress={onMicLongPressIn}
        onPressOut={() => {
          if (phase === "listening") onMicLongPressOut();
        }}
        delayLongPress={200}
        style={[
          styles.bigMic,
          {
            borderColor: micColor,
            backgroundColor: colors.background,
            transform: [
              {
                scale:
                  phase === "listening" ? 1 + currentLevel * 0.18 : 1,
              },
            ],
          },
        ]}
      >
        {phase === "processing" ? (
          <ActivityIndicator color={micColor} size="large" />
        ) : (
          <Feather
            name={phase === "listening" ? "mic" : phase === "speaking" ? "volume-2" : "mic"}
            size={42}
            color={micColor}
          />
        )}
      </Pressable>

      {phase === "listening" ? (
        <Waveform levels={levels} color={micColor} />
      ) : null}

      <Text style={[styles.phase, { color: colors.mutedForeground }]}>
        {phaseLabel}
      </Text>

      {phase === "listening" ? (
        <Text
          style={[styles.partialTranscript, { color: colors.mutedForeground }]}
          accessibilityLiveRegion="polite"
        >
          Hearing you…
        </Text>
      ) : null}

      {error ? (
        <Text style={[styles.error, { color: "#dc2626" }]}>{error}</Text>
      ) : null}

      {transcript ? (
        <View
          style={[styles.bubble, { backgroundColor: colors.muted, alignSelf: "flex-end" }]}
        >
          <Text style={[styles.bubbleLabel, { color: colors.mutedForeground }]}>
            You said
          </Text>
          <Text style={[styles.bubbleText, { color: colors.foreground }]}>
            {transcript}
          </Text>
        </View>
      ) : null}

      {reply ? (
        <View
          style={[
            styles.bubble,
            {
              backgroundColor: colors.background,
              borderColor: colors.border,
              borderWidth: 1,
              alignSelf: "flex-start",
            },
          ]}
        >
          <Text style={[styles.bubbleLabel, { color: colors.mutedForeground }]}>
            1INME Voice
          </Text>
          <Text style={[styles.bubbleText, { color: colors.foreground }]}>
            {reply}
          </Text>
        </View>
      ) : null}

      {pending.length > 0 ? (
        <View
          style={[
            styles.confirmBox,
            { backgroundColor: colors.muted, borderColor: "#dc2626" },
          ]}
        >
          <Text style={[styles.confirmTitle, { color: colors.foreground }]}>
            Confirm action
          </Text>
          {pending.map((p) => (
            <Text
              key={p.tool}
              style={[styles.confirmDesc, { color: colors.mutedForeground }]}
            >
              {p.description || p.tool}
            </Text>
          ))}
          <View style={styles.confirmActions}>
            <Button label="Yes, do it" onPress={onApprove} />
            <Button label="Cancel" variant="outline" onPress={onDecline} />
          </View>
        </View>
      ) : null}

      {lastCredits ? (
        <View
          style={[
            styles.creditsBox,
            { backgroundColor: colors.muted, borderColor: colors.border },
          ]}
        >
          <Text style={[styles.creditsTitle, { color: colors.foreground }]}>
            Coins used this turn
          </Text>
          {(["stt", "llm", "tts"] as const).map((k) => (
            <View key={k} style={styles.creditsRow}>
              <Text
                style={[styles.creditsLabel, { color: colors.mutedForeground }]}
              >
                {STAGE_LABEL[k]}
              </Text>
              <Text style={[styles.creditsValue, { color: colors.foreground }]}>
                {lastCredits[k]}
              </Text>
            </View>
          ))}
          <View
            style={[styles.creditsRow, { borderTopColor: colors.border, borderTopWidth: 1, paddingTop: 6 }]}
          >
            <Text style={[styles.creditsLabel, { color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold" }]}>
              Total
            </Text>
            <Text style={[styles.creditsValue, { color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold" }]}>
              {lastCredits.total}
            </Text>
          </View>
          {balance !== null ? (
            <Text style={[styles.balanceLine, { color: colors.mutedForeground }]}>
              Balance: {balance.toLocaleString()} coins
            </Text>
          ) : null}
        </View>
      ) : null}
    </ScrollView>
  );
}

/* ── Live waveform ────────────────────────────────────────────── */

/**
 * Tiny pure-RN bar visualiser. Each bar's height is driven by a
 * normalised mic level (0..1) sampled from `useAudioRecorderState`.
 * No SVG / canvas / Skia dependency — just sized Views, which is
 * cheap enough to redraw at the ~12.5 Hz metering cadence we use.
 */
function Waveform({ levels, color }: { levels: number[]; color: string }) {
  return (
    <View style={styles.waveform} accessibilityElementsHidden>
      {levels.map((v, i) => (
        <View
          key={i}
          style={[
            styles.waveformBar,
            {
              height: Math.max(3, v * 36),
              backgroundColor: color,
              opacity: 0.35 + v * 0.65,
            },
          ]}
        />
      ))}
    </View>
  );
}

/* ── Help (capabilities) view ─────────────────────────────────── */

function HelpView(props: {
  colors: ColorsT;
  capabilities: VoiceCapabilities | null;
  error: string | null;
}) {
  const { colors, capabilities, error } = props;

  if (error) {
    return (
      <Text style={[styles.error, { color: "#dc2626" }]}>{error}</Text>
    );
  }
  if (!capabilities) {
    return (
      <View style={{ alignItems: "center", paddingVertical: 32 }}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={{ gap: 16, paddingBottom: 8 }}>
      <Text style={[styles.helpIntro, { color: colors.foreground }]}>
        Here&apos;s what 1INME Voice can and can&apos;t do for you right now.
      </Text>

      <View>
        <Text style={[styles.helpHeading, { color: colors.foreground }]}>I can</Text>
        {Object.entries(capabilities.tools).map(([category, tools]) => (
          <View key={category} style={{ marginTop: 8 }}>
            <Text style={[styles.helpCategory, { color: colors.mutedForeground }]}>
              {prettyCategory(category)}
            </Text>
            {tools.map((t) => (
              <View key={t.name} style={styles.helpRow}>
                <Feather
                  name={t.destructive ? "alert-circle" : "check-circle"}
                  size={14}
                  color={t.destructive ? "#dc2626" : "#16a34a"}
                />
                <Text style={[styles.helpRowText, { color: colors.foreground }]}>
                  {t.description}
                </Text>
              </View>
            ))}
          </View>
        ))}
      </View>

      <View>
        <Text style={[styles.helpHeading, { color: colors.foreground }]}>I can&apos;t</Text>
        {capabilities.limitations.map((l, i) => (
          <View key={i} style={styles.helpRow}>
            <Feather name="x-circle" size={14} color={colors.mutedForeground} />
            <Text style={[styles.helpRowText, { color: colors.foreground }]}>{l}</Text>
          </View>
        ))}
      </View>

      <Text style={[styles.helpFootnote, { color: colors.mutedForeground }]}>
        Pricing: ~{capabilities.pricing.stt_coins_per_minute} coins per
        minute of audio, ~{capabilities.pricing.tts_coins_per_1k_chars}{" "}
        coins per 1k characters spoken back. Up to{" "}
        {capabilities.rate_limit} turns per minute.
      </Text>
    </ScrollView>
  );
}

function prettyCategory(c: string): string {
  switch (c) {
    case "creator":
      return "Create & manage";
    case "viewer":
      return "Look things up";
    case "navigate":
      return "Get around the app";
    case "studio":
      return "AI Studio";
    case "billing":
      return "Plan & billing";
    case "admin":
      return "Admin";
    default:
      return c.charAt(0).toUpperCase() + c.slice(1);
  }
}

/* ── styles ────────────────────────────────────────────────────── */

const styles = StyleSheet.create({
  fab: {
    position: "absolute",
    right: 18,
    width: 52,
    height: 52,
    borderRadius: 26,
    alignItems: "center",
    justifyContent: "center",
    shadowOpacity: 0.25,
    shadowOffset: { width: 0, height: 4 },
    shadowRadius: 8,
    elevation: 6,
    zIndex: 999,
  },
  backdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.5)",
    justifyContent: "flex-end",
  },
  sheet: {
    borderTopLeftRadius: 22,
    borderTopRightRadius: 22,
    borderTopWidth: 1,
    padding: 20,
    gap: 12,
    maxHeight: "90%",
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  headerActions: { flexDirection: "row", alignItems: "center", gap: 16 },
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 18 },
  bigMic: {
    alignSelf: "center",
    width: 110,
    height: 110,
    borderRadius: 55,
    borderWidth: 3,
    alignItems: "center",
    justifyContent: "center",
    marginVertical: 6,
  },
  phase: {
    textAlign: "center",
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
  },
  waveform: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    height: 40,
    gap: 3,
  },
  waveformBar: {
    width: 3,
    borderRadius: 2,
  },
  partialTranscript: {
    textAlign: "center",
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    fontStyle: "italic",
  },
  error: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    textAlign: "center",
  },
  bubble: {
    borderRadius: 14,
    paddingHorizontal: 12,
    paddingVertical: 10,
    maxWidth: "92%",
    gap: 4,
  },
  bubbleLabel: { fontSize: 11, fontFamily: "SpaceGrotesk_500Medium" },
  bubbleText: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 20 },
  confirmBox: {
    borderRadius: 14,
    borderWidth: 1,
    padding: 12,
    gap: 8,
  },
  confirmTitle: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 14 },
  confirmDesc: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, lineHeight: 18 },
  confirmActions: { flexDirection: "row", gap: 8, marginTop: 4 },
  creditsBox: {
    borderRadius: 12,
    borderWidth: 1,
    padding: 12,
    gap: 6,
  },
  creditsTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  creditsRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  creditsLabel: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },
  creditsValue: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  balanceLine: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    marginTop: 4,
  },
  helpIntro: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 14 },
  helpHeading: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 15, marginBottom: 4 },
  helpCategory: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
    textTransform: "uppercase",
    letterSpacing: 0.6,
    marginBottom: 4,
  },
  helpRow: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 8,
    paddingVertical: 4,
  },
  helpRowText: {
    flex: 1,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 18,
  },
  helpFootnote: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 17,
    marginTop: 4,
  },
});
