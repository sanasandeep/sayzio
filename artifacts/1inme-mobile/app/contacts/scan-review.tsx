import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Image } from "expo-image";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  getCardScan,
  saveCardScan,
  type CardScanContactRow,
  type CardScanExtracted,
  type CardScanSocials,
  type DuplicateHit,
} from "@/lib/api/cardScan";
import { handlePlanLockedError } from "@/lib/upgradePrompt";

type SocialKey = keyof CardScanSocials;
const SOCIAL_KEYS: SocialKey[] = [
  "instagram",
  "tiktok",
  "youtube",
  "twitter",
  "linkedin",
  "facebook",
];

export default function ScanReviewScreen() {
  const colors = useColors();
  const router = useRouter();
  const qc = useQueryClient();
  const { id } = useLocalSearchParams<{ id?: string }>();
  const scanId = Number(id);

  const q = useQuery({
    queryKey: ["card-scan", scanId],
    queryFn: () => getCardScan(scanId),
    enabled: Number.isFinite(scanId) && scanId > 0,
  });

  const [form, setForm] = useState<CardScanExtracted | null>(null);
  const [createContact, setCreateContact] = useState(true);
  const [createBiolink, setCreateBiolink] = useState(false);

  useEffect(() => {
    if (q.data?.scan.extracted && !form) {
      setForm(q.data.scan.extracted);
    }
  }, [q.data, form]);

  const saveMut = useMutation({
    mutationFn: () => {
      if (!form) throw new Error("Not ready");
      return saveCardScan(scanId, {
        create_contact: createContact,
        create_biolink: createBiolink,
        full_name: form.full_name,
        first_name: form.first_name,
        last_name: form.last_name,
        title: form.title,
        company: form.company,
        tagline: form.tagline,
        description: form.description,
        website: form.website,
        address: form.address,
        emails: form.emails,
        phones: form.phones,
        socials: form.socials,
      });
    },
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ["contacts"] });
      if (res.biolink) {
        // Hand the seeded answers to the stateless mobile wizard.
        const params = new URLSearchParams({
          prefillCategory: res.biolink.category,
          prefillAnswers: JSON.stringify(res.biolink.answers ?? {}),
        });
        router.replace(`/links/wizard?${params.toString()}` as never);
        return;
      }
      Alert.alert("Saved", "Contact added from your scan.");
      router.replace("/contacts" as never);
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      Alert.alert("Couldn't save", e?.message ?? "Please try again.");
    },
  });

  if (q.isLoading) {
    return (
      <Centered colors={colors}>
        <ActivityIndicator color={colors.primary} />
      </Centered>
    );
  }
  if (q.isError || !q.data) {
    return (
      <Centered colors={colors}>
        <Text style={{ color: colors.destructive }}>
          Couldn&apos;t load that scan.
        </Text>
      </Centered>
    );
  }
  if (!form) {
    return (
      <Centered colors={colors}>
        <ActivityIndicator color={colors.primary} />
      </Centered>
    );
  }

  const scan = q.data.scan;
  const dupes = q.data.duplicates ?? [];
  const conf = form.confidence?.overall ?? 0;

  const set = (patch: Partial<CardScanExtracted>) =>
    setForm((f) => (f ? { ...f, ...patch } : f));

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen
        options={{
          title: "Review scan",
          headerStyle: { backgroundColor: colors.card },
          headerTitleStyle: {
            fontFamily: "SpaceGrotesk_600SemiBold",
            color: colors.foreground,
          },
          headerTintColor: colors.primary,
        }}
      />
      <ScrollView contentContainerStyle={styles.body}>
        {scan.source_images.length || scan.logo_url ? (
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={{ gap: 10 }}
          >
            {scan.logo_url ? (
              <Image
                source={{ uri: scan.logo_url }}
                style={styles.preview}
                contentFit="contain"
              />
            ) : null}
            {scan.source_images.map((u) => (
              <Image
                key={u}
                source={{ uri: u }}
                style={styles.preview}
                contentFit="cover"
              />
            ))}
          </ScrollView>
        ) : null}

        <View
          style={[
            styles.confRow,
            { backgroundColor: colors.primary + "12", borderRadius: colors.radius },
          ]}
        >
          <Feather name="zap" size={14} color={colors.primary} />
          <Text style={[styles.confText, { color: colors.foreground }]}>
            AI confidence {Math.round(conf * 100)}% · {scan.credits_spent} credit
            {scan.credits_spent === 1 ? "" : "s"} used
          </Text>
        </View>

        {dupes.length ? (
          <View
            style={[
              styles.dupeBox,
              {
                backgroundColor: colors.destructive + "12",
                borderColor: colors.destructive + "44",
                borderRadius: colors.radius,
              },
            ]}
          >
            <Text style={[styles.dupeTitle, { color: colors.foreground }]}>
              Possible duplicate
            </Text>
            {dupes.map((d: DuplicateHit, i) => (
              <Text
                key={`${d.type}-${i}`}
                style={[styles.dupeBody, { color: colors.mutedForeground }]}
              >
                {d.value} already on {d.contacts.map((c) => c.name).join(", ")}
              </Text>
            ))}
          </View>
        ) : null}

        <Section title="Person" colors={colors}>
          <TextField
            label="Full name"
            value={form.full_name ?? ""}
            onChangeText={(v) => set({ full_name: v })}
          />
          <TextField
            label="Job title"
            value={form.title ?? ""}
            onChangeText={(v) => set({ title: v })}
          />
        </Section>

        <Section title="Company" colors={colors}>
          <TextField
            label="Company"
            value={form.company ?? ""}
            onChangeText={(v) => set({ company: v })}
          />
          <TextField
            label="Tagline"
            value={form.tagline ?? ""}
            onChangeText={(v) => set({ tagline: v })}
          />
          <TextField
            label="Description"
            value={form.description ?? ""}
            onChangeText={(v) => set({ description: v })}
            multiline
            style={{ minHeight: 90, paddingTop: 14, textAlignVertical: "top" }}
          />
        </Section>

        <RowsSection
          title="Emails"
          colors={colors}
          rows={form.emails}
          placeholder="name@company.com"
          keyboardType="email-address"
          onChange={(emails) => set({ emails })}
        />

        <RowsSection
          title="Phones"
          colors={colors}
          rows={form.phones}
          placeholder="+1 555 000 1234"
          keyboardType="phone-pad"
          onChange={(phones) => set({ phones })}
        />

        <Section title="Web & location" colors={colors}>
          <TextField
            label="Website"
            value={form.website ?? ""}
            onChangeText={(v) => set({ website: v })}
            autoCapitalize="none"
            keyboardType="url"
          />
          <TextField
            label="Address"
            value={form.address ?? ""}
            onChangeText={(v) => set({ address: v })}
          />
        </Section>

        <Section title="Socials" colors={colors}>
          {SOCIAL_KEYS.map((k) => (
            <TextField
              key={k}
              label={k[0].toUpperCase() + k.slice(1)}
              value={form.socials?.[k] ?? ""}
              onChangeText={(v) =>
                set({ socials: { ...form.socials, [k]: v } })
              }
              autoCapitalize="none"
            />
          ))}
        </Section>

        <View style={{ gap: 10, marginTop: 4 }}>
          <Toggle
            colors={colors}
            label="Save as a contact"
            value={createContact}
            onToggle={() => setCreateContact((v) => !v)}
          />
          <Toggle
            colors={colors}
            label="Start a Link in Bio from this"
            value={createBiolink}
            onToggle={() => setCreateBiolink((v) => !v)}
          />
        </View>

        <Button
          label="Save"
          onPress={() => saveMut.mutate()}
          loading={saveMut.isPending}
          disabled={!createContact && !createBiolink}
        />
      </ScrollView>
    </View>
  );
}

function Centered({
  children,
  colors,
}: {
  children: React.ReactNode;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View
      style={{
        flex: 1,
        alignItems: "center",
        justifyContent: "center",
        backgroundColor: colors.background,
      }}
    >
      <Stack.Screen options={{ title: "Review scan" }} />
      {children}
    </View>
  );
}

function Section({
  title,
  colors,
  children,
}: {
  title: string;
  colors: ReturnType<typeof useColors>;
  children: React.ReactNode;
}) {
  return (
    <View style={{ gap: 12 }}>
      <Text style={[styles.sectionTitle, { color: colors.primary }]}>
        {title}
      </Text>
      {children}
    </View>
  );
}

function RowsSection({
  title,
  colors,
  rows,
  placeholder,
  keyboardType,
  onChange,
}: {
  title: string;
  colors: ReturnType<typeof useColors>;
  rows: CardScanContactRow[];
  placeholder: string;
  keyboardType: "email-address" | "phone-pad";
  onChange: (rows: CardScanContactRow[]) => void;
}) {
  const setVal = (idx: number, value: string) =>
    onChange(rows.map((r, i) => (i === idx ? { ...r, value } : r)));
  const remove = (idx: number) => onChange(rows.filter((_, i) => i !== idx));
  const add = () => onChange([...rows, { value: "", label: null }]);

  return (
    <View style={{ gap: 12 }}>
      <Text style={[styles.sectionTitle, { color: colors.primary }]}>
        {title}
      </Text>
      {rows.map((r, i) => (
        <View key={i} style={styles.rowWithRemove}>
          <View style={{ flex: 1 }}>
            <TextField
              label={r.label ?? undefined}
              value={r.value}
              onChangeText={(v) => setVal(i, v)}
              placeholder={placeholder}
              keyboardType={keyboardType}
              autoCapitalize="none"
            />
          </View>
          <Pressable onPress={() => remove(i)} hitSlop={8} style={styles.removeBtn}>
            <Feather name="trash-2" size={18} color={colors.mutedForeground} />
          </Pressable>
        </View>
      ))}
      <Pressable onPress={add} style={styles.addRow}>
        <Feather name="plus" size={14} color={colors.primary} />
        <Text style={[styles.addRowText, { color: colors.primary }]}>
          Add {title.toLowerCase().replace(/s$/, "")}
        </Text>
      </Pressable>
    </View>
  );
}

function Toggle({
  colors,
  label,
  value,
  onToggle,
}: {
  colors: ReturnType<typeof useColors>;
  label: string;
  value: boolean;
  onToggle: () => void;
}) {
  return (
    <Pressable
      onPress={onToggle}
      style={[
        styles.toggle,
        {
          backgroundColor: colors.card,
          borderColor: value ? colors.primary : colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      <View
        style={[
          styles.checkbox,
          {
            backgroundColor: value ? colors.primary : "transparent",
            borderColor: value ? colors.primary : colors.border,
          },
        ]}
      >
        {value ? (
          <Feather name="check" size={14} color={colors.primaryForeground} />
        ) : null}
      </View>
      <Text style={[styles.toggleLabel, { color: colors.foreground }]}>
        {label}
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  body: { padding: 20, gap: 18, paddingBottom: 48 },
  preview: { width: 120, height: 80, borderRadius: 10 },
  confRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  confText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  dupeBox: { padding: 12, borderWidth: 1, gap: 4 },
  dupeTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  dupeBody: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 12 },
  sectionTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 12,
    letterSpacing: 0.5,
    textTransform: "uppercase",
  },
  rowWithRemove: { flexDirection: "row", alignItems: "flex-end", gap: 8 },
  removeBtn: { paddingBottom: 14 },
  addRow: { flexDirection: "row", alignItems: "center", gap: 6 },
  addRowText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  toggle: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  checkbox: {
    width: 22,
    height: 22,
    borderRadius: 6,
    borderWidth: 1.5,
    alignItems: "center",
    justifyContent: "center",
  },
  toggleLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 15 },
});
