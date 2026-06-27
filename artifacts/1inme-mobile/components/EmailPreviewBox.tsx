import { createElement, useMemo } from "react";
import { Platform, StyleSheet, Text, View } from "react-native";

import { useColors } from "@/hooks/useColors";

// react-native-webview ships an iframe shim for web, but importing it on web
// is problematic — mirror the EmbedModal pattern and only require it natively.
let WebView: typeof import("react-native-webview").WebView | null = null;
if (Platform.OS !== "web") {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  WebView = require("react-native-webview").WebView;
}

// Renders the central-pipeline preview of an email exactly as it would send:
// the resolved subject plus the body. HTML bodies render in a sandboxed
// WebView on native (and a same-origin srcDoc iframe on web); text bodies
// render as monospace plain text so whitespace is preserved.
export function EmailPreviewBox({
  subject,
  body,
  format,
  height = 320,
}: {
  subject: string;
  body: string;
  format: string;
  height?: number;
}) {
  const colors = useColors();
  const isHtml = format !== "text";

  const wrappedHtml = useMemo(() => {
    if (!isHtml) return "";
    return `<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">` +
      `<style>body{margin:0;padding:12px;font-family:-apple-system,Segoe UI,Roboto,sans-serif;` +
      `color:#111;background:#fff;word-break:break-word;} img{max-width:100%;height:auto;}</style>` +
      `</head><body>${body || "<p style='color:#888'>(empty body)</p>"}</body></html>`;
  }, [body, isHtml]);

  return (
    <View style={{ gap: 8 }}>
      <View
        style={[
          styles.subjectRow,
          { backgroundColor: colors.background, borderColor: colors.border, borderRadius: colors.radius },
        ]}
      >
        <Text style={[styles.subjectLabel, { color: colors.mutedForeground }]}>
          SUBJECT
        </Text>
        <Text style={[styles.subjectText, { color: colors.foreground }]}>
          {subject || "(no subject)"}
        </Text>
      </View>

      <View
        style={[
          styles.bodyWrap,
          { borderColor: colors.border, borderRadius: colors.radius, height },
        ]}
      >
        {isHtml ? (
          Platform.OS === "web" ? (
            <HtmlIframe html={wrappedHtml} />
          ) : WebView ? (
            <WebView
              originWhitelist={["*"]}
              source={{ html: wrappedHtml }}
              style={{ flex: 1, backgroundColor: "#fff" }}
              scrollEnabled
            />
          ) : (
            <Text style={[styles.textBody, { color: colors.foreground }]}>{body}</Text>
          )
        ) : (
          <Text style={[styles.textBody, { color: colors.foreground }]}>
            {body || "(empty body)"}
          </Text>
        )}
      </View>
    </View>
  );
}

// On web, react-native-web renders View as a div, so we can drop a sandboxed
// iframe in via createElement (avoids native JSX intrinsic typing for the
// web-only <iframe>). Kept isolated so the native path never touches DOM.
function HtmlIframe({ html }: { html: string }) {
  return createElement("iframe", {
    srcDoc: html,
    sandbox: "",
    style: { width: "100%", height: "100%", border: "0", background: "#fff" },
    title: "Email preview",
  });
}

const styles = StyleSheet.create({
  subjectRow: { borderWidth: StyleSheet.hairlineWidth, padding: 10, gap: 2 },
  subjectLabel: {
    fontSize: 10,
    fontFamily: "SpaceGrotesk_600SemiBold",
    letterSpacing: 0.5,
  },
  subjectText: { fontSize: 14, fontFamily: "SpaceGrotesk_600SemiBold" },
  bodyWrap: { borderWidth: StyleSheet.hairlineWidth, overflow: "hidden", backgroundColor: "#fff" },
  textBody: {
    fontFamily: Platform.OS === "ios" ? "Menlo" : "monospace",
    fontSize: 12,
    padding: 12,
  },
});
