import { Feather } from "@expo/vector-icons";
import * as Device from "expo-device";
import * as Haptics from "expo-haptics";
import * as Location from "expo-location";
import { useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Modal,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { createNfcWrite } from "@/lib/api/nfc";

// react-native-nfc-manager pulls native code that does not exist in
// Expo Go. We require it lazily so the rest of the app keeps working
// in Expo Go (the sheet itself surfaces a friendly explanation in
// that case).
type NfcManagerModule = typeof import("react-native-nfc-manager");
let nfcMod: NfcManagerModule | null = null;
let nfcLoadError: Error | null = null;
function loadNfc(): NfcManagerModule | null {
  if (nfcMod || nfcLoadError) return nfcMod;
  try {
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    nfcMod = require("react-native-nfc-manager") as NfcManagerModule;
  } catch (e) {
    nfcLoadError = e as Error;
    nfcMod = null;
  }
  return nfcMod;
}

type Phase = "idle" | "checking" | "unsupported" | "off" | "ready" | "writing" | "success" | "error";

export function NfcWriteSheet({
  visible,
  onClose,
  linkId,
  url,
  onWritten,
}: {
  visible: boolean;
  onClose: () => void;
  linkId: number;
  url: string;
  onWritten?: () => void;
}) {
  const colors = useColors();
  const [phase, setPhase] = useState<Phase>("idle");
  const [errMsg, setErrMsg] = useState<string | null>(null);
  const cancelledRef = useRef(false);

  const deviceLabel = useMemo(
    () => `${Device.manufacturer ?? ""} ${Device.modelName ?? Device.deviceName ?? "Phone"}`.trim(),
    [],
  );

  // Check NFC availability whenever the sheet opens.
  useEffect(() => {
    if (!visible) return;
    cancelledRef.current = false;
    setErrMsg(null);
    setPhase("checking");

    const nfc = loadNfc();
    if (!nfc || Platform.OS === "web") {
      setPhase("unsupported");
      return;
    }
    let ignore = false;
    (async () => {
      try {
        const supported = await nfc.default.isSupported();
        if (ignore) return;
        if (!supported) {
          setPhase("unsupported");
          return;
        }
        await nfc.default.start();
        if (Platform.OS === "android") {
          const enabled = await nfc.default.isEnabled();
          if (ignore) return;
          if (!enabled) {
            setPhase("off");
            return;
          }
        }
        setPhase("ready");
      } catch (e) {
        if (ignore) return;
        setErrMsg(e instanceof Error ? e.message : "NFC unavailable");
        setPhase("unsupported");
      }
    })();
    return () => {
      ignore = true;
      const m = loadNfc();
      try {
        m?.default.cancelTechnologyRequest().catch(() => {});
      } catch {
        /* noop */
      }
    };
  }, [visible]);

  async function bestEffortLocation(): Promise<{ lat: number; lng: number } | null> {
    try {
      const { status } = await Location.getForegroundPermissionsAsync();
      if (status !== "granted") return null;
      const pos = await Location.getLastKnownPositionAsync({
        maxAge: 5 * 60 * 1000,
      });
      if (!pos) return null;
      return {
        // Coarse to ~3 decimal places (~100m) — we don't need precision.
        lat: Math.round(pos.coords.latitude * 1000) / 1000,
        lng: Math.round(pos.coords.longitude * 1000) / 1000,
      };
    } catch {
      return null;
    }
  }

  async function startWrite() {
    const nfc = loadNfc();
    if (!nfc) return;
    setErrMsg(null);
    setPhase("writing");
    cancelledRef.current = false;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);

    try {
      const NfcManager = nfc.default;
      const { NfcTech, Ndef } = nfc;

      await NfcManager.requestTechnology(NfcTech.Ndef, {
        alertMessage: "Hold your phone against the NFC tag",
      });

      const tag = await NfcManager.getTag().catch(() => null);
      const bytes = Ndef.encodeMessage([Ndef.uriRecord(url)]);
      if (!bytes) throw new Error("Could not encode the URL for NFC.");

      // Type-narrow: writeNdefMessage exists in NfcManager runtime API.
      const ndefHandler = (NfcManager as unknown as {
        ndefHandler: { writeNdefMessage: (b: number[]) => Promise<void> };
      }).ndefHandler;
      await ndefHandler.writeNdefMessage(bytes);

      const loc = await bestEffortLocation();
      await createNfcWrite(linkId, {
        written_url: url,
        tag_uid: (tag?.id as string | undefined) ?? null,
        tag_type: (tag?.type as string | undefined) ?? null,
        tag_capacity_bytes:
          typeof (tag as { maxSize?: number } | null)?.maxSize === "number"
            ? (tag as { maxSize?: number }).maxSize ?? null
            : null,
        device: Device.modelName ?? null,
        device_label: deviceLabel || null,
        platform: Platform.OS === "ios" ? "ios" : Platform.OS === "android" ? "android" : null,
        lat: loc?.lat ?? null,
        lng: loc?.lng ?? null,
      });

      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      setPhase("success");
      onWritten?.();
    } catch (e) {
      if (cancelledRef.current) {
        setPhase("ready");
        return;
      }
      const msg = e instanceof Error ? e.message : String(e);
      setErrMsg(msg.includes("cancelled") || msg.includes("UserCancel")
        ? "Cancelled."
        : msg);
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      setPhase("error");
    } finally {
      try {
        await nfc.default.cancelTechnologyRequest();
      } catch {
        /* noop */
      }
    }
  }

  function close() {
    cancelledRef.current = true;
    const nfc = loadNfc();
    try {
      nfc?.default.cancelTechnologyRequest().catch(() => {});
    } catch {
      /* noop */
    }
    setPhase("idle");
    setErrMsg(null);
    onClose();
  }

  return (
    <Modal
      visible={visible}
      transparent
      animationType="slide"
      onRequestClose={close}
    >
      <View style={styles.backdrop}>
        <View style={[styles.sheet, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <View style={styles.header}>
            <Text style={[styles.title, { color: colors.foreground }]}>Write to NFC tag</Text>
            <Pressable onPress={close} hitSlop={12}>
              <Feather name="x" size={22} color={colors.mutedForeground} />
            </Pressable>
          </View>

          <Text style={[styles.url, { color: colors.mutedForeground }]} numberOfLines={2}>
            {url}
          </Text>

          <View style={styles.body}>
            {phase === "checking" && (
              <View style={styles.center}>
                <ActivityIndicator color={colors.primary} />
                <Text style={[styles.msg, { color: colors.mutedForeground }]}>
                  Checking NFC support…
                </Text>
              </View>
            )}

            {phase === "unsupported" && (
              <View style={styles.center}>
                <Feather name="slash" size={36} color={colors.mutedForeground} />
                <Text style={[styles.msg, { color: colors.foreground }]}>
                  This device can&apos;t write NFC tags
                </Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                  {errMsg ??
                    "NFC isn't available on this device, or the app is running in a development sandbox without NFC support."}
                </Text>
              </View>
            )}

            {phase === "off" && (
              <View style={styles.center}>
                <Feather name="alert-circle" size={36} color={colors.mutedForeground} />
                <Text style={[styles.msg, { color: colors.foreground }]}>NFC is turned off</Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                  Turn NFC on in Settings, then come back.
                </Text>
              </View>
            )}

            {phase === "ready" && (
              <View style={styles.center}>
                <Feather name="wifi" size={40} color={colors.primary} />
                <Text style={[styles.msg, { color: colors.foreground }]}>Ready to write</Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                  Tap &ldquo;Write&rdquo; and hold your phone against a writable NFC tag.
                </Text>
              </View>
            )}

            {phase === "writing" && (
              <View style={styles.center}>
                <ActivityIndicator size="large" color={colors.primary} />
                <Text style={[styles.msg, { color: colors.foreground }]}>
                  Hold your phone against the tag…
                </Text>
              </View>
            )}

            {phase === "success" && (
              <View style={styles.center}>
                <Feather name="check-circle" size={40} color={colors.success} />
                <Text style={[styles.msg, { color: colors.foreground }]}>Tag written</Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                  Tapping this tag will now open your link.
                </Text>
              </View>
            )}

            {phase === "error" && (
              <View style={styles.center}>
                <Feather name="alert-triangle" size={36} color="#dc2626" />
                <Text style={[styles.msg, { color: colors.foreground }]}>Write failed</Text>
                <Text style={[styles.sub, { color: colors.mutedForeground }]}>
                  {errMsg ?? "Try again with the tag closer to your phone."}
                </Text>
              </View>
            )}
          </View>

          <View style={styles.actions}>
            {phase === "ready" || phase === "error" ? (
              <Button label="Write" onPress={startWrite} />
            ) : null}
            {phase === "success" ? (
              <Button label="Done" onPress={close} />
            ) : null}
            {phase !== "success" && phase !== "ready" && phase !== "error" ? (
              <Button label="Close" variant="outline" onPress={close} />
            ) : null}
            {(phase === "ready" || phase === "error") && (
              <Button label="Cancel" variant="outline" onPress={close} />
            )}
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
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
    gap: 14,
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  title: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 18 },
  url: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },
  body: { minHeight: 180, justifyContent: "center" },
  center: { alignItems: "center", gap: 10, paddingVertical: 20 },
  msg: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15, textAlign: "center" },
  sub: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    textAlign: "center",
    paddingHorizontal: 16,
    lineHeight: 19,
  },
  actions: { gap: 8 },
});
