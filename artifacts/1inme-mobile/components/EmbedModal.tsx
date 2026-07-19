import { Feather } from "@expo/vector-icons";
import * as Linking from "expo-linking";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Modal,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";

// react-native-webview ships an iframe shim for web, but importing it from
// the web bundle is unreliable across Expo SDKs. Lazy-require so the web
// build never tries to evaluate it.
let WebView: typeof import("react-native-webview").WebView | null = null;
if (Platform.OS !== "web") {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  WebView = require("react-native-webview").WebView;
}

export type EmbedModalProps = {
  visible: boolean;
  url: string | null;
  title?: string;
  // Show a "third-party content" notice. Recommended for free-form custom
  // HTML / iframe blocks where the embedded site is fully creator-defined.
  sandboxed?: boolean;
  onClose: () => void;
};

export function EmbedModal({ visible, url, title, sandboxed, onClose }: EmbedModalProps) {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const [loading, setLoading] = useState(true);

  // Reset the loading spinner whenever the URL changes so reopening the
  // modal for a new embed doesn't briefly show a stale ready state.
  useEffect(() => {
    if (visible) setLoading(true);
  }, [visible, url]);

  // On web we can't sandbox a third-party iframe through our domain, so
  // hand off to the system browser instead of trying to render a WebView.
  useEffect(() => {
    if (visible && url && Platform.OS === "web") {
      void Linking.openURL(url);
      onClose();
    }
  }, [visible, url, onClose]);

  if (Platform.OS === "web") return null;

  return (
    <Modal
      visible={visible && !!url}
      animationType="slide"
      onRequestClose={onClose}
      presentationStyle="pageSheet"
    >
      <View style={[styles.root, { backgroundColor: colors.background }]}>
        <View
          style={[
            styles.header,
            {
              paddingTop: insets.top + 10,
              borderBottomColor: colors.border,
              backgroundColor: colors.card,
            },
          ]}
        >
          <Pressable onPress={onClose} hitSlop={12} style={styles.headerBtn}>
            <Feather name="x" size={22} color={colors.foreground} />
          </Pressable>
          <Text
            style={[styles.headerTitle, { color: colors.foreground }]}
            numberOfLines={1}
          >
            {title ?? "Preview"}
          </Text>
          <Pressable
            onPress={() => url && void Linking.openURL(url)}
            hitSlop={12}
            style={styles.headerBtn}
          >
            <Feather name="external-link" size={20} color={colors.foreground} />
          </Pressable>
        </View>

        {sandboxed ? (
          <View
            style={[
              styles.notice,
              { backgroundColor: colors.card, borderBottomColor: colors.border },
            ]}
          >
            <Feather name="alert-triangle" size={14} color={colors.mutedForeground} />
            <Text style={[styles.noticeText, { color: colors.mutedForeground }]}>
              Third-party embed: content is provided by the creator.
            </Text>
          </View>
        ) : null}

        <View style={{ flex: 1 }}>
          {url && WebView ? (
            <WebView
              source={{ uri: url }}
              style={{ flex: 1, backgroundColor: colors.background }}
              onLoadStart={() => setLoading(true)}
              onLoadEnd={() => setLoading(false)}
              // Sandbox: never let an embed pop up auto-launches into other
              // apps or opt into JS bridges we don't control.
              javaScriptEnabled
              domStorageEnabled
              originWhitelist={["http://*", "https://*"]}
              setSupportMultipleWindows={false}
              allowsBackForwardNavigationGestures
              // Multiple windows are disabled, so target="_blank" links (e.g.
              // the "Get directions" button on the RSVP page) load in this
              // webview and fire this hook. Hand off maps + app-scheme links
              // (tel/mailto/geo) to the system so the native maps app / dialer
              // opens instead of navigating away from the embedded page. All
              // other same-flow navigations continue in the webview.
              onShouldStartLoadWithRequest={(req) => {
                const u = req.url || "";
                if (
                  /^(tel:|mailto:|geo:|maps:)/i.test(u) ||
                  /^https?:\/\/((www|maps)\.)?(google\.[^/]+\/maps|maps\.apple\.com)/i.test(
                    u,
                  )
                ) {
                  void Linking.openURL(u);
                  return false;
                }
                return true;
              }}
            />
          ) : null}
          {loading ? (
            <View pointerEvents="none" style={styles.spinner}>
              <ActivityIndicator color={colors.primary} />
            </View>
          ) : null}
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  header: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 16,
    paddingBottom: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
    gap: 12,
  },
  headerBtn: { padding: 4 },
  headerTitle: {
    flex: 1,
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
    textAlign: "center",
  },
  notice: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  noticeText: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    flex: 1,
  },
  spinner: {
    ...StyleSheet.absoluteFillObject,
    alignItems: "center",
    justifyContent: "center",
  },
});
