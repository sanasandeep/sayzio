import { Feather } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { useRouter } from "expo-router";
import {
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { BrandWordmark } from "@/components/Brand";
import { Button } from "@/components/Button";
import { useAuth } from "@/contexts/AuthContext";
import { useThemeControls } from "@/contexts/ThemeContext";
import { useColors } from "@/hooks/useColors";
import type { ThemePref } from "@/lib/secure";

const INFO_PAGES: {
  href: "/info/about" | "/info/nfc" | "/info/privacy" | "/info/terms" | "/info/help";
  label: string;
  icon: keyof typeof Feather.glyphMap;
}[] = [
  { href: "/info/about", label: "About 1INME", icon: "info" },
  { href: "/info/nfc", label: "How NFC works", icon: "wifi" },
  { href: "/info/help", label: "Help & support", icon: "life-buoy" },
  { href: "/info/privacy", label: "Privacy", icon: "shield" },
  { href: "/info/terms", label: "Terms of service", icon: "file-text" },
];

const TOOL_PAGES: {
  href:
    | "/posts"
    | "/contacts"
    | "/dialer"
    | "/forms"
    | "/followers"
    | "/social"
    | "/notifications"
    | "/subscribers"
    | "/projects"
    | "/qr"
    | "/splash"
    | "/calendar";
  label: string;
  icon: keyof typeof Feather.glyphMap;
}[] = [
  { href: "/posts", label: "Posts", icon: "message-square" },
  { href: "/contacts", label: "Contacts", icon: "users" },
  { href: "/dialer", label: "Dialer", icon: "phone" },
  { href: "/forms", label: "Forms", icon: "file-text" },
  { href: "/followers", label: "Followers & Following", icon: "user-check" },
  { href: "/subscribers", label: "Subscribers", icon: "user-plus" },
  { href: "/social", label: "Social accounts", icon: "share-2" },
  { href: "/projects", label: "Projects", icon: "folder" },
  { href: "/qr", label: "QR codes", icon: "grid" },
  { href: "/splash", label: "Splash pages", icon: "layout" },
  { href: "/calendar", label: "Calendars", icon: "calendar" },
  { href: "/notifications", label: "Notifications", icon: "bell" },
];

const SETTINGS_PAGES: {
  href: "/profile-edit" | "/workspaces" | "/domains" | "/integrations" | "/vault" | "/verification";
  label: string;
  icon: keyof typeof Feather.glyphMap;
}[] = [
  { href: "/profile-edit", label: "Edit profile", icon: "edit-3" },
  { href: "/workspaces", label: "Workspaces", icon: "briefcase" },
  { href: "/domains", label: "Custom domains", icon: "globe" },
  { href: "/integrations", label: "Integrations", icon: "link" },
  { href: "/vault", label: "Vault", icon: "lock" },
  { href: "/verification", label: "Verification", icon: "award" },
];

const THEME_OPTIONS: {
  value: ThemePref;
  label: string;
  icon: keyof typeof Feather.glyphMap;
}[] = [
  { value: "system", label: "System", icon: "smartphone" },
  { value: "light", label: "Light", icon: "sun" },
  { value: "dark", label: "Dark", icon: "moon" },
];

export default function Profile() {
  const colors = useColors();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { user, signOut } = useAuth();
  const { pref, setPref } = useThemeControls();
  const webTop = Platform.OS === "web" ? 67 : 0;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <LinearGradient
        colors={[colors.primary + "1c", "transparent"]}
        start={{ x: 0, y: 0 }}
        end={{ x: 0, y: 0.5 }}
        style={StyleSheet.absoluteFill}
      />
      <ScrollView
        contentContainerStyle={{
          paddingTop: insets.top + 16 + webTop,
          paddingBottom: 32,
          paddingHorizontal: 24,
          gap: 24,
        }}
      >
        <View style={styles.headerRow}>
          <BrandWordmark size={26} />
        </View>

        <View
          style={[
            styles.card,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              borderRadius: colors.radius,
            },
          ]}
        >
          <Text style={[styles.hello, { color: colors.mutedForeground }]}>
            Signed in as
          </Text>
          <Text style={[styles.name, { color: colors.foreground }]}>
            {user?.display_name || user?.email || user?.mobile || "1INME member"}
          </Text>
          {user?.role ? (
            <View
              style={[styles.badge, { backgroundColor: colors.primary + "22" }]}
            >
              <Text style={[styles.badgeText, { color: colors.primary }]}>
                {user.role}
              </Text>
            </View>
          ) : null}
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Appearance
          </Text>
          <View
            style={[
              styles.segment,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            {THEME_OPTIONS.map((opt) => {
              const active = pref === opt.value;
              return (
                <Pressable
                  key={opt.value}
                  onPress={() => setPref(opt.value)}
                  style={[
                    styles.segmentItem,
                    {
                      backgroundColor: active ? colors.background : "transparent",
                      borderRadius: colors.radius - 4,
                    },
                  ]}
                >
                  <Feather
                    name={opt.icon}
                    size={16}
                    color={active ? colors.primary : colors.mutedForeground}
                  />
                  <Text
                    style={[
                      styles.segmentText,
                      {
                        color: active ? colors.primary : colors.mutedForeground,
                      },
                    ]}
                  >
                    {opt.label}
                  </Text>
                </Pressable>
              );
            })}
          </View>
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Manage
          </Text>
          <View
            style={[
              styles.list,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            {TOOL_PAGES.map((p, i) => (
              <Pressable
                key={p.href}
                onPress={() => router.push(p.href)}
                style={({ pressed }) => [
                  styles.listItem,
                  {
                    borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                    borderTopColor: colors.border,
                    opacity: pressed ? 0.7 : 1,
                  },
                ]}
              >
                <Feather name={p.icon} size={18} color={colors.primary} />
                <Text style={[styles.listLabel, { color: colors.foreground }]}>
                  {p.label}
                </Text>
                <Feather
                  name="chevron-right"
                  size={18}
                  color={colors.mutedForeground}
                />
              </Pressable>
            ))}
          </View>
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Settings
          </Text>
          <View
            style={[
              styles.list,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            {SETTINGS_PAGES.map((p, i) => (
              <Pressable
                key={p.href}
                onPress={() => router.push(p.href)}
                style={({ pressed }) => [
                  styles.listItem,
                  {
                    borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                    borderTopColor: colors.border,
                    opacity: pressed ? 0.7 : 1,
                  },
                ]}
              >
                <Feather name={p.icon} size={18} color={colors.primary} />
                <Text style={[styles.listLabel, { color: colors.foreground }]}>
                  {p.label}
                </Text>
                <Feather
                  name="chevron-right"
                  size={18}
                  color={colors.mutedForeground}
                />
              </Pressable>
            ))}
          </View>
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Account
          </Text>
          <View
            style={[
              styles.list,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            <Pressable
              onPress={() => router.push("/wallet" as never)}
              style={({ pressed }) => [
                styles.listItem,
                { opacity: pressed ? 0.7 : 1 },
              ]}
            >
              <Feather name="credit-card" size={18} color={colors.primary} />
              <Text style={[styles.listLabel, { color: colors.foreground }]}>
                Wallet & coins
              </Text>
              <Feather
                name="chevron-right"
                size={18}
                color={colors.mutedForeground}
              />
            </Pressable>
            <Pressable
              onPress={() => router.push("/upgrade" as never)}
              style={({ pressed }) => [
                styles.listItem,
                {
                  borderTopWidth: StyleSheet.hairlineWidth,
                  borderTopColor: colors.border,
                  opacity: pressed ? 0.7 : 1,
                },
              ]}
            >
              <Feather name="zap" size={18} color={colors.primary} />
              <Text style={[styles.listLabel, { color: colors.foreground }]}>
                Upgrade
              </Text>
              <Feather
                name="chevron-right"
                size={18}
                color={colors.mutedForeground}
              />
            </Pressable>
          </View>
        </View>

        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Learn
          </Text>
          <View
            style={[
              styles.list,
              {
                backgroundColor: colors.card,
                borderColor: colors.border,
                borderRadius: colors.radius,
              },
            ]}
          >
            {INFO_PAGES.map((p, i) => (
              <Pressable
                key={p.href}
                onPress={() => router.push(p.href)}
                style={({ pressed }) => [
                  styles.listItem,
                  {
                    borderTopWidth: i === 0 ? 0 : StyleSheet.hairlineWidth,
                    borderTopColor: colors.border,
                    opacity: pressed ? 0.7 : 1,
                  },
                ]}
              >
                <Feather name={p.icon} size={18} color={colors.primary} />
                <Text style={[styles.listLabel, { color: colors.foreground }]}>
                  {p.label}
                </Text>
                <Feather
                  name="chevron-right"
                  size={18}
                  color={colors.mutedForeground}
                />
              </Pressable>
            ))}
          </View>
        </View>

        <Button label="Sign out" variant="outline" onPress={signOut} />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  headerRow: { flexDirection: "row", alignItems: "center" },
  card: { padding: 20, borderWidth: 1, gap: 6 },
  hello: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.6,
    textTransform: "uppercase",
  },
  name: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 24 },
  badge: {
    alignSelf: "flex-start",
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    marginTop: 4,
  },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 11,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  section: { gap: 8 },
  sectionLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.6,
    textTransform: "uppercase",
  },
  segment: { flexDirection: "row", padding: 4, borderWidth: 1 },
  segmentItem: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    paddingVertical: 10,
  },
  segmentText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  list: { borderWidth: 1, overflow: "hidden" },
  listItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 14,
    paddingHorizontal: 16,
    paddingVertical: 16,
  },
  listLabel: { flex: 1, fontFamily: "SpaceGrotesk_500Medium", fontSize: 16 },
});
