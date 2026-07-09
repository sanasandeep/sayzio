// Browser extension info page. Linked from the Profile → Settings section so
// the mobile app mirrors the web "Settings → Connected Accounts & Apps" card
// (the knowledge base points users there for the extension install links).
import { Feather } from "@expo/vector-icons";
import { Stack } from "expo-router";
import {
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";

const STORES: { label: string; url: string; icon: keyof typeof Feather.glyphMap }[] = [
  {
    label: "Chrome Web Store",
    url: "https://chromewebstore.google.com/search/Sayzio",
    icon: "chrome",
  },
  {
    label: "Edge Add-ons",
    url: "https://microsoftedge.microsoft.com/addons/Search/Sayzio",
    icon: "globe",
  },
  {
    label: "Firefox Add-ons",
    url: "https://addons.mozilla.org/en-US/firefox/search/?q=Sayzio",
    icon: "globe",
  },
];

const SECTIONS: { heading: string; body: string }[] = [
  {
    heading: "What it does",
    body: "Shorten the page you're on, capture Google Maps and Trustpilot reviews, save events to your calendar, turn any page into a Link in Bio, and see your unread notification count as a badge on the extension icon.",
  },
  {
    heading: "How to install",
    body: 'Open one of the stores below on your computer, search for "Sayzio", and add the extension to your browser. It works on Chrome, Edge, and Firefox.',
  },
  {
    heading: "Signing in",
    body: "After installing, click the extension icon and choose Sign in with Sayzio. A tab opens to log you in — once done, the extension is connected. You can revoke its access any time from Devices & sessions in Settings.",
  },
];

export default function BrowserExtensionInfo() {
  const colors = useColors();
  const insets = useSafeAreaInsets();

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Browser extension",
          headerStyle: { backgroundColor: colors.background },
          headerTitleStyle: {
            color: colors.foreground,
            fontFamily: "SpaceGrotesk_600SemiBold",
          },
          headerTintColor: colors.primary,
        }}
      />
      <ScrollView
        contentContainerStyle={[
          styles.content,
          { paddingBottom: insets.bottom + 32 },
        ]}
      >
        <Text style={[styles.title, { color: colors.foreground }]}>
          Browser extension
        </Text>
        <Text style={[styles.intro, { color: colors.mutedForeground }]}>
          The Sayzio extension brings link shortening, review capture, and
          notifications right into your desktop browser.
        </Text>

        {SECTIONS.map((s) => (
          <View key={s.heading} style={styles.section}>
            <Text style={[styles.h2, { color: colors.foreground }]}>
              {s.heading}
            </Text>
            <Text style={[styles.body, { color: colors.foreground }]}>
              {s.body}
            </Text>
          </View>
        ))}

        <View style={styles.section}>
          <Text style={[styles.h2, { color: colors.foreground }]}>
            Get the extension
          </Text>
          {STORES.map((store) => (
            <Pressable
              key={store.label}
              onPress={() => Linking.openURL(store.url)}
              style={[
                styles.storeButton,
                { backgroundColor: colors.card, borderColor: colors.border },
              ]}
            >
              <Feather name={store.icon} size={18} color={colors.primary} />
              <Text style={[styles.storeLabel, { color: colors.foreground }]}>
                {store.label}
              </Text>
              <Feather
                name="external-link"
                size={14}
                color={colors.mutedForeground}
              />
            </Pressable>
          ))}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  content: { padding: 24, gap: 18 },
  title: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 32,
    letterSpacing: -0.5,
  },
  intro: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 17, lineHeight: 26 },
  section: { gap: 8, marginTop: 8 },
  h2: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 20 },
  body: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 16, lineHeight: 25 },
  storeButton: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    borderRadius: 14,
    borderWidth: StyleSheet.hairlineWidth,
    paddingVertical: 14,
    paddingHorizontal: 16,
    marginTop: 4,
  },
  storeLabel: {
    flex: 1,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 15,
  },
});
