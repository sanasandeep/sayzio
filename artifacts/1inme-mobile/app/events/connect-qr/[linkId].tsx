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
import { EventsModuleGate } from "@/components/EventsModuleGate";

// Host's Connect QR for an event link (Task #6687 — mobile parity for the
// web /user/links/{link}/connect-qr page from Task #6685). Guests who scan
// it land on the tagged event page where a single OTP step signs them in,
// RSVPs them "yes" and follows the host. View, share the link, or save the
// QR image for printing.

function EventConnectQrScreenInner() {
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

  const [printing, setPrinting] = useState(false);

  // Format the event date line for the poster from the API's ISO string.
  // Formatting happens IN THE EVENT'S TIMEZONE (not the device's) so the
  // printed time matches the venue clock the label claims; if the zone id
  // is unknown to the runtime we fall back to device-local WITHOUT the
  // misleading zone label.
  // [extract:posterDateLine:start]
  const posterDateLine = (): string | null => {
    if (!data?.event?.start_date) return null;
    const d = new Date(data.event.start_date);
    if (Number.isNaN(d.getTime())) return null;
    const tz = data.event.timezone || undefined;
    const fmt = (opts: Intl.DateTimeFormatOptions, timeZone?: string) =>
      new Intl.DateTimeFormat(undefined, { ...opts, timeZone }).format(d);
    const dateOpts: Intl.DateTimeFormatOptions = {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    };
    const timeOpts: Intl.DateTimeFormatOptions = {
      hour: "numeric",
      minute: "2-digit",
    };
    try {
      const dateStr = fmt(dateOpts, tz);
      if (data.event.all_day) return dateStr;
      const timeStr = fmt(timeOpts, tz);
      return `${dateStr} at ${timeStr}${tz ? ` (${tz})` : ""}`;
    } catch {
      // Unknown/invalid IANA zone: device-local rendering, no zone label.
      const dateStr = fmt(dateOpts);
      return data.event.all_day ? dateStr : `${dateStr} at ${fmt(timeOpts)}`;
    }
  };
  // [extract:posterDateLine:end]

  // Print-ready A4/Letter poster HTML (Task #6693): event name, date/venue,
  // the SVG QR and a scan instruction. SVG-only — no server PNG needed.
  const posterHtml = (): string => {
    const esc = (s: string) =>
      s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    const name = esc(data!.event?.name || data!.link.title || data!.link.alias);
    const dateLine = posterDateLine();
    const location = data!.event?.location;
    return `<!DOCTYPE html><html><head><meta charset="utf-8"><style>
      *{margin:0;padding:0;box-sizing:border-box}
      @page{size:A4 portrait;margin:0}
      body{font-family:'Segoe UI',Arial,Helvetica,sans-serif;color:#111827;background:#fff}
      .poster{width:210mm;min-height:297mm;margin:0 auto;display:flex;flex-direction:column;align-items:center;text-align:center;padding:22mm 18mm}
      .kicker{font-size:13pt;letter-spacing:.35em;text-transform:uppercase;color:#2563eb;font-weight:700;margin-bottom:8mm}
      h1{font-size:34pt;line-height:1.15;font-weight:800;margin-bottom:6mm;overflow-wrap:anywhere}
      .meta{font-size:15pt;color:#374151;margin-bottom:3mm}
      .qr{margin:10mm auto;padding:8mm;border:1.2mm solid #111827;border-radius:8mm;display:inline-block}
      .qr svg{display:block;width:120mm;height:120mm}
      .instruction{font-size:20pt;font-weight:800;margin-bottom:3mm}
      .sub{font-size:12pt;color:#4b5563;max-width:150mm;line-height:1.5}
      .url{margin-top:8mm;font-family:monospace;font-size:11pt;color:#2563eb;overflow-wrap:anywhere}
    </style></head><body><div class="poster">
      <div class="kicker">You're invited</div>
      <h1>${name}</h1>
      ${dateLine ? `<div class="meta"><strong>${esc(dateLine)}</strong></div>` : ""}
      ${location ? `<div class="meta">${esc(location)}</div>` : ""}
      <div class="qr">${data!.qr_svg}</div>
      <div class="instruction">Scan to RSVP &amp; connect</div>
      <p class="sub">Point your phone's camera at the code. One quick verification code signs you in, saves your "Going" RSVP and connects you with the host.</p>
      <div class="url">${esc(data!.connect_url)}</div>
    </div></body></html>`;
  };

  const printPoster = async () => {
    if (!data || printing) return;
    setPrinting(true);
    try {
      const html = posterHtml();
      if (Platform.OS === "web") {
        // Open the poster in a new window and trigger the browser's print
        // dialog (print or save-as-PDF), matching the web app's flow.
        const w = window.open("", "_blank");
        if (!w) throw new Error("Allow pop-ups to print the poster.");
        w.document.write(html);
        w.document.close();
        w.focus();
        setTimeout(() => w.print(), 300);
      } else {
        // Native: render to PDF and hand it to the share sheet so the host
        // can print (AirPrint / Android print service) or save/send it.
        const Print = await import("expo-print");
        const Sharing = await import("expo-sharing");
        const { uri } = await Print.printToFileAsync({ html });
        if (await Sharing.isAvailableAsync()) {
          await Sharing.shareAsync(uri, {
            mimeType: "application/pdf",
            dialogTitle: "Print or share the poster",
            UTI: "com.adobe.pdf",
          });
        } else {
          await Print.printAsync({ html });
        }
      }
    } catch (e) {
      showAlert(
        "Couldn't prepare the poster",
        (e as { message?: string })?.message ?? "Please try again.",
      );
    } finally {
      setPrinting(false);
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
              label={printing ? "Preparing poster…" : "Print poster"}
              variant="outline"
              loading={printing}
              onPress={printPoster}
              leading={
                <Feather name="printer" size={16} color={colors.foreground} />
              }
            />
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

// Task #6729 — platform-wide Events module gate: shows a graceful
// "not available" state (instead of API 404 errors) when events are off.
export default function EventConnectQrScreen() {
  return (
    <EventsModuleGate>
      <EventConnectQrScreenInner />
    </EventsModuleGate>
  );
}
