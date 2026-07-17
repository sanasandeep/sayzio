import AsyncStorage from "@react-native-async-storage/async-storage";
import { Feather } from "@expo/vector-icons";
import * as Contacts from "expo-contacts";
import * as Haptics from "expo-haptics";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import {
  type ComponentProps,
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Linking,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import {
  ChannelActions,
  type ChannelPrefs,
  chanOpen,
  featherName,
  publishChannelPrefs,
  resolveChannels,
  useChannelPrefs,
} from "@/components/ChannelActions";
import { useColors } from "@/hooks/useColors";
import {
  type DialerFavorite,
  type DialerFrequent,
  type DialerRecent,
  type DialerSearchItem,
  type DialerSearchResult,
  dialerHistory,
  dialerLive,
  dialerSearch,
  getDialerChannels,
  listFavorites,
  type DialerSuggestionsResult,
  getDialerSuggestions,
  lookupNumber,
  updateDialerChannels,
  assignSpeedDial,
  unassignSpeedDial,
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

// Direct channel actions (chanOpen / catalog / prefs / <ChannelActions/>)
// live in the shared @/components/ChannelActions module so every surface
// showing a phone number (search, contacts, profile, call screens) renders
// the exact same one-tap row.

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

  // Preferred messaging channels (shared with the web dialer via the server;
  // fetched + cached app-wide by the shared ChannelActions store).
  const channelPrefs = useChannelPrefs();
  const [channelPickerOpen, setChannelPickerOpen] = useState(false);

  // Speed-dial digit assignment modal.
  const [speedDialModal, setSpeedDialModal] = useState<{
    favId: number | null;   // null = assigning from keypad long-press
    digit: number | null;   // pre-selected digit (from long-press on a taken key)
    currentDigit: number | null; // digit already owned by favId (for clearing)
    forDigit: number | null;     // digit slot coming from a keypad long-press
  } | null>(null);
  const [speedDialBusy, setSpeedDialBusy] = useState(false);

  const [contactsQuery, setContactsQuery] = useState("");
  const [appContacts, setAppContacts] = useState<Contact[]>([]);
  // Universal finder (keypad): grouped results across Contacts, People, My
  // links, Followed and Workspaces via the shared server contract. `keypadMode`
  // toggles the T9 digit grid ↔ an alphanumeric keyboard; both feed this.
  const [keypadMode, setKeypadMode] = useState<"t9" | "abc">("t9");
  const [uni, setUni] = useState<DialerSearchResult | null>(null);
  const [uniLoading, setUniLoading] = useState(false);
  const [suggestions, setSuggestions] = useState<DialerSuggestionsResult | null>(null);
  const [filterVerified, setFilterVerified] = useState(false);
  const [filterBiolink, setFilterBiolink] = useState(false);
  const [deviceContacts, setDeviceContacts] = useState<DeviceContact[]>([]);
  const [contactsLoading, setContactsLoading] = useState(false);
  const [deviceAccess, setDeviceAccess] = useState<
    "unknown" | "granted" | "denied"
  >("unknown");

  // Initial load.
  useEffect(() => {
    void refreshRecent();
    void refreshFavorites();
    void getDialerSuggestions()
      .then((s) => setSuggestions(s.total > 0 ? s : null))
      .catch(() => { /* offline — leave null */ });
  }, []);

  // Near-real-time cross-device sync: poll the lastId-style cursor. The
  // server only ships fresh lists when they actually changed, so this stays
  // cheap. Favorites edited (or a call logged) on another device land here
  // within ~12s without any sockets. We start with no cursor and always apply
  // any changed payload the server returns — so even if the initial screen
  // fetch failed, the first poll backfills the lists rather than leaving them
  // stale.
  const liveCursor = useRef<string | null>(null);
  useEffect(() => {
    let cancelled = false;
    const poll = async () => {
      try {
        const state = await dialerLive(liveCursor.current ?? undefined);
        if (cancelled) return;
        liveCursor.current = state.cursor ?? liveCursor.current;
        if (!state.changed) return;
        if (state.favorites) setFavorites(state.favorites);
        if (state.frequent) setFrequent(state.frequent);
        if (state.recents) setRecents(state.recents);
        // The cursor also advances on favorite edits and new followers /
        // subscribers, so re-fetch the empty-state suggestions on the same
        // cycle. No changed=false cycle ever triggers this extra call.
        void getDialerSuggestions()
          .then((s) => setSuggestions(s.total > 0 ? s : null))
          .catch(() => { /* offline — keep the current suggestions */ });
      } catch {
        /* offline / transient — keep what we have */
      }
    };
    void poll();
    const id = setInterval(() => void poll(), 12000);
    return () => {
      cancelled = true;
      clearInterval(id);
    };
  }, []);

  // Debounced contacts search (Contacts tab).
  useEffect(() => {
    if (tab !== "contacts") return;
    const t = setTimeout(() => void refreshContacts(contactsQuery), 250);
    return () => clearTimeout(t);
  }, [tab, contactsQuery]);

  // Debounced universal finder as the user types on the keypad (either mode)
  // or flips a filter chip. T9 smart-dial is preserved server-side.
  useEffect(() => {
    const q = number.trim();
    const anyFilter = filterVerified || filterBiolink;
    if (q.length < 2 && !anyFilter) {
      setUni(null);
      return;
    }
    const t = setTimeout(() => void runUniversal(q), 220);
    return () => clearTimeout(t);
  }, [number, filterVerified, filterBiolink]);

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

  // Universal grouped finder — shared server contract (web/API/mobile). Fed
  // by both keypad modes + the verified / on-Sayzio filter chips.
  const runUniversal = useCallback(
    async (q: string) => {
      setUniLoading(true);
      try {
        const res = await dialerSearch(q, {
          verified: filterVerified,
          has_biolink: filterBiolink,
        });
        setUni(res);
      } catch {
        setUni(null);
      } finally {
        setUniLoading(false);
      }
    },
    [filterVerified, filterBiolink],
  );

  // Route a universal result to the right place. Contacts open the in-app
  // profile; people / links open their public URL; workspaces have no mobile
  // switch target so they're informational.
  const openUniversalItem = useCallback(
    (item: DialerSearchItem) => {
      const a = item.action || {};
      // Open dialer profile for any item that carries a phone number —
      // this includes contacts, favorites, recents, and leads in addition
      // to items that have type==="contact". The original contact-only guard
      // was too narrow and silently no-oped on suggestion rows.
      if (a.number) {
        openProfile(a.number, {
          contactId: a.contact_id ?? null,
          name: item.title,
        });
        return;
      }
      if (a.url) void Linking.openURL(a.url);
    },
    // openProfile is stable (defined below via useCallback)
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  );

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
              <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between", marginBottom: 8 }}>
                <Text style={[styles.sectionLabel, { color: colors.mutedForeground, marginBottom: 0 }]}>
                  Speed dial
                </Text>
                <Pressable
                  onPress={() => setSpeedDialModal({ favId: null, digit: null, currentDigit: null, forDigit: null })}
                  style={{ padding: 4 }}
                >
                  <Text style={{ fontSize: 11, color: colors.primary, fontFamily: "SpaceGrotesk_600SemiBold" }}>
                    # Manage digits
                  </Text>
                </Pressable>
              </View>
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
                    onLongPress={() =>
                      setSpeedDialModal({
                        favId: f.id,
                        digit: f.speed_dial_digit,
                        currentDigit: f.speed_dial_digit,
                        forDigit: null,
                      })
                    }
                    style={styles.bubble}
                  >
                    {/* Speed-dial digit badge */}
                    {f.speed_dial_digit != null && (
                      <View style={{
                        position: "absolute", top: -2, left: -2, zIndex: 10,
                        width: 18, height: 18, borderRadius: 9,
                        backgroundColor: "#3d6bff", alignItems: "center", justifyContent: "center",
                      }}>
                        <Text style={{ fontSize: 9, fontFamily: "SpaceGrotesk_700Bold", color: "#fff" }}>
                          {f.speed_dial_digit}
                        </Text>
                      </View>
                    )}
                    <View style={[styles.bubbleAvatar, { backgroundColor: colors.primary }]}>
                      <Text style={styles.bubbleInitials}>{f.initials}</Text>
                    </View>
                    <Text
                      numberOfLines={1}
                      style={[styles.bubbleLabel, { color: colors.foreground }]}
                    >
                      {f.label || f.number}
                    </Text>
                    {f.number ? (
                      <View style={{ marginTop: 6 }}>
                        <ChannelActions number={f.number} size="sm" />
                      </View>
                    ) : null}
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
                        { backgroundColor: fr.is_spam ? colors.destructive : colors.primary },
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
                    {fr.number ? (
                      <View style={{ marginTop: 6 }}>
                        <ChannelActions number={fr.number} size="sm" />
                      </View>
                    ) : null}
                  </Pressable>
                ))}
              </ScrollView>
            </View>
          )}

          <View style={styles.keypadWrap}>
            {/* Keypad mode toggle: T9 digit grid ↔ alphanumeric keyboard.
                Both write to the same query and feed the same universal search. */}
            <View style={styles.modeToggle}>
              {(["t9", "abc"] as const).map((m) => {
                const active = keypadMode === m;
                return (
                  <Pressable
                    key={m}
                    onPress={() => setKeypadMode(m)}
                    style={[
                      styles.modeBtn,
                      {
                        backgroundColor: active ? colors.primary : colors.card,
                        borderColor: active ? colors.primary : colors.border,
                      },
                    ]}
                  >
                    <Feather
                      name={m === "t9" ? "grid" : "type"}
                      size={13}
                      color={active ? "#fff" : colors.mutedForeground}
                    />
                    <Text
                      style={{
                        color: active ? "#fff" : colors.mutedForeground,
                        fontSize: 12,
                        fontFamily: "SpaceGrotesk_600SemiBold",
                        marginLeft: 6,
                      }}
                    >
                      {m === "t9" ? "T9" : "Keyboard"}
                    </Text>
                  </Pressable>
                );
              })}
            </View>

            {/* Advanced filter chips (verification badge / on Sayzio). */}
            <View style={styles.filterRow}>
              {(
                [
                  { key: "verified", label: "Verified", on: filterVerified, set: setFilterVerified, icon: "check-circle" },
                  { key: "biolink", label: "On Sayzio", on: filterBiolink, set: setFilterBiolink, icon: "link" },
                ] as const
              ).map((f) => (
                <Pressable
                  key={f.key}
                  onPress={() => f.set((v) => !v)}
                  style={[
                    styles.filterChip,
                    {
                      backgroundColor: f.on ? colors.primary : colors.card,
                      borderColor: f.on ? colors.primary : colors.border,
                    },
                  ]}
                >
                  <Feather name={f.icon} size={12} color={f.on ? "#fff" : colors.mutedForeground} />
                  <Text
                    style={{
                      color: f.on ? "#fff" : colors.mutedForeground,
                      fontSize: 11,
                      fontFamily: "SpaceGrotesk_500Medium",
                      marginLeft: 5,
                    }}
                  >
                    {f.label}
                  </Text>
                </Pressable>
              ))}
            </View>

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

            {/* Keyboard mode: full alphanumeric input feeding the same search. */}
            {keypadMode === "abc" && (
              <TextInput
                value={number}
                onChangeText={setNumber}
                autoFocus
                autoCapitalize="none"
                autoCorrect={false}
                placeholder="Name, handle, alias, keyword…"
                placeholderTextColor={colors.mutedForeground}
                style={[
                  styles.abcInput,
                  { color: colors.foreground, borderColor: colors.border, backgroundColor: colors.card },
                ]}
              />
            )}

            {/* Universal grouped results (Contacts / People / My links /
                Followed / Workspaces) — same contract as web + REST. */}
            {uni && uni.groups.length > 0 && (
              <View style={styles.t9Wrap}>
                {uni.groups.map((g) => (
                  <View key={g.key} style={{ marginBottom: 10 }}>
                    <Text style={[styles.uniGroupLabel, { color: colors.mutedForeground }]}>
                      {g.label} · {g.items.length}
                    </Text>
                    {g.items.map((item) => {
                      const chanNum = item.action?.number || null;
                      return (
                        <View key={`${g.key}-${item.id}`} style={{ marginBottom: 6 }}>
                          <Pressable
                            onPress={() => openUniversalItem(item)}
                            style={({ pressed }) => [
                              styles.t9Row,
                              { borderColor: colors.border, backgroundColor: pressed ? colors.muted : colors.card },
                            ]}
                          >
                            <View style={[styles.t9Avatar, { backgroundColor: colors.primary }]}>
                              <Text style={styles.t9Initials}>{item.initials}</Text>
                            </View>
                            <View style={{ flex: 1, minWidth: 0 }}>
                              <View style={{ flexDirection: "row", alignItems: "center", gap: 5 }}>
                                <Text
                                  numberOfLines={1}
                                  style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold", flexShrink: 1 }}
                                >
                                  {item.title}
                                </Text>
                                {item.verified && (
                                  <Feather name="check-circle" size={13} color={colors.primary} />
                                )}
                                {item.badge && (
                                  <View style={[styles.uniBadge, { borderColor: colors.border }]}>
                                    <Text style={{ color: colors.mutedForeground, fontSize: 9, fontFamily: "SpaceGrotesk_600SemiBold" }}>
                                      {item.badge}
                                    </Text>
                                  </View>
                                )}
                              </View>
                              {!!item.subtitle && (
                                <Text numberOfLines={1} style={{ color: colors.mutedForeground, fontSize: 12 }}>
                                  {item.subtitle}
                                </Text>
                              )}
                            </View>
                            <Text style={{ color: colors.mutedForeground, fontSize: 10 }}>
                              {item.type_label}
                            </Text>
                          </Pressable>
                          {/* Direct app-connection links for results with a phone
                              number — call / SMS / WhatsApp / Telegram / … in one
                              tap, without leaving search. */}
                          {!!chanNum && (
                            <View style={styles.uniChannels}>
                              {resolveChannels(channelPrefs).map((c) => (
                                <Pressable
                                  key={c.key}
                                  onPress={() => chanOpen(c.js, chanNum)}
                                  hitSlop={6}
                                  style={[styles.uniChanBtn, { backgroundColor: c.color + "24" }]}
                                >
                                  <Feather name={featherName(c)} size={13} color={c.color} />
                                </Pressable>
                              ))}
                            </View>
                          )}
                        </View>
                      );
                    })}
                  </View>
                ))}
              </View>
            )}
            {uni && uni.groups.length === 0 && !uniLoading && (number.trim().length >= 2 || filterVerified || filterBiolink) && (
              <Text style={[styles.uniEmpty, { color: colors.mutedForeground }]}>No matches</Text>
            )}

            {/* Suggestions: shown in the empty state (no number typed, no search). */}
            {!number.trim() && !uni && suggestions && suggestions.groups.length > 0 && (
              <View style={styles.t9Wrap}>
                {suggestions.groups.map((g) => (
                  <View key={g.key} style={{ marginBottom: 10 }}>
                    <Text style={[styles.uniGroupLabel, { color: colors.mutedForeground }]}>
                      {g.label} · {g.items.length}
                    </Text>
                    {g.items.map((item) => {
                      const chanNum = item.action?.number || null;
                      return (
                        <View key={`sugg-${g.key}-${item.id}`} style={{ marginBottom: 6 }}>
                          <Pressable
                            onPress={() => openUniversalItem(item)}
                            style={({ pressed }) => [
                              styles.t9Row,
                              { borderColor: colors.border, backgroundColor: pressed ? colors.muted : colors.card },
                            ]}
                          >
                            <View style={[styles.t9Avatar, { backgroundColor: colors.primary }]}>
                              <Text style={styles.t9Initials}>{item.initials}</Text>
                            </View>
                            <View style={{ flex: 1, minWidth: 0 }}>
                              <View style={{ flexDirection: "row", alignItems: "center", gap: 5 }}>
                                <Text
                                  numberOfLines={1}
                                  style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold", flexShrink: 1 }}
                                >
                                  {item.title}
                                </Text>
                                {item.badge && (
                                  <View style={[styles.uniBadge, { borderColor: colors.border }]}>
                                    <Text style={{ color: colors.mutedForeground, fontSize: 9, fontFamily: "SpaceGrotesk_600SemiBold" }}>
                                      {item.badge}
                                    </Text>
                                  </View>
                                )}
                              </View>
                              {!!item.subtitle && (
                                <Text numberOfLines={1} style={{ color: colors.mutedForeground, fontSize: 12 }}>
                                  {item.subtitle}
                                </Text>
                              )}
                            </View>
                            <Text style={{ color: colors.mutedForeground, fontSize: 10 }}>
                              {item.type_label}
                            </Text>
                          </Pressable>
                          {!!chanNum && (
                            <View style={styles.uniChannels}>
                              {resolveChannels(channelPrefs).map((c) => (
                                <Pressable
                                  key={c.key}
                                  onPress={() => chanOpen(c.js, chanNum)}
                                  hitSlop={6}
                                  accessibilityLabel={c.label}
                                  style={{
                                    width: 28,
                                    height: 28,
                                    borderRadius: 14,
                                    alignItems: "center",
                                    justifyContent: "center",
                                    backgroundColor: `${c.color}22`,
                                  }}
                                >
                                  <Feather name={featherName(c)} size={13} color={c.color} />
                                </Pressable>
                              ))}
                            </View>
                          )}
                        </View>
                      );
                    })}
                  </View>
                ))}
              </View>
            )}

            {keypadMode === "t9" && (
              <View style={styles.keypad}>
                {KEYS.map((k) => {
                  const digitNum = /^[1-9]$/.test(k.v) ? parseInt(k.v, 10) : null;
                  const sdFav = digitNum != null
                    ? favorites.find((f) => f.speed_dial_digit === digitNum) ?? null
                    : null;
                  return (
                  <Pressable
                    key={k.v}
                    onPress={() => press(k.v)}
                    onLongPress={
                      k.v === "0"
                        ? longPressZero
                        : digitNum != null
                          ? () => {
                              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
                              suppressNextPress.current = true;
                              if (sdFav) {
                                setSpeedDialModal({
                                  favId: sdFav.id,
                                  digit: sdFav.speed_dial_digit,
                                  currentDigit: sdFav.speed_dial_digit,
                                  forDigit: null,
                                });
                              } else {
                                setSpeedDialModal({
                                  favId: null,
                                  digit: digitNum,
                                  currentDigit: null,
                                  forDigit: digitNum,
                                });
                              }
                            }
                          : undefined
                    }
                    delayLongPress={600}
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
                    {digitNum != null && sdFav && (
                      <Text style={{ fontSize: 8, color: "#90acff", fontFamily: "SpaceGrotesk_700Bold", marginTop: 1 }}>
                        {(sdFav.initials ?? "").slice(0, 3)}
                      </Text>
                    )}
                  </Pressable>
                  );
                })}
              </View>
            )}

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

            {/* Direct channel actions on the typed number — reach anyone on
                your preferred channels without saving a contact. Customize
                which channels appear via the picker. */}
            {!!number && (
              <View style={styles.channelRow}>
                {resolveChannels(channelPrefs).map((c) => (
                  <ChannelBtn
                    key={c.key}
                    icon={featherName(c)}
                    label={c.short}
                    color={c.color}
                    onPress={() => chanOpen(c.js, number)}
                  />
                ))}
                <ChannelBtn
                  icon="sliders"
                  label="Customize"
                  color={colors.mutedForeground}
                  onPress={() => setChannelPickerOpen(true)}
                />
              </View>
            )}
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
                  {item.isSpam && <MiniTag text="SPAM" color={colors.destructive} />}
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
              <View style={styles.rowActions}>
                <ChannelActions number={item.number} size="md" />
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

      <ChannelPickerModal
        open={channelPickerOpen}
        prefs={channelPrefs}
        onClose={() => setChannelPickerOpen(false)}
        onSaved={(prefs) => {
          publishChannelPrefs(prefs);
          setChannelPickerOpen(false);
        }}
      />

      {/* Speed-dial digit assignment modal */}
      <Modal
        visible={speedDialModal != null}
        transparent
        animationType="slide"
        onRequestClose={() => setSpeedDialModal(null)}
      >
        <Pressable
          style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.6)", justifyContent: "flex-end" }}
          onPress={() => setSpeedDialModal(null)}
        >
          <Pressable
            style={{ borderRadius: 20, margin: 12, padding: 20, backgroundColor: colors.card, borderWidth: StyleSheet.hairlineWidth, borderColor: colors.border }}
            onPress={() => {/* prevent close on inner tap */}}
          >
            <Text style={{ fontSize: 16, fontFamily: "SpaceGrotesk_700Bold", color: colors.foreground, marginBottom: 4 }}>
              Speed-dial digit
            </Text>
            <Text style={{ fontSize: 12, color: colors.mutedForeground, marginBottom: 16, fontFamily: "SpaceGrotesk_400Regular" }}>
              {speedDialModal?.favId
                ? "Assign a keypad digit (1–9). Long-pressing that key will open this contact instantly."
                : speedDialModal?.forDigit
                  ? `Pick a favorite to assign to key ${speedDialModal.forDigit}.`
                  : "Manage speed-dial assignments for keys 1–9."}
            </Text>

            {/* Favorite selector when opened from a keypad long-press with no owner */}
            {speedDialModal?.forDigit && !speedDialModal.favId && (
              <View style={{ marginBottom: 12 }}>
                <Text style={{ fontSize: 11, fontFamily: "SpaceGrotesk_600SemiBold", color: colors.mutedForeground, marginBottom: 6, textTransform: "uppercase", letterSpacing: 0.5 }}>
                  Assign favorite to key {speedDialModal.forDigit}
                </Text>
                {favorites.length === 0 ? (
                  <Text style={{ color: colors.mutedForeground, fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 }}>
                    No favorites yet. Add favorites to your speed dial strip first.
                  </Text>
                ) : (
                  favorites.map((f) => (
                    <Pressable
                      key={f.id}
                      onPress={async () => {
                        if (speedDialBusy) return;
                        setSpeedDialBusy(true);
                        try {
                          await assignSpeedDial(f.id, speedDialModal!.forDigit!);
                          const updated = await listFavorites();
                          setFavorites(updated);
                          setSpeedDialModal(null);
                        } catch { Alert.alert("Error", "Could not assign digit. Try again."); }
                        finally { setSpeedDialBusy(false); }
                      }}
                      style={({ pressed }) => ({
                        flexDirection: "row", alignItems: "center", gap: 10, paddingVertical: 10,
                        paddingHorizontal: 12, borderRadius: 12, marginBottom: 6,
                        backgroundColor: pressed ? colors.muted : colors.background,
                        borderWidth: StyleSheet.hairlineWidth, borderColor: colors.border,
                      })}
                    >
                      <View style={{ width: 32, height: 32, borderRadius: 16, backgroundColor: colors.primary, alignItems: "center", justifyContent: "center" }}>
                        <Text style={{ color: "#fff", fontFamily: "SpaceGrotesk_700Bold", fontSize: 11 }}>{f.initials}</Text>
                      </View>
                      <Text style={{ flex: 1, color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 }}>{f.label || f.number}</Text>
                      {f.speed_dial_digit != null && (
                        <Text style={{ fontSize: 11, color: colors.mutedForeground, fontFamily: "SpaceGrotesk_400Regular" }}>key {f.speed_dial_digit}</Text>
                      )}
                    </Pressable>
                  ))
                )}
              </View>
            )}

            {/* Digit picker grid when opened from a favorite's long-press */}
            {speedDialModal?.favId != null && (
              <View style={{ marginBottom: 12 }}>
                <Text style={{ fontSize: 11, fontFamily: "SpaceGrotesk_600SemiBold", color: colors.mutedForeground, marginBottom: 8, textTransform: "uppercase", letterSpacing: 0.5 }}>
                  Pick a key
                </Text>
                <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
                  {[1,2,3,4,5,6,7,8,9].map((d) => {
                    const owner = favorites.find((f) => f.speed_dial_digit === d);
                    const isMine = owner?.id === speedDialModal?.favId;
                    return (
                      <Pressable
                        key={d}
                        onPress={async () => {
                          if (speedDialBusy || !speedDialModal?.favId) return;
                          setSpeedDialBusy(true);
                          try {
                            await assignSpeedDial(speedDialModal.favId!, d);
                            const updated = await listFavorites();
                            setFavorites(updated);
                            setSpeedDialModal(null);
                          } catch { Alert.alert("Error", "Could not assign digit. Try again."); }
                          finally { setSpeedDialBusy(false); }
                        }}
                        style={({ pressed }) => ({
                          width: 54, height: 54, borderRadius: 12, alignItems: "center", justifyContent: "center",
                          backgroundColor: isMine ? "#3d6bff" : pressed ? colors.muted : colors.background,
                          borderWidth: StyleSheet.hairlineWidth,
                          borderColor: isMine ? "#3d6bff" : owner ? "#90acff55" : colors.border,
                        })}
                      >
                        <Text style={{ fontSize: 18, fontFamily: "SpaceGrotesk_700Bold", color: isMine ? "#fff" : colors.foreground }}>{d}</Text>
                        {owner && !isMine && (
                          <Text style={{ fontSize: 8, color: "#90acff", fontFamily: "SpaceGrotesk_600SemiBold" }} numberOfLines={1}>
                            {(owner.initials ?? "").slice(0, 3)}
                          </Text>
                        )}
                      </Pressable>
                    );
                  })}
                </View>
              </View>
            )}

            {/* Slot manager when opened with no specific fav or digit */}
            {!speedDialModal?.favId && !speedDialModal?.forDigit && (
              <View style={{ marginBottom: 12 }}>
                {[1,2,3,4,5,6,7,8,9].map((d) => {
                  const owner = favorites.find((f) => f.speed_dial_digit === d);
                  return (
                    <View key={d} style={{ flexDirection: "row", alignItems: "center", gap: 10, paddingVertical: 8, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.border }}>
                      <View style={{ width: 30, height: 30, borderRadius: 15, backgroundColor: "#3d6bff33", alignItems: "center", justifyContent: "center" }}>
                        <Text style={{ fontSize: 14, fontFamily: "SpaceGrotesk_700Bold", color: "#90acff" }}>{d}</Text>
                      </View>
                      <Text style={{ flex: 1, fontSize: 13, fontFamily: "SpaceGrotesk_500Medium", color: owner ? colors.foreground : colors.mutedForeground }}>
                        {owner ? (owner.label || owner.number || "Favorite") : "— unassigned"}
                      </Text>
                      {owner ? (
                        <Pressable
                          onPress={async () => {
                            if (speedDialBusy) return;
                            setSpeedDialBusy(true);
                            try {
                              const updated = await unassignSpeedDial({ digit: d });
                              setFavorites(updated);
                            } catch { Alert.alert("Error", "Could not clear digit."); }
                            finally { setSpeedDialBusy(false); }
                          }}
                          style={{ padding: 6, borderRadius: 8, backgroundColor: "rgba(239,68,68,0.12)" }}
                        >
                          <Feather name="x" size={14} color="#ef4444" />
                        </Pressable>
                      ) : (
                        <Pressable
                          onPress={() => setSpeedDialModal({ favId: null, digit: null, currentDigit: null, forDigit: d })}
                          style={{ padding: 6, borderRadius: 8, backgroundColor: "rgba(61,107,255,0.12)" }}
                        >
                          <Feather name="plus" size={14} color="#90acff" />
                        </Pressable>
                      )}
                    </View>
                  );
                })}
              </View>
            )}

            {/* Clear current digit (when opened from an assigned favorite's long-press) */}
            {speedDialModal?.currentDigit != null && speedDialModal.favId != null && (
              <Pressable
                onPress={async () => {
                  if (speedDialBusy || !speedDialModal?.favId) return;
                  setSpeedDialBusy(true);
                  try {
                    const updated = await unassignSpeedDial({ favorite_id: speedDialModal.favId! });
                    setFavorites(updated);
                    setSpeedDialModal(null);
                  } catch { Alert.alert("Error", "Could not clear digit."); }
                  finally { setSpeedDialBusy(false); }
                }}
                style={({ pressed }) => ({
                  paddingVertical: 10, borderRadius: 12, alignItems: "center",
                  backgroundColor: pressed ? "rgba(239,68,68,0.2)" : "rgba(239,68,68,0.1)",
                  borderWidth: StyleSheet.hairlineWidth, borderColor: "rgba(239,68,68,0.3)",
                  marginBottom: 8,
                })}
              >
                <Text style={{ color: "#ef4444", fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 }}>
                  Remove key {speedDialModal.currentDigit}
                </Text>
              </Pressable>
            )}

            <Pressable
              onPress={() => setSpeedDialModal(null)}
              style={({ pressed }) => ({
                paddingVertical: 10, borderRadius: 12, alignItems: "center",
                backgroundColor: pressed ? colors.muted : colors.background,
                borderWidth: StyleSheet.hairlineWidth, borderColor: colors.border,
              })}
            >
              <Text style={{ color: colors.mutedForeground, fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 }}>Cancel</Text>
            </Pressable>
          </Pressable>
        </Pressable>
      </Modal>
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

// Pick which messaging channels appear on the dialer's one-tap rows. Saves to
// the account (shared with the web dialer), so the choice follows the user
// across devices.
function ChannelPickerModal({
  open,
  prefs,
  onClose,
  onSaved,
}: {
  open: boolean;
  prefs: ChannelPrefs;
  onClose: () => void;
  onSaved: (prefs: ChannelPrefs) => void;
}) {
  const colors = useColors();
  const [selected, setSelected] = useState<string[]>(prefs.enabled);
  const [saving, setSaving] = useState(false);

  // Reset the draft each time the sheet opens so it reflects the live prefs.
  useEffect(() => {
    if (open) setSelected(prefs.enabled);
  }, [open, prefs.enabled]);

  const toggle = (key: string) => {
    setSelected((cur) =>
      cur.includes(key) ? cur.filter((k) => k !== key) : [...cur, key],
    );
  };

  const save = async () => {
    if (selected.length === 0) {
      Alert.alert("Pick at least one", "Choose at least one channel to show.");
      return;
    }
    setSaving(true);
    try {
      const next = await updateDialerChannels(selected);
      onSaved(next);
    } catch {
      Alert.alert("Couldn't save", "Please try again.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      visible={open}
      transparent
      animationType="slide"
      onRequestClose={onClose}
    >
      <Pressable
        onPress={onClose}
        style={{ flex: 1, backgroundColor: "#0008", justifyContent: "flex-end" }}
      >
        <Pressable
          onPress={(e) => e.stopPropagation()}
          style={{
            backgroundColor: colors.background,
            borderTopLeftRadius: 20,
            borderTopRightRadius: 20,
            paddingHorizontal: 20,
            paddingTop: 16,
            paddingBottom: 32,
          }}
        >
          <View
            style={{
              flexDirection: "row",
              alignItems: "center",
              justifyContent: "space-between",
              marginBottom: 4,
            }}
          >
            <Text
              style={{
                color: colors.foreground,
                fontFamily: "SpaceGrotesk_600SemiBold",
                fontSize: 18,
              }}
            >
              Channels
            </Text>
            <Pressable onPress={onClose} hitSlop={10}>
              <Feather name="x" size={22} color={colors.mutedForeground} />
            </Pressable>
          </View>
          <Text
            style={{
              color: colors.mutedForeground,
              fontSize: 13,
              marginBottom: 12,
            }}
          >
            Choose which messaging channels show on the keypad, recents and
            favourites.
          </Text>

          {prefs.catalog.map((c) => {
            const on = selected.includes(c.key);
            return (
              <Pressable
                key={c.key}
                onPress={() => toggle(c.key)}
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  paddingVertical: 12,
                  borderBottomWidth: StyleSheet.hairlineWidth,
                  borderBottomColor: colors.border,
                }}
              >
                <View
                  style={{
                    width: 34,
                    height: 34,
                    borderRadius: 17,
                    alignItems: "center",
                    justifyContent: "center",
                    backgroundColor: `${c.color}22`,
                    marginRight: 12,
                  }}
                >
                  <Feather name={featherName(c)} size={16} color={c.color} />
                </View>
                <Text
                  style={{
                    flex: 1,
                    color: colors.foreground,
                    fontFamily: "SpaceGrotesk_500Medium",
                    fontSize: 15,
                  }}
                >
                  {c.label}
                </Text>
                <Feather
                  name={on ? "check-circle" : "circle"}
                  size={22}
                  color={on ? colors.primary : colors.mutedForeground}
                />
              </Pressable>
            );
          })}

          <Pressable
            onPress={() => void save()}
            disabled={saving}
            style={({ pressed }) => ({
              marginTop: 18,
              borderRadius: 12,
              paddingVertical: 14,
              alignItems: "center",
              backgroundColor: colors.primary,
              opacity: pressed || saving ? 0.7 : 1,
            })}
          >
            <Text
              style={{
                color: "#fff",
                fontFamily: "SpaceGrotesk_600SemiBold",
                fontSize: 15,
              }}
            >
              {saving ? "Saving…" : "Save"}
            </Text>
          </Pressable>
        </Pressable>
      </Pressable>
    </Modal>
  );
}

function ChannelBtn({
  icon,
  label,
  color,
  onPress,
}: {
  icon: ComponentProps<typeof Feather>["name"];
  label: string;
  color: string;
  onPress: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [styles.channelBtn, { opacity: pressed ? 0.6 : 1 }]}
    >
      <View style={[styles.channelIcon, { backgroundColor: `${color}22` }]}>
        <Feather name={icon} size={18} color={color} />
      </View>
      <Text style={[styles.channelLabel, { color }]}>{label}</Text>
    </Pressable>
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
  modeToggle: { flexDirection: "row", gap: 8, marginBottom: 10 },
  modeBtn: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    paddingVertical: 8,
    borderRadius: 10,
    borderWidth: StyleSheet.hairlineWidth,
  },
  filterRow: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginBottom: 10 },
  filterChip: {
    flexDirection: "row",
    alignItems: "center",
    paddingVertical: 5,
    paddingHorizontal: 10,
    borderRadius: 999,
    borderWidth: StyleSheet.hairlineWidth,
  },
  abcInput: {
    borderWidth: StyleSheet.hairlineWidth,
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 16,
    fontFamily: "SpaceGrotesk_500Medium",
    marginBottom: 10,
  },
  uniGroupLabel: {
    fontSize: 11,
    fontFamily: "SpaceGrotesk_700Bold",
    textTransform: "uppercase",
    letterSpacing: 0.5,
    marginBottom: 5,
  },
  uniBadge: {
    paddingHorizontal: 5,
    paddingVertical: 1,
    borderRadius: 5,
    borderWidth: StyleSheet.hairlineWidth,
  },
  uniEmpty: {
    textAlign: "center",
    fontSize: 13,
    fontFamily: "SpaceGrotesk_500Medium",
    paddingVertical: 16,
  },
  uniChannels: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 6,
    paddingHorizontal: 8,
    paddingTop: 6,
  },
  uniChanBtn: {
    width: 28,
    height: 28,
    borderRadius: 14,
    alignItems: "center",
    justifyContent: "center",
  },
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
  channelRow: {
    flexDirection: "row",
    justifyContent: "space-around",
    marginTop: 12,
  },
  channelBtn: { alignItems: "center", flex: 1 },
  channelIcon: {
    width: 46,
    height: 46,
    borderRadius: 23,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 4,
  },
  channelLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11 },
  rowActions: { flexDirection: "row", alignItems: "center", flexShrink: 0, maxWidth: 118 },
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
