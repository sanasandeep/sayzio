import Feather from "@expo/vector-icons/Feather";
import { useRouter } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";
import {
  type DialerSearchItem,
  type DialerSearchResult,
  dialerSearch,
} from "@/lib/api/dialer";

const E164 = /^\+[1-9]\d{6,14}$/;

/**
 * Dedicated grouped universal finder. Backed by the same server-side
 * DialerSearch as the web + REST dialer, so results never drift: one search
 * across Contacts, People, My links, Followed and Workspaces.
 */
export default function SearchScreen() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();

  const [q, setQ] = useState("");
  const [verifiedOnly, setVerifiedOnly] = useState(false);
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<DialerSearchResult | null>(null);

  const run = useCallback(
    async (term: string, verified: boolean) => {
      if (!term.trim()) {
        setResult(null);
        return;
      }
      setLoading(true);
      try {
        const res = await dialerSearch(term, { verified });
        setResult(res);
      } catch {
        setResult(null);
      } finally {
        setLoading(false);
      }
    },
    [],
  );

  useEffect(() => {
    const t = setTimeout(() => void run(q, verifiedOnly), 220);
    return () => clearTimeout(t);
  }, [q, verifiedOnly, run]);

  const open = useCallback(
    (item: DialerSearchItem) => {
      const a = item.action;
      if (a.number && E164.test(a.number)) {
        router.push({ pathname: "/dialer-profile", params: { number: a.number } });
        return;
      }
      if (a.contact_id) {
        router.push({ pathname: "/contacts/[id]", params: { id: String(a.contact_id) } });
        return;
      }
      if (a.url) {
        void Linking.openURL(a.url);
      }
    },
    [router],
  );

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View style={[styles.searchbar, { borderColor: colors.border }]}>
        <Feather name="search" size={18} color={colors.mutedForeground} />
        <TextInput
          autoFocus
          value={q}
          onChangeText={setQ}
          placeholder="Search contacts, people, links, workspaces"
          placeholderTextColor={colors.mutedForeground}
          style={[styles.input, { color: colors.foreground }]}
          returnKeyType="search"
        />
        {q.length > 0 ? (
          <Pressable onPress={() => setQ("")} hitSlop={10}>
            <Feather name="x" size={18} color={colors.mutedForeground} />
          </Pressable>
        ) : null}
      </View>

      <View style={styles.chips}>
        <Pressable
          onPress={() => setVerifiedOnly((v) => !v)}
          style={[
            styles.chip,
            {
              borderColor: verifiedOnly ? colors.primary : colors.border,
              backgroundColor: verifiedOnly ? colors.primary : "transparent",
            },
          ]}
        >
          <Feather
            name="check-circle"
            size={14}
            color={verifiedOnly ? colors.primaryForeground : colors.mutedForeground}
          />
          <Text
            style={[
              styles.chipText,
              { color: verifiedOnly ? colors.primaryForeground : colors.mutedForeground },
            ]}
          >
            Verified only
          </Text>
        </Pressable>
      </View>

      <ScrollView
        contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}
        keyboardShouldPersistTaps="handled"
      >
        {loading ? (
          <View style={styles.center}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : !result ? (
          <Text style={[styles.empty, { color: colors.mutedForeground }]}>
            Start typing to search across everything you can reach.
          </Text>
        ) : result.total === 0 ? (
          <Text style={[styles.empty, { color: colors.mutedForeground }]}>
            No matches for "{result.q}".
          </Text>
        ) : (
          result.groups.map((group) => (
            <View key={group.key} style={styles.group}>
              <Text style={[styles.groupLabel, { color: colors.mutedForeground }]}>
                {group.label}
              </Text>
              {group.items.map((item) => (
                <Pressable
                  key={`${item.type}-${item.id}`}
                  onPress={() => open(item)}
                  style={({ pressed }) => [
                    styles.row,
                    { backgroundColor: pressed ? colors.muted : colors.card, borderColor: colors.border },
                  ]}
                >
                  <View style={[styles.avatar, { backgroundColor: colors.muted }]}>
                    <Text style={[styles.avatarText, { color: colors.foreground }]}>
                      {item.initials}
                    </Text>
                  </View>
                  <View style={{ flex: 1 }}>
                    <View style={styles.rowTitle}>
                      <Text
                        numberOfLines={1}
                        style={[styles.title, { color: colors.foreground }]}
                      >
                        {item.title}
                      </Text>
                      {item.verified ? (
                        <Feather name="check-circle" size={14} color={colors.primary} />
                      ) : null}
                    </View>
                    {item.subtitle ? (
                      <Text
                        numberOfLines={1}
                        style={[styles.subtitle, { color: colors.mutedForeground }]}
                      >
                        {item.subtitle}
                      </Text>
                    ) : null}
                  </View>
                  <Text style={[styles.typeLabel, { color: colors.mutedForeground }]}>
                    {item.type_label}
                  </Text>
                </Pressable>
              ))}
            </View>
          ))
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  searchbar: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    margin: 16,
    marginBottom: 8,
    paddingHorizontal: 12,
    height: 46,
    borderWidth: 1,
    borderRadius: 12,
  },
  input: { flex: 1, fontSize: 16, fontFamily: "SpaceGrotesk_400Regular" },
  chips: { flexDirection: "row", gap: 8, paddingHorizontal: 16, paddingBottom: 8 },
  chip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderWidth: 1,
    borderRadius: 999,
  },
  chipText: { fontSize: 13, fontFamily: "SpaceGrotesk_500Medium" },
  center: { paddingVertical: 40, alignItems: "center" },
  empty: {
    textAlign: "center",
    marginTop: 40,
    paddingHorizontal: 32,
    fontSize: 15,
    lineHeight: 21,
    fontFamily: "SpaceGrotesk_400Regular",
  },
  group: { paddingHorizontal: 16, marginBottom: 8 },
  groupLabel: {
    fontSize: 12,
    textTransform: "uppercase",
    letterSpacing: 0.5,
    marginBottom: 6,
    fontFamily: "SpaceGrotesk_600SemiBold",
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 10,
    borderWidth: 1,
    borderRadius: 12,
    marginBottom: 8,
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: "center",
    justifyContent: "center",
  },
  avatarText: { fontSize: 14, fontFamily: "SpaceGrotesk_600SemiBold" },
  rowTitle: { flexDirection: "row", alignItems: "center", gap: 6 },
  title: { fontSize: 15, fontFamily: "SpaceGrotesk_600SemiBold" },
  subtitle: { fontSize: 13, fontFamily: "SpaceGrotesk_400Regular" },
  typeLabel: { fontSize: 12, fontFamily: "SpaceGrotesk_400Regular" },
});
