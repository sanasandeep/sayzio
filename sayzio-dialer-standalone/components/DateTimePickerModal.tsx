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
  // Existing follow-up note to pre-fill the (optional) note field so the user
  // can add or edit it in the same step as picking the time.
  initialNote?: string | null;
  onClose: () => void;
  // `value` is a naive `datetime-local` string (YYYY-MM-DDTHH:mm) in the
  // device's wall-clock time — the server interprets it in the user's timezone.
  // `note` is the (possibly edited/cleared) follow-up note.
  onPick: (value: string, note: string) => void;
};

// Format a Date as a `datetime-local` value string (local wall-clock, no tz).
function localInput(d: Date): string {
  const p = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(
    d.getHours(),
  )}:${p(d.getMinutes())}`;
}

// Escape a string for safe interpolation into the WebView HTML template. Covers
// HTML entities plus backtick/`$` so a note can't break out of the JS template
// literal or the textarea/attribute context.
function escapeForHtml(s: string): string {
  return s
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;")
    .replace(/`/g, "&#96;")
    .replace(/\$/g, "&#36;");
}

function buildHtml(
  initial: string,
  min: string,
  accent: string,
  note: string,
): string {
  const noteText = escapeForHtml(note);
  return `<!DOCTYPE html><html><head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
<style>
  html, body { height: 100%; margin: 0; padding: 0; background: transparent; }
  body { display: flex; flex-direction: column; align-items: stretch; justify-content: flex-start;
         font-family: -apple-system, system-ui, sans-serif; padding: 16px; box-sizing: border-box; }
  label { display: block; font-size: 11px; font-weight: 700; letter-spacing: .4px;
    text-transform: uppercase; opacity: .6; margin-bottom: 6px; }
  input[type="datetime-local"], textarea {
    width: 100%; box-sizing: border-box; font-size: 18px; padding: 12px;
    border-radius: 12px; border: 1px solid rgba(127,127,127,.35);
    background: rgba(127,127,127,.08); color: inherit; margin-bottom: 14px;
    font-family: inherit; color-scheme: light dark;
  }
  textarea { font-size: 15px; resize: none; }
  button { width: 100%; box-sizing: border-box; font-size: 16px; font-weight: 700;
    padding: 14px; border-radius: 12px; border: none; color: #fff; background: ${accent};
    -webkit-tap-highlight-color: transparent; }
  .err { color: #ef4444; font-size: 13px; margin-bottom: 12px; min-height: 16px; text-align: center; }
</style>
</head><body>
<label>Date &amp; time</label>
<input type="datetime-local" id="dt" value="${initial}" min="${min}" />
<label>Note (optional)</label>
<textarea id="note" rows="2" placeholder="e.g. call about renewal">${noteText}</textarea>
<div class="err" id="err"></div>
<button id="set">Set reminder</button>
<script>
  function post(o){ if (window.ReactNativeWebView) window.ReactNativeWebView.postMessage(JSON.stringify(o)); }
  var input = document.getElementById('dt');
  var noteEl = document.getElementById('note');
  var err = document.getElementById('err');
  document.getElementById('set').addEventListener('click', function(){
    var v = input.value;
    if (!v) { err.textContent = 'Please pick a date and time.'; return; }
    if (v <= '${min}') { err.textContent = 'Pick a time in the future.'; return; }
    post({ type: 'pick', value: v, note: (noteEl.value || '').trim() });
  });
  setTimeout(function(){ post({ type: 'ready' }); }, 60);
</script>
</body></html>`;
}

export function DateTimePickerModal({
  visible,
  title,
  initialNote,
  onClose,
  onPick,
}: DateTimePickerModalProps) {
  const colors = useColors();
  const insets = useSafeAreaInsets();

  const html = useMemo(() => {
    const now = new Date();
    const seed = new Date(now.getTime() + 86400000);
    seed.setHours(9, 0, 0, 0);
    return buildHtml(
      localInput(seed),
      localInput(now),
      colors.primary,
      initialNote ?? "",
    );
  }, [visible, colors.primary, initialNote]);

  const onMessage = (raw: string) => {
    try {
      const msg = JSON.parse(raw) as {
        type: string;
        value?: string;
        note?: string;
      };
      if (msg.type === "pick" && typeof msg.value === "string") {
        onPick(msg.value, typeof msg.note === "string" ? msg.note : "");
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
          <View style={{ height: 330 }}>
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
