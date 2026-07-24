import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  getCreatorProfileSettings,
  updateCreatorProfile,
  type CreatorProfileUpdatePayload,
  type CtaButton,
  type FeaturedLinksStyle,
} from "@/lib/api/creatorProfile";
import { listLinks, type Link } from "@/lib/api/links";
import { showAlert } from "@/lib/webAlert";

// Same style catalog as the server's featured_links_style validation
// (CreatorProfileController::FEATURED_LINK_STYLES keys).
const LINK_STYLES: { key: FeaturedLinksStyle; label: string }[] = [
  { key: "classic", label: "Classic" },
  { key: "outline", label: "Outline" },
  { key: "solid", label: "Solid" },
  { key: "ghost", label: "Ghost" },
  { key: "pill", label: "Pill" },
  { key: "card_heading", label: "Card" },
];

// Mirrors User::PROFILE_DEFAULT_VISIBILITY keys (the profile "tabs").
const SECTION_LABELS: { key: string; label: string }[] = [
  { key: "stats", label: "Stats" },
  { key: "about", label: "About" },
  { key: "posts", label: "Posts" },
  { key: "socials", label: "Socials" },
  { key: "biolink", label: "Biolink" },
  { key: "contact", label: "Contact" },
  { key: "events", label: "Events" },
  { key: "featured_links", label: "Featured links" },
  { key: "showcase", label: "Showcase" },
  { key: "highlights", label: "Highlights" },
  { key: "cta", label: "Action buttons" },
];

const HIGHLIGHT_LABELS: { key: HighlightKey; label: string }[] = [
  { key: "show_followers", label: "Followers count" },
  { key: "show_links", label: "Links count" },
  { key: "show_member_since", label: "Member since" },
  { key: "show_verified", label: "Verified badge" },
];

type HighlightKey =
  | "show_followers"
  | "show_links"
  | "show_member_since"
  | "show_verified";

const CTA_KINDS: { key: CtaButton["kind"]; label: string }[] = [
  { key: "email", label: "Email" },
  { key: "whatsapp", label: "WhatsApp" },
  { key: "call", label: "Call" },
  { key: "link", label: "Link" },
  { key: "form", label: "Form" },
];

const THEME_PRESETS = [
  "#3b82f6", "#6366f1", "#ec4899", "#ef4444",
  "#f59e0b", "#10b981", "#14b8a6", "#64748b",
];

const bit = (b: boolean): "0" | "1" => (b ? "1" : "0");

type FeaturedEntry = { id: number; enabled: boolean; label: string };

export default function CreatorSettingsScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();

  const q = useQuery({
    queryKey: ["creator-profile-settings"],
    queryFn: getCreatorProfileSettings,
  });

  // Owned links for the featured-link picker. per_page 100 covers the
  // realistic case; the server caps featured links at 8 anyway.
  const linksQ = useQuery({
    queryKey: ["creator-settings-links"],
    queryFn: () => listLinks({ per_page: 100 }),
  });

  const [themeColor, setThemeColor] = useState<string>("");
  const [sections, setSections] = useState<Record<string, boolean>>({});
  const [featured, setFeatured] = useState<FeaturedEntry[]>([]);
  const [linkStyle, setLinkStyle] = useState<FeaturedLinksStyle>("classic");
  const [showLinkStats, setShowLinkStats] = useState(false);
  const [highlights, setHighlights] = useState<Record<HighlightKey, boolean>>({
    show_followers: true,
    show_links: true,
    show_member_since: true,
    show_verified: true,
  });
  const [ctaPrimary, setCtaPrimary] = useState<CtaButton | null>(null);
  const [ctaSecondary, setCtaSecondary] = useState<CtaButton[]>([]);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [seeded, setSeeded] = useState(false);

  useEffect(() => {
    if (!q.data || seeded) return;
    const p = q.data;
    setThemeColor(p.profile_theme_color ?? "");
    setSections({ ...p.sections });
    setFeatured(
      p.showcase.featured_links.map((fl) => ({
        id: fl.id,
        enabled: fl.enabled,
        label: fl.title || fl.alias || `Link #${fl.id}`,
      })),
    );
    setLinkStyle(p.showcase.featured_links_style);
    setShowLinkStats(p.showcase.show_link_stats);
    setHighlights({ ...p.showcase.highlights });
    setCtaPrimary(p.showcase.cta.primary);
    setCtaSecondary(p.showcase.cta.secondary);
    setSeeded(true);
  }, [q.data, seeded]);

  const save = useMutation({
    mutationFn: () => {
      const payload: CreatorProfileUpdatePayload = {
        profile_theme_color:
          themeColor && /^#[0-9a-fA-F]{6}$/.test(themeColor) ? themeColor : null,
        sections: Object.fromEntries(
          Object.entries(sections).map(([k, v]) => [k, bit(v)]),
        ),
        featured_links: featured.map((f) => ({ id: f.id, enabled: bit(f.enabled) })),
        featured_links_style: linkStyle,
        showcase_show_link_stats: bit(showLinkStats),
        // Pass the existing showcase cards through untouched — the save
        // replaces the showcase block as a whole.
        showcase_items: (q.data?.showcase.showcase_items ?? []).map((it) => ({
          type: it.type,
          link_id: it.link_id,
        })),
        highlights_show_followers: bit(highlights.show_followers),
        highlights_show_links: bit(highlights.show_links),
        highlights_show_member_since: bit(highlights.show_member_since),
        highlights_show_verified: bit(highlights.show_verified),
        cta_primary_kind: ctaPrimary?.kind ?? null,
        cta_primary_label: ctaPrimary?.label ?? null,
        cta_primary_value: ctaPrimary?.value ?? null,
        cta_secondary: ctaSecondary.filter(
          (c) => c.label.trim() !== "" && c.value.trim() !== "",
        ),
      };
      return updateCreatorProfile(payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["creator-profile-settings"] });
      qc.invalidateQueries({ queryKey: ["creator-profile"] });
      if (Platform.OS === "web") {
        router.back();
      } else {
        showAlert("Saved", "Creator profile updated.", [
          { text: "OK", onPress: () => router.back() },
        ]);
      }
    },
    onError: (e: any) => {
      showAlert("Could not save", e?.message ?? "Unknown error");
    },
  });

  if (q.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Creator profile" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (q.isError || !q.data) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Creator profile" }} />
        <EmptyState
          icon="alert-circle"
          title="Couldn't load your creator profile"
          body={(q.error as any)?.message ?? "Please try again."}
        />
      </View>
    );
  }

  const availableLinks: Link[] = (linksQ.data?.items ?? []).filter(
    (l) => !featured.some((f) => f.id === l.id),
  );

  const setHighlight = (k: HighlightKey, v: boolean) =>
    setHighlights((h) => ({ ...h, [k]: v }));

  const updateSecondary = (i: number, patch: Partial<CtaButton>) =>
    setCtaSecondary((list) =>
      list.map((c, idx) => (idx === i ? { ...c, ...patch } : c)),
    );

  const card = {
    backgroundColor: colors.card,
    borderColor: colors.border,
    borderRadius: colors.radius,
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Creator profile" }} />
      <ScrollView contentContainerStyle={{ padding: 20, gap: 14, paddingBottom: 40 }}>
        {!q.data.handle ? (
          <Text style={[styles.hint, { color: colors.mutedForeground }]}>
            Claim a handle in Edit profile first. Your public page lives at
            /@handle.
          </Text>
        ) : null}

        {/* Theme color */}
        <Text style={[styles.section, { color: colors.foreground }]}>Theme color</Text>
        <View style={styles.swatchRow}>
          <Pressable
            onPress={() => setThemeColor("")}
            accessibilityRole="button"
            style={[
              styles.swatch,
              {
                borderColor: themeColor === "" ? colors.primary : colors.border,
                backgroundColor: colors.card,
              },
            ]}
          >
            <Feather name="x" size={14} color={colors.mutedForeground} />
          </Pressable>
          {THEME_PRESETS.map((hex) => (
            <Pressable
              key={hex}
              onPress={() => setThemeColor(hex)}
              accessibilityRole="button"
              style={[
                styles.swatch,
                {
                  backgroundColor: hex,
                  borderColor:
                    themeColor.toLowerCase() === hex ? colors.foreground : "transparent",
                },
              ]}
            />
          ))}
        </View>
        <TextField
          label="Custom hex"
          value={themeColor}
          onChangeText={(t) => setThemeColor(t.trim())}
          placeholder="#3b82f6"
          autoCapitalize="none"
          hint="Leave empty for the platform default."
        />

        {/* Profile tabs */}
        <Text style={[styles.section, { color: colors.foreground }]}>Profile sections</Text>
        <View style={[styles.groupCard, card]}>
          {SECTION_LABELS.map((s, i) => (
            <View
              key={s.key}
              style={[
                styles.rowBetween,
                i > 0 && { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.border },
              ]}
            >
              <Text style={[styles.rowLabel, { color: colors.foreground }]}>{s.label}</Text>
              <Switch
                value={sections[s.key] ?? true}
                onValueChange={(v) => setSections((sec) => ({ ...sec, [s.key]: v }))}
                trackColor={{ true: colors.primary }}
              />
            </View>
          ))}
        </View>

        {/* Featured links */}
        <Text style={[styles.section, { color: colors.foreground }]}>Featured links</Text>
        {featured.length === 0 ? (
          <Text style={[styles.hint, { color: colors.mutedForeground }]}>
            No featured links yet. Add up to 8 from your links.
          </Text>
        ) : (
          <View style={[styles.groupCard, card]}>
            {featured.map((f, i) => (
              <View
                key={f.id}
                style={[
                  styles.rowBetween,
                  i > 0 && { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.border },
                ]}
              >
                <Text
                  style={[styles.rowLabel, { color: colors.foreground, flex: 1 }]}
                  numberOfLines={1}
                >
                  {f.label}
                </Text>
                <Switch
                  value={f.enabled}
                  onValueChange={(v) =>
                    setFeatured((list) =>
                      list.map((x) => (x.id === f.id ? { ...x, enabled: v } : x)),
                    )
                  }
                  trackColor={{ true: colors.primary }}
                />
                <Pressable
                  onPress={() =>
                    setFeatured((list) => list.filter((x) => x.id !== f.id))
                  }
                  accessibilityRole="button"
                  accessibilityLabel={`Remove ${f.label}`}
                  hitSlop={8}
                  style={{ marginLeft: 10 }}
                >
                  <Feather name="trash-2" size={16} color={colors.mutedForeground} />
                </Pressable>
              </View>
            ))}
          </View>
        )}
        {featured.length < 8 ? (
          <Pressable
            onPress={() => setPickerOpen((o) => !o)}
            accessibilityRole="button"
            style={[styles.addBtn, { borderColor: colors.border, backgroundColor: colors.card }]}
          >
            <Feather name={pickerOpen ? "chevron-up" : "plus"} size={15} color={colors.primary} />
            <Text style={[styles.rowLabel, { color: colors.primary }]}>
              {pickerOpen ? "Hide link picker" : "Add a featured link"}
            </Text>
          </Pressable>
        ) : null}
        {pickerOpen ? (
          linksQ.isLoading ? (
            <ActivityIndicator color={colors.primary} />
          ) : availableLinks.length === 0 ? (
            <Text style={[styles.hint, { color: colors.mutedForeground }]}>
              No more links to add.
            </Text>
          ) : (
            <View style={[styles.groupCard, card]}>
              {availableLinks.slice(0, 30).map((l, i) => (
                <Pressable
                  key={l.id}
                  onPress={() => {
                    setFeatured((list) =>
                      list.length >= 8
                        ? list
                        : [...list, { id: l.id, enabled: true, label: l.title || l.alias }],
                    );
                    setPickerOpen(false);
                  }}
                  accessibilityRole="button"
                  style={[
                    styles.rowBetween,
                    i > 0 && { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.border },
                  ]}
                >
                  <View style={{ flex: 1 }}>
                    <Text style={[styles.rowLabel, { color: colors.foreground }]} numberOfLines={1}>
                      {l.title || l.alias}
                    </Text>
                    <Text style={[styles.hint, { color: colors.mutedForeground }]} numberOfLines={1}>
                      /{l.alias} · {l.type}
                    </Text>
                  </View>
                  <Feather name="plus-circle" size={16} color={colors.primary} />
                </Pressable>
              ))}
            </View>
          )
        ) : null}

        <View style={styles.chipRow}>
          {LINK_STYLES.map((s) => (
            <Pressable
              key={s.key}
              onPress={() => setLinkStyle(s.key)}
              accessibilityRole="button"
              accessibilityState={{ selected: linkStyle === s.key }}
              style={[
                styles.chip,
                {
                  borderColor: linkStyle === s.key ? colors.primary : colors.border,
                  backgroundColor: linkStyle === s.key ? colors.primary : colors.card,
                },
              ]}
            >
              <Text
                style={[
                  styles.chipLabel,
                  { color: linkStyle === s.key ? "#fff" : colors.mutedForeground },
                ]}
              >
                {s.label}
              </Text>
            </Pressable>
          ))}
        </View>
        <View style={[styles.rowBetween, styles.groupCard, card]}>
          <Text style={[styles.rowLabel, { color: colors.foreground }]}>
            Show click counts on featured links
          </Text>
          <Switch
            value={showLinkStats}
            onValueChange={setShowLinkStats}
            trackColor={{ true: colors.primary }}
          />
        </View>

        {/* Highlights */}
        <Text style={[styles.section, { color: colors.foreground }]}>Highlights</Text>
        <View style={[styles.groupCard, card]}>
          {HIGHLIGHT_LABELS.map((h, i) => (
            <View
              key={h.key}
              style={[
                styles.rowBetween,
                i > 0 && { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.border },
              ]}
            >
              <Text style={[styles.rowLabel, { color: colors.foreground }]}>{h.label}</Text>
              <Switch
                value={highlights[h.key]}
                onValueChange={(v) => setHighlight(h.key, v)}
                trackColor={{ true: colors.primary }}
              />
            </View>
          ))}
        </View>

        {/* Primary CTA */}
        <Text style={[styles.section, { color: colors.foreground }]}>Primary action button</Text>
        <View style={styles.chipRow}>
          <Pressable
            onPress={() => setCtaPrimary(null)}
            accessibilityRole="button"
            style={[
              styles.chip,
              {
                borderColor: !ctaPrimary ? colors.primary : colors.border,
                backgroundColor: !ctaPrimary ? colors.primary : colors.card,
              },
            ]}
          >
            <Text style={[styles.chipLabel, { color: !ctaPrimary ? "#fff" : colors.mutedForeground }]}>
              None
            </Text>
          </Pressable>
          {CTA_KINDS.map((k) => {
            const active = ctaPrimary?.kind === k.key;
            return (
              <Pressable
                key={k.key}
                onPress={() =>
                  setCtaPrimary((c) => ({
                    kind: k.key,
                    label: c?.label ?? "",
                    value: c?.value ?? "",
                  }))
                }
                accessibilityRole="button"
                accessibilityState={{ selected: active }}
                style={[
                  styles.chip,
                  {
                    borderColor: active ? colors.primary : colors.border,
                    backgroundColor: active ? colors.primary : colors.card,
                  },
                ]}
              >
                <Text style={[styles.chipLabel, { color: active ? "#fff" : colors.mutedForeground }]}>
                  {k.label}
                </Text>
              </Pressable>
            );
          })}
        </View>
        {ctaPrimary ? (
          <View style={{ gap: 10 }}>
            <TextField
              label="Button label"
              value={ctaPrimary.label}
              onChangeText={(t) => setCtaPrimary((c) => (c ? { ...c, label: t } : c))}
              placeholder="Get in touch"
            />
            <TextField
              label={ctaPrimary.kind === "form" ? "Form alias" : "Destination"}
              value={ctaPrimary.value}
              onChangeText={(t) => setCtaPrimary((c) => (c ? { ...c, value: t } : c))}
              placeholder={
                ctaPrimary.kind === "email"
                  ? "you@example.com"
                  : ctaPrimary.kind === "link"
                    ? "https://…"
                    : ctaPrimary.kind === "form"
                      ? "my-form-alias"
                      : "+1 555 000 0000"
              }
              autoCapitalize="none"
            />
          </View>
        ) : null}

        {/* Secondary CTAs */}
        <Text style={[styles.section, { color: colors.foreground }]}>Secondary buttons</Text>
        {ctaSecondary.map((c, i) => (
          <View key={i} style={[styles.groupCard, card, { padding: 12, gap: 8 }]}>
            <View style={styles.chipRow}>
              {CTA_KINDS.map((k) => {
                const active = c.kind === k.key;
                return (
                  <Pressable
                    key={k.key}
                    onPress={() => updateSecondary(i, { kind: k.key })}
                    accessibilityRole="button"
                    style={[
                      styles.chip,
                      {
                        borderColor: active ? colors.primary : colors.border,
                        backgroundColor: active ? colors.primary : colors.card,
                      },
                    ]}
                  >
                    <Text style={[styles.chipLabel, { color: active ? "#fff" : colors.mutedForeground }]}>
                      {k.label}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
            <TextInput
              value={c.label}
              onChangeText={(t) => updateSecondary(i, { label: t })}
              placeholder="Label"
              placeholderTextColor={colors.mutedForeground}
              style={[styles.input, { color: colors.foreground, borderColor: colors.border }]}
            />
            <TextInput
              value={c.value}
              onChangeText={(t) => updateSecondary(i, { value: t })}
              placeholder="Destination"
              placeholderTextColor={colors.mutedForeground}
              autoCapitalize="none"
              style={[styles.input, { color: colors.foreground, borderColor: colors.border }]}
            />
            <Pressable
              onPress={() => setCtaSecondary((list) => list.filter((_, idx) => idx !== i))}
              accessibilityRole="button"
              style={{ alignSelf: "flex-end" }}
            >
              <Text style={[styles.chipLabel, { color: colors.mutedForeground }]}>Remove</Text>
            </Pressable>
          </View>
        ))}
        {ctaSecondary.length < 3 ? (
          <Pressable
            onPress={() =>
              setCtaSecondary((list) => [...list, { kind: "link", label: "", value: "" }])
            }
            accessibilityRole="button"
            style={[styles.addBtn, { borderColor: colors.border, backgroundColor: colors.card }]}
          >
            <Feather name="plus" size={15} color={colors.primary} />
            <Text style={[styles.rowLabel, { color: colors.primary }]}>Add secondary button</Text>
          </Pressable>
        ) : null}

        <Button label="Save changes" onPress={() => save.mutate()} loading={save.isPending} />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 20 },
  section: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 13,
    textTransform: "uppercase",
    letterSpacing: 0.6,
    marginTop: 12,
  },
  hint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  groupCard: { borderWidth: 1, paddingHorizontal: 14 },
  rowBetween: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
    paddingVertical: 12,
  },
  rowLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  swatchRow: { flexDirection: "row", gap: 8, flexWrap: "wrap" },
  swatch: {
    width: 32,
    height: 32,
    borderRadius: 16,
    borderWidth: 2,
    alignItems: "center",
    justifyContent: "center",
  },
  chipRow: { flexDirection: "row", gap: 8, flexWrap: "wrap" },
  chip: {
    paddingVertical: 7,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderRadius: 999,
  },
  chipLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  addBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderWidth: 1,
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
    justifyContent: "center",
  },
  input: {
    borderWidth: 1,
    borderRadius: 10,
    paddingVertical: 10,
    paddingHorizontal: 12,
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
  },
});
