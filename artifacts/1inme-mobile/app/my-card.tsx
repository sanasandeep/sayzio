import { Feather } from "@expo/vector-icons";
import { router } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { SvgXml } from "react-native-svg";

import { useColors } from "@/hooks/useColors";
import { getMyEventCard, type MyEventCard } from "@/lib/api/events";

/**
 * Task #5008 — "My card" screen. Displays the current user's contact-exchange
 * QR code (encoded to their public profile URL) so they can show it face-to-
 * face at events. Scanning with any camera opens their profile; in-app
 * scanning deep-links to the follow/subscribe/save-to-contacts flow.
 */
export default function MyCardScreen() {
  const colors = useColors();
  const [card, setCard] = useState<MyEventCard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await getMyEventCard();
      setCard(data);
    } catch {
      setError("Could not load your card. Please try again.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <View style={[styles.container, { backgroundColor: colors.background }]}>
      <View style={styles.header}>
        <Pressable style={styles.backBtn} onPress={() => router.back()}>
          <Feather name="arrow-left" size={22} color={colors.foreground} />
        </Pressable>
        <Text style={[styles.headerTitle, { color: colors.foreground }]}>
          My Card
        </Text>
      </View>

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {loading && <ActivityIndicator color={colors.primary} size="large" />}

        {!loading && error && (
          <>
            <Text style={[styles.errorText, { color: colors.destructive }]}>
              {error}
            </Text>
            <Pressable
              style={[styles.retryBtn, { backgroundColor: colors.primary }]}
              onPress={load}
            >
              <Text style={styles.retryText}>Try again</Text>
            </Pressable>
          </>
        )}

        {!loading && card && (
          <View
            style={[
              styles.card,
              { backgroundColor: colors.card, borderColor: colors.border },
            ]}
          >
            <View style={styles.qrWrapper}>
              <SvgXml xml={card.qr_svg} width={220} height={220} />
            </View>

            {card.name ? (
              <Text style={[styles.name, { color: colors.foreground }]}>
                {card.name}
              </Text>
            ) : null}
            {card.handle ? (
              <Text
                style={[styles.handle, { color: colors.mutedForeground }]}
              >
                @{card.handle}
              </Text>
            ) : null}

            {card.bio ? (
              <>
                <View
                  style={[styles.divider, { backgroundColor: colors.border }]}
                />
                <Text
                  style={[styles.bio, { color: colors.mutedForeground }]}
                >
                  {card.bio}
                </Text>
              </>
            ) : null}

            <View
              style={[styles.divider, { backgroundColor: colors.border }]}
            />
            <Text style={[styles.hint, { color: colors.mutedForeground }]}>
              Show this QR code to fellow attendees to exchange contacts.
              Scanning it opens your public profile with one-tap follow,
              subscribe, and save-to-contacts actions.
            </Text>
          </View>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 16,
    paddingTop: 52,
    paddingBottom: 12,
    gap: 8,
  },
  backBtn: {
    padding: 6,
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: "700",
    flex: 1,
  },
  scroll: {
    flex: 1,
  },
  scrollContent: {
    alignItems: "center",
    paddingVertical: 32,
    paddingHorizontal: 24,
    gap: 24,
  },
  card: {
    width: "100%",
    borderRadius: 20,
    borderWidth: 1,
    padding: 28,
    alignItems: "center",
    gap: 16,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.10,
    shadowRadius: 12,
    elevation: 5,
  },
  qrWrapper: {
    backgroundColor: "#ffffff",
    borderRadius: 12,
    padding: 12,
  },
  name: {
    fontSize: 20,
    fontWeight: "700",
    textAlign: "center",
  },
  handle: {
    fontSize: 14,
    textAlign: "center",
  },
  bio: {
    fontSize: 14,
    textAlign: "center",
    lineHeight: 20,
  },
  divider: {
    width: "100%",
    height: 1,
  },
  hint: {
    fontSize: 13,
    textAlign: "center",
    lineHeight: 20,
  },
  errorText: {
    textAlign: "center",
    fontSize: 14,
    paddingHorizontal: 24,
  },
  retryBtn: {
    marginTop: 12,
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 8,
  },
  retryText: {
    color: "#fff",
    fontWeight: "600",
  },
});
