import {
  AudioModule,
  RecordingPresets,
  setAudioModeAsync,
  useAudioRecorder,
} from "expo-audio";
import { useCallback, useState } from "react";
import { Platform } from "react-native";

import { transcribe } from "@/lib/api/voice";

/**
 * Reusable voice-dictation control for any text field. Records one clip,
 * sends it to the dictation-only STT endpoint (plan-gated + metered like
 * a voice turn, but no LLM/TTS), and returns the transcribed text via
 * `onText`. Mirrors the web's `voiceDictation()` Alpine factory.
 *
 *   const dict = useVoiceDictation((t) => setDraft((d) => (d ? d + " " : "") + t));
 *   <Pressable onPress={dict.toggle}><Feather name="mic" .../></Pressable>
 */
export function useVoiceDictation(onText: (text: string) => void) {
  const recorder = useAudioRecorder(RecordingPresets.HIGH_QUALITY);
  const [recording, setRecording] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const start = useCallback(async () => {
    setError(null);
    if (Platform.OS === "web") {
      setError("Dictation isn't supported in the web preview yet.");
      return;
    }
    try {
      const perm = await AudioModule.requestRecordingPermissionsAsync();
      if (!perm.granted) {
        setError("Microphone permission is required for dictation.");
        return;
      }
      await setAudioModeAsync({ allowsRecording: true, playsInSilentMode: true });
      await recorder.prepareToRecordAsync();
      recorder.record();
      setRecording(true);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not start recording.");
    }
  }, [recorder]);

  const stopAndSend = useCallback(async () => {
    if (!recorder.isRecording) {
      setRecording(false);
      return;
    }
    try {
      await recorder.stop();
      setRecording(false);
      const uri = recorder.uri;
      if (!uri) {
        setError("Could not capture audio.");
        return;
      }
      const ext = uri.split(".").pop()?.toLowerCase() ?? "m4a";
      const mime =
        ext === "caf"
          ? "audio/x-caf"
          : ext === "wav"
            ? "audio/wav"
            : ext === "webm"
              ? "audio/webm"
              : ext === "mp3"
                ? "audio/mpeg"
                : "audio/mp4";
      setBusy(true);
      const res = await transcribe({
        uri,
        mime,
        filename: `dictation-${Date.now()}.${ext}`,
      });
      if (res.text) onText(res.text);
    } catch (e: unknown) {
      const err = e as { status?: number; message?: string } | undefined;
      setError(
        err?.status === 402
          ? "Out of AI credits — top up to keep dictating."
          : (err?.message ?? "Could not transcribe."),
      );
    } finally {
      setBusy(false);
    }
  }, [recorder, onText]);

  const toggle = useCallback(() => {
    if (recording) {
      void stopAndSend();
    } else {
      void start();
    }
  }, [recording, start, stopAndSend]);

  return { recording, busy, error, toggle, start, stopAndSend };
}
