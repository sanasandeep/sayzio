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
import { usePathname, useRouter } from "expo-router";
import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import {
  ActivityIndicator,
  AppState,
  FlatList,
  Image,
  Keyboard,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
  useWindowDimensions,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AiDisabledNotice } from "@/components/AiDisabledNotice";
import { Button } from "@/components/Button";
import { CoinCostHint, insufficientCoins } from "@/components/CoinCostHint";
import { NfcWriteSheet } from "@/components/NfcWriteSheet";
import { useAuth } from "@/contexts/AuthContext";
import { useColors } from "@/hooks/useColors";
import {
  type AssistantBlock,
  type AssistantBootstrap,
  type AssistantTurnResponse,
  type QuickContactChannel,
  assistantBootstrap,
  assistantChoice,
  assistantHandoff,
  assistantMessage,
  assistantSession,
} from "@/lib/api/assistant";
import {
  type VoiceCapabilities,
  type VoiceClientAction,
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
import { showAlert } from "@/lib/webAlert";

type Phase = "idle" | "listening" | "processing" | "speaking";
type View_ = "chat" | "voice" | "help";

type ChatBubble = {
  id: string;
  role: "user" | "assistant" | "error";
  text: string;
  blocks?: AssistantBlock[];
};

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
  if (u.includes("/user/ai/marketing-strategist"))
    return "/marketing-strategist";
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

/**
 * The surface the floating mic is currently driving. Screens call
 * `setVoiceSurface("companion")` on focus and `setVoiceSurface(null)`
 * on blur so the next voice turn tells the server which tools to
 * prefer (mirrors the web's `window.__voiceSurface`). Kept as a module
 * singleton because the assistant is mounted once, globally.
 */
let activeVoiceSurface: string | null = null;
export function setVoiceSurface(surface: string | null): void {
  activeVoiceSurface = surface;
}

/**
 * Lightweight client_action bus. The assistant emits the structured
 * intent a voice tool returns (search / select_link_type / wizard_*),
 * and whichever screen registered a handler via `onVoiceAction` acts on
 * it. Mirrors the web's `voice-action` CustomEvent + `@voice-action.window`
 * listeners. Last-writer-wins (one active surface at a time).
 */
type VoiceActionHandler = (action: VoiceClientAction) => void;
const voiceActionHandlers = new Set<VoiceActionHandler>();
export function onVoiceAction(handler: VoiceActionHandler): () => void {
  voiceActionHandlers.add(handler);
  return () => voiceActionHandlers.delete(handler);
}
function emitVoiceAction(action: VoiceClientAction): void {
  for (const h of voiceActionHandlers) {
    try {
      h(action);
    } catch {
      /* a misbehaving surface shouldn't break the turn */
    }
  }
}

export function VoiceAssistant() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const { height: windowHeight } = useWindowDimensions();
  const router = useRouter();
  const pathname = usePathname() ?? "";
  const { user } = useAuth();

  // ── Chat state ────────────────────────────────────────────────────
  const [chatMessages, setChatMessages] = useState<ChatBubble[]>([]);
  const [chatToken, setChatToken] = useState<string | null>(null);
  const [chatInput, setChatInput] = useState("");
  const [chatTyping, setChatTyping] = useState(false);
  const [chatBootstrap, setChatBootstrap] = useState<AssistantBootstrap | null>(null);
  const [chatBootstrapping, setChatBootstrapping] = useState(false);
  const [chatError, setChatError] = useState<string | null>(null);
  const [handoffOpen, setHandoffOpen] = useState(false);
  const chatScrollRef = useRef<ScrollView>(null);

  /** Current page context sent with every assistant request. */
  const pageContext = useMemo(
    () => ({ path: pathname }),
    [pathname],
  );

  /**
   * Initialise the chat session (bootstrap + session) the first time the
   * panel opens on the chat tab. Safe to call multiple times — bails early
   * when already bootstrapped.
   */
  const initChat = useCallback(async () => {
    if (chatToken || chatBootstrapping) return;
    setChatBootstrapping(true);
    setChatError(null);
    try {
      const [boot, sess] = await Promise.all([
        assistantBootstrap(),
        assistantSession({ page: pageContext }),
      ]);
      setChatBootstrap(boot);
      setChatToken(sess.visitor_token);
      // Show the greeting as the first assistant bubble.
      if (boot.greeting) {
        setChatMessages([
          {
            id: "greeting",
            role: "assistant",
            text: boot.greeting,
          },
        ]);
      }
    } catch {
      setChatError("Couldn't connect to Zio. Please try again.");
    } finally {
      setChatBootstrapping(false);
    }
  }, [chatToken, chatBootstrapping, pageContext]);

  const sendChatMessage = useCallback(
    async (text: string) => {
      if (!chatToken || !text.trim()) return;
      const userBubble: ChatBubble = {
        id: `u-${Date.now()}`,
        role: "user",
        text: text.trim(),
      };
      setChatMessages((prev) => [...prev, userBubble]);
      setChatInput("");
      setChatTyping(true);
      Keyboard.dismiss();
      try {
        const res = await assistantMessage({
          visitorToken: chatToken,
          message: text.trim(),
          page: pageContext,
        });
        const asBubble = turnToBubble(res);
        setChatMessages((prev) => [...prev, asBubble]);
        if (res.handoff_open) setHandoffOpen(true);
      } catch {
        setChatMessages((prev) => [
          ...prev,
          { id: `err-${Date.now()}`, role: "error", text: "Something went wrong. Please try again." },
        ]);
      } finally {
        setChatTyping(false);
        setTimeout(() => chatScrollRef.current?.scrollToEnd({ animated: true }), 100);
      }
    },
    [chatToken, pageContext],
  );

  const sendChatChoice = useCallback(
    async (choice: { label?: string; value?: string; template?: string }) => {
      if (!chatToken) return;
      const label = choice.label ?? choice.value ?? "Selected";
      const userBubble: ChatBubble = {
        id: `u-${Date.now()}`,
        role: "user",
        text: label,
      };
      setChatMessages((prev) => [...prev, userBubble]);
      setChatTyping(true);
      try {
        const res = await assistantChoice({
          visitorToken: chatToken,
          choice,
          page: pageContext,
        });
        const asBubble = turnToBubble(res);
        setChatMessages((prev) => [...prev, asBubble]);
        if (res.handoff_open) setHandoffOpen(true);
      } catch {
        setChatMessages((prev) => [
          ...prev,
          { id: `err-${Date.now()}`, role: "error", text: "Something went wrong. Please try again." },
        ]);
      } finally {
        setChatTyping(false);
        setTimeout(() => chatScrollRef.current?.scrollToEnd({ animated: true }), 100);
      }
    },
    [chatToken, pageContext],
  );

  const sendChatHandoff = useCallback(
    async (params: {
      channel: QuickContactChannel;
      name?: string;
      email?: string;
      phone?: string;
      message?: string;
    }) => {
      if (!chatToken) return;
      setChatTyping(true);
      try {
        const res = await assistantHandoff({
          visitorToken: chatToken,
          ...params,
          page: pageContext,
        });
        setHandoffOpen(false);
        setChatMessages((prev) => [
          ...prev,
          {
            id: `ha-${Date.now()}`,
            role: "assistant",
            text: res.reply ?? "Got it! Our team will be in touch soon.",
          },
        ]);
      } catch {
        showAlert("Couldn't submit", "Please try again.");
      } finally {
        setChatTyping(false);
        setTimeout(() => chatScrollRef.current?.scrollToEnd({ animated: true }), 100);
      }
    },
    [chatToken, pageContext],
  );

  const [open, setOpen] = useState(false);
  // Chat is the default view; voice and help are additional tabs.
  const [handsFree, setHandsFree] = useState(false);
  const handsFreeRef = useRef(false);
  useEffect(() => {
    handsFreeRef.current = handsFree;
  }, [handsFree]);
  const pendingRef = useRef(false);
  // Holds the latest startListening callback so the audio-finished
  // handler can chain into it without a use-before-declaration cycle.
  const startListeningRef = useRef<(() => void) | null>(null);
  const [view, setView] = useState<View_>("chat");
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
  // Engine/plan state for Voice. `null` until the first capabilities
  // fetch resolves; we fail open (treat as enabled) on error so a
  // network blip never wrongly hides the assistant.
  const aiEnabled = capabilities ? capabilities.enabled : null;
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

  // Probe Voice availability once on mount so we can show an "AI off"
  // dot on the launcher and the disabled explainer inside the sheet
  // before the user records anything. Fails open (no dot) on error.
  useEffect(() => {
    let cancelled = false;
    void fetchCapabilities()
      .then((caps) => {
        if (!cancelled) setCapabilities(caps);
      })
      .catch(() => {
        /* fail open — keep the assistant usable on a transient error */
      });
    return () => {
      cancelled = true;
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
          // Hands-free: chain straight into the next listen once the
          // reply finishes, unless a confirmation is awaiting the user.
          if (handsFreeRef.current && !pendingRef.current) {
            setTimeout(() => startListeningRef.current?.(), 350);
          }
        }
      });
      return () => sub.remove();
    } catch {
      /* noop */
    }
  }, [audioPath, player]);

  const closeSheet = useCallback(() => {
    setOpen(false);
    setView("chat");
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
          surface: activeVoiceSurface ?? undefined,
        });
        setTranscript(out.transcript);
        setReply(out.reply);
        setPending(out.pending_confirmations);
        // Mirror into a ref so the hands-free chain can pause itself
        // while a destructive tool awaits confirmation.
        pendingRef.current = (out.pending_confirmations?.length ?? 0) > 0;
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
          // Drive the active surface (wizard / create-link / search)
          // with any structured intent the tool returned.
          if (tr.result.client_action) {
            const action = tr.result.client_action;
            setTimeout(() => emitVoiceAction(action), 300);
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
            err.message ?? "You're out of coins. Top up to keep going.",
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
        setError("Microphone permission is required for the AI Voice Assistant.");
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

  // Keep the ref pointed at the latest startListening so the hands-free
  // audio-finished handler (declared earlier) can chain into it.
  useEffect(() => {
    startListeningRef.current = () => {
      void startListening();
    };
  }, [startListening]);

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
            setView("voice");
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
      {/* ── Floating Zio mascot button ─────────────────────────── */}
      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Open Zio Assistant"
        onPress={() => {
          setOpen(true);
          void initChat();
        }}
        style={({ pressed }) => [
          styles.fab,
          {
            bottom: insets.bottom + 84,
            opacity: pressed ? 0.75 : 1,
          },
        ]}
      >
        <Image
          source={require("../assets/images/zio-bot.png")}
          style={styles.fabMascot}
          resizeMode="contain"
          accessibilityElementsHidden
        />
        {aiEnabled === false ? (
          <View
            style={[
              styles.fabOffBadge,
              { backgroundColor: colors.mutedForeground, borderColor: colors.card },
            ]}
            accessibilityLabel="AI is off"
          >
            <Feather name="slash" size={9} color={colors.card} />
          </View>
        ) : null}
      </Pressable>

      {/* ── Zio assistant sheet ────────────────────────────────── */}
      <Modal
        visible={open}
        transparent
        animationType="slide"
        statusBarTranslucent
        navigationBarTranslucent
        onRequestClose={closeSheet}
      >
        <KeyboardAvoidingView
          style={styles.backdrop}
          behavior={Platform.OS === "ios" ? "padding" : undefined}
        >
          <View
            style={[
              styles.sheet,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                paddingBottom: insets.bottom + 16,
                // Open as a tall panel (like the desktop widget) with an
                // explicit pixel height. Percentage heights can resolve
                // against a frame taller than the visible window on Android
                // edge-to-edge, which clipped the composer off-screen.
                height: Math.round(windowHeight * 0.86),
              },
            ]}
          >
            {/* Header row */}
            <View style={styles.header}>
              <View style={styles.headerLeft}>
                <Image
                  source={require("../assets/images/zio-bot-peek.png")}
                  style={styles.headerMascot}
                  resizeMode="contain"
                  accessibilityElementsHidden
                />
                <Text style={[styles.title, { color: colors.foreground }]}>
                  Ask Zio
                </Text>
              </View>
              <View style={styles.headerActions}>
                {/* Hands-free toggle shown only on the voice tab */}
                {view === "voice" && aiEnabled !== false ? (
                  <Pressable
                    onPress={() => setHandsFree((v) => !v)}
                    hitSlop={12}
                    accessibilityLabel={
                      handsFree ? "Turn off hands-free" : "Turn on hands-free"
                    }
                  >
                    <Feather
                      name={handsFree ? "radio" : "mic"}
                      size={20}
                      color={handsFree ? colors.primary : colors.mutedForeground}
                    />
                  </Pressable>
                ) : null}
                {/* Help icon: toggles help view, returns to prior tab */}
                <Pressable
                  onPress={() =>
                    view === "help" ? setView("chat") : openHelp()
                  }
                  hitSlop={12}
                  accessibilityLabel={
                    view === "help" ? "Back" : "What can Zio do?"
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

            {/* Chat / Voice tab pills */}
            {view !== "help" ? (
              <View style={styles.tabRow}>
                <Pressable
                  style={[
                    styles.tabPill,
                    {
                      backgroundColor:
                        view === "chat" ? colors.primary : colors.muted,
                    },
                  ]}
                  onPress={() => {
                    setView("chat");
                    void initChat();
                  }}
                  accessibilityRole="tab"
                  accessibilityState={{ selected: view === "chat" }}
                >
                  <Feather
                    name="message-circle"
                    size={13}
                    color={view === "chat" ? "#fff" : colors.mutedForeground}
                  />
                  <Text
                    style={[
                      styles.tabLabel,
                      {
                        color:
                          view === "chat" ? "#fff" : colors.mutedForeground,
                      },
                    ]}
                  >
                    Chat
                  </Text>
                </Pressable>
                <Pressable
                  style={[
                    styles.tabPill,
                    {
                      backgroundColor:
                        view === "voice" ? colors.primary : colors.muted,
                    },
                  ]}
                  onPress={() => setView("voice")}
                  accessibilityRole="tab"
                  accessibilityState={{ selected: view === "voice" }}
                >
                  <Feather
                    name="mic"
                    size={13}
                    color={view === "voice" ? "#fff" : colors.mutedForeground}
                  />
                  <Text
                    style={[
                      styles.tabLabel,
                      {
                        color:
                          view === "voice" ? "#fff" : colors.mutedForeground,
                      },
                    ]}
                  >
                    Voice
                  </Text>
                </Pressable>
              </View>
            ) : null}

            {/* Active view */}
            {view === "chat" ? (
              <ChatView
                colors={colors}
                messages={chatMessages}
                typing={chatTyping}
                bootstrapping={chatBootstrapping}
                bootstrapError={chatError}
                bootstrap={chatBootstrap}
                input={chatInput}
                onChangeInput={setChatInput}
                onSend={() => void sendChatMessage(chatInput)}
                onChoice={sendChatChoice}
                handoffOpen={handoffOpen}
                handoffEnabled={chatBootstrap?.handoff_enabled ?? false}
                onOpenHandoff={() => setHandoffOpen(true)}
                onCloseHandoff={() => setHandoffOpen(false)}
                onSubmitHandoff={sendChatHandoff}
                scrollRef={chatScrollRef}
              />
            ) : view === "voice" ? (
              aiEnabled === false ? (
                <AiDisabledNotice feature="Voice" compact />
              ) : (
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
                  coinCost={capabilities?.coin_cost ?? null}
                  coinBalance={balance ?? capabilities?.coin_balance ?? null}
                  levels={levels}
                  currentLevel={currentLevel}
                  onMicTap={onMicTap}
                  onMicLongPressIn={startListening}
                  onMicLongPressOut={stopAndSend}
                  onApprove={approveAll}
                  onDecline={declineAll}
                />
              )
            ) : (
              <HelpView
                colors={colors}
                capabilities={capabilities}
                error={capError}
              />
            )}
          </View>
        </KeyboardAvoidingView>
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
  /** Worst-case coins one full voice turn may spend (server estimate). */
  coinCost: number | null;
  /** Freshest known wallet balance (post-turn balance, else loader's). */
  coinBalance: number | null;
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
    coinCost,
    coinBalance,
    levels,
    currentLevel,
    onMicTap,
    onMicLongPressIn,
    onMicLongPressOut,
    onApprove,
    onDecline,
  } = props;

  // Block STARTING a new turn when the wallet can't cover the worst case;
  // never block stopping/sending a turn that is already in flight.
  const short = insufficientCoins(coinCost, coinBalance);
  const micDisabled = short && phase === "idle";

  const micColor =
    phase === "listening"
      ? "#dc2626"
      : phase === "processing"
      ? colors.mutedForeground
      : micDisabled
      ? colors.mutedForeground
      : colors.primary;

  return (
    <ScrollView contentContainerStyle={{ gap: 14 }}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Talk"
        accessibilityState={{ disabled: micDisabled }}
        disabled={micDisabled}
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

      <View style={{ alignItems: "center" }}>
        <CoinCostHint
          cost={coinCost}
          balance={coinBalance}
          actionLabel="a voice turn"
          verb="voice turn"
          testID="voice-assistant-coins"
        />
      </View>

      {phase === "listening" ? (
        <Text
          style={[styles.partialTranscript, { color: colors.mutedForeground }]}
          accessibilityLiveRegion="polite"
        >
          Hearing you…
        </Text>
      ) : null}

      {error ? (
        <Text style={[styles.error, { color: colors.destructive }]}>{error}</Text>
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
            Sayzio Voice
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
            { backgroundColor: colors.muted, borderColor: colors.destructive },
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
      <Text style={[styles.error, { color: colors.destructive }]}>{error}</Text>
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
        Here&apos;s what Sayzio Voice can and can&apos;t do for you right now.
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
                  color={t.destructive ? colors.destructive : colors.success}
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

/* ── Helpers ────────────────────────────────────────────────────── */

/** Convert a raw assistant turn response into a local ChatBubble. */
function turnToBubble(res: AssistantTurnResponse): ChatBubble {
  if (!res.ok) {
    return {
      id: `err-${Date.now()}`,
      role: "error",
      text: res.error ?? "Something went wrong. Please try again.",
    };
  }
  return {
    id: `a-${Date.now()}`,
    role: "assistant",
    text: res.reply ?? "",
    blocks: res.blocks?.length ? res.blocks : undefined,
  };
}

/* ── ChatView ────────────────────────────────────────────────────── */

type ChatViewProps = {
  colors: ColorsT;
  messages: ChatBubble[];
  typing: boolean;
  bootstrapping: boolean;
  bootstrapError: string | null;
  bootstrap: AssistantBootstrap | null;
  input: string;
  onChangeInput: (t: string) => void;
  onSend: () => void;
  onChoice: (choice: { label?: string; value?: string; template?: string }) => void;
  handoffOpen: boolean;
  handoffEnabled: boolean;
  onOpenHandoff: () => void;
  onCloseHandoff: () => void;
  onSubmitHandoff: (params: {
    channel: QuickContactChannel;
    name?: string;
    email?: string;
    phone?: string;
    message?: string;
  }) => void;
  scrollRef: React.RefObject<ScrollView | null>;
};

function ChatView(props: ChatViewProps) {
  const {
    colors,
    messages,
    typing,
    bootstrapping,
    bootstrapError,
    bootstrap,
    input,
    onChangeInput,
    onSend,
    onChoice,
    handoffOpen,
    handoffEnabled,
    onOpenHandoff,
    onCloseHandoff,
    onSubmitHandoff,
    scrollRef,
  } = props;

  const starterPrompts = bootstrap?.starter_prompts ?? [];

  if (bootstrapping) {
    return (
      <View style={{ alignItems: "center", paddingVertical: 40 }}>
        <ActivityIndicator color={colors.primary} />
        <Text
          style={[
            styles.chatBubbleText,
            { color: colors.mutedForeground, marginTop: 10 },
          ]}
        >
          Connecting to Zio…
        </Text>
      </View>
    );
  }

  if (bootstrapError) {
    return (
      <View style={{ alignItems: "center", paddingVertical: 24, gap: 10 }}>
        <Text style={[styles.chatBubbleText, { color: colors.destructive }]}>
          {bootstrapError}
        </Text>
      </View>
    );
  }

  const lastMsg = messages[messages.length - 1];
  const choiceBlock =
    lastMsg?.role === "assistant" && lastMsg.blocks
      ? (lastMsg.blocks.find((b) => b.type === "buttons") as
          | { type: "buttons"; items: { label: string; value: string; template?: string }[] }
          | undefined)
      : undefined;

  return (
    <View style={styles.chatContainer}>
      {/* Message list */}
      <ScrollView
        ref={scrollRef}
        style={styles.chatScroll}
        contentContainerStyle={styles.chatScrollContent}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
        onContentSizeChange={() =>
          scrollRef.current?.scrollToEnd({ animated: true })
        }
      >
        {messages.map((msg) => {
          if (msg.role === "user") {
            return (
              <View
                key={msg.id}
                style={[
                  styles.chatBubbleUser,
                  { backgroundColor: colors.primary },
                ]}
              >
                <Text style={[styles.chatBubbleText, { color: "#fff" }]}>
                  {msg.text}
                </Text>
              </View>
            );
          }
          if (msg.role === "error") {
            return (
              <View
                key={msg.id}
                style={[
                  styles.chatBubbleError,
                  { backgroundColor: colors.muted },
                ]}
              >
                <Text
                  style={[
                    styles.chatBubbleText,
                    { color: colors.destructive, fontSize: 13 },
                  ]}
                >
                  {msg.text}
                </Text>
              </View>
            );
          }
          // assistant bubble
          return (
            <View
              key={msg.id}
              style={[
                styles.chatBubbleAssistant,
                {
                  backgroundColor: colors.background,
                  borderColor: colors.border,
                },
              ]}
            >
              {msg.text ? (
                <Text style={[styles.chatBubbleText, { color: colors.foreground }]}>
                  {msg.text}
                </Text>
              ) : null}
            </View>
          );
        })}

        {/* Typing indicator */}
        {typing ? (
          <View
            style={[
              styles.chatBubbleAssistant,
              {
                backgroundColor: colors.background,
                borderColor: colors.border,
              },
            ]}
          >
            <Text style={[styles.chatTypingDots, { color: colors.mutedForeground }]}>
              · · ·
            </Text>
          </View>
        ) : null}
      </ScrollView>

      {/* Choice buttons from last assistant block */}
      {choiceBlock && !typing ? (
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
          {choiceBlock.items.map((item) => (
            <Pressable
              key={item.value}
              style={({ pressed }) => [
                styles.chatChoiceBtn,
                {
                  borderColor: colors.primary,
                  backgroundColor: pressed
                    ? colors.primary
                    : colors.background,
                },
              ]}
              onPress={() =>
                onChoice({
                  label: item.label,
                  value: item.value,
                  template: item.template,
                })
              }
            >
              <Text
                style={[styles.chatChoiceBtnText, { color: colors.primary }]}
              >
                {item.label}
              </Text>
            </Pressable>
          ))}
        </View>
      ) : null}

      {/* Starter prompts — shown only when there are no messages yet */}
      {messages.length === 0 && starterPrompts.length > 0 ? (
        <View style={styles.chatHintsRow}>
          {starterPrompts.slice(0, 4).map((p, i) => (
            <Pressable
              key={i}
              style={[
                styles.chatHint,
                { borderColor: colors.border, backgroundColor: colors.muted },
              ]}
              onPress={() => onChoice({ value: p, label: p })}
            >
              <Text style={[styles.chatHintText, { color: colors.foreground }]}>
                {p}
              </Text>
            </Pressable>
          ))}
        </View>
      ) : null}

      {/* Handoff form */}
      {handoffOpen ? (
        <HandoffView
          colors={colors}
          note={bootstrap?.handoff_note}
          onSubmit={onSubmitHandoff}
          onCancel={onCloseHandoff}
        />
      ) : null}

      {/* Input row */}
      {!handoffOpen ? (
        <View
          style={[
            styles.chatInputRow,
            { borderTopColor: colors.border },
          ]}
        >
          <TextInput
            style={[
              styles.chatInput,
              {
                borderColor: colors.border,
                backgroundColor: colors.muted,
                color: colors.foreground,
              },
            ]}
            value={input}
            onChangeText={onChangeInput}
            placeholder={bootstrap?.input_placeholder ?? "Ask anything…"}
            placeholderTextColor={colors.mutedForeground}
            multiline
            returnKeyType="send"
            onSubmitEditing={onSend}
            blurOnSubmit
            editable={!typing}
            accessibilityLabel="Message input"
          />
          <Pressable
            style={[
              styles.chatSendBtn,
              {
                backgroundColor:
                  input.trim() && !typing ? colors.primary : colors.muted,
              },
            ]}
            onPress={onSend}
            disabled={!input.trim() || typing}
            accessibilityLabel="Send message"
          >
            <Feather
              name="send"
              size={16}
              color={input.trim() && !typing ? "#fff" : colors.mutedForeground}
            />
          </Pressable>
          {handoffEnabled ? (
            <Pressable
              style={[
                styles.chatSendBtn,
                { backgroundColor: colors.muted },
              ]}
              onPress={onOpenHandoff}
              accessibilityLabel="Contact support"
            >
              <Feather name="phone" size={16} color={colors.mutedForeground} />
            </Pressable>
          ) : null}
        </View>
      ) : null}
    </View>
  );
}

/* ── HandoffView ──────────────────────────────────────────────────── */

type HandoffViewProps = {
  colors: ColorsT;
  note?: string;
  onSubmit: (params: {
    channel: QuickContactChannel;
    name?: string;
    email?: string;
    phone?: string;
    message?: string;
  }) => void;
  onCancel: () => void;
};

const HANDOFF_CHANNELS: { value: QuickContactChannel; label: string }[] = [
  { value: "email", label: "Email" },
  { value: "callback", label: "Call back" },
  { value: "whatsapp", label: "WhatsApp" },
];

function HandoffView({ colors, note, onSubmit, onCancel }: HandoffViewProps) {
  const [channel, setChannel] = useState<QuickContactChannel>("email");
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [message, setMessage] = useState("");

  const valid =
    name.trim().length > 0 &&
    (channel === "email" ? email.trim().length > 0 : phone.trim().length > 0);

  return (
    <View
      style={[
        styles.handoffContainer,
        { borderColor: colors.border, backgroundColor: colors.muted },
      ]}
    >
      <Text style={[styles.handoffTitle, { color: colors.foreground }]}>
        Get in touch
      </Text>
      {note ? (
        <Text style={[styles.handoffNote, { color: colors.mutedForeground }]}>
          {note}
        </Text>
      ) : null}

      {/* Channel selector */}
      <View style={styles.handoffChannelRow}>
        {HANDOFF_CHANNELS.map((ch) => (
          <Pressable
            key={ch.value}
            style={[
              styles.handoffChannelPill,
              {
                borderColor:
                  channel === ch.value ? colors.primary : colors.border,
                backgroundColor:
                  channel === ch.value ? colors.primary : colors.background,
              },
            ]}
            onPress={() => setChannel(ch.value)}
          >
            <Text
              style={[
                styles.handoffChannelLabel,
                { color: channel === ch.value ? "#fff" : colors.foreground },
              ]}
            >
              {ch.label}
            </Text>
          </Pressable>
        ))}
      </View>

      <TextInput
        style={[
          styles.handoffInput,
          { borderColor: colors.border, color: colors.foreground, backgroundColor: colors.background },
        ]}
        value={name}
        onChangeText={setName}
        placeholder="Your name"
        placeholderTextColor={colors.mutedForeground}
      />
      {channel === "email" ? (
        <TextInput
          style={[
            styles.handoffInput,
            { borderColor: colors.border, color: colors.foreground, backgroundColor: colors.background },
          ]}
          value={email}
          onChangeText={setEmail}
          placeholder="Email address"
          placeholderTextColor={colors.mutedForeground}
          keyboardType="email-address"
          autoCapitalize="none"
        />
      ) : (
        <TextInput
          style={[
            styles.handoffInput,
            { borderColor: colors.border, color: colors.foreground, backgroundColor: colors.background },
          ]}
          value={phone}
          onChangeText={setPhone}
          placeholder={
            channel === "whatsapp"
              ? "Phone with country code (+91…)"
              : "10-digit mobile number"
          }
          placeholderTextColor={colors.mutedForeground}
          keyboardType="phone-pad"
        />
      )}
      <TextInput
        style={[
          styles.handoffInput,
          { borderColor: colors.border, color: colors.foreground, backgroundColor: colors.background },
        ]}
        value={message}
        onChangeText={setMessage}
        placeholder="Message (optional)"
        placeholderTextColor={colors.mutedForeground}
        multiline
        numberOfLines={2}
      />

      <View style={styles.handoffActions}>
        <Button
          label="Submit"
          onPress={() => {
            if (!valid) return;
            onSubmit({
              channel,
              name: name.trim(),
              email: channel === "email" ? email.trim() : undefined,
              phone:
                channel !== "email" ? phone.trim() : undefined,
              message: message.trim() || undefined,
            });
          }}
        />
        <Button label="Cancel" variant="outline" onPress={onCancel} />
      </View>
    </View>
  );
}

/* ── styles ────────────────────────────────────────────────────── */

const styles = StyleSheet.create({
  fab: {
    position: "absolute",
    right: 14,
    width: 64,
    height: 64,
    alignItems: "center",
    justifyContent: "center",
    zIndex: 999,
  },
  fabMascot: {
    width: 64,
    height: 64,
  },
  fabOffBadge: {
    position: "absolute",
    top: 0,
    right: 0,
    width: 18,
    height: 18,
    borderRadius: 9,
    borderWidth: 2,
    alignItems: "center",
    justifyContent: "center",
  },
  headerMascot: {
    width: 28,
    height: 28,
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
  headerLeft: { flexDirection: "row", alignItems: "center", gap: 8 },
  headerActions: { flexDirection: "row", alignItems: "center", gap: 16 },
  tabRow: {
    flexDirection: "row",
    gap: 8,
    marginTop: 2,
    marginBottom: 4,
  },
  tabPill: {
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: 20,
  },
  tabLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 13,
  },
  /* ── Chat view styles ──────────────────────────────────────── */
  chatContainer: {
    flex: 1,
    gap: 8,
  },
  chatScroll: {
    flex: 1,
    minHeight: 220,
  },
  chatScrollContent: {
    gap: 10,
    paddingVertical: 4,
  },
  chatBubbleUser: {
    alignSelf: "flex-end",
    borderRadius: 16,
    borderBottomRightRadius: 4,
    paddingHorizontal: 14,
    paddingVertical: 10,
    maxWidth: "82%",
  },
  chatBubbleAssistant: {
    alignSelf: "flex-start",
    borderRadius: 16,
    borderBottomLeftRadius: 4,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderWidth: 1,
    maxWidth: "86%",
    gap: 8,
  },
  chatBubbleError: {
    alignSelf: "center",
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    maxWidth: "90%",
  },
  chatBubbleText: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    lineHeight: 20,
  },
  chatTypingDots: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 18,
    letterSpacing: 4,
  },
  chatInputRow: {
    flexDirection: "row",
    alignItems: "flex-end",
    gap: 8,
    borderTopWidth: 1,
    paddingTop: 10,
    marginTop: 4,
  },
  chatInput: {
    flex: 1,
    borderRadius: 20,
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 9,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    lineHeight: 18,
    maxHeight: 96,
  },
  chatSendBtn: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: "center",
    justifyContent: "center",
  },
  chatHintsRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 6,
    marginTop: 4,
  },
  chatHint: {
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 11,
    paddingVertical: 6,
  },
  chatHintText: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
  },
  chatChoiceBtn: {
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 8,
    marginTop: 4,
  },
  chatChoiceBtnText: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
  },
  /* ── Handoff form styles ─────────────────────────────────── */
  handoffContainer: {
    borderRadius: 14,
    borderWidth: 1,
    padding: 14,
    gap: 10,
    marginTop: 4,
  },
  handoffTitle: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 14,
  },
  handoffNote: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 16,
  },
  handoffChannelRow: {
    flexDirection: "row",
    gap: 8,
    flexWrap: "wrap",
  },
  handoffChannelPill: {
    borderRadius: 14,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  handoffChannelLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
  },
  handoffInput: {
    borderRadius: 10,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 9,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
  },
  handoffActions: {
    flexDirection: "row",
    gap: 8,
    marginTop: 2,
  },
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
