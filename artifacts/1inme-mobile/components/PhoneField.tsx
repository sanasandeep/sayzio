import { useEffect, useMemo, useRef, useState } from "react";
import {
  FlatList,
  Modal,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
  type TextStyle,
} from "react-native";

import { useColors } from "@/hooks/useColors";

type Country = {
  code: string;
  name: string;
  dial: string;
  flag: string;
};

const COUNTRIES: Country[] = [
  { code: "US", name: "United States",           dial: "+1",    flag: "🇺🇸" },
  { code: "CA", name: "Canada",                  dial: "+1",    flag: "🇨🇦" },
  { code: "GB", name: "United Kingdom",          dial: "+44",   flag: "🇬🇧" },
  { code: "AU", name: "Australia",               dial: "+61",   flag: "🇦🇺" },
  { code: "NZ", name: "New Zealand",             dial: "+64",   flag: "🇳🇿" },
  { code: "IN", name: "India",                   dial: "+91",   flag: "🇮🇳" },
  { code: "PK", name: "Pakistan",                dial: "+92",   flag: "🇵🇰" },
  { code: "BD", name: "Bangladesh",              dial: "+880",  flag: "🇧🇩" },
  { code: "LK", name: "Sri Lanka",               dial: "+94",   flag: "🇱🇰" },
  { code: "NP", name: "Nepal",                   dial: "+977",  flag: "🇳🇵" },
  { code: "DE", name: "Germany",                 dial: "+49",   flag: "🇩🇪" },
  { code: "FR", name: "France",                  dial: "+33",   flag: "🇫🇷" },
  { code: "IT", name: "Italy",                   dial: "+39",   flag: "🇮🇹" },
  { code: "ES", name: "Spain",                   dial: "+34",   flag: "🇪🇸" },
  { code: "NL", name: "Netherlands",             dial: "+31",   flag: "🇳🇱" },
  { code: "BE", name: "Belgium",                 dial: "+32",   flag: "🇧🇪" },
  { code: "CH", name: "Switzerland",             dial: "+41",   flag: "🇨🇭" },
  { code: "AT", name: "Austria",                 dial: "+43",   flag: "🇦🇹" },
  { code: "SE", name: "Sweden",                  dial: "+46",   flag: "🇸🇪" },
  { code: "NO", name: "Norway",                  dial: "+47",   flag: "🇳🇴" },
  { code: "DK", name: "Denmark",                 dial: "+45",   flag: "🇩🇰" },
  { code: "FI", name: "Finland",                 dial: "+358",  flag: "🇫🇮" },
  { code: "PT", name: "Portugal",                dial: "+351",  flag: "🇵🇹" },
  { code: "PL", name: "Poland",                  dial: "+48",   flag: "🇵🇱" },
  { code: "CZ", name: "Czech Republic",          dial: "+420",  flag: "🇨🇿" },
  { code: "HU", name: "Hungary",                 dial: "+36",   flag: "🇭🇺" },
  { code: "RO", name: "Romania",                 dial: "+40",   flag: "🇷🇴" },
  { code: "GR", name: "Greece",                  dial: "+30",   flag: "🇬🇷" },
  { code: "IE", name: "Ireland",                 dial: "+353",  flag: "🇮🇪" },
  { code: "RU", name: "Russia",                  dial: "+7",    flag: "🇷🇺" },
  { code: "TR", name: "Turkey",                  dial: "+90",   flag: "🇹🇷" },
  { code: "UA", name: "Ukraine",                 dial: "+380",  flag: "🇺🇦" },
  { code: "AE", name: "UAE",                     dial: "+971",  flag: "🇦🇪" },
  { code: "SA", name: "Saudi Arabia",            dial: "+966",  flag: "🇸🇦" },
  { code: "IL", name: "Israel",                  dial: "+972",  flag: "🇮🇱" },
  { code: "QA", name: "Qatar",                   dial: "+974",  flag: "🇶🇦" },
  { code: "KW", name: "Kuwait",                  dial: "+965",  flag: "🇰🇼" },
  { code: "BH", name: "Bahrain",                 dial: "+973",  flag: "🇧🇭" },
  { code: "OM", name: "Oman",                    dial: "+968",  flag: "🇴🇲" },
  { code: "JO", name: "Jordan",                  dial: "+962",  flag: "🇯🇴" },
  { code: "LB", name: "Lebanon",                 dial: "+961",  flag: "🇱🇧" },
  { code: "EG", name: "Egypt",                   dial: "+20",   flag: "🇪🇬" },
  { code: "ZA", name: "South Africa",            dial: "+27",   flag: "🇿🇦" },
  { code: "NG", name: "Nigeria",                 dial: "+234",  flag: "🇳🇬" },
  { code: "KE", name: "Kenya",                   dial: "+254",  flag: "🇰🇪" },
  { code: "GH", name: "Ghana",                   dial: "+233",  flag: "🇬🇭" },
  { code: "ET", name: "Ethiopia",                dial: "+251",  flag: "🇪🇹" },
  { code: "MA", name: "Morocco",                 dial: "+212",  flag: "🇲🇦" },
  { code: "TZ", name: "Tanzania",                dial: "+255",  flag: "🇹🇿" },
  { code: "UG", name: "Uganda",                  dial: "+256",  flag: "🇺🇬" },
  { code: "CN", name: "China",                   dial: "+86",   flag: "🇨🇳" },
  { code: "JP", name: "Japan",                   dial: "+81",   flag: "🇯🇵" },
  { code: "KR", name: "South Korea",             dial: "+82",   flag: "🇰🇷" },
  { code: "SG", name: "Singapore",               dial: "+65",   flag: "🇸🇬" },
  { code: "MY", name: "Malaysia",                dial: "+60",   flag: "🇲🇾" },
  { code: "ID", name: "Indonesia",               dial: "+62",   flag: "🇮🇩" },
  { code: "PH", name: "Philippines",             dial: "+63",   flag: "🇵🇭" },
  { code: "TH", name: "Thailand",                dial: "+66",   flag: "🇹🇭" },
  { code: "VN", name: "Vietnam",                 dial: "+84",   flag: "🇻🇳" },
  { code: "HK", name: "Hong Kong",               dial: "+852",  flag: "🇭🇰" },
  { code: "TW", name: "Taiwan",                  dial: "+886",  flag: "🇹🇼" },
  { code: "BR", name: "Brazil",                  dial: "+55",   flag: "🇧🇷" },
  { code: "MX", name: "Mexico",                  dial: "+52",   flag: "🇲🇽" },
  { code: "AR", name: "Argentina",               dial: "+54",   flag: "🇦🇷" },
  { code: "CO", name: "Colombia",                dial: "+57",   flag: "🇨🇴" },
  { code: "CL", name: "Chile",                   dial: "+56",   flag: "🇨🇱" },
  { code: "PE", name: "Peru",                    dial: "+51",   flag: "🇵🇪" },
  { code: "VE", name: "Venezuela",               dial: "+58",   flag: "🇻🇪" },
];

function parsePhone(value: string): [Country, string] {
  const v = (value ?? "").trim();
  if (!v) return [COUNTRIES[0], ""];
  if (!v.startsWith("+")) return [COUNTRIES[0], v];
  const sorted = [...new Set(COUNTRIES.map((c) => c.dial))].sort(
    (a, b) => b.length - a.length,
  );
  for (const dial of sorted) {
    if (v.startsWith(dial)) {
      const country =
        COUNTRIES.find((c) => c.dial === dial) ?? COUNTRIES[0];
      return [country, v.slice(dial.length).trimStart()];
    }
  }
  return [COUNTRIES[0], v];
}

type Props = {
  label?: string;
  value: string;
  onChange: (combined: string) => void;
  error?: string;
};

export function PhoneField({ label, value, onChange, error }: Props) {
  const colors = useColors();
  const [country, setCountry] = useState<Country>(() => parsePhone(value ?? "")[0]);
  const [number, setNumber] = useState(() => parsePhone(value ?? "")[1]);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [focused, setFocused] = useState(false);
  const lastEmitted = useRef<string>(value ?? "");

  // Re-sync when the external value changes (e.g. async profile fetch resolves
  // after first render). Skip when it matches what we last emitted so live
  // typing is never clobbered.
  useEffect(() => {
    const incoming = value ?? "";
    if (incoming === lastEmitted.current) return;
    const [c, n] = parsePhone(incoming);
    setCountry(c);
    setNumber(n);
    lastEmitted.current = incoming;
  }, [value]);

  const filtered = useMemo(() => {
    const q = search.toLowerCase();
    if (!q) return COUNTRIES;
    return COUNTRIES.filter(
      (c) =>
        c.name.toLowerCase().includes(q) ||
        c.dial.includes(q) ||
        c.code.toLowerCase().includes(q),
    );
  }, [search]);

  function emit(c: Country, n: string) {
    const trimmed = n.trim();
    onChange(trimmed ? `${c.dial} ${trimmed}` : "");
  }

  function pickCountry(c: Country) {
    setCountry(c);
    setPickerOpen(false);
    setSearch("");
    emit(c, number);
  }

  function onNumberChange(t: string) {
    setNumber(t);
    emit(country, t);
  }

  const inputBorderColor = error
    ? colors.destructive
    : focused
      ? colors.primary
      : colors.border;

  return (
    <View style={styles.wrap}>
      {label ? (
        <Text style={[styles.label, { color: colors.mutedForeground }]}>
          {label}
        </Text>
      ) : null}

      <View
        style={[
          styles.row,
          {
            backgroundColor: colors.card,
            borderColor: inputBorderColor,
            borderRadius: colors.radius,
          },
        ]}
      >
        {/* Country trigger */}
        <Pressable
          onPress={() => setPickerOpen(true)}
          style={[
            styles.trigger,
            { borderRightColor: colors.border },
          ]}
          accessibilityLabel="Select country code"
        >
          <Text style={styles.flag}>{country.flag}</Text>
          <Text style={[styles.dial, { color: colors.foreground }]}>
            {country.dial}
          </Text>
          <Text style={[styles.chevron, { color: colors.mutedForeground }]}>
            ▾
          </Text>
        </Pressable>

        {/* Number input */}
        <TextInput
          value={number}
          onChangeText={onNumberChange}
          keyboardType="phone-pad"
          placeholder="555 0100"
          placeholderTextColor={colors.mutedForeground}
          onFocus={() => setFocused(true)}
          onBlur={() => setFocused(false)}
          autoComplete="tel-national"
          style={[styles.numberInput, { color: colors.foreground } as TextStyle]}
        />
      </View>

      {error ? (
        <Text style={[styles.hint, { color: colors.destructive }]}>{error}</Text>
      ) : null}

      {/* Country picker modal */}
      <Modal
        visible={pickerOpen}
        animationType="slide"
        transparent
        onRequestClose={() => setPickerOpen(false)}
      >
        <Pressable
          style={styles.backdrop}
          onPress={() => setPickerOpen(false)}
        />
        <View
          style={[
            styles.sheet,
            { backgroundColor: colors.card, borderColor: colors.border },
          ]}
        >
          <View style={[styles.sheetHeader, { borderBottomColor: colors.border }]}>
            <Text style={[styles.sheetTitle, { color: colors.foreground }]}>
              Select country
            </Text>
            <Pressable
              onPress={() => setPickerOpen(false)}
              style={styles.closeBtn}
            >
              <Text style={{ color: colors.mutedForeground, fontSize: 20 }}>✕</Text>
            </Pressable>
          </View>

          {/* Search */}
          <View style={[styles.searchWrap, { borderBottomColor: colors.border }]}>
            <TextInput
              value={search}
              onChangeText={setSearch}
              placeholder="Search country or dial code…"
              placeholderTextColor={colors.mutedForeground}
              autoFocus={Platform.OS !== "web"}
              style={[
                styles.searchInput,
                {
                  color: colors.foreground,
                  backgroundColor: colors.background,
                  borderColor: colors.border,
                  borderRadius: colors.radius,
                },
              ]}
            />
          </View>

          <FlatList
            data={filtered}
            keyExtractor={(c) => c.code}
            keyboardShouldPersistTaps="handled"
            renderItem={({ item: c }) => (
              <Pressable
                onPress={() => pickCountry(c)}
                style={[
                  styles.countryRow,
                  { borderBottomColor: colors.border },
                  c.code === country.code && {
                    backgroundColor: colors.primary + "22",
                  },
                ]}
              >
                <Text style={styles.countryFlag}>{c.flag}</Text>
                <Text
                  style={[styles.countryName, { color: colors.foreground }]}
                  numberOfLines={1}
                >
                  {c.name}
                </Text>
                <Text style={[styles.countryDial, { color: colors.mutedForeground }]}>
                  {c.dial}
                </Text>
              </Pressable>
            )}
            ListEmptyComponent={
              <Text style={[styles.emptyText, { color: colors.mutedForeground }]}>
                No countries found
              </Text>
            }
          />
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { gap: 6 },
  label: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  row: {
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    overflow: "hidden",
    minHeight: 52,
  },
  trigger: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    paddingHorizontal: 12,
    paddingVertical: 14,
    borderRightWidth: 1,
  },
  flag: { fontSize: 20 },
  dial: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 14,
  },
  chevron: { fontSize: 10, marginLeft: 2 },
  numberInput: {
    flex: 1,
    paddingHorizontal: 12,
    paddingVertical: 14,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 16,
  },
  hint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  backdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.5)",
  },
  sheet: {
    maxHeight: "75%",
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    borderTopWidth: 1,
    borderLeftWidth: 1,
    borderRightWidth: 1,
    overflow: "hidden",
  },
  sheetHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 20,
    paddingVertical: 16,
    borderBottomWidth: 1,
  },
  sheetTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 17,
  },
  closeBtn: { padding: 4 },
  searchWrap: {
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderBottomWidth: 1,
  },
  searchInput: {
    paddingHorizontal: 14,
    paddingVertical: 10,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
    borderWidth: 1,
  },
  countryRow: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 20,
    paddingVertical: 14,
    gap: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  countryFlag: { fontSize: 24, width: 32, textAlign: "center" },
  countryName: {
    flex: 1,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 15,
  },
  countryDial: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 13,
  },
  emptyText: {
    textAlign: "center",
    padding: 24,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
  },
});
