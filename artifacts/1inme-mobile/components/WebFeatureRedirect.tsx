import { Feather } from "@expo/vector-icons";
import { Stack } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useState } from "react";
import { Platform, ScrollView, StyleSheet, Text, View } from "react-native";

import { Button } from "@/components/Button";
import { useColors } from "@/hooks/useColors";
import { getBaseUrl } from "@/lib/api";
import { showAlert } from "@/lib/webAlert";

export type WebFeatureRedirectProps = {
  title: string;
  blurb: string;
  webPath: string;
  iconName?: keyof typeof Feather.glyphMap;
  /** Optional bullet points describing what's available on the web. */
  features?: string[];
};

/**
 * Lightweight bridge screen for web-only features that aren't yet
 * implemented natively on mobile. Surfaces the feature in the mobile
 * navigation so it's reachable, explains what it does, and opens the
 * authenticated web page in an in-app browser tab.
 *
 * Replaces the previous behavior where these features were silently
 * missing from the mobile app despite being part of the product.
 */
export function WebFeatureRedirect({
  title,
  blurb,
  webPath,
  iconName = "external-link",
  features,
}: WebFeatureRedirectProps) {
  const colors = useColors();
  const [busy, setBusy] = useState(false);

  const onOpen = async () => {
    setBusy(true);
    try {
      const url = `${getBaseUrl()}${webPath.startsWith("/") ? "" : "/"}${webPath}`;
      await WebBrowser.openBrowserAsync(url, {
        toolbarColor: colors.background,
        controlsColor: colors.primary,
      });
    } catch (e) {
      const msg = e instanceof Error ? e.message : "Could not open the web view";
      if (Platform.OS === "web") {
        // On web, just navigate the tab directly.
        window.location.href = `${getBaseUrl()}${webPath}`;
      } else {
        showAlert("Couldn't open", msg);
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title }} />
      <ScrollView contentContainerStyle={styles.body}>
        <View
          style={[
            styles.hero,
            {
              backgroundColor: colors.primary + "12",
              borderColor: colors.primary + "33",
              borderRadius: colors.radius,
            },
          ]}
        >
          <View
            style={[
              styles.iconWrap,
              { backgroundColor: colors.primary + "20" },
            ]}
          >
            <Feather name={iconName} size={26} color={colors.primary} />
          </View>
          <Text style={[styles.title, { color: colors.foreground }]}>
            {title}
          </Text>
          <Text style={[styles.blurb, { color: colors.mutedForeground }]}>
            {blurb}
          </Text>
        </View>

        {features?.length ? (
          <View
            style={[
              styles.list,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            {features.map((line, i) => (
              <View
                key={`${i}-${line}`}
                style={[
                  styles.row,
                  {
                    borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                    borderTopColor: colors.border,
                  },
                ]}
              >
                <Feather name="check" size={16} color={colors.primary} />
                <Text style={[styles.rowText, { color: colors.foreground }]}>
                  {line}
                </Text>
              </View>
            ))}
          </View>
        ) : null}

        <Text style={[styles.hint, { color: colors.mutedForeground }]}>
          We're rolling this experience out natively on mobile. In the meantime,
          tap the button below to use it on the web; you'll stay signed in
          inside the in-app browser.
        </Text>

        <Button
          label={`Open ${title.toLowerCase()} on the web`}
          onPress={onOpen}
          loading={busy}
        />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  body: { padding: 20, gap: 16, paddingBottom: 40 },
  hero: { padding: 20, gap: 8, borderWidth: 1 },
  iconWrap: {
    width: 52,
    height: 52,
    borderRadius: 999,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 4,
  },
  title: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 22,
    letterSpacing: -0.3,
  },
  blurb: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    lineHeight: 20,
  },
  list: { borderWidth: 1, paddingVertical: 4 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingVertical: 12,
    paddingHorizontal: 14,
  },
  rowText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 14, flex: 1 },
  hint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 18,
    paddingHorizontal: 4,
  },
});

export default WebFeatureRedirect;
