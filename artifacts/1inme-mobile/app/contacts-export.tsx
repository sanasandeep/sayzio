import { useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  ScrollView,
  Text,
  TouchableOpacity,
  View,
} from "react-native";

import { Stack } from "expo-router";

import { useColors } from "@/hooks/useColors";
import {
  getExportStatus,
  requestContactExport,
  type ExportFormat,
  type ExportStatus,
} from "@/lib/api/contactExport";
import { showAlert } from "@/lib/webAlert";

type FormatOption = { format: ExportFormat; label: string; sub: string; icon: string };

const FORMAT_OPTIONS: FormatOption[] = [
  {
    format: "csv",
    label: "CSV",
    sub: "Opens in Excel, Google Sheets. Re-importable, all fields preserved.",
    icon: "📄",
  },
  {
    format: "vcf",
    label: "vCard (.vcf)",
    sub: "Import into iPhone Contacts, Google Contacts, Outlook & more.",
    icon: "👤",
  },
];

export default function ContactsExportScreen() {
  const colors = useColors();
  const [selectedFormat, setSelectedFormat] = useState<ExportFormat>("csv");
  const [exportId, setExportId] = useState<number | null>(null);
  const [status, setStatus] = useState<ExportStatus | null>(null);
  const [contactCount, setContactCount] = useState<number>(0);
  const [downloadUrl, setDownloadUrl] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    return () => {
      if (pollRef.current) clearInterval(pollRef.current);
    };
  }, []);

  async function startExport() {
    setLoading(true);
    setStatus(null);
    setExportId(null);
    setDownloadUrl(null);
    try {
      const res = await requestContactExport(selectedFormat);
      setExportId(res.id);
      setStatus(res.status);
      setContactCount(res.contact_count ?? 0);
      if (res.download_url) {
        setDownloadUrl(res.download_url);
      } else {
        startPolling(res.id);
      }
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : "Something went wrong. Please try again.";
      showAlert("Export failed", msg);
    } finally {
      setLoading(false);
    }
  }

  function startPolling(id: number) {
    if (pollRef.current) clearInterval(pollRef.current);
    pollRef.current = setInterval(async () => {
      try {
        const res = await getExportStatus(id);
        setStatus(res.status);
        setContactCount(res.contact_count ?? 0);
        if (res.download_url) {
          setDownloadUrl(res.download_url);
        }
        if (res.status !== "pending" && res.status !== "processing") {
          clearInterval(pollRef.current!);
          pollRef.current = null;
        }
      } catch (_) {}
    }, 2500);
  }

  async function openDownload() {
    if (!downloadUrl) return;
    try {
      const can = await Linking.canOpenURL(downloadUrl);
      if (can) {
        await Linking.openURL(downloadUrl);
      } else {
        showAlert("Cannot open", "Please copy the download link and open it in your browser.");
      }
    } catch {
      showAlert("Error", "Could not open the download link.");
    }
  }

  const isIdle    = !loading && status === null;
  const isPending = status === "pending" || status === "processing";
  const isReady   = status === "completed" && !!downloadUrl;
  const isFailed  = status === "failed";

  const borderColor = colors.border;
  const cardBg      = colors.card;
  const textPrimary = colors.text;
  const textMuted   = colors.mutedForeground;

  return (
    <>
      <Stack.Screen options={{ title: "Export Contacts" }} />
      <ScrollView
        style={{ flex: 1, backgroundColor: colors.background }}
        contentContainerStyle={{ padding: 20, paddingBottom: 60 }}
      >
        <Text style={{ fontSize: 22, fontWeight: "700", color: textPrimary, marginBottom: 4 }}>
          Export Contacts
        </Text>
        <Text style={{ fontSize: 13, color: textMuted, marginBottom: 24 }}>
          Download your address book as a file you can open or import elsewhere.
        </Text>

        {/* Format selector */}
        <Text style={{ fontSize: 13, fontWeight: "600", color: textPrimary, marginBottom: 10 }}>
          Format
        </Text>
        {FORMAT_OPTIONS.map((opt) => {
          const active = selectedFormat === opt.format;
          return (
            <TouchableOpacity
              key={opt.format}
              onPress={() => setSelectedFormat(opt.format)}
              style={{
                flexDirection: "row",
                alignItems: "flex-start",
                backgroundColor: cardBg,
                borderRadius: 12,
                borderWidth: 1,
                borderColor: active ? "#3d6bff" : borderColor,
                padding: 14,
                marginBottom: 10,
                gap: 12,
              }}
            >
              <Text style={{ fontSize: 24, lineHeight: 28 }}>{opt.icon}</Text>
              <View style={{ flex: 1 }}>
                <Text style={{ fontSize: 14, fontWeight: "700", color: textPrimary }}>
                  {opt.label}
                </Text>
                <Text style={{ fontSize: 12, color: textMuted, marginTop: 2 }}>
                  {opt.sub}
                </Text>
              </View>
              {active && (
                <View
                  style={{
                    width: 18,
                    height: 18,
                    borderRadius: 9,
                    backgroundColor: "#3d6bff",
                    alignItems: "center",
                    justifyContent: "center",
                    marginTop: 2,
                  }}
                >
                  <Text style={{ color: "#fff", fontSize: 10, fontWeight: "700" }}>✓</Text>
                </View>
              )}
            </TouchableOpacity>
          );
        })}

        {/* Export button */}
        {(isIdle || isFailed) && (
          <TouchableOpacity
            onPress={startExport}
            disabled={loading}
            style={{
              marginTop: 16,
              borderRadius: 14,
              overflow: "hidden",
              backgroundColor: "#3d6bff",
              paddingVertical: 14,
              alignItems: "center",
            }}
          >
            {loading ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={{ color: "#fff", fontWeight: "700", fontSize: 15 }}>
                {isFailed ? "Retry Export" : "Export Contacts"}
              </Text>
            )}
          </TouchableOpacity>
        )}

        {/* In-progress */}
        {isPending && (
          <View
            style={{
              marginTop: 20,
              padding: 16,
              backgroundColor: cardBg,
              borderRadius: 14,
              borderWidth: 1,
              borderColor: borderColor,
              alignItems: "center",
              gap: 12,
            }}
          >
            <ActivityIndicator color="#3d6bff" size="large" />
            <Text style={{ color: textPrimary, fontWeight: "600", fontSize: 15 }}>
              Generating export…
            </Text>
            {contactCount > 0 && (
              <Text style={{ color: textMuted, fontSize: 12 }}>
                {contactCount.toLocaleString()} contacts
              </Text>
            )}
            <Text style={{ color: textMuted, fontSize: 12, textAlign: "center" }}>
              Large address books may take a few seconds.{"\n"}We'll let you know when it's ready.
            </Text>
          </View>
        )}

        {/* Ready */}
        {isReady && (
          <View
            style={{
              marginTop: 20,
              padding: 16,
              backgroundColor: cardBg,
              borderRadius: 14,
              borderWidth: 1,
              borderColor: borderColor,
            }}
          >
            <Text style={{ color: textPrimary, fontWeight: "700", fontSize: 15, marginBottom: 4 }}>
              Export ready!
            </Text>
            {contactCount > 0 && (
              <Text style={{ color: textMuted, fontSize: 12, marginBottom: 14 }}>
                {contactCount.toLocaleString()} contacts ·{" "}
                {selectedFormat === "vcf" ? "vCard (.vcf)" : "CSV"}
              </Text>
            )}
            <TouchableOpacity
              onPress={openDownload}
              style={{
                borderRadius: 12,
                backgroundColor: "#3d6bff",
                paddingVertical: 12,
                alignItems: "center",
              }}
            >
              <Text style={{ color: "#fff", fontWeight: "700", fontSize: 14 }}>
                Download File
              </Text>
            </TouchableOpacity>
            <Text style={{ color: textMuted, fontSize: 11, marginTop: 8 }}>
              Link expires in 24 hours. Opens in your device browser.
            </Text>
            <TouchableOpacity
              onPress={() => {
                setStatus(null);
                setExportId(null);
                setDownloadUrl(null);
              }}
              style={{ marginTop: 12 }}
            >
              <Text style={{ color: "#3d6bff", fontSize: 13 }}>Start new export</Text>
            </TouchableOpacity>
          </View>
        )}

        {/* Failed */}
        {isFailed && (
          <View
            style={{
              marginTop: 12,
              padding: 14,
              backgroundColor: "rgba(239,68,68,0.08)",
              borderRadius: 12,
              borderWidth: 1,
              borderColor: "rgba(239,68,68,0.25)",
            }}
          >
            <Text style={{ color: "#f87171", fontSize: 13 }}>
              Something went wrong generating your export. Please try again.
            </Text>
          </View>
        )}
      </ScrollView>
    </>
  );
}
