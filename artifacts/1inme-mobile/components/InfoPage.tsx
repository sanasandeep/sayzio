import { Stack } from "expo-router";
import { ScrollView, StyleSheet, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { Platform } from "react-native";

import { useColors } from "@/hooks/useColors";

export type InfoSection = {
  heading?: string;
  body: string;
};

export function InfoPage({
  title,
  intro,
  sections,
}: {
  title: string;
  intro?: string;
  sections: InfoSection[];
}) {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const webBottom = Platform.OS === "web" ? 34 : 0;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title,
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
          { paddingBottom: insets.bottom + 32 + webBottom },
        ]}
      >
        <Text style={[styles.title, { color: colors.foreground }]}>{title}</Text>
        {intro ? (
          <Text style={[styles.intro, { color: colors.mutedForeground }]}>
            {intro}
          </Text>
        ) : null}
        {sections.map((s, i) => (
          <View key={i} style={styles.section}>
            {s.heading ? (
              <Text style={[styles.h2, { color: colors.foreground }]}>
                {s.heading}
              </Text>
            ) : null}
            <Text style={[styles.body, { color: colors.foreground }]}>
              {s.body}
            </Text>
          </View>
        ))}
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
});
