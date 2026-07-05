import { Feather } from "@expo/vector-icons";
import { useEffect, useRef, useState } from "react";
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
import { getBaseUrl } from "@/lib/api";

// Lazy-require so the web bundle never tries to evaluate the native module.
let WebView: typeof import("react-native-webview").WebView | null = null;
if (Platform.OS !== "web") {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  WebView = require("react-native-webview").WebView;
}

export type RsvpWebViewModalProps = {
  visible: boolean;
  alias: string;
  onClose: () => void;
  /** Fires once the visitor's RSVP has been recorded (redirected to the manage page). */
  onSubmitted?: () => void;
};

/**
 * The free RSVP flow (Yes/No/Maybe, plus-ones, custom questions, capacity,
 * waitlists) lives entirely server-side in the existing public `/{alias}/rsvp`
 * page — there is no separate JSON API for it. Rather than duplicating that
 * logic client-side (out of scope: backend/API changes), we bring it into the
 * app by embedding the same page in a WebView, so the visitor never has to
 * leave the dialer app. A successful submit redirects to `/rsvp/manage/...`,
 * which we watch for to report completion back to the caller.
 */
export function RsvpWebViewModal({
  visible,
  alias,
  onClose,
  onSubmitted,
}: RsvpWebViewModalProps) {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const [loading, setLoading] = useState(true);
  const submittedRef = useRef(false);

  useEffect(() => {
    if (visible) {
      setLoading(true);
      submittedRef.current = false;
    }
  }, [visible]);

  const url = `${getBaseUrl()}/${alias}/rsvp`;

  return (
    <Modal
      visible={visible}
      animationType="slide"
      onRequestClose={onClose}
      presentationStyle="pageSheet"
    >
      <View style={{ flex: 1, backgroundColor: colors.background }}>
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            paddingTop: insets.top + 10,
            paddingHorizontal: 16,
            paddingBottom: 10,
            borderBottomWidth: 1,
            borderBottomColor: colors.border,
            backgroundColor: colors.card,
          }}
        >
          <Pressable onPress={onClose} hitSlop={12} style={{ padding: 4, width: 30 }}>
            <Feather name="x" size={22} color={colors.foreground} />
          </Pressable>
          <Text
            style={{
              flex: 1,
              textAlign: "center",
              fontWeight: "700",
              fontSize: 15,
              color: colors.foreground,
            }}
            numberOfLines={1}
          >
            RSVP
          </Text>
          <View style={{ width: 30 }} />
        </View>

        <View style={{ flex: 1 }}>
          {WebView ? (
            <WebView
              source={{ uri: url }}
              style={{ flex: 1, backgroundColor: colors.background }}
              javaScriptEnabled
              domStorageEnabled
              sharedCookiesEnabled
              onLoadEnd={() => setLoading(false)}
              onNavigationStateChange={(nav) => {
                if (
                  !submittedRef.current &&
                  typeof nav.url === "string" &&
                  nav.url.includes("/rsvp/manage/")
                ) {
                  submittedRef.current = true;
                  onSubmitted?.();
                }
              }}
            />
          ) : (
            <View
              style={{
                flex: 1,
                alignItems: "center",
                justifyContent: "center",
                paddingHorizontal: 24,
              }}
            >
              <Text style={{ color: colors.mutedForeground, textAlign: "center" }}>
                RSVP isn't available here. Please try again from a device that
                supports in-app browsing.
              </Text>
            </View>
          )}
          {loading && WebView ? (
            <View
              pointerEvents="none"
              style={[
                StyleSheet.absoluteFillObject,
                { alignItems: "center", justifyContent: "center" },
              ]}
            >
              <ActivityIndicator color={colors.primary} />
            </View>
          ) : null}
        </View>
      </View>
    </Modal>
  );
}
