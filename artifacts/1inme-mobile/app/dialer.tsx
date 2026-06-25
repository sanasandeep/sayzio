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
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useColors } from "@/hooks/useColors";
import {
  type DialerFavorite,
  type DialerFrequent,
  type DialerRecent,
  dialerHistory,
  listFavorites,
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

// T9 keypad letter map for client-side smart-dial name matching.
const T9_MAP: Record<string, string> = {
  a: "2", b: "2", c: "2",
  d: "3", e: "3", f: "3",
  g: "4", h: "4", i: "4",
  j: "5", k: "5", l: "5",
  m: "6", n: "6", o: "6",
  p: "7", q: "7", r: "7", s: "7",
  t: "8", u: "8", v: "8",
  w: "9", x: "9", y: "9", z: "9",
};

function t9Encode(name: string): string {
  return name
    .toLowerCase()
    .split("")
    .map((ch) => T9_MAP[ch] ?? "")
    .join("");
}

function contactName(c: Contact): string {
  return (
    c.display_name?.trim() ||
    [c.given_name, c.family_name].filter(Boolean).join(" ").trim() ||
    c.phones[0]?.value ||
    ""
  );
}

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
  const [recents, setRecents] = useState<DialerRecent[]>([]);
  const [frequent, setFrequent] = useState<DialerFrequent[]>([]);
  const [favorites, setFavorites] = useState<DialerFavorite[]>([]);
  const [recentLoading, setRecentLoading] = useState(false);

  const [contactsQuery, setContactsQuery] = useState("");
  const [appContacts, setAppContacts] = useState<Contact[]>([]);
  const [keypadMatches, setKeypadMatches] = useState<Contact[]>([]);
  const [deviceContacts, setDeviceContacts] = useState<DeviceContact[]>([]);
  const [contactsLoading, setContactsLoading] = useState(false);
  const [deviceAccess, setDeviceAccess] = useState<
    "unknown" | "granted" | "denied"
  >("unknown");

  // Initial load.
  useEffect(() => {
    void refreshRecent();
    void refreshFavorites();
  }, []);

  // Debounced contacts search (Contacts tab).
  useEffect(() => {
    if (tab !== "contacts") return;
    const t = setTimeout(() => void refreshContacts(contactsQuery), 250);
    return () => clearTimeout(t);
  }, [tab, contactsQuery]);

  // Debounced T9 smart-dial as the user types on the keypad.
  useEffect(() => {
    const q = number.trim();
    if (q.length < 2) {
      setKeypadMatches([]);
      return;
    }
    const t = setTimeout(() => void runT9Search(q), 200);
    return () => clearTimeout(t);
  }, [number]);

  const refreshRecent = useCallback(async () => {
    setRecentLoading(true);
    const local = await loadLocalRecent();
    setLocalRecent(local);
    try {
      const hist = await dialerHistory();
      setRecents(hist.recents);
      setFrequent(hist.frequent);
    } catch {
      /* ignore — local list is the primary surface */
    } finally {
      setRecentLoading(false);
    }
  }, []);

  const refreshFavorites = useCallback(async () => {
    try {
      setFavorites(await listFavorites());
    } catch {
      /* ignore */
    }
  }, []);

  const refreshContacts = useCallback(async (q: string) => {
    setContactsLoading(true);
    try {
      const res = await listContacts(q || undefined);
      setAppContacts(res.items.filter((c) => (c.phones?.length ?? 0) > 0));
    } catch {
      setAppContacts([]);
    } finally {
      setContactsLoading(false);
    }
  }, []);

  // T9 smart-dial: a pure digit sequence matches phone numbers (server)
  // OR keypad-spelled names (client-side over the same result set + a
  // broader fetch). Text queries match names/numbers server-side.
  const runT9Search = useCallback(async (q: string) => {
    try {
      const isDigits = /^[+\d*#]+$/.test(q);
      const res = await listContacts(q || undefined);
      let items = res.items.filter((c) => (c.phones?.length ?? 0) > 0);

      if (isDigits) {
        const seq = q.replace(/\D+/g, "");
        if (seq.length >= 2) {
          const broad = await listContacts();
          const have = new Set(items.map((c) => c.id));
          const t9Hits = broad.items
            .filter((c) => (c.phones?.length ?? 0) > 0 && !have.has(c.id))
            .filter((c) => t9Encode(contactName(c)).includes(seq));
          items = [...items, ...t9Hits];
        }
      }
      setKeypadMatches(items.slice(0, 8));
    } catch {
      setKeypadMatches([]);
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

  // Smart grouped recents from the server, layered with local-only labels
  // (numbers dialed before a server roundtrip, or non-E.164 rejects).
  const recentRows = useMemo(() => {
    type Row = {
      key: string;
      number: string;
      label: string | null;
      contactId: number | null;
      calls: number;
      isSpam: boolean;
      isBlocked: boolean;
      biolink: boolean;
      sub: string;
    };
    const localByNumber = new Map<string, LocalRecent>();
    for (const r of localRecent) localByNumber.set(r.number, r);

    const rows: Row[] = [];
    const seen = new Set<string>();
    for (const r of recents) {
      const num = r.number || "";
      if (!num) continue;
      seen.add(num);
      rows.push({
        key: `s${num}`,
        number: num,
        label: r.name && r.name !== num ? r.name : localByNumber.get(num)?.label ?? null,
        contactId: r.contact_id,
        calls: r.calls,
        isSpam: r.is_spam,
        isBlocked: r.is_blocked,
        biolink: r.biolink,
        sub: r.last_human ?? "",
      });
    }
    for (const r of localRecent) {
      if (seen.has(r.number)) continue;
      rows.push({
        key: `l${r.number}-${r.at}`,
        number: r.number,
        label: r.label,
        contactId: null,
        calls: 1,
        isSpam: false,
        isBlocked: false,
        biolink: false,
        sub: relativeMs(r.at),
      });
    }
    return rows.slice(0, RECENT_MAX);
  }, [recents, localRecent]);

  // React Native's Pressable fires onPress AFTER onLongPress on release,
  // which would otherwise turn long-press-0 into "+0" instead of "+".
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

      const cleanedLabel =
        typeof label === "string" && label.trim() !== "" ? label.trim() : null;
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

      if (E164.test(trimmed)) {
        lookupNumber(trimmed).catch(() => {});
      }

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

  // Open the caller-ID / mini-CRM profile for a number.
  const openProfile = useCallback(
    (num: string, opts?: { contactId?: number | null; name?: string | null }) => {
      const trimmed = num.trim();
      if (!trimmed) return;
      router.push({
        pathname: "/dialer-profile",
        params: {
          number: trimmed,
          ...(opts?.contactId ? { contactId: String(opts.contactId) } : {}),
          ...(opts?.name ? { name: opts.name } : {}),
        },
      });
    },
    [router],
  );

  // Auto-dial when navigated here with `?prefill=…&autoDial=1`.
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
        <ScrollView
          style={{ flex: 1 }}
          contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}
          keyboardShouldPersistTaps="handled"
        >
          {/* Favorites / speed dial */}
          {favorites.length > 0 && (
            <View style={styles.section}>
              <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
                Speed dial
              </Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false}>
                {favorites.map((f) => (
                  <Pressable
                    key={`fav-${f.id}`}
                    onPress={() =>
                      f.number &&
                      openProfile(f.number, {
                        contactId: f.contact_id,
                        name: f.label,
                      })
                    }
                    onLongPress={() => f.number && dial(f.number, f.label)}
                    style={styles.bubble}
                  >
                    <View style={[styles.bubbleAvatar, { backgroundColor: colors.primary }]}>
                      <Text style={styles.bubbleInitials}>{f.initials}</Text>
                    </View>
                    <Text
                      numberOfLines={1}
                      style={[styles.bubbleLabel, { color: colors.foreground }]}
                    >
                      {f.label || f.number}
                    </Text>
                  </Pressable>
                ))}
              </ScrollView>
            </View>
          )}

          {/* Frequently contacted */}
          {frequent.length > 0 && (
            <View style={styles.section}>
              <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
                Frequently contacted
              </Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false}>
                {frequent.map((fr, i) => (
                  <Pressable
                    key={`freq-${fr.number}-${i}`}
                    onPress={() =>
                      fr.number &&
                      openProfile(fr.number, {
                        contactId: fr.contact_id,
                        name: fr.name,
                      })
                    }
                    onLongPress={() => fr.number && dial(fr.number, fr.name)}
                    style={styles.bubble}
                  >
                    <View
                      style={[
                        styles.bubbleAvatar,
                        { backgroundColor: fr.is_spam ? "#ef4444" : colors.primary },
                      ]}
                    >
                      <Text style={styles.bubbleInitials}>{fr.initials}</Text>
                    </View>
                    <Text
                      numberOfLines={1}
                      style={[styles.bubbleLabel, { color: colors.foreground }]}
                    >
                      {fr.name || fr.number}
                    </Text>
                    <Text style={[styles.bubbleSub, { color: colors.mutedForeground }]}>
                      {fr.calls} calls
                    </Text>
                  </Pressable>
                ))}
              </ScrollView>
            </View>
          )}

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
                  <Feather name="delete" size={26} color={colors.mutedForeground} />
                </Pressable>
              )}
            </View>

            {/* T9 live matches */}
            {keypadMatches.length > 0 && (
              <View style={styles.t9Wrap}>
                {keypadMatches.map((c) => {
                  const phone = c.phones.find((p) => p.is_primary) ?? c.phones[0];
                  const num = phone?.value_e164 || phone?.value || "";
                  const name = contactName(c);
                  return (
                    <Pressable
                      key={`t9-${c.id}`}
                      onPress={() => num && openProfile(num, { contactId: c.id, name })}
                      style={({ pressed }) => [
                        styles.t9Row,
                        { borderColor: colors.border, backgroundColor: pressed ? colors.muted : colors.card },
                      ]}
                    >
                      <View style={[styles.t9Avatar, { backgroundColor: colors.primary }]}>
                        <Text style={styles.t9Initials}>
                          {name.slice(0, 2).toUpperCase()}
                        </Text>
                      </View>
                      <View style={{ flex: 1, minWidth: 0 }}>
                        <Text
                          numberOfLines={1}
                          style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold" }}
                        >
                          {name}
                        </Text>
                        <Text
                          numberOfLines={1}
                          style={{ color: colors.mutedForeground, fontSize: 12 }}
                        >
                          {phone?.value}
                        </Text>
                      </View>
                      <Pressable
                        onPress={() => num && dial(num, name)}
                        hitSlop={10}
                        style={[styles.callPill, { backgroundColor: "#16a34a" }]}
                      >
                        <Feather name="phone" size={15} color="#fff" />
                      </Pressable>
                    </Pressable>
                  );
                })}
              </View>
            )}

            <View style={styles.keypad}>
              {KEYS.map((k) => (
                <Pressable
                  key={k.v}
                  onPress={() => press(k.v)}
                  onLongPress={k.v === "0" ? longPressZero : undefined}
                  style={({ pressed }) => [
                    styles.key,
                    {
                      backgroundColor: pressed ? colors.muted : colors.card,
                      borderColor: colors.border,
                    },
                  ]}
                >
                  <Text style={[styles.keyV, { color: colors.foreground }]}>{k.v}</Text>
                  {k.sub && (
                    <Text style={[styles.keySub, { color: colors.mutedForeground }]}>
                      {k.sub}
                    </Text>
                  )}
                </Pressable>
              ))}
            </View>

            <View style={styles.actionRow}>
              <Pressable
                onPress={() => number && openProfile(number)}
                disabled={!number}
                style={({ pressed }) => [
                  styles.secondaryBtn,
                  { borderColor: colors.border, opacity: !number ? 0.4 : pressed ? 0.7 : 1 },
                ]}
              >
                <Feather name="info" size={20} color={colors.foreground} />
              </Pressable>
              <Pressable
                onPress={() => dial(number)}
                disabled={!number}
                style={({ pressed }) => [
                  styles.callBtn,
                  {
                    backgroundColor: number ? "#16a34a" : colors.muted,
                    opacity: pressed ? 0.85 : 1,
                  },
                ]}
              >
                <Feather name="phone" size={26} color="#fff" />
              </Pressable>
              <View style={styles.secondaryBtn} />
            </View>
          </View>
        </ScrollView>
      )}

      {tab === "recent" && (
        <FlatList
          data={recentRows}
          keyExtractor={(r) => r.key}
          contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}
          ListEmptyComponent={
            recentLoading ? (
              <View style={styles.loading}>
                <ActivityIndicator color={colors.primary} />
              </View>
            ) : (
              <View style={styles.empty}>
                <Feather name="phone-missed" size={28} color={colors.mutedForeground} />
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
              onPress={() =>
                openProfile(item.number, {
                  contactId: item.contactId,
                  name: item.label,
                })
              }
              onLongPress={() => {
                const isLocal = localRecent.some((r) => r.number === item.number);
                if (!isLocal) {
                  Alert.alert(
                    "Synced from another device",
                    "This call came from your account history on another device. It can't be removed here.",
                  );
                  return;
                }
                Alert.alert("Remove from this device's recents?", item.number, [
                  { text: "Cancel", style: "cancel" },
                  {
                    text: "Remove",
                    style: "destructive",
                    onPress: () => removeRecent(item.number),
                  },
                ]);
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
                <View style={styles.rowTitleLine}>
                  <Text
                    style={{
                      color: colors.foreground,
                      fontFamily: "SpaceGrotesk_600SemiBold",
                      fontSize: 16,
                    }}
                  >
                    {item.label ?? item.number}
                  </Text>
                  {item.calls > 1 && (
                    <Text style={[styles.countPill, { color: colors.primary }]}>
                      ×{item.calls}
                    </Text>
                  )}
                  {item.biolink && <MiniTag text="Sayzio" color="#d76dff" />}
                  {item.isSpam && <MiniTag text="SPAM" color="#ef4444" />}
                  {item.isBlocked && <MiniTag text="BLOCKED" color="#9ca3af" />}
                </View>
                {item.label && (
                  <Text style={{ color: colors.mutedForeground, fontSize: 13, marginTop: 2 }}>
                    {item.number}
                  </Text>
                )}
                <Text style={{ color: colors.mutedForeground, fontSize: 12, marginTop: 2 }}>
                  {item.sub}
                </Text>
              </View>
              <Pressable
                onPress={() => dial(item.number, item.label)}
                hitSlop={10}
                style={[styles.callPill, { backgroundColor: "#16a34a" }]}
              >
                <Feather name="phone" size={16} color="#fff" />
              </Pressable>
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
            contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}
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
                  Sayzio contacts
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
              const phone = item.phones.find((p) => p.is_primary) ?? item.phones[0];
              const dialNumber = phone?.value_e164 || phone?.value || "";
              const name = contactName(item) || dialNumber;
              return (
                <Pressable
                  onPress={() =>
                    dialNumber && openProfile(dialNumber, { contactId: item.id, name })
                  }
                  onLongPress={() => dialNumber && dial(dialNumber, name)}
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
                      {name || dialNumber}
                    </Text>
                    {dialNumber && (
                      <Text
                        style={{ color: colors.mutedForeground, fontSize: 13, marginTop: 2 }}
                      >
                        {dialNumber}
                      </Text>
                    )}
                  </View>
                  <Pressable
                    onPress={() => dialNumber && dial(dialNumber, name)}
                    hitSlop={10}
                    style={[styles.callPill, { backgroundColor: "#16a34a" }]}
                  >
                    <Feather name="phone" size={16} color="#fff" />
                  </Pressable>
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
                    <Feather name="smartphone" size={16} color={colors.primary} />
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
                    Permission denied — enable Contacts access in Settings to show
                    your phone's address book here.
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
                          onPress={() => phone && openProfile(phone, { name: c.name ?? null })}
                          onLongPress={() => phone && dial(phone, c.name ?? null)}
                          style={({ pressed }) => [
                            styles.row,
                            {
                              borderBottomColor: colors.border,
                              backgroundColor: pressed ? colors.muted : "transparent",
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
                          <Pressable
                            onPress={() => phone && dial(phone, c.name ?? null)}
                            hitSlop={10}
                            style={[styles.callPill, { backgroundColor: "#16a34a" }]}
                          >
                            <Feather name="phone" size={16} color="#fff" />
                          </Pressable>
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

function MiniTag({ text, color }: { text: string; color: string }) {
  return (
    <View style={[styles.miniTag, { backgroundColor: `${color}22` }]}>
      <Text style={[styles.miniTagText, { color }]}>{text}</Text>
    </View>
  );
}

function relativeMs(at: number): string {
  const diff = Date.now() - at;
  if (diff < 60_000) return "just now";
  if (diff < 3_600_000) return `${Math.floor(diff / 60_000)}m ago`;
  if (diff < 86_400_000) return `${Math.floor(diff / 3_600_000)}h ago`;
  if (diff < 7 * 86_400_000) return `${Math.floor(diff / 86_400_000)}d ago`;
  return new Date(at).toLocaleDateString();
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
  section: { paddingTop: 14, paddingLeft: 16 },
  sectionLabel: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
    letterSpacing: 0.5,
    textTransform: "uppercase",
    marginBottom: 10,
  },
  bubble: { alignItems: "center", width: 76, marginRight: 6 },
  bubbleAvatar: {
    width: 52,
    height: 52,
    borderRadius: 26,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 4,
  },
  bubbleInitials: { color: "#fff", fontFamily: "SpaceGrotesk_700Bold", fontSize: 16 },
  bubbleLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 11,
    textAlign: "center",
  },
  bubbleSub: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 10, marginTop: 1 },
  keypadWrap: { paddingHorizontal: 16, paddingTop: 8 },
  numberRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    minHeight: 72,
    paddingHorizontal: 8,
  },
  numberDisplay: {
    flex: 1,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 38,
    textAlign: "center",
    letterSpacing: 1,
  },
  backspace: { paddingHorizontal: 12, paddingVertical: 8 },
  t9Wrap: { marginBottom: 8, gap: 6 },
  t9Row: {
    flexDirection: "row",
    alignItems: "center",
    padding: 8,
    borderRadius: 12,
    borderWidth: StyleSheet.hairlineWidth,
    gap: 10,
  },
  t9Avatar: { width: 36, height: 36, borderRadius: 18, alignItems: "center", justifyContent: "center" },
  t9Initials: { color: "#fff", fontFamily: "SpaceGrotesk_700Bold", fontSize: 13 },
  keypad: {
    flexDirection: "row",
    flexWrap: "wrap",
    justifyContent: "space-between",
    marginTop: 8,
  },
  key: {
    width: "31%",
    aspectRatio: 1.5,
    marginBottom: 10,
    borderRadius: 14,
    borderWidth: StyleSheet.hairlineWidth,
    alignItems: "center",
    justifyContent: "center",
  },
  keyV: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 28 },
  keySub: { fontSize: 10, letterSpacing: 1, marginTop: 2 },
  actionRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginTop: 4,
  },
  secondaryBtn: {
    width: 56,
    height: 56,
    borderRadius: 28,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "transparent",
    alignItems: "center",
    justifyContent: "center",
  },
  callBtn: {
    width: 72,
    height: 72,
    borderRadius: 36,
    alignItems: "center",
    justifyContent: "center",
  },
  loading: { paddingVertical: 48, alignItems: "center", justifyContent: "center" },
  empty: { paddingVertical: 64, alignItems: "center", justifyContent: "center" },
  row: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 14,
    paddingHorizontal: 16,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  rowTitleLine: { flexDirection: "row", alignItems: "center", flexWrap: "wrap", gap: 6 },
  countPill: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  miniTag: { paddingHorizontal: 5, paddingVertical: 1, borderRadius: 4 },
  miniTagText: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 8, letterSpacing: 0.5 },
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
