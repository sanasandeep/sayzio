import { useQuery } from "@tanstack/react-query";
import {
  Stack,
  useFocusEffect,
  useLocalSearchParams,
  useRouter,
} from "expo-router";
import { useCallback, useEffect, useState } from "react";
import {
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { DictationMic } from "@/components/DictationMic";
import { DomainPicker } from "@/components/DomainPicker";
import { TextField } from "@/components/TextField";
import { UpgradeLockBadge } from "@/components/UpgradeLockBadge";
import { setVoiceSurface } from "@/components/VoiceAssistant";
import { useColors } from "@/hooks/useColors";
import { usePlanFeatures } from "@/hooks/usePlanFeatures";
import { listAvailableDomains } from "@/lib/api/domains";
import { checkAlias, createLink, type AliasCheck } from "@/lib/api/links";
import { metaForKind, type LinkKind } from "@/lib/linkKinds";
import { PAID_PAGE_TEMPLATES } from "@/lib/paidPage";
import { handlePlanLockedError, showUpgradePrompt } from "@/lib/upgradePrompt";

export default function CreateLinkScreen() {
  const colors = useColors();
  const router = useRouter();
  const { kind } = useLocalSearchParams<{ kind: LinkKind }>();
  const meta = metaForKind((kind as LinkKind) || "url");
  const plan = usePlanFeatures();
  const locked = plan.isLinkTypeLocked(meta.apiType);

  const [title, setTitle] = useState("");
  const [alias, setAlias] = useState("");
  const [longUrl, setLongUrl] = useState("");
  // vCard
  const [vcFullName, setVcFullName] = useState("");
  const [vcOrg, setVcOrg] = useState("");
  const [vcEmail, setVcEmail] = useState("");
  const [vcPhone, setVcPhone] = useState("");
  // Calendar / event
  const [evStart, setEvStart] = useState("");
  const [evEnd, setEvEnd] = useState("");
  const [evLocation, setEvLocation] = useState("");
  // File
  const [fileUrl, setFileUrl] = useState("");
  const [fileName, setFileName] = useState("");
  // Paid Page
  const [paidTemplate, setPaidTemplate] = useState<string>("aurora");

  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Live "Custom URL availability" check — mobile parity for the web Create
  // Link page's debounced indicator. A blank alias is the auto-generate case
  // (status "empty"), so we surface it as a neutral hint, not an error.
  const [aliasCheck, setAliasCheck] = useState<AliasCheck | null>(null);
  const [aliasChecking, setAliasChecking] = useState(false);

  useEffect(() => {
    const trimmed = alias.trim();
    if (trimmed === "") {
      setAliasCheck(null);
      setAliasChecking(false);
      return;
    }
    setAliasChecking(true);
    let cancelled = false;
    const t = setTimeout(async () => {
      try {
        const res = await checkAlias(trimmed);
        if (!cancelled) setAliasCheck(res);
      } catch {
        // Network/transient errors leave the last known state; the create
        // submit still enforces the rules server-side regardless.
        if (!cancelled) setAliasCheck(null);
      } finally {
        if (!cancelled) setAliasChecking(false);
      }
    }, 450);
    return () => {
      cancelled = true;
      clearTimeout(t);
    };
  }, [alias]);

  // Tell the floating Voice Assistant that voice turns started while
  // this form is open should prefer the create-link tools.
  useFocusEffect(
    useCallback(() => {
      setVoiceSurface("create_link");
      return () => setVoiceSurface(null);
    }, []),
  );

  // Append a dictated chunk to whichever field's setter is passed in,
  // mirroring the web's per-field `l({ onText })` dictation factory.
  const dictateInto =
    (setter: React.Dispatch<React.SetStateAction<string>>) => (t: string) =>
      setter((v) => (v ? v.trim() + " " : "") + t);

  const domainsQ = useQuery({
    queryKey: ["domains-available"],
    queryFn: listAvailableDomains,
  });
  const [domainId, setDomainId] = useState<number | null>(null);
  const [domainTouched, setDomainTouched] = useState(false);

  // Pre-select the admin-chosen primary global domain once it loads,
  // unless the user has already picked one. Falls back to the env
  // default host (domainId === null) when no primary is configured.
  useEffect(() => {
    if (domainTouched) return;
    const primary = domainsQ.data?.primary_domain_id ?? null;
    if (primary !== null) setDomainId(primary);
  }, [domainsQ.data?.primary_domain_id, domainTouched]);

  async function onSubmit() {
    if (locked) {
      showUpgradePrompt({
        message: `${meta.label} isn't available on your current plan. Upgrade to unlock it.`,
      });
      return;
    }
    setError(null);
    setBusy(true);
    try {
      const settings: Record<string, unknown> = {};
      let payload: Parameters<typeof createLink>[0] = {
        type: meta.apiType,
        title: title || null,
        alias: alias || undefined,
        domain_id: domainId,
      };

      if (meta.kind === "url") {
        if (!longUrl) throw new Error("Please enter a destination URL");
        payload.long_url = longUrl;
      } else if (meta.kind === "biolink") {
        // biolink uses no long_url; blocks built next
      } else if (meta.kind === "ai_chat") {
        // ai_chat uses no long_url; persona/greeting configured next
      } else if (meta.kind === "file") {
        if (!fileUrl) throw new Error("Please enter the file URL");
        payload.long_url = fileUrl;
        settings.file = { url: fileUrl, filename: fileName || null };
      } else if (meta.kind === "vcard") {
        if (!vcFullName) throw new Error("Please enter a name for the vCard");
        settings.vcard = {
          full_name: vcFullName,
          organization: vcOrg || null,
          email: vcEmail || null,
          phone: vcPhone || null,
        };
      } else if (meta.kind === "calendar") {
        if (!evStart) throw new Error("Please enter a start time");
        settings.event = {
          start: evStart,
          end: evEnd || null,
          location: evLocation || null,
        };
      } else if (meta.kind === "paid_page") {
        settings.paid_page = { template: paidTemplate };
      }

      payload.settings = settings;
      const link = await createLink(payload);

      if (meta.kind === "biolink") {
        router.replace(`/links/${link.id}/blocks` as any);
      } else if (meta.kind === "ai_chat") {
        router.replace(`/links/${link.id}/ai-chat` as any);
      } else if (meta.kind === "resume") {
        // Resume / Portfolio links bridge to the native resume builder.
        // Land on the generic link editor (which now surfaces an
        // "Edit resume" action), then open the native editor so the
        // user can start filling in content right away.
        router.replace(`/links/${link.id}/edit` as any);
        router.push("/resume" as any);
      } else {
        router.replace(`/links/${link.id}/edit` as any);
      }
    } catch (e: any) {
      if (handlePlanLockedError(e)) {
        setError(null);
      } else {
        setError(e?.message || "Failed to create link");
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: `New ${meta.label}` }} />
      <ScrollView contentContainerStyle={styles.body}>
        <Text style={[styles.blurb, { color: colors.mutedForeground }]}>
          {meta.blurb}
        </Text>

        {locked ? (
          <Pressable
            onPress={() =>
              showUpgradePrompt({
                message: `${meta.label} isn't available on your current plan. Upgrade to unlock it.`,
              })
            }
            style={[
              styles.lockBanner,
              {
                backgroundColor: colors.primary + "12",
                borderColor: colors.primary + "44",
                borderRadius: colors.radius,
              },
            ]}
          >
            <View style={{ flex: 1, gap: 2 }}>
              <Text style={[styles.lockTitle, { color: colors.foreground }]}>
                {meta.label} is a plan feature
              </Text>
              <Text style={[styles.lockBody, { color: colors.mutedForeground }]}>
                Upgrade your plan to create this. Tap to see your options.
              </Text>
            </View>
            <UpgradeLockBadge />
          </Pressable>
        ) : null}

        <TextField
          label="Title"
          value={title}
          onChangeText={setTitle}
          placeholder="Optional internal label"
          trailing={<DictationMic onText={dictateInto(setTitle)} />}
        />
        <TextField
          label="Custom alias"
          value={alias}
          onChangeText={setAlias}
          placeholder="leave blank to auto-generate"
          autoCapitalize="none"
          autoCorrect={false}
          trailing={<DictationMic onText={dictateInto(setAlias)} />}
        />
        {alias.trim() !== "" ? (
          <Text
            style={[
              styles.aliasStatus,
              {
                color: aliasChecking
                  ? colors.mutedForeground
                  : aliasCheck?.available
                    ? colors.success
                    : aliasCheck
                      ? colors.destructive
                      : colors.mutedForeground,
              },
            ]}
          >
            {aliasChecking
              ? "Checking availability…"
              : aliasCheck
                ? `${aliasCheck.available ? "✓" : "✕"} ${aliasCheck.message}`
                : ""}
          </Text>
        ) : null}

        <DomainPicker
          value={domainId}
          onChange={(id) => {
            setDomainTouched(true);
            setDomainId(id);
          }}
          data={domainsQ.data}
          loading={domainsQ.isLoading}
        />

        {meta.kind === "url" ? (
          <TextField
            label="Destination URL"
            value={longUrl}
            onChangeText={setLongUrl}
            keyboardType="url"
            autoCapitalize="none"
            placeholder="https://example.com/very/long/path"
          />
        ) : null}

        {meta.kind === "file" ? (
          <>
            <TextField
              label="File URL"
              value={fileUrl}
              onChangeText={setFileUrl}
              autoCapitalize="none"
              placeholder="https://your.cdn/file.pdf"
            />
            <TextField
              label="File name"
              value={fileName}
              onChangeText={setFileName}
              placeholder="report.pdf"
              trailing={<DictationMic onText={dictateInto(setFileName)} />}
            />
          </>
        ) : null}

        {meta.kind === "vcard" ? (
          <>
            <TextField
              label="Full name"
              value={vcFullName}
              onChangeText={setVcFullName}
              placeholder="Jane Doe"
              trailing={<DictationMic onText={dictateInto(setVcFullName)} />}
            />
            <TextField
              label="Organization"
              value={vcOrg}
              onChangeText={setVcOrg}
              placeholder="Acme Inc."
              trailing={<DictationMic onText={dictateInto(setVcOrg)} />}
            />
            <TextField
              label="Email"
              value={vcEmail}
              onChangeText={setVcEmail}
              keyboardType="email-address"
              autoCapitalize="none"
              placeholder="jane@acme.com"
            />
            <TextField
              label="Phone"
              value={vcPhone}
              onChangeText={setVcPhone}
              keyboardType="phone-pad"
              placeholder="+1 555 0100"
            />
          </>
        ) : null}

        {meta.kind === "calendar" ? (
          <>
            <TextField
              label="Starts (ISO 8601)"
              value={evStart}
              onChangeText={setEvStart}
              autoCapitalize="none"
              placeholder="2025-12-31T18:00:00Z"
            />
            <TextField
              label="Ends (ISO 8601)"
              value={evEnd}
              onChangeText={setEvEnd}
              autoCapitalize="none"
              placeholder="2025-12-31T20:00:00Z"
            />
            <TextField
              label="Location"
              value={evLocation}
              onChangeText={setEvLocation}
              placeholder="123 Main St"
              trailing={<DictationMic onText={dictateInto(setEvLocation)} />}
            />
          </>
        ) : null}

        {meta.kind === "paid_page" ? (
          <View style={{ gap: 10 }}>
            <Text style={[styles.pickLabel, { color: colors.mutedForeground }]}>
              Choose a starting template
            </Text>
            <View style={styles.tplGrid}>
              {PAID_PAGE_TEMPLATES.map((t) => {
                const active = paidTemplate === t.id;
                return (
                  <Pressable
                    key={t.id}
                    onPress={() => setPaidTemplate(t.id)}
                    style={[
                      styles.tplCard,
                      {
                        borderColor: active ? colors.primary : colors.border,
                        borderWidth: active ? 2 : 1,
                      },
                    ]}
                  >
                    <View
                      style={[styles.tplSwatch, { backgroundColor: t.swatch }]}
                    />
                    <Text style={[styles.tplName, { color: colors.foreground }]}>
                      {t.name}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
            <Text style={[styles.tplHint, { color: colors.mutedForeground }]}>
              Your posts and tiers appear on this page automatically — there's
              no linking step. You can change the template and toggle public /
              gated access anytime from the web editor.
            </Text>
          </View>
        ) : null}

        {error ? (
          <Text style={{ color: colors.destructive }}>{error}</Text>
        ) : null}

        <Button
          label={
            meta.kind === "biolink" || meta.kind === "ai_chat"
              ? "Create & open editor"
              : "Create link"
          }
          onPress={onSubmit}
          loading={busy}
        />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  body: { padding: 20, gap: 14, paddingBottom: 40 },
  aliasStatus: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    marginTop: -8,
  },
  blurb: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 20 },
  lockBanner: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  lockTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
  lockBody: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12,
    lineHeight: 16,
  },
  pickLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  tplGrid: { flexDirection: "row", flexWrap: "wrap", gap: 10 },
  tplCard: {
    width: "30%",
    borderRadius: 14,
    padding: 8,
    alignItems: "center",
    gap: 6,
  },
  tplSwatch: { width: "100%", height: 36, borderRadius: 8 },
  tplName: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11 },
  tplHint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 11,
    lineHeight: 16,
  },
});
