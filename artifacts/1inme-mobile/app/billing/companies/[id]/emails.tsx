import { Feather } from "@expo/vector-icons";
import { useQuery } from "@tanstack/react-query";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import { getCompanyEmailTemplates } from "@/lib/api/company-mail";

// Mobile parity for the web per-company "Client emails" list: the small set of
// client-facing accounting templates (invoice + receipt) a creator can rewrite
// per billing company. Tap one to open the editor with a live preview. An
// "Edited" badge marks templates that carry a company override.

export default function CompanyEmailTemplatesScreen() {
  const colors = useColors();
  const router = useRouter();
  const { id: rawId } = useLocalSearchParams<{ id: string }>();
  const companyId = Number(rawId);

  const query = useQuery({
    queryKey: ["company-email-templates", companyId],
    queryFn: () => getCompanyEmailTemplates(companyId),
    enabled: Number.isFinite(companyId),
  });

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Client emails", headerBackTitle: "Back" }} />
      <ScrollView contentContainerStyle={{ padding: 16, gap: 16, paddingBottom: 48 }}>
        <Text style={{ color: colors.mutedForeground, fontSize: 13, lineHeight: 18 }}>
          Customise the invoice and receipt emails your clients receive from this
          company. Reset any one to fall back to the inherited default.
        </Text>

        {query.isLoading ? (
          <ActivityIndicator color={colors.primary} style={{ marginTop: 24 }} />
        ) : query.isError ? (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Feather name="alert-triangle" size={20} color={colors.destructive} />
            <Text style={{ color: colors.foreground, marginTop: 6 }}>
              {(query.error as any)?.status === 404
                ? "This company couldn't be found."
                : "Couldn't load the email templates."}
            </Text>
          </View>
        ) : query.data && query.data.length > 0 ? (
          <View
            style={[
              styles.list,
              { backgroundColor: colors.card, borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            {query.data.map((t, i) => (
              <Pressable
                key={t.key}
                onPress={() =>
                  router.push(
                    `/billing/companies/${companyId}/emails/${encodeURIComponent(t.key)}` as never,
                  )
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
                        <Text style={[styles.badgeText, { color: colors.primary }]}>Edited</Text>
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
        ) : (
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Text style={{ color: colors.mutedForeground }}>No editable templates.</Text>
          </View>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  card: { padding: 14, borderWidth: 1, borderRadius: 12, gap: 6 },
  list: { borderWidth: StyleSheet.hairlineWidth, overflow: "hidden" },
  listItem: { flexDirection: "row", alignItems: "center", gap: 12, padding: 14 },
  rowHead: { flexDirection: "row", alignItems: "center", gap: 8 },
  itemLabel: { fontSize: 15, fontFamily: "SpaceGrotesk_600SemiBold" },
  badge: { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 999 },
  badgeText: { fontSize: 10, fontFamily: "SpaceGrotesk_700Bold" },
});
