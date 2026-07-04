import { Feather } from "@expo/vector-icons";
import { useMemo } from "react";
import { Modal, Platform, Pressable, StyleSheet, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";

// Lazy-require so the web bundle never tries to evaluate the native module.
let WebView: typeof import("react-native-webview").WebView | null = null;
if (Platform.OS !== "web") {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  WebView = require("react-native-webview").WebView;
}

export type DateTimePickerModalProps = {
  visible: boolean;
  title?: string;
  onClose: () => void;
  // `value` is a naive `datetime-local` string (YYYY-MM-DDTHH:mm) in the
  // device's wall-clock time — the server interprets it in the user's timezone.
  onPick: (value: string) => void;
};

// Format a Date as a `datetime-local` value string (local wall-clock, no tz).
function localInput(d: Date): string {
  const p = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(
    d.getHours(),
  )}:${p(d.getMinutes())}`;
}

function buildHtml(initial: string, min: string, accent: string): string {
  return `<!DOCTYPE html><html><head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
<style>
  html, body { height: 100%; margin: 0; padding: 0; background: transparent; }
  body { display: flex; flex-direction: column; align-items: center; justify-content: flex-start;
         font-family: -apple-system, system-ui, sans-serif; padding: 20px 16px; box-sizing: border-box; }
  input[type="datetime-local"] {
    width: 100%; box-sizing: border-box; font-size: 20px; padding: 14px;
    border-radius: 12px; border: 1px solid rgba(127,127,127,.35);
    background: rgba(127,127,127,.08); color: inherit; margin-bottom: 16px;
    color-scheme: light dark;
  }
  button { width: 100%; box-sizing: border-box; font-size: 16px; font-weight: 700;
    padding: 14px; border-radius: 12px; border: none; color: #fff; background: ${accent};
    -webkit-tap-highlight-color: transparent; }
  .err { color: #ef4444; font-size: 13px; margin-bottom: 12px; min-height: 16px; text-align: center; }
</style>
</head><body>
<input type="datetime-local" id="dt" value="${initial}" min="${min}" />
<div class="err" id="err"></div>
<button id="set">Set reminder</button>
<script>
  function post(o){ if (window.ReactNativeWebView) window.ReactNativeWebView.postMessage(JSON.stringify(o)); }
  var input = document.getElementById('dt');
  var err = document.getElementById('err');
  document.getElementById('set').addEventListener('click', function(){
    var v = input.value;
    if (!v) { err.textContent = 'Please pick a date and time.'; return; }
    if (v <= '${min}') { err.textContent = 'Pick a time in the future.'; return; }
    post({ type: 'pick', value: v });
  });
  setTimeout(function(){ post({ type: 'ready' }); }, 60);
</script>
</body></html>`;
}

export function DateTimePickerModal({
  visible,
  title,
  onClose,
  onPick,
}: DateTimePickerModalProps) {
  const colors = useColors();
  const insets = useSafeAreaInsets();

  const html = useMemo(() => {
    const now = new Date();
    const seed = new Date(now.getTime() + 86400000);
    seed.setHours(9, 0, 0, 0);
    return buildHtml(localInput(seed), localInput(now), colors.primary);
  }, [visible, colors.primary]);

  const onMessage = (raw: string) => {
    try {
      const msg = JSON.parse(raw) as { type: string; value?: string };
      if (msg.type === "pick" && typeof msg.value === "string") {
        onPick(msg.value);
        onClose();
      }
    } catch {
      /* ignore malformed messages */
    }
  };

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent
      onRequestClose={onClose}
    >
      <View style={styles.backdrop}>
        <Pressable style={styles.backdropFill} onPress={onClose} />
        <View
          style={[
            styles.sheet,
            {
              backgroundColor: colors.card,
              paddingBottom: insets.bottom + 12,
              borderColor: colors.border,
            },
          ]}
        >
          <View style={styles.header}>
            <Text style={[styles.title, { color: colors.foreground }]}>
              {title ?? "Pick a time"}
            </Text>
            <Pressable onPress={onClose} hitSlop={12}>
              <Feather name="x" size={22} color={colors.foreground} />
            </Pressable>
          </View>
          <View style={{ height: 220 }}>
            {WebView ? (
              <WebView
                originWhitelist={["*"]}
                source={{ html }}
                style={{ flex: 1, backgroundColor: "transparent" }}
                javaScriptEnabled
                onMessage={(e) => onMessage(e.nativeEvent.data)}
              />
            ) : (
              <Text
                style={{
                  color: colors.mutedForeground,
                  textAlign: "center",
                  padding: 24,
                }}
              >
                The time picker isn't available here.
              </Text>
            )}
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: { flex: 1, justifyContent: "flex-end" },
  backdropFill: { ...StyleSheet.absoluteFillObject, backgroundColor: "rgba(0,0,0,0.45)" },
  sheet: {
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    borderWidth: StyleSheet.hairlineWidth,
    paddingHorizontal: 16,
    paddingTop: 14,
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 10,
  },
  title: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 16 },
});
