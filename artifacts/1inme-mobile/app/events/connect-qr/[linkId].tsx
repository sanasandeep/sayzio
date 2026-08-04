import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Platform,
  ScrollView,
  Share,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { SvgXml } from "react-native-svg";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { getEventConnectQr } from "@/lib/api/events";
import { showAlert } from "@/lib/webAlert";

// Host's Connect QR for an event link (Task #6687 — mobile parity for the
// web /user/links/{link}/connect-qr page from Task #6685). Guests who scan
// it land on the tagged event page where a single OTP step signs them in,
// RSVPs them "yes" and follows the host. View, share the link, or save the
// QR image for printing.

export default function EventConnectQrScreen() {
  const colors = useColors();
  const { linkId } = useLocalSearchParams<{ linkId: string }>();
  const id = Number(linkId);
  const [saving, setSaving] = useState(false);

  const q = useQuery({
    queryKey: ["event-connect-qr", id],
    queryFn: () => getEventConnectQr(id),
    enabled: Number.isFinite(id),
  });

  const data = q.data;

  const shareUrl = async () => {
    if (!data) return;
    try {
      await Share.share(
        Platform.OS === "ios"
          ? { url: data.connect_url }
          : { message: data.connect_url },
      );
    } catch {
      // user dismissed the sheet — nothing to do
    }
  };

  const savePng = async () => {
    if (!data || saving) return;
    setSaving(true);
    try {
      // PNG is best-effort server-side; fall back to the SVG when absent.
      const isPng = !!data.qr_png_base64;
      const ext = isPng ? "png" : "svg";
      const filename = `connect-qr-${data.link.alias || data.link.id}.${ext}`;
      if (Platform.OS === "web") {
        const a = document.createElement("a");
        a.href = isPng
          ? `data:image/png;base64,${data.qr_png_base64}`
          : `data:image/svg+xml;charset=utf-8,${encodeURIComponent(data.qr_svg)}`;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
      } else {
        const FileSystem = await import("expo-file-system/legacy");
        const Sharing = await import("expo-sharing");
        const target = `${FileSystem.cacheDirectory ?? ""}${filename}`;
        if (isPng) {
          await FileSystem.writeAsStringAsync(target, data.qr_png_base64!, {
            encoding: FileSystem.EncodingType.Base64,
          });
        } else {
          await FileSystem.writeAsStringAsync(target, data.qr_svg);
        }
        if (await Sharing.isAvailableAsync()) {
          await Sharing.shareAsync(target, {
            mimeType: isPng ? "image/png" : "image/svg+xml",
            dialogTitle: "Save or share the Connect QR",
          });
        }
      }
    } catch (e) {
      showAlert(
        "Couldn't save the QR",
        (e as { message?: string })?.message ?? "Please try again.",
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{ headerShown: true, title: "Connect QR", headerBackTitle: "Back" }}
      />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14, paddingBottom: 40 }}>
        {q.isLoading ? (
          <View style={{ paddingVertical: 60, alignItems: "center" }}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : q.isError || !data ? (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            <Text style={{ color: colors.foreground }}>
              Couldn't load the Connect QR for this event.
            </Text>
          </View>
        ) : (
          <>
            <View
              style={[
                styles.card,
                {
                  backgroundColor: colors.card,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                  alignItems: "center",
                  gap: 12,
                },
              ]}
            >
              <View style={styles.qrWrap}>
                <SvgXml xml={data.qr_svg} width={240} height={240} />
              </View>
              <Text
                style={{
                  color: colors.foreground,
                  fontFamily: "SpaceGrotesk_600SemiBold",
                  fontSize: 15,
                  textAlign: "center",
                }}
              >
                {data.link.title || `/${data.link.alias}`}
              </Text>
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontSize: 12,
                  fontFamily: "SpaceGrotesk_500Medium",
                  textAlign: "center",
                }}
                numberOfLines={1}
              >
                {data.connect_url}
              </Text>
            </View>

            <View
              style={[
                styles.card,
                { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius, gap: 8 },
              ]}
            >
              <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <Feather name="zap" size={16} color={colors.primary} />
                <Text
                  style={{
                    color: colors.foreground,
                    fontFamily: "SpaceGrotesk_600SemiBold",
                    fontSize: 13,
                  }}
                >
                  Scan-to-connect
                </Text>
              </View>
              <Text
                style={{
                  color: colors.mutedForeground,
                  fontSize: 12,
                  lineHeight: 18,
                  fontFamily: "SpaceGrotesk_400Regular",
                }}
              >
                Print this QR at the door or on invites. Guests who scan it
                verify one code — that signs them in (creating an account if
                needed), RSVPs them "yes" and connects them with you. Track the
                results in Visitor Insights.
              </Text>
            </View>

            <Button label="Share the link" onPress={shareUrl} leading={
              <Feather name="share-2" size={16} color={colors.primaryForeground} />
            } />
            <Button
              label={saving ? "Preparing…" : "Download QR image"}
              variant="outline"
              loading={saving}
              onPress={savePng}
            />
          </>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderWidth: 1,
    padding: 16,
  },
  qrWrap: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 12,
  },
});
