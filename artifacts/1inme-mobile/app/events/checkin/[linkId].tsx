import { Feather } from "@expo/vector-icons";
import { CameraView, useCameraPermissions } from "expo-camera";
import { useLocalSearchParams } from "expo-router";
import { useCallback, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import { checkinScan, type CheckinResult } from "@/lib/api/events";

export default function CheckinScannerScreen() {
  const { linkId } = useLocalSearchParams<{ linkId: string }>();
  const colors = useColors();
  const id = Number(linkId);
  const [permission, requestPermission] = useCameraPermissions();
  const [manualCode, setManualCode] = useState("");
  const [result, setResult] = useState<CheckinResult | null>(null);
  const [scanning, setScanning] = useState(true);
  const lockRef = useRef(false);

  const extractCode = (raw: string): string => {
    // The QR content is a full check-in lookup URL; ticket codes are
    // TKT-xxxxxxxxxx. Support both raw codes and the full URL.
    const m = raw.match(/TKT-[A-Za-z0-9]+/);
    return m ? m[0] : raw.trim();
  };

  const submit = useCallback(
    async (code: string) => {
      if (!code || lockRef.current) return;
      lockRef.current = true;
      setScanning(false);
      try {
        const res = await checkinScan(id, code);
        setResult(res);
      } catch (err) {
        setResult({ ok: false, status: "error", message: (err as Error)?.message ?? "Check-in failed." });
      }
    },
    [id],
  );

  const scanAgain = useCallback(() => {
    setResult(null);
    setManualCode("");
    lockRef.current = false;
    setScanning(true);
  }, []);

  if (!permission) {
    return <View style={{ flex: 1, backgroundColor: colors.background }} />;
  }

  if (!permission.granted) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background, padding: 24 }]}>
        <Feather name="camera-off" size={32} color={colors.mutedForeground} />
        <Text style={{ color: colors.foreground, marginTop: 12, textAlign: "center" }}>
          Camera access is needed to scan ticket QR codes.
        </Text>
        <Pressable
          onPress={requestPermission}
          style={[styles.buyBtn, { backgroundColor: colors.primary, marginTop: 16 }]}
        >
          <Text style={{ color: colors.primaryForeground, fontWeight: "700" }}>Grant access</Text>
        </Pressable>
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      {scanning ? (
        <CameraView
          style={{ flex: 1 }}
          barcodeScannerSettings={{ barcodeTypes: ["qr"] }}
          onBarcodeScanned={({ data }) => submit(extractCode(data))}
        />
      ) : (
        <View style={styles.resultWrap}>
          {result ? (
            <>
              <Feather
                name={result.ok ? "check-circle" : "x-circle"}
                size={64}
                color={result.ok ? colors.primary : colors.destructive}
              />
              <Text style={[styles.resultMsg, { color: colors.foreground }]}>{result.message}</Text>
              {result.ticket ? (
                <Text style={{ color: colors.mutedForeground, marginTop: 4 }}>
                  {result.ticket.attendee_name} · {result.ticket.tier?.name}
                </Text>
              ) : null}
            </>
          ) : (
            <ActivityIndicator color={colors.primary} />
          )}
          <Pressable
            onPress={scanAgain}
            style={[styles.buyBtn, { backgroundColor: colors.primary, marginTop: 24 }]}
          >
            <Text style={{ color: colors.primaryForeground, fontWeight: "700" }}>Scan next</Text>
          </Pressable>
        </View>
      )}

      <View style={[styles.manualRow, { backgroundColor: colors.card, borderColor: colors.border }]}>
        <TextInput
          value={manualCode}
          onChangeText={setManualCode}
          placeholder="Or enter ticket code manually"
          placeholderTextColor={colors.mutedForeground}
          autoCapitalize="characters"
          style={[styles.manualInput, { color: colors.foreground }]}
          onSubmitEditing={() => submit(manualCode.trim())}
        />
        <Pressable onPress={() => submit(manualCode.trim())}>
          <Feather name="arrow-right-circle" size={26} color={colors.primary} />
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  resultWrap: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  resultMsg: { fontSize: 17, fontWeight: "700", textAlign: "center", marginTop: 16 },
  buyBtn: { height: 48, borderRadius: 14, alignItems: "center", justifyContent: "center", paddingHorizontal: 24 },
  manualRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    borderTopWidth: 1,
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  manualInput: { flex: 1, fontSize: 15, height: 40 },
});
