import AsyncStorage from "@react-native-async-storage/async-storage";
import { Feather } from "@expo/vector-icons";
import * as Contacts from "expo-contacts";
import * as Haptics from "expo-haptics";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";
import {
  type DialerHistoryItem,
  dialerHistory,
  lookupNumber,
} from "@/lib/api/dialer";
import { type Contact, listContacts } from "@/lib/api/contacts";

type Tab = "keypad" | "recent" | "contacts";

// Structural type for the subset of an expo-contacts contact we render —
// expo-contacts changes its exported types between minors, so we avoid
// importing the named type and pluck the fields we actually use.
type DeviceContact = {
  id?: string;
  name?: string;
  phoneNumbers?: { number?: string; digits?: string }[];
};

type LocalRecent = {
  /** Number as the user dialed it (may not be E.164). */
  number: string;
  /** Best-effort label resolved at dial time. */
  label: string | null;
  /** Unix ms. */
  at: number;
};

const RECENT_KEY = "1inme.dialer.recent.v1";
const RECENT_MAX = 50;

const KEYS: { v: string; sub?: string }[] = [
  { v: "1" },
  { v: "2", sub: "ABC" },
  { v: "3", sub: "DEF" },
  { v: "4", sub: "GHI" },
  { v: "5", sub: "JKL" },
  { v: "6", sub: "MNO" },
  { v: "7", sub: "PQRS" },
  { v: "8", sub: "TUV" },
  { v: "9", sub: "WXYZ" },
  { v: "*" },
  { v: "0", sub: "+" },
  { v: "#" },
];

// Strict E.164 — matches the server-side validator. Only numbers that
// satisfy this are eligible for the server lookup/history POST.
const E164 = /^\+[1-9]\d{6,14}$/;

async function loadLocalRecent(): Promise<LocalRecent[]> {
  try {
    const raw = await AsyncStorage.getItem(RECENT_KEY);
    return raw ? (JSON.parse(raw) as LocalRecent[]) : [];
  } catch {
    return [];
  }
}

async function saveLocalRecent(list: LocalRecent[]): Promise<void> {
  try {
    await AsyncStorage.setItem(
      RECENT_KEY,
      JSON.stringify(list.slice(0, RECENT_MAX)),
    );
  } catch {
    /* best-effort; not worth surfacing to the user */
  }
}

function formatRelative(at: number | string | null): string {
  if (!at) return "";
  const ms = typeof at === "number" ? at : new Date(at).getTime();
  if (Number.isNaN(ms)) return "";
  const diff = Date.now() - ms;
  if (diff < 60_000) return "just now";
  if (diff < 3_600_000) return `${Math.floor(diff / 60_000)}m ago`;
  if (diff < 86_400_000) return `${Math.floor(diff / 3_600_000)}h ago`;
  if (diff < 7 * 86_400_000) return `${Math.floor(diff / 86_400_000)}d ago`;
  return new Date(ms).toLocaleDateString();
}

export default function DialerScreen() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  // Other screens (e.g. a biolink `tel:` block) deep-link into the dialer
  // with a number to pre-fill, optionally auto-dialing on mount.
  const params = useLocalSearchParams<{
    prefill?: string;
    name?: string;
    autoDial?: string;
  }>();

  const [tab, setTab] = useState<Tab>("keypad");
  const [number, setNumber] = useState(
    typeof params.prefill === "string" ? params.prefill : "",
  );

  const [localRecent, setLocalRecent] = useState<LocalRecent[]>([]);
  const [serverHistory, setServerHistory] = useState<DialerHistoryItem[]>([]);
  const [recentLoading, setRecentLoading] = useState(false);

  const [contactsQuery, setContactsQuery] = useState("");
  const [appContacts, setAppContacts] = useState<Contact[]>([]);
  const [deviceContacts, setDeviceContacts] = useState<DeviceContact[]>([]);
  const [contactsLoading, setContactsLoading] = useState(false);
  const [deviceAccess, setDeviceAccess] = useState<
    "unknown" | "granted" | "denied"
  >("unknown");

  // Initial recent load.
  useEffect(() => {
    void refreshRecent();
  }, []);

  // Debounced contacts search.
  useEffect(() => {
    if (tab !== "contacts") return;
    const t = setTimeout(() => void refreshContacts(contactsQuery), 250);
    return () => clearTimeout(t);
  }, [tab, contactsQuery]);

  const refreshRecent = useCallback(async () => {
    setRecentLoading(true);
    const local = await loadLocalRecent();
    setLocalRecent(local);
    try {
      const items = await dialerHistory();
      setServerHistory(items);
    } catch {
      /* ignore — local list is the primary surface */
    } finally {
      setRecentLoading(false);
    }
  }, []);

  const refreshContacts = useCallback(async (q: string) => {
    setContactsLoading(true);
    try {
      const res = await listContacts(q || undefined);
      // Only contacts that have at least one phone are dialable.
      setAppContacts(res.items.filter((c) => (c.phones?.length ?? 0) > 0));
    } catch {
      setAppContacts([]);
    } finally {
      setContactsLoading(false);
    }
  }, []);

  const requestDeviceContacts = useCallback(async () => {
    try {
      const { status } = await Contacts.requestPermissionsAsync();
      if (status !== "granted") {
        setDeviceAccess("denied");
        return;
      }
      setDeviceAccess("granted");
      const { data } = await Contacts.getContactsAsync({
        fields: [Contacts.Fields.PhoneNumbers, Contacts.Fields.Name],
      });
      const list = (data as unknown as DeviceContact[]).filter(
        (c) => (c.phoneNumbers?.length ?? 0) > 0,
      );
      // Sort client-side so we don't depend on expo-contacts' SortTypes
      // (it moves between releases). Plain locale-aware A→Z by name.
      list.sort((a, b) =>
        (a.name ?? "").localeCompare(b.name ?? "", undefined, {
          sensitivity: "base",
        }),
      );
      setDeviceContacts(list);
    } catch {
      setDeviceAccess("denied");
    }
  }, []);

  // Combined recent list — server history is the cross-device source of
  // truth for numbers; we layer the *local* device-only labels on top
  // when we recognise a match by number, so the redial button shows the
  // friendly name even if the server didn't resolve a contact.
  const recentList = useMemo(() => {
    const byNumber = new Map<string, LocalRecent>();
    for (const r of localRecent) byNumber.set(r.number, r);

    type Row = {
      key: string;
      number: string;
      label: string | null;
      at: number;
      source: "server" | "local";
    };
    const rows: Row[] = [];

    for (const s of serverHistory) {
      const local = byNumber.get(s.number_e164);
      rows.push({
        key: `s${s.id}`,
        number: s.number_e164,
        label: local?.label ?? null,
        at: s.looked_up_at ? new Date(s.looked_up_at).getTime() : 0,
        source: "server",
      });
    }
    // Local-only entries (e.g. dialed before the server roundtrip
    // succeeded, or non-E.164 numbers the server rejected).
    const seen = new Set(rows.map((r) => r.number));
    for (const r of localRecent) {
      if (seen.has(r.number)) continue;
      rows.push({
        key: `l${r.number}-${r.at}`,
        number: r.number,
        label: r.label,
        at: r.at,
        source: "local",
      });
    }
    rows.sort((a, b) => b.at - a.at);
    return rows.slice(0, RECENT_MAX);
  }, [serverHistory, localRecent]);

  // React Native's Pressable fires onPress AFTER onLongPress on release,
  // which would otherwise turn long-press-0 into "+0" instead of "+".
  // We set a ref when the long-press fires and skip the next onPress.
  const suppressNextPress = useRef(false);

  const press = useCallback((v: string) => {
    if (suppressNextPress.current) {
      suppressNextPress.current = false;
      return;
    }
    void Haptics.selectionAsync();
    setNumber((n) => n + v);
  }, []);

  const longPressZero = useCallback(() => {
    suppressNextPress.current = true;
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    setNumber((n) => n + "+");
  }, []);

  const backspace = useCallback(() => {
    void Haptics.selectionAsync();
    setNumber((n) => n.slice(0, -1));
  }, []);

  const clearAll = useCallback(() => {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Heavy);
    setNumber("");
  }, []);

  const dial = useCallback(
    async (raw: string, label?: string | null) => {
      const trimmed = raw.trim();
      if (!trimmed) {
        Alert.alert("No number", "Enter a number to dial.");
        return;
      }
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);

      // Update local recents immediately so the Recent tab reflects the
      // dial even if the device dialer fails to open. Coerce empty/blank
      // labels to null so the Recent row never renders a blank line where
      // the name should be.
      const cleanedLabel =
        typeof label === "string" && label.trim() !== ""
          ? label.trim()
          : null;
      const entry: LocalRecent = {
        number: trimmed,
        label: cleanedLabel,
        at: Date.now(),
      };
      const next = [
        entry,
        ...localRecent.filter((r) => r.number !== trimmed),
      ].slice(0, RECENT_MAX);
      setLocalRecent(next);
      void saveLocalRecent(next);

      // Best-effort cross-device record (E.164 only).
      if (E164.test(trimmed)) {
        lookupNumber(trimmed).catch(() => {
          /* swallow — local recents are still authoritative */
        });
      }

      // Open the in-app active-call screen instead of the device's
      // native phone dialer — see task #395. Real telephony is wired
      // separately; this screen is the UI shell with mute/end controls.
      router.push({
        pathname: "/call/active",
        params: {
          number: trimmed,
          ...(cleanedLabel ? { name: cleanedLabel } : {}),
        },
      });
    },
    [localRecent, router],
  );

  // Auto-dial when navigated here with `?prefill=…&autoDial=1` (e.g. a
  // biolink `tel:` block). Guarded by a ref so re-renders / param echo
  // can't trigger a second dial.
  const autoDialedRef = useRef(false);
  useEffect(() => {
    if (autoDialedRef.current) return;
    const prefill =
      typeof params.prefill === "string" ? params.prefill.trim() : "";
    const autoDial =
      typeof params.autoDial === "string" &&
      params.autoDial !== "" &&
      params.autoDial !== "0" &&
      params.autoDial !== "false";
    if (!prefill || !autoDial) return;
    autoDialedRef.current = true;
    const name = typeof params.name === "string" ? params.name : null;
    void dial(prefill, name);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [params.prefill, params.autoDial]);

  const removeRecent = useCallback(
    async (n: string) => {
      const next = localRecent.filter((r) => r.number !== n);
      setLocalRecent(next);
      await saveLocalRecent(next);
    },
    [localRecent],
  );

  return (
    <View style={[styles.root, { backgroundColor: colors.background }]}>
      <Stack.Screen options={{ title: "Dialer" }} />

      {/* Tab switcher */}
      <View style={[styles.tabs, { borderBottomColor: colors.border }]}>
        {(["keypad", "recent", "contacts"] as Tab[]).map((t) => {
          const active = t === tab;
          return (
            <Pressable
              key={t}
              onPress={() => setTab(t)}
              style={({ pressed }) => [
                styles.tab,
                active && {
                  borderBottomColor: colors.primary,
                  borderBottomWidth: 2,
                },
                { opacity: pressed ? 0.7 : 1 },
              ]}
            >
              <Text
                style={{
                  color: active ? colors.foreground : colors.mutedForeground,
                  fontFamily: "SpaceGrotesk_600SemiBold",
                  textTransform: "capitalize",
                }}
              >
                {t}
              </Text>
            </Pressable>
          );
        })}
      </View>

      {tab === "keypad" && (
        <View style={styles.keypadWrap}>
          <View style={styles.numberRow}>
            <Text
              numberOfLines={1}
              adjustsFontSizeToFit
              style={[styles.numberDisplay, { color: colors.foreground }]}
            >
              {number || " "}
            </Text>
            {number.length > 0 && (
              <Pressable
                onPress={backspace}
                onLongPress={clearAll}
                hitSlop={12}
                style={({ pressed }) => [
                  styles.backspace,
                  { opacity: pressed ? 0.5 : 1 },
                ]}
              >
                <Feather
                  name="delete"
                  size={26}
                  color={colors.mutedForeground}
                />
              </Pressable>
            )}
          </View>

          <View style={styles.keypad}>
            {KEYS.map((k) => (
              <Pressable
                key={k.v}
                onPress={() => press(k.v)}
                onLongPress={k.v === "0" ? longPressZero : undefined}
                style={({ pressed }) => [
                  styles.key,
                  {
                    backgroundColor: pressed
                      ? colors.muted
                      : colors.card,
                    borderColor: colors.border,
                  },
                ]}
              >
                <Text style={[styles.keyV, { color: colors.foreground }]}>
                  {k.v}
                </Text>
                {k.sub && (
                  <Text
                    style={[styles.keySub, { color: colors.mutedForeground }]}
                  >
                    {k.sub}
                  </Text>
                )}
              </Pressable>
            ))}
          </View>

          <Pressable
            onPress={() => dial(number)}
            disabled={!number}
            style={({ pressed }) => [
              styles.callBtn,
              {
                backgroundColor: number
                  ? "#16a34a"
                  : colors.muted,
                opacity: pressed ? 0.85 : 1,
                marginBottom: insets.bottom + 12,
              },
            ]}
          >
            <Feather name="phone" size={26} color="#fff" />
          </Pressable>
        </View>
      )}

      {tab === "recent" && (
        <FlatList
          data={recentList}
          keyExtractor={(r) => r.key}
          contentContainerStyle={{
            paddingBottom: insets.bottom + 24,
          }}
          ListEmptyComponent={
            recentLoading ? (
              <View style={styles.loading}>
                <ActivityIndicator color={colors.primary} />
              </View>
            ) : (
              <View style={styles.empty}>
                <Feather
                  name="phone-missed"
                  size={28}
                  color={colors.mutedForeground}
                />
                <Text
                  style={{
                    color: colors.mutedForeground,
                    marginTop: 12,
                    fontFamily: "SpaceGrotesk_500Medium",
                  }}
                >
                  No recent calls yet.
                </Text>
              </View>
            )
          }
          renderItem={({ item }) => (
            <Pressable
              onPress={() => dial(item.number, item.label)}
              onLongPress={() => {
                // Server-only entries (those that never went through this
                // device) can't be removed from this device — the row
                // re-appears next time we fetch /dialer/history. Be honest
                // about that rather than presenting a no-op destructive
                // option.
                const isLocal = localRecent.some(
                  (r) => r.number === item.number,
                );
                if (!isLocal) {
                  Alert.alert(
                    "Synced from another device",
                    "This call came from your account history on another device. It can't be removed here.",
                  );
                  return;
                }
                Alert.alert(
                  "Remove from this device's recents?",
                  item.number,
                  [
                    { text: "Cancel", style: "cancel" },
                    {
                      text: "Remove",
                      style: "destructive",
                      onPress: () => removeRecent(item.number),
                    },
                  ],
                );
              }}
              style={({ pressed }) => [
                styles.row,
                {
                  borderBottomColor: colors.border,
                  backgroundColor: pressed ? colors.muted : "transparent",
                },
              ]}
            >
              <View style={{ flex: 1 }}>
                <Text
                  style={{
                    color: colors.foreground,
                    fontFamily: "SpaceGrotesk_600SemiBold",
                    fontSize: 16,
                  }}
                >
                  {item.label ?? item.number}
                </Text>
                {item.label && (
                  <Text
                    style={{
                      color: colors.mutedForeground,
                      fontSize: 13,
                      marginTop: 2,
                    }}
                  >
                    {item.number}
                  </Text>
                )}
                <Text
                  style={{
                    color: colors.mutedForeground,
                    fontSize: 12,
                    marginTop: 2,
                  }}
                >
                  {formatRelative(item.at)}
                </Text>
              </View>
              <View
                style={[styles.callPill, { backgroundColor: "#16a34a" }]}
              >
                <Feather name="phone" size={16} color="#fff" />
              </View>
            </Pressable>
          )}
        />
      )}

      {tab === "contacts" && (
        <View style={{ flex: 1 }}>
          <View style={[styles.searchWrap, { borderBottomColor: colors.border }]}>
            <Feather
              name="search"
              size={16}
              color={colors.mutedForeground}
              style={{ marginRight: 8 }}
            />
            <TextInput
              value={contactsQuery}
              onChangeText={setContactsQuery}
              placeholder="Search contacts"
              placeholderTextColor={colors.mutedForeground}
              autoCapitalize="none"
              autoCorrect={false}
              style={{
                flex: 1,
                color: colors.foreground,
                fontFamily: "SpaceGrotesk_500Medium",
                paddingVertical: 8,
              }}
            />
          </View>

          <FlatList
            data={appContacts}
            keyExtractor={(c) => `app-${c.id}`}
            contentContainerStyle={{
              paddingBottom: insets.bottom + 24,
            }}
            ListHeaderComponent={
              <View style={{ paddingHorizontal: 16, paddingTop: 12 }}>
                <Text
                  style={{
                    color: colors.mutedForeground,
                    fontFamily: "SpaceGrotesk_600SemiBold",
                    fontSize: 12,
                    letterSpacing: 0.5,
                    textTransform: "uppercase",
                  }}
                >
                  1INME contacts
                </Text>
              </View>
            }
            ListEmptyComponent={
              contactsLoading ? (
                <View style={styles.loading}>
                  <ActivityIndicator color={colors.primary} />
                </View>
              ) : (
                <View style={styles.empty}>
                  <Text
                    style={{
                      color: colors.mutedForeground,
                      fontFamily: "SpaceGrotesk_500Medium",
                    }}
                  >
                    {contactsQuery
                      ? "No contacts match that search."
                      : "Your saved contacts will appear here."}
                  </Text>
                </View>
              )
            }
            renderItem={({ item }) => {
              const phone =
                item.phones.find((p) => p.is_primary) ?? item.phones[0];
              const dialNumber = phone?.value_e164 || phone?.value || "";
              // join() returns "" (not nullish) when both names are blank,
              // so use a logical OR chain that treats "" as falsy too.
              const name =
                item.display_name?.trim() ||
                [item.given_name, item.family_name]
                  .filter(Boolean)
                  .join(" ")
                  .trim() ||
                dialNumber;
              return (
                <Pressable
                  onPress={() => dialNumber && dial(dialNumber, name)}
                  style={({ pressed }) => [
                    styles.row,
                    {
                      borderBottomColor: colors.border,
                      backgroundColor: pressed
                        ? colors.muted
                        : "transparent",
                    },
                  ]}
                >
                  <View style={{ flex: 1 }}>
                    <Text
                      style={{
                        color: colors.foreground,
                        fontFamily: "SpaceGrotesk_600SemiBold",
                        fontSize: 16,
                      }}
                    >
                      {name || dialNumber}
                    </Text>
                    {dialNumber && (
                      <Text
                        style={{
                          color: colors.mutedForeground,
                          fontSize: 13,
                          marginTop: 2,
                        }}
                      >
                        {dialNumber}
                      </Text>
                    )}
                  </View>
                  <View
                    style={[
                      styles.callPill,
                      { backgroundColor: "#16a34a" },
                    ]}
                  >
                    <Feather name="phone" size={16} color="#fff" />
                  </View>
                </Pressable>
              );
            }}
            ListFooterComponent={
              <View style={{ paddingHorizontal: 16, paddingTop: 24 }}>
                <Text
                  style={{
                    color: colors.mutedForeground,
                    fontFamily: "SpaceGrotesk_600SemiBold",
                    fontSize: 12,
                    letterSpacing: 0.5,
                    textTransform: "uppercase",
                  }}
                >
                  Phone contacts
                </Text>

                {deviceAccess === "unknown" && (
                  <Pressable
                    onPress={requestDeviceContacts}
                    style={({ pressed }) => [
                      styles.deviceCta,
                      {
                        borderColor: colors.border,
                        backgroundColor: pressed ? colors.muted : colors.card,
                      },
                    ]}
                  >
                    <Feather
                      name="smartphone"
                      size={16}
                      color={colors.primary}
                    />
                    <Text
                      style={{
                        color: colors.foreground,
                        marginLeft: 8,
                        fontFamily: "SpaceGrotesk_500Medium",
                      }}
                    >
                      Use my phone's contacts too
                    </Text>
                  </Pressable>
                )}
                {deviceAccess === "denied" && (
                  <Text
                    style={{
                      color: colors.mutedForeground,
                      marginTop: 8,
                      fontSize: 13,
                    }}
                  >
                    Permission denied — enable Contacts access in Settings to
                    show your phone's address book here.
                  </Text>
                )}
                {deviceAccess === "granted" &&
                  deviceContacts
                    .filter((c) => {
                      if (!contactsQuery) return true;
                      const n = (c.name ?? "").toLowerCase();
                      return n.includes(contactsQuery.toLowerCase());
                    })
                    .slice(0, 200)
                    .map((c) => {
                      const phone =
                        c.phoneNumbers?.[0]?.number ??
                        c.phoneNumbers?.[0]?.digits ??
                        "";
                      return (
                        <Pressable
                          key={`dev-${c.id}`}
                          onPress={() => phone && dial(phone, c.name ?? null)}
                          style={({ pressed }) => [
                            styles.row,
                            {
                              borderBottomColor: colors.border,
                              backgroundColor: pressed
                                ? colors.muted
                                : "transparent",
                              marginHorizontal: -16,
                            },
                          ]}
                        >
                          <View style={{ flex: 1 }}>
                            <Text
                              style={{
                                color: colors.foreground,
                                fontFamily: "SpaceGrotesk_600SemiBold",
                                fontSize: 16,
                              }}
                            >
                              {c.name || phone}
                            </Text>
                            {!!phone && (
                              <Text
                                style={{
                                  color: colors.mutedForeground,
                                  fontSize: 13,
                                  marginTop: 2,
                                }}
                              >
                                {phone}
                              </Text>
                            )}
                          </View>
                          <View
                            style={[
                              styles.callPill,
                              { backgroundColor: "#16a34a" },
                            ]}
                          >
                            <Feather name="phone" size={16} color="#fff" />
                          </View>
                        </Pressable>
                      );
                    })}
              </View>
            }
          />
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1 },
  tabs: {
    flexDirection: "row",
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  tab: {
    flex: 1,
    paddingVertical: 14,
    alignItems: "center",
    borderBottomWidth: 2,
    borderBottomColor: "transparent",
  },
  keypadWrap: {
    flex: 1,
    paddingHorizontal: 16,
    paddingTop: 8,
  },
  numberRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    minHeight: 80,
    paddingHorizontal: 8,
  },
  numberDisplay: {
    flex: 1,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 38,
    textAlign: "center",
    letterSpacing: 1,
  },
  backspace: {
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  keypad: {
    flexDirection: "row",
    flexWrap: "wrap",
    justifyContent: "space-between",
    marginTop: 12,
  },
  key: {
    width: "31%",
    aspectRatio: 1.4,
    marginBottom: 10,
    borderRadius: 14,
    borderWidth: StyleSheet.hairlineWidth,
    alignItems: "center",
    justifyContent: "center",
  },
  keyV: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 28,
  },
  keySub: {
    fontSize: 10,
    letterSpacing: 1,
    marginTop: 2,
  },
  callBtn: {
    alignSelf: "center",
    width: 72,
    height: 72,
    borderRadius: 36,
    alignItems: "center",
    justifyContent: "center",
    marginTop: 8,
  },
  loading: {
    paddingVertical: 48,
    alignItems: "center",
    justifyContent: "center",
  },
  empty: {
    paddingVertical: 64,
    alignItems: "center",
    justifyContent: "center",
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 14,
    paddingHorizontal: 16,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  callPill: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: "center",
    justifyContent: "center",
  },
  searchWrap: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 16,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  deviceCta: {
    marginTop: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderRadius: 10,
    borderWidth: StyleSheet.hairlineWidth,
    flexDirection: "row",
    alignItems: "center",
  },
});
