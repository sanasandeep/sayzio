import { Feather } from "@expo/vector-icons";
import * as Haptics from "expo-haptics";
import {
  AudioModule,
  RecordingPresets,
  createAudioPlayer,
  setAudioModeAsync,
  useAudioRecorder,
  useAudioRecorderState,
} from "expo-audio";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Animated,
  Easing,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";
import {
  type VoiceCapabilities,
  type VoiceCredits,
  type VoiceMessage,
  type VoicePendingConfirmation,
  voiceAssistant,
} from "@/lib/api";

/**
 * Floating tap-to-talk Voice Assistant — mobile parity for the web's
 * floating mic widget. Mounted at the tab layout level so every main
 * app screen shows the mic above the bottom tab bar.
 *
 *   tap         → start recording (asks mic permission first)
 *   tap again   → stop, upload, transcribe, get spoken reply
 *   long-press  → open the chat / capabilities panel without recording
 *
 * Destructive tools come back as `pending_confirmations` and are
 * rendered as Confirm/Cancel chips inside the panel — tapping Confirm
 * replays the same audio with `confirmed_tools[name]=true`, exactly
 * like the web widget. AI credit spend is reported by the server and
 * shown under each turn so the user can see STT/LLM/TTS line items.
 */
export function VoiceAssistant() {
  const colors = useColors();
  const insets = useSafeAreaInsets();

  const recorder = useAudioRecorder(RecordingPresets.HIGH_QUALITY);
  const recState = useAudioRecorderState(recorder);

  const [panelOpen, setPanelOpen] = useState(false);
  const [tab, setTab] = useState<"chat" | "caps">("chat");
  const [busy, setBusy] = useState(false);
  const [status, setStatus] = useState<string | null>(null);
  const [messages, setMessages] = useState<VoiceMessage[]>([]);
  const [pending, setPending] = useState<VoicePendingConfirmation[]>([]);
  const [lastCredits, setLastCredits] = useState<VoiceCredits | null>(null);
  const [balance, setBalance] = useState<number | null>(null);
  const [caps, setCaps] = useState<VoiceCapabilities | null>(null);
  const [capsLoading, setCapsLoading] = useState(false);
  // null = still checking, false = hide the mic entirely (plan/feature
  // doesn't include Voice Assistant), true = show it.
  const [enabled, setEnabled] = useState<boolean | null>(null);

  useEffect(() => {
    let alive = true;
    voiceAssistant
      .capabilities()
      .then((r) => {
        if (!alive) return;
        setEnabled(!!r.enabled);
        setCaps(r);
        if (typeof r.balance === "number") setBalance(r.balance);
      })
      .catch(() => {
        if (alive) setEnabled(false);
      });
    return () => {
      alive = false;
    };
  }, []);

  // We re-send the same recording when the user confirms a destructive
  // tool, mirroring `lastAudio` in the Alpine widget.
  const lastAudioRef = useRef<{ uri: string; mime: string } | null>(null);
  const playerRef = useRef<ReturnType<typeof createAudioPlayer> | null>(null);

  // Pulsing red ring while recording.
  const pulse = useRef(new Animated.Value(0)).current;
  useEffect(() => {
    if (recState.isRecording) {
      const loop = Animated.loop(
        Animated.sequence([
          Animated.timing(pulse, {
            toValue: 1,
            duration: 700,
            easing: Easing.out(Easing.quad),
            useNativeDriver: true,
          }),
          Animated.timing(pulse, {
            toValue: 0,
            duration: 700,
            easing: Easing.in(Easing.quad),
            useNativeDriver: true,
          }),
        ]),
      );
      loop.start();
      return () => loop.stop();
    }
    pulse.setValue(0);
    return undefined;
  }, [pulse, recState.isRecording]);

  // Tear down the player when the screen unmounts.
  useEffect(() => {
    return () => {
      try {
        playerRef.current?.remove();
      } catch {
        /* noop */
      }
    };
  }, []);

  const playReply = useCallback(async (audioBase64: string) => {
    try {
      // Replace any in-flight playback so two replies don't overlap.
      try {
        playerRef.current?.remove();
      } catch {
        /* noop */
      }
      const src = `data:audio/mpeg;base64,${audioBase64}`;
      const p = createAudioPlayer({ uri: src });
      playerRef.current = p;
      // Route to the loud speaker (not the earpiece) on iOS.
      await setAudioModeAsync({
        playsInSilentMode: true,
        allowsRecording: false,
      }).catch(() => {});
      p.play();
    } catch {
      /* swallow — the transcript is still visible in the panel */
    }
  }, []);

  const ensureMicPermission = useCallback(async () => {
    const status = await AudioModule.requestRecordingPermissionsAsync();
    return status.granted;
  }, []);

  const startRecording = useCallback(async () => {
    setStatus(null);
    const ok = await ensureMicPermission();
    if (!ok) {
      setStatus("Microphone permission denied.");
      setPanelOpen(true);
      return;
    }
    try {
      await setAudioModeAsync({
        allowsRecording: true,
        playsInSilentMode: true,
      });
      await recorder.prepareToRecordAsync();
      recorder.record();
      Haptics.selectionAsync().catch(() => {});
      setStatus("Listening…");
      setPanelOpen(true);
    } catch (e: any) {
      setStatus(e?.message || "Couldn't start recording.");
    }
  }, [ensureMicPermission, recorder]);

  const sendTurn = useCallback(
    async (
      audio: { uri: string; mime: string },
      confirmedTools?: Record<string, boolean>,
    ) => {
      setBusy(true);
      setStatus(confirmedTools ? "Running…" : "Thinking…");
      try {
        const res = await voiceAssistant.turn({
          audioUri: audio.uri,
          mimeType: audio.mime,
          context: {
            messages,
            confirmed_tools: confirmedTools ?? {},
          },
        });
        const next: VoiceMessage[] = [...messages];
        if (res.transcript)
          next.push({ role: "user", content: res.transcript });
        if (res.reply) next.push({ role: "assistant", content: res.reply });
        setMessages(next);
        setPending(res.pending_confirmations ?? []);
        setLastCredits(res.credits ?? null);
        if (typeof res.balance === "number") setBalance(res.balance);
        setStatus(null);
        if (res.audio_base64) {
          await playReply(res.audio_base64);
        }
      } catch (e: any) {
        const msg =
          e?.status === 402
            ? "Out of AI credits — top up to keep using voice."
            : e?.status === 403
              ? "Voice Assistant isn't enabled on your plan."
              : e?.message || "Network error — please retry.";
        setStatus(msg);
      } finally {
        setBusy(false);
      }
    },
    [messages, playReply],
  );

  const stopAndSend = useCallback(async () => {
    if (!recState.isRecording) return;
    try {
      await recorder.stop();
    } catch {
      /* noop */
    }
    const uri = recorder.uri;
    if (!uri) {
      setStatus("Recording came back empty — please try again.");
      return;
    }
    // expo-audio's HIGH_QUALITY preset writes m4a (AAC) on both
    // platforms — Whisper accepts it.
    const mime =
      Platform.OS === "ios" || Platform.OS === "android"
        ? "audio/m4a"
        : "audio/webm";
    lastAudioRef.current = { uri, mime };
    await sendTurn({ uri, mime });
  }, [recorder, recState.isRecording, sendTurn]);

  const onMicPress = useCallback(() => {
    if (busy) return;
    if (recState.isRecording) {
      stopAndSend();
      return;
    }
    startRecording();
  }, [busy, recState.isRecording, startRecording, stopAndSend]);

  const onConfirm = useCallback(
    (tool: string, accepted: boolean) => {
      if (!accepted) {
        setPending((p) => p.filter((c) => c.tool !== tool));
        setMessages((m) => [
          ...m,
          { role: "assistant", content: `Cancelled ${tool}.` },
        ]);
        return;
      }
      const audio = lastAudioRef.current;
      if (!audio) return;
      setPending((p) => p.filter((c) => c.tool !== tool));
      sendTurn(audio, { [tool]: true });
    },
    [sendTurn],
  );

  const loadCaps = useCallback(async () => {
    if (caps || capsLoading) return;
    setCapsLoading(true);
    try {
      const r = await voiceAssistant.capabilities();
      setCaps(r);
      if (typeof r.balance === "number") setBalance(r.balance);
    } catch {
      setCaps({
        enabled: true,
        balance: 0,
        rate_limit: 0,
        pricing: { stt_credits_per_minute: 0, tts_credits_per_1k_chars: 0 },
        tools: {},
        limitations: ["Could not load capabilities."],
      });
    } finally {
      setCapsLoading(false);
    }
  }, [caps, capsLoading]);

  const recording = recState.isRecording;

  // Don't render anything until we know whether the user's plan
  // includes voice — and if it doesn't, stay invisible.
  if (enabled !== true) return null;

  // Position the mic just above the tab bar.
  const micBottom = (Platform.OS === "ios" ? 100 : 80) + insets.bottom * 0.25;

  return (
    <>
      <View
        pointerEvents="box-none"
        style={[StyleSheet.absoluteFill, { zIndex: 999 }]}
      >
        <View style={[styles.micWrap, { bottom: micBottom }]}>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={
              recording ? "Stop and send recording" : "Tap to talk"
            }
            onPress={onMicPress}
            onLongPress={() => setPanelOpen(true)}
            disabled={busy}
            style={({ pressed }) => [
              styles.mic,
              {
                backgroundColor: recording ? "#ef4444" : "#7c3aed",
                opacity: pressed ? 0.85 : 1,
              },
            ]}
          >
            {recording ? (
              <Animated.View
                pointerEvents="none"
                style={[
                  StyleSheet.absoluteFill,
                  {
                    borderRadius: 28,
                    backgroundColor: "#ef4444",
                    opacity: pulse.interpolate({
                      inputRange: [0, 1],
                      outputRange: [0.0, 0.5],
                    }),
                    transform: [
                      {
                        scale: pulse.interpolate({
                          inputRange: [0, 1],
                          outputRange: [1, 1.4],
                        }),
                      },
                    ],
                  },
                ]}
              />
            ) : null}
            {busy ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Feather
                name={recording ? "square" : "mic"}
                size={22}
                color="#fff"
              />
            )}
          </Pressable>
        </View>
      </View>

      <Modal
        visible={panelOpen}
        transparent
        animationType="fade"
        onRequestClose={() => setPanelOpen(false)}
      >
        <Pressable
          style={styles.backdrop}
          onPress={() => setPanelOpen(false)}
        >
          <Pressable
            onPress={() => {
              /* swallow taps so the backdrop doesn't close it */
            }}
            style={[
              styles.panel,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                marginBottom: micBottom + 70,
              },
            ]}
          >
            {/* Tabs */}
            <View
              style={[styles.tabsRow, { borderBottomColor: colors.border }]}
            >
              <View style={{ flexDirection: "row", gap: 16 }}>
                <Pressable onPress={() => setTab("chat")}>
                  <Text
                    style={[
                      styles.tabLabel,
                      {
                        color:
                          tab === "chat"
                            ? colors.primary
                            : colors.mutedForeground,
                      },
                    ]}
                  >
                    Voice
                  </Text>
                </Pressable>
                <Pressable
                  onPress={() => {
                    setTab("caps");
                    loadCaps();
                  }}
                >
                  <Text
                    style={[
                      styles.tabLabel,
                      {
                        color:
                          tab === "caps"
                            ? colors.primary
                            : colors.mutedForeground,
                      },
                    ]}
                  >
                    What I can do
                  </Text>
                </Pressable>
              </View>
              <Pressable onPress={() => setPanelOpen(false)} hitSlop={10}>
                <Feather name="x" size={18} color={colors.mutedForeground} />
              </Pressable>
            </View>

            {tab === "chat" ? (
              <ChatTab
                colors={colors}
                messages={messages}
                pending={pending}
                onConfirm={onConfirm}
                status={status}
                lastCredits={lastCredits}
                balance={balance}
              />
            ) : (
              <CapsTab colors={colors} caps={caps} loading={capsLoading} />
            )}
          </Pressable>
        </Pressable>
      </Modal>
    </>
  );
}

function ChatTab({
  colors,
  messages,
  pending,
  onConfirm,
  status,
  lastCredits,
  balance,
}: {
  colors: ReturnType<typeof useColors>;
  messages: VoiceMessage[];
  pending: VoicePendingConfirmation[];
  onConfirm: (tool: string, accepted: boolean) => void;
  status: string | null;
  lastCredits: VoiceCredits | null;
  balance: number | null;
}) {
  return (
    <ScrollView
      style={{ maxHeight: 360 }}
      contentContainerStyle={{ padding: 14, gap: 8 }}
    >
      {messages.length === 0 && !status ? (
        <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
          Tap the mic and ask anything — &quot;open my dashboard&quot;,
          &quot;how many clicks today?&quot;, &quot;delete link 42&quot;.
          Destructive actions always ask before running.
        </Text>
      ) : null}

      {messages.map((m, i) => (
        <View
          key={i}
          style={{
            alignSelf: m.role === "user" ? "flex-end" : "flex-start",
            maxWidth: "88%",
          }}
        >
          <Text
            style={{
              backgroundColor:
                m.role === "user" ? "#7c3aed33" : colors.background,
              color: colors.foreground,
              paddingVertical: 6,
              paddingHorizontal: 10,
              borderRadius: 14,
              fontSize: 13,
              overflow: "hidden",
            }}
          >
            {m.content}
          </Text>
        </View>
      ))}

      {pending.length > 0 ? (
        <View
          style={{
            borderWidth: 1,
            borderColor: "#f59e0b66",
            backgroundColor: "#f59e0b1a",
            borderRadius: 12,
            padding: 10,
            gap: 8,
          }}
        >
          <Text style={{ color: "#f59e0b", fontSize: 12, fontWeight: "600" }}>
            Confirm before I run:
          </Text>
          {pending.map((c) => (
            <View
              key={c.tool}
              style={{
                flexDirection: "row",
                justifyContent: "space-between",
                alignItems: "center",
                gap: 8,
              }}
            >
              <Text
                numberOfLines={1}
                style={{
                  color: colors.foreground,
                  fontSize: 12,
                  flexShrink: 1,
                  fontFamily: Platform.select({
                    ios: "Menlo",
                    android: "monospace",
                  }),
                }}
              >
                {c.tool}
              </Text>
              <View style={{ flexDirection: "row", gap: 6 }}>
                <Pressable
                  onPress={() => onConfirm(c.tool, true)}
                  style={{
                    paddingHorizontal: 10,
                    paddingVertical: 5,
                    borderRadius: 8,
                    backgroundColor: "#10b981",
                  }}
                >
                  <Text
                    style={{ color: "#fff", fontSize: 11, fontWeight: "600" }}
                  >
                    Yes
                  </Text>
                </Pressable>
                <Pressable
                  onPress={() => onConfirm(c.tool, false)}
                  style={{
                    paddingHorizontal: 10,
                    paddingVertical: 5,
                    borderRadius: 8,
                    backgroundColor: colors.border,
                  }}
                >
                  <Text style={{ color: colors.foreground, fontSize: 11 }}>
                    Cancel
                  </Text>
                </Pressable>
              </View>
            </View>
          ))}
        </View>
      ) : null}

      {status ? (
        <Text style={{ color: colors.mutedForeground, fontSize: 11 }}>
          {status}
        </Text>
      ) : null}

      {lastCredits ? (
        <Text style={{ color: colors.mutedForeground, fontSize: 10 }}>
          Last turn: STT {lastCredits.stt} · LLM {lastCredits.llm} · TTS{" "}
          {lastCredits.tts} (= {lastCredits.total} credits)
          {balance != null ? ` · Balance ${balance}` : ""}
        </Text>
      ) : null}
    </ScrollView>
  );
}

function CapsTab({
  colors,
  caps,
  loading,
}: {
  colors: ReturnType<typeof useColors>;
  caps: VoiceCapabilities | null;
  loading: boolean;
}) {
  if (loading || !caps) {
    return (
      <View style={{ padding: 16, alignItems: "center" }}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }
  return (
    <ScrollView
      style={{ maxHeight: 360 }}
      contentContainerStyle={{ padding: 14, gap: 12 }}
    >
      {Object.entries(caps.tools).map(([group, items]) => (
        <View key={group}>
          <Text
            style={{
              color: colors.primary,
              fontSize: 10,
              letterSpacing: 1,
              textTransform: "uppercase",
              marginBottom: 4,
            }}
          >
            {group.replace(/_/g, " ")}
          </Text>
          {items.map((t) => (
            <View key={t.name} style={{ marginBottom: 6 }}>
              <Text style={{ color: colors.foreground, fontSize: 12 }}>
                <Text
                  style={{
                    fontFamily: Platform.select({
                      ios: "Menlo",
                      android: "monospace",
                    }),
                    color: colors.mutedForeground,
                  }}
                >
                  {t.name}
                </Text>
                {t.destructive ? (
                  <Text style={{ color: "#f59e0b", fontSize: 10 }}>
                    {"  ⚠ confirms"}
                  </Text>
                ) : null}
              </Text>
              {t.description ? (
                <Text
                  style={{ color: colors.mutedForeground, fontSize: 11 }}
                >
                  {t.description}
                </Text>
              ) : null}
            </View>
          ))}
        </View>
      ))}

      <View>
        <Text
          style={{
            color: "#f43f5e",
            fontSize: 10,
            letterSpacing: 1,
            textTransform: "uppercase",
            marginBottom: 4,
          }}
        >
          What I can&apos;t do
        </Text>
        {caps.limitations.map((l) => (
          <Text
            key={l}
            style={{
              color: colors.mutedForeground,
              fontSize: 11,
              marginBottom: 3,
            }}
          >
            • {l}
          </Text>
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  micWrap: {
    position: "absolute",
    right: 16,
  },
  mic: {
    width: 56,
    height: 56,
    borderRadius: 28,
    alignItems: "center",
    justifyContent: "center",
    shadowColor: "#000",
    shadowOpacity: 0.25,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 4 },
    elevation: 6,
  },
  backdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.25)",
    justifyContent: "flex-end",
    alignItems: "flex-end",
  },
  panel: {
    width: 340,
    maxWidth: "94%",
    marginRight: 12,
    borderRadius: 18,
    borderWidth: 1,
    overflow: "hidden",
  },
  tabsRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderBottomWidth: 1,
  },
  tabLabel: {
    fontSize: 12,
    fontWeight: "600",
  },
});

export default VoiceAssistant;
