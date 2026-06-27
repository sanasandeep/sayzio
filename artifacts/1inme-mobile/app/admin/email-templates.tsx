import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import { getEmailTemplates } from "@/lib/api/email";

// Super-admin parity for the web "Email Templates" page. Lists every templated
// email grouped by category with an "edited" badge for the ones that carry an
// admin override; tap one to open the editor with a live preview. Gated
// server-side behind `settings.manage` (403 → access message).

export default function EmailTemplatesScreen() {
  const colors = useColors();
  const router = useRouter();

  const query = useQuery({
    queryKey: ["admin-email-templates"],
    queryFn: getEmailTemplates,
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Email templates", headerBackTitle: "Back" }} />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 16, paddingBottom: 48 }}>
        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 403
                ? "You need admin access to manage email templates."
                : "Couldn't load email templates."}
            </Text>
          </View>
        ) : query.data && query.data.length > 0 ? (
          query.data.map((cat) => (
            <View key={cat.category} style={{ gap: 8 }}>
              <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
                {cat.label}
              </Text>
              <View
                style={[
                  styles.list,
                  { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
                ]}
              >
                {cat.templates.map((t, i) => (
                  <Pressable
                    key={t.key}
                    onPress={() =>
                      router.push(`/admin/email-templates/${encodeURIComponent(t.key)}` as never)
                    }
                    style={({ pressed }) => [
                      styles.listItem,
                      {
                        borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                        borderTopColor: colors.border,
                        opacity: pressed ? 0.7 : 1,
                      },
                    ]}
                  >
                    <View style={{ flex: 1 }}>
                      <View style={styles.rowHead}>
                        <Text style={[styles.itemLabel, { color: colors.foreground }]}>
                          {t.label}
                        </Text>
                        {t.overridden ? (
                          <View style={[styles.badge, { backgroundColor: colors.primary + "1a" }]}>
                            <Text style={[styles.badgeText, { color: colors.primary }]}>
                              Edited
                            </Text>
                          </View>
                        ) : null}
                      </View>
                      {t.description ? (
                        <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 2 }}>
                          {t.description}
                        </Text>
                      ) : null}
                    </View>
                    <Feather name="chevron-right" size={18} color={colors.mutedForeground} />
                  </Pressable>
                ))}
              </View>
            </View>
          ))
        ) : (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Text style={{ color: colors.mutedForeground }}>No email templates found.</Text>
          </View>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { padding: 14, borderWidth: 1, borderRadius: 12, gap: 6 },
  sectionLabel: {
    fontSize: 12,
    fontFamily: "SpaceGrotesk_600SemiBold",
    letterSpacing: 0.5,
    textTransform: "uppercase",
  },
  list: { borderWidth: StyleSheet.hairlineWidth, overflow: "hidden" },
  listItem: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14 },
  rowHead: { flexDirection: "row", alignItems: "center", gap: 8 },
  itemLabel: { fontSize: 15, fontFamily: "SpaceGrotesk_600SemiBold" },
  badge: { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 999 },
  badgeText: { fontSize: 10, fontFamily: "SpaceGrotesk_700Bold" },
});
