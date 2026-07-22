import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { router, Stack } from "expo-router";
import { useEffect, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { Card } from "@/components/Card";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  confirmBrandStudioKit,
  deleteBrandStudioKit,
  deleteBrandStudioPreset,
  estimateBrandStudio,
  getBrandStudio,
  getBrandStudioKit,
  planBrandStudio,
  renameBrandStudioPreset,
  saveBrandStudioPreset,
  type BrandStudioAssetKind,
  type BrandStudioCompositionRow,
  type BrandStudioIndex,
  type BrandStudioKitDetail,
  type BrandStudioKitShow,
  type BrandStudioKitSummary,
  type BrandStudioPlanInput,
} from "@/lib/api/brandStudio";
import { handlePlanLockedError, showUpgradePrompt } from "@/lib/upgradePrompt";
import { showAlert } from "@/lib/webAlert";

/**
 * Mobile parity for the web "AI Brand Studio" feature (Task #5551,
 * /user/brand-studio). One plain-language brief (plus a saved Brand Kit or
 * inline brand details) becomes a structured multi-asset plan - a Link in Bio
 * page, short links, QR codes, a form and a digital card - reviewed asset by
 * asset before anything is created. Planning is charged in coins with
 * auto-refund on failure; confirming is free and enforces per-type plan caps
 * server-side (handled in AiBrandStudioService).
 */

const KIND_META: Record<BrandStudioAssetKind, { icon: any; label: string }> = {
  biolink: { icon: "user", label: "Link in Bio" },
  short_link: { icon: "link", label: "Short link" },
  qr_code: { icon: "grid", label: "QR code" },
  form: { icon: "list", label: "Form" },
  vcard: { icon: "credit-card", label: "Digital card" },
};

// One-click combo presets for the kit composer (Task #5570). Mirrors the web
// presets in resources/views/user/brand-studio/index.blade.php.
const COMPOSITION_PRESETS: { label: string; rows: BrandStudioCompositionRow[] }[] = [
  {
    label: "Product + sales + card",
    rows: [
      { kind: "biolink", count: 1, purpose: "Product page" },
      { kind: "biolink", count: 1, purpose: "Sales offer page" },
      { kind: "vcard", count: 1, purpose: "Digital business card" },
    ],
  },
  {
    label: "Launch pack",
    rows: [
      { kind: "biolink", count: 1, purpose: "Launch landing page" },
      { kind: "short_link", count: 3, purpose: "Campaign links" },
      { kind: "qr_code", count: 2, purpose: "Poster QR codes" },
    ],
  },
  {
    label: "Lead-gen pack",
    rows: [
      { kind: "biolink", count: 1, purpose: "Lead capture page" },
      { kind: "form", count: 1, purpose: "Lead form" },
    ],
  },
  {
    label: "Personal brand",
    rows: [
      { kind: "biolink", count: 1, purpose: "Personal bio page" },
      { kind: "vcard", count: 1, purpose: "Digital card" },
      { kind: "qr_code", count: 1, purpose: "Share-me QR code" },
    ],
  },
];

const DEFAULT_KIT_CAPS: Record<BrandStudioAssetKind, number> = {
  biolink: 3,
  short_link: 10,
  qr_code: 10,
  form: 3,
  vcard: 2,
};

const BULK_KINDS: { kind: BrandStudioAssetKind; label: string }[] = [
  { kind: "short_link", label: "Short links" },
  { kind: "qr_code", label: "QR codes" },
  { kind: "biolink", label: "Bio pages" },
  { kind: "form", label: "Forms" },
  { kind: "vcard", label: "Cards" },
];

export default function BrandStudioScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const [brief, setBrief] = useState("");
  const [mode, setMode] = useState<"kit" | "bulk">("kit");
  const [bulkKind, setBulkKind] = useState<BrandStudioAssetKind>("short_link");
  const [bulkCount, setBulkCount] = useState("5");
  const [composition, setComposition] = useState<BrandStudioCompositionRow[]>([]);
  const [brandKitId, setBrandKitId] = useState<number | null>(null);
  const [brandName, setBrandName] = useState("");
  const [brandColors, setBrandColors] = useState("");
  const [brandVoice, setBrandVoice] = useState("");
  const [estimate, setEstimate] = useState<number | null>(null);
  const [estBalance, setEstBalance] = useState<number | null>(null);
  const [openKitId, setOpenKitId] = useState<number | null>(null);
  const [dropped, setDropped] = useState<number[]>([]);
  const [savingPreset, setSavingPreset] = useState(false);
  const [presetName, setPresetName] = useState("");
  const [renamingPreset, setRenamingPreset] = useState<{
    id: number;
    label: string;
  } | null>(null);
  const [renameName, setRenameName] = useState("");

  const query = useQuery<BrandStudioIndex>({
    queryKey: ["brand-studio"],
    queryFn: getBrandStudio,
  });
  const data = query.data;

  const detailQuery = useQuery<BrandStudioKitShow>({
    queryKey: ["brand-studio-kit", openKitId],
    queryFn: () => getBrandStudioKit(openKitId!),
    enabled: openKitId != null,
  });
  const detail = detailQuery.data?.kit;
  const detailBalance = detailQuery.data?.balance ?? null;
  const detailLowThreshold = detailQuery.data?.low_balance_threshold ?? 0;
  const detailLowBalance =
    detailBalance != null && detailBalance <= detailLowThreshold;

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["brand-studio"] });
    if (openKitId != null) {
      qc.invalidateQueries({ queryKey: ["brand-studio-kit", openKitId] });
    }
  };

  const planInput = (): BrandStudioPlanInput => ({
    request: brief.trim(),
    mode,
    bulk_kind: mode === "bulk" ? bulkKind : null,
    bulk_count: mode === "bulk" ? Math.max(1, parseInt(bulkCount, 10) || 1) : null,
    composition: mode === "kit" && composition.length ? composition : null,
    brand_kit_id: brandKitId,
    brand_name: brandName.trim(),
    brand_colors: brandColors.trim(),
    brand_voice: brandVoice.trim(),
  });

  const estimateMut = useMutation({
    mutationFn: (_vars?: { silent?: boolean }) =>
      estimateBrandStudio(planInput()),
    onSuccess: (r) => {
      setEstimate(r.estimated_credits);
      setEstBalance(r.balance);
    },
    onError: (e: any, vars) => {
      if (vars?.silent) return;
      if (handlePlanLockedError(e)) return;
      showAlert("Couldn't estimate", e?.message ?? "Please try again.");
    },
  });

  // Live cost preview: re-estimate (debounced) whenever the brief, mode,
  // bulk settings or brand context change, so the credit cost and the
  // low-balance warning stay accurate before the user commits credits.
  const canEstimate = !!data?.available && !!data?.ai_enabled;
  const kitCaps = data?.kit_caps ?? DEFAULT_KIT_CAPS;
  const compositionError = (() => {
    if (mode !== "kit") return "";
    const sums: Partial<Record<BrandStudioAssetKind, number>> = {};
    for (const r of composition) {
      sums[r.kind] = (sums[r.kind] ?? 0) + Math.max(1, r.count);
      const cap = kitCaps[r.kind] ?? 0;
      if ((sums[r.kind] ?? 0) > cap) {
        return `Too many ${KIND_META[r.kind]?.label ?? r.kind}s: max ${cap} per kit.`;
      }
    }
    return "";
  })();
  const canGenerate =
    mode === "kit" && composition.length
      ? !compositionError
      : !!brief.trim();
  useEffect(() => {
    setEstimate(null);
    if (!canEstimate || !canGenerate) return;
    const t = setTimeout(() => estimateMut.mutate({ silent: true }), 700);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    canEstimate,
    canGenerate,
    brief,
    mode,
    bulkKind,
    bulkCount,
    composition,
    brandKitId,
    brandName,
    brandColors,
    brandVoice,
  ]);

  const updateRow = (i: number, patch: Partial<BrandStudioCompositionRow>) =>
    setComposition((rows) =>
      rows.map((r, idx) => (idx === i ? { ...r, ...patch } : r)),
    );

  const bulkCap = data?.bulk_cap ?? -1;
  const bulkVariants = (() => {
    let n = Math.max(1, parseInt(bulkCount, 10) || 1);
    if (bulkCap > 0) n = Math.min(n, bulkCap);
    return n;
  })();
  const perVariantCredits =
    estimate != null ? Math.max(1, Math.round(estimate / bulkVariants)) : null;
  const availableCredits = estBalance ?? data?.balance ?? 0;
  const lowBalance = estimate != null && estimate > availableCredits;

  const planMut = useMutation({
    mutationFn: () => planBrandStudio(planInput()),
    onSuccess: (r) => {
      setEstimate(null);
      setDropped([]);
      invalidate();
      qc.setQueryData(["brand-studio-kit", r.kit.id], r.kit);
      setOpenKitId(r.kit.id);
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      showAlert("Couldn't plan", e?.message ?? "Please try again.");
    },
  });

  const confirmMut = useMutation({
    mutationFn: (vars: { id: number; keep: number[] }) =>
      confirmBrandStudioKit(vars.id, vars.keep),
    onSuccess: (r) => {
      invalidate();
      qc.setQueryData(["brand-studio-kit", r.kit.id], r.kit);
      showAlert(
        "Assets created",
        `${r.created} asset${r.created === 1 ? "" : "s"} created.` +
          (r.skipped.length ? ` ${r.skipped.length} skipped.` : ""),
      );
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      showAlert("Couldn't create", e?.message ?? "Please try again.");
    },
  });

  // Saved kit combos (Task #5577): persist the current composition as a
  // reusable preset shown alongside the built-in ones.
  const savePresetMut = useMutation({
    mutationFn: (vars: { name: string; rows: BrandStudioCompositionRow[] }) =>
      saveBrandStudioPreset(vars.name, vars.rows),
    onSuccess: () => {
      setSavingPreset(false);
      setPresetName("");
      qc.invalidateQueries({ queryKey: ["brand-studio"] });
    },
    onError: (e: any) =>
      showAlert("Couldn't save combo", e?.message ?? "Please try again."),
  });

  const deletePresetMut = useMutation({
    mutationFn: (id: number) => deleteBrandStudioPreset(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["brand-studio"] }),
    onError: (e: any) =>
      showAlert("Couldn't delete combo", e?.message ?? "Please try again."),
  });

  // Rename a saved combo in place (Task #5580) — long-press a saved chip to
  // pick Rename or Delete; rename edits the name inline via a small form.
  const renamePresetMut = useMutation({
    mutationFn: (vars: { id: number; name: string }) =>
      renameBrandStudioPreset(vars.id, vars.name),
    onSuccess: () => {
      setRenamingPreset(null);
      setRenameName("");
      qc.invalidateQueries({ queryKey: ["brand-studio"] });
    },
    onError: (e: any) =>
      showAlert("Couldn't rename combo", e?.message ?? "Please try again."),
  });

  const startRenamePreset = (p: { id: number; label: string }) => {
    setRenamingPreset(p);
    setRenameName(p.label);
  };

  const confirmDeletePreset = (p: { id: number; label: string }) => {
    showAlert(`Delete “${p.label}”?`, "This saved combo will be removed.", [
      { text: "Cancel", style: "cancel" },
      {
        text: "Delete",
        style: "destructive",
        onPress: () => deletePresetMut.mutate(p.id),
      },
    ]);
  };

  const presetActionsSheet = (p: { id: number; label: string }) => {
    showAlert(`“${p.label}”`, "What would you like to do with this saved combo?", [
      { text: "Cancel", style: "cancel" },
      { text: "Rename", onPress: () => startRenamePreset(p) },
      {
        text: "Delete",
        style: "destructive",
        onPress: () => confirmDeletePreset(p),
      },
    ]);
  };

  const deleteMut = useMutation({
    mutationFn: (id: number) => deleteBrandStudioKit(id),
    onSuccess: () => {
      setOpenKitId(null);
      invalidate();
    },
    onError: (e: any) =>
      showAlert("Couldn't delete", e?.message ?? "Please try again."),
  });

  const toggleDrop = (i: number) =>
    setDropped((d) => (d.includes(i) ? d.filter((x) => x !== i) : [...d, i]));

  const confirmKit = (kit: BrandStudioKitDetail) => {
    const keep = kit.proposal.assets
      .map((_, i) => i)
      .filter((i) => !dropped.includes(i));
    if (!keep.length) {
      showAlert("Nothing selected", "Keep at least one asset to create.");
      return;
    }
    confirmMut.mutate({ id: kit.id, keep });
  };

  const confirmDelete = (kit: BrandStudioKitSummary | BrandStudioKitDetail) => {
    showAlert("Delete this kit record?", "Created assets are kept.", [
      { text: "Cancel", style: "cancel" },
      {
        text: "Delete",
        style: "destructive",
        onPress: () => deleteMut.mutate(kit.id),
      },
    ]);
  };

  return (
    <View style={[styles.flex, { backgroundColor: colors.background }]}>
      <Stack.Screen options={{ title: "AI Brand Studio" }} />
      <ScrollView contentContainerStyle={styles.content}>
        {query.isLoading ? (
          <View style={styles.center}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : query.isError ? (
          <Card style={styles.card}>
            <Text style={[styles.body, { color: colors.foreground }]}>
              Couldn't load AI Brand Studio.
            </Text>
            <Button label="Retry" variant="outline" onPress={() => query.refetch()} />
          </Card>
        ) : openKitId != null ? (
          // ── Kit detail: proposal review or results ──────────────────
          <>
            <Pressable onPress={() => setOpenKitId(null)} style={styles.backRow}>
              <Feather name="arrow-left" size={16} color={colors.mutedForeground} />
              <Text style={[styles.link, { color: colors.mutedForeground }]}>
                Back to AI Brand Studio
              </Text>
            </Pressable>
            {detailQuery.isLoading || !detail ? (
              <View style={styles.center}>
                <ActivityIndicator color={colors.primary} />
              </View>
            ) : (
              <>
                <Text style={[styles.title, { color: colors.foreground }]}>
                  {detail.name}
                </Text>
                <Text style={[styles.subtitle, { color: colors.mutedForeground }]}>
                  {detail.status === "created"
                    ? "These assets have been created."
                    : "Review the plan below. Tap an asset to keep or drop it - nothing is created until you confirm."}
                </Text>
                {detail.request ? (
                  <Card style={styles.card}>
                    <Text style={[styles.small, { color: colors.mutedForeground }]}>
                      Your brief
                    </Text>
                    <Text style={[styles.body, { color: colors.foreground }]}>
                      {detail.request}
                    </Text>
                  </Card>
                ) : null}

                {detail.status === "created" ? (
                  <>
                    {detail.results.assets.map((a, i) => {
                      const meta = KIND_META[a.kind] ?? {
                        icon: "box",
                        label: a.kind,
                      };
                      return (
                        <Card key={`${a.kind}-${a.id}-${i}`} style={styles.card}>
                          <View style={styles.row}>
                            <Feather name={meta.icon} size={18} color={colors.primary} />
                            <View style={styles.flex}>
                              <Text style={[styles.body, { color: colors.foreground }]}>
                                {a.title || a.name || meta.label}
                              </Text>
                              <Text style={[styles.small, { color: colors.mutedForeground }]}>
                                {meta.label}
                                {a.alias ? ` · /${a.alias}` : ""}
                                {a.purpose ? ` · ${a.purpose}` : ""}
                              </Text>
                            </View>
                            <Feather name="check-circle" size={18} color={colors.success} />
                          </View>
                        </Card>
                      );
                    })}
                    {detail.results.skipped.map((msg, i) => (
                      <Card key={`skip-${i}`} style={styles.card}>
                        <View style={styles.row}>
                          <Feather name="alert-triangle" size={16} color={colors.warning} />
                          <Text style={[styles.small, { color: colors.mutedForeground, flex: 1 }]}>
                            {msg}
                          </Text>
                        </View>
                      </Card>
                    ))}
                  </>
                ) : (
                  <>
                    {detailBalance != null ? (
                      <Card style={styles.card}>
                        <View style={styles.row}>
                          <View style={styles.flex}>
                            <Text style={[styles.small, { color: colors.mutedForeground }]}>
                              Coins spent on this plan
                            </Text>
                            <Text style={[styles.body, { color: colors.foreground }]}>
                              {detail.credits_spent}
                            </Text>
                          </View>
                          <View style={styles.flex}>
                            <Text style={[styles.small, { color: colors.mutedForeground }]}>
                              Your coin balance
                            </Text>
                            <Text
                              style={[
                                styles.body,
                                {
                                  color: detailLowBalance
                                    ? colors.warning
                                    : colors.foreground,
                                },
                              ]}
                            >
                              {detailBalance}
                            </Text>
                          </View>
                        </View>
                        <Text style={[styles.small, { color: colors.mutedForeground }]}>
                          Creating the selected assets is free; the plan is
                          already paid for.
                        </Text>
                        {detailLowBalance ? (
                          <View style={styles.notice}>
                            <Feather
                              name="alert-triangle"
                              size={16}
                              color={colors.warning}
                            />
                            <View style={styles.flex}>
                              <Text style={[styles.small, { color: colors.mutedForeground }]}>
                                {detailBalance <= 0
                                  ? "You're out of coins, so future AI runs (like re-planning after edits) will fail until you top up."
                                  : "Your coin balance is running low, so future AI runs (like re-planning after edits) may not go through."}
                              </Text>
                              <Pressable onPress={() => router.push("/coin-packages")}>
                                <Text style={[styles.link, { color: colors.primary }]}>
                                  Top up coins
                                </Text>
                              </Pressable>
                            </View>
                          </View>
                        ) : null}
                      </Card>
                    ) : null}
                    {detail.proposal.assets.map((a, i) => {
                      const meta = KIND_META[a.kind] ?? {
                        icon: "box",
                        label: a.kind,
                      };
                      const off = dropped.includes(i);
                      return (
                        <Pressable key={i} onPress={() => toggleDrop(i)}>
                          <Card style={[styles.card, off && styles.cardOff]}>
                            <View style={styles.row}>
                              <Feather
                                name={off ? "square" : "check-square"}
                                size={18}
                                color={off ? colors.mutedForeground : colors.primary}
                              />
                              <View style={styles.flex}>
                                <Text style={[styles.body, { color: colors.foreground }]}>
                                  {a.title || a.name || meta.label}
                                </Text>
                                <Text style={[styles.small, { color: colors.mutedForeground }]}>
                                  {meta.label}
                                  {a.purpose ? ` · ${a.purpose}` : ""}
                                  {a.url ? ` · ${a.url}` : ""}
                                  {a.kind === "biolink" && a.blocks
                                    ? ` · ${a.blocks.length} blocks`
                                    : ""}
                                  {a.kind === "form" && a.template
                                    ? ` · ${a.template}`
                                    : ""}
                                </Text>
                              </View>
                              <Feather name={meta.icon} size={16} color={colors.mutedForeground} />
                            </View>
                          </Card>
                        </Pressable>
                      );
                    })}
                    <Button
                      label={
                        confirmMut.isPending
                          ? "Creating…"
                          : "Create selected assets"
                      }
                      disabled={confirmMut.isPending}
                      onPress={() => confirmKit(detail)}
                    />
                  </>
                )}
                <Button
                  label="Delete kit record"
                  variant="outline"
                  onPress={() => confirmDelete(detail)}
                />
              </>
            )}
          </>
        ) : (
          // ── Studio home: brief form + past kits ─────────────────────
          <>
            <Text style={[styles.title, { color: colors.foreground }]}>
              AI Brand Studio
            </Text>
            <Text style={[styles.subtitle, { color: colors.mutedForeground }]}>
              Describe what you need and get a whole on-brand asset kit - a bio
              page, short links, QR codes, a form and a digital card - planned
              by AI and reviewed by you before anything is created.
            </Text>

            {!data?.available ? (
              <Card style={styles.card}>
                <View style={styles.notice}>
                  <Feather name="lock" size={16} color={colors.warning} />
                  <View style={styles.flex}>
                    <Text style={[styles.body, { color: colors.foreground }]}>
                      AI Brand Studio isn't included in your plan.
                    </Text>
                    <Pressable
                      onPress={() =>
                        showUpgradePrompt({
                          message:
                            "Upgrade to turn one brief into a complete set of on-brand assets.",
                        })
                      }
                    >
                      <Text style={[styles.link, { color: colors.primary }]}>
                        See upgrade options
                      </Text>
                    </Pressable>
                  </View>
                </View>
              </Card>
            ) : !data.ai_enabled ? (
              <Card style={styles.card}>
                <View style={styles.notice}>
                  <Feather name="info" size={16} color={colors.mutedForeground} />
                  <Text style={[styles.body, { color: colors.mutedForeground }]}>
                    AI features are currently unavailable. Please try again later.
                  </Text>
                </View>
              </Card>
            ) : (
              <Card style={styles.card}>
                {data.brand_kits.length ? (
                  <>
                    <Text style={[styles.label, { color: colors.foreground }]}>
                      Brand context
                    </Text>
                    <View style={styles.chips}>
                      <Pressable
                        onPress={() => setBrandKitId(null)}
                        style={[
                          styles.chip,
                          {
                            borderColor:
                              brandKitId == null ? colors.primary : colors.border,
                          },
                        ]}
                      >
                        <Text
                          style={[
                            styles.small,
                            {
                              color:
                                brandKitId == null
                                  ? colors.primary
                                  : colors.mutedForeground,
                            },
                          ]}
                        >
                          Describe below
                        </Text>
                      </Pressable>
                      {data.brand_kits.map((bk) => (
                        <Pressable
                          key={bk.id}
                          onPress={() => setBrandKitId(bk.id)}
                          style={[
                            styles.chip,
                            {
                              borderColor:
                                brandKitId === bk.id
                                  ? colors.primary
                                  : colors.border,
                            },
                          ]}
                        >
                          <Text
                            style={[
                              styles.small,
                              {
                                color:
                                  brandKitId === bk.id
                                    ? colors.primary
                                    : colors.mutedForeground,
                              },
                            ]}
                          >
                            {bk.name}
                          </Text>
                        </Pressable>
                      ))}
                    </View>
                  </>
                ) : null}

                {brandKitId == null ? (
                  <>
                    <TextField
                      label="Brand name"
                      value={brandName}
                      onChangeText={setBrandName}
                      placeholder="e.g. Nova Coffee"
                    />
                    <TextField
                      label="Brand colors"
                      value={brandColors}
                      onChangeText={setBrandColors}
                      placeholder="e.g. #0f172a and gold"
                    />
                    <TextField
                      label="Voice & tone"
                      value={brandVoice}
                      onChangeText={setBrandVoice}
                      placeholder="e.g. playful, expert, minimal"
                    />
                  </>
                ) : null}

                <TextField
                  label="What do you want to create?"
                  value={brief}
                  onChangeText={setBrief}
                  placeholder="e.g. Launching our summer sale - I need a bio page, short links, QR codes for posters and a lead form."
                  multiline
                />

                <Text style={[styles.label, { color: colors.foreground }]}>Mode</Text>
                <View style={styles.chips}>
                  <Pressable
                    onPress={() => setMode("kit")}
                    style={[
                      styles.chip,
                      { borderColor: mode === "kit" ? colors.primary : colors.border },
                    ]}
                  >
                    <Text
                      style={[
                        styles.small,
                        { color: mode === "kit" ? colors.primary : colors.mutedForeground },
                      ]}
                    >
                      Full kit
                    </Text>
                  </Pressable>
                  <Pressable
                    onPress={() => setMode("bulk")}
                    style={[
                      styles.chip,
                      { borderColor: mode === "bulk" ? colors.primary : colors.border },
                    ]}
                  >
                    <Text
                      style={[
                        styles.small,
                        { color: mode === "bulk" ? colors.primary : colors.mutedForeground },
                      ]}
                    >
                      Bulk variations
                    </Text>
                  </Pressable>
                </View>

                {mode === "bulk" ? (
                  <>
                    <View style={styles.chips}>
                      {BULK_KINDS.map((b) => (
                        <Pressable
                          key={b.kind}
                          onPress={() => setBulkKind(b.kind)}
                          style={[
                            styles.chip,
                            {
                              borderColor:
                                bulkKind === b.kind ? colors.primary : colors.border,
                            },
                          ]}
                        >
                          <Text
                            style={[
                              styles.small,
                              {
                                color:
                                  bulkKind === b.kind
                                    ? colors.primary
                                    : colors.mutedForeground,
                              },
                            ]}
                          >
                            {b.label}
                          </Text>
                        </Pressable>
                      ))}
                    </View>
                    <TextField
                      label={`How many? (max ${data.bulk_cap === -1 ? "50" : data.bulk_cap} per run)`}
                      value={bulkCount}
                      onChangeText={setBulkCount}
                      keyboardType="number-pad"
                    />
                  </>
                ) : null}

                {mode === "kit" ? (
                  <>
                    <Text style={[styles.label, { color: colors.foreground }]}>
                      Pick exactly what to create (optional)
                    </Text>
                    <Text style={[styles.small, { color: colors.mutedForeground }]}>
                      Leave empty to let the AI decide from your brief, or lock
                      in an exact composition below.
                    </Text>
                    <View style={styles.chips}>
                      {COMPOSITION_PRESETS.map((p) => (
                        <Pressable
                          key={p.label}
                          onPress={() =>
                            setComposition(p.rows.map((r) => ({ ...r })))
                          }
                          style={[styles.chip, { borderColor: colors.border }]}
                        >
                          <Text
                            style={[styles.small, { color: colors.mutedForeground }]}
                          >
                            {p.label}
                          </Text>
                        </Pressable>
                      ))}
                      {(data.saved_presets ?? []).map((p) => (
                        <Pressable
                          key={`saved-${p.id}`}
                          onPress={() =>
                            setComposition(p.rows.map((r) => ({ ...r })))
                          }
                          onLongPress={() => presetActionsSheet(p)}
                          style={[styles.chip, styles.savedChip, { borderColor: colors.primary }]}
                        >
                          <Feather
                            name="bookmark"
                            size={12}
                            color={colors.primary}
                          />
                          <Text style={[styles.small, { color: colors.primary }]}>
                            {p.label}
                          </Text>
                          <Pressable
                            onPress={() => confirmDeletePreset(p)}
                            hitSlop={8}
                          >
                            <Feather
                              name="x"
                              size={12}
                              color={colors.mutedForeground}
                            />
                          </Pressable>
                        </Pressable>
                      ))}
                    </View>
                    {renamingPreset ? (
                      <>
                        <TextField
                          label={`Rename “${renamingPreset.label}”`}
                          value={renameName}
                          onChangeText={(t) => setRenameName(t.slice(0, 60))}
                          placeholder="Combo name"
                        />
                        <View style={styles.row}>
                          <Button
                            label={
                              renamePresetMut.isPending ? "Renaming…" : "Rename"
                            }
                            disabled={
                              renamePresetMut.isPending || !renameName.trim()
                            }
                            onPress={() =>
                              renamePresetMut.mutate({
                                id: renamingPreset.id,
                                name: renameName.trim(),
                              })
                            }
                          />
                          <Pressable
                            onPress={() => {
                              setRenamingPreset(null);
                              setRenameName("");
                            }}
                          >
                            <Text
                              style={[styles.link, { color: colors.mutedForeground }]}
                            >
                              Cancel
                            </Text>
                          </Pressable>
                        </View>
                      </>
                    ) : null}
                    {composition.map((row, i) => (
                      <View key={i} style={styles.compRow}>
                        <View style={styles.chips}>
                          {BULK_KINDS.map((b) => (
                            <Pressable
                              key={b.kind}
                              onPress={() => updateRow(i, { kind: b.kind })}
                              style={[
                                styles.chip,
                                {
                                  borderColor:
                                    row.kind === b.kind
                                      ? colors.primary
                                      : colors.border,
                                },
                              ]}
                            >
                              <Text
                                style={[
                                  styles.small,
                                  {
                                    color:
                                      row.kind === b.kind
                                        ? colors.primary
                                        : colors.mutedForeground,
                                  },
                                ]}
                              >
                                {b.label}
                              </Text>
                            </Pressable>
                          ))}
                        </View>
                        <View style={styles.row}>
                          <Pressable
                            onPress={() =>
                              updateRow(i, { count: Math.max(1, row.count - 1) })
                            }
                            style={[styles.chip, { borderColor: colors.border }]}
                          >
                            <Text style={[styles.body, { color: colors.foreground }]}>
                              −
                            </Text>
                          </Pressable>
                          <Text style={[styles.body, { color: colors.foreground }]}>
                            {row.count}
                          </Text>
                          <Pressable
                            onPress={() =>
                              updateRow(i, {
                                count: Math.min(
                                  kitCaps[row.kind] ?? 1,
                                  row.count + 1,
                                ),
                              })
                            }
                            style={[styles.chip, { borderColor: colors.border }]}
                          >
                            <Text style={[styles.body, { color: colors.foreground }]}>
                              +
                            </Text>
                          </Pressable>
                          <Pressable
                            onPress={() =>
                              setComposition((rows) =>
                                rows.filter((_, idx) => idx !== i),
                              )
                            }
                            style={[styles.chip, { borderColor: colors.border }]}
                          >
                            <Feather name="x" size={14} color={colors.mutedForeground} />
                          </Pressable>
                        </View>
                        <TextField
                          label="Purpose"
                          value={row.purpose}
                          onChangeText={(t) =>
                            updateRow(i, { purpose: t.slice(0, 120) })
                          }
                          placeholder="e.g. for the product page"
                        />
                      </View>
                    ))}
                    <View style={styles.row}>
                      <Pressable
                        onPress={() =>
                          setComposition((rows) => [
                            ...rows,
                            { kind: "biolink", count: 1, purpose: "" },
                          ])
                        }
                      >
                        <Text style={[styles.link, { color: colors.primary }]}>
                          + Add asset
                        </Text>
                      </Pressable>
                      {composition.length && !compositionError ? (
                        <Pressable onPress={() => setSavingPreset((v) => !v)}>
                          <Text
                            style={[styles.link, { color: colors.mutedForeground }]}
                          >
                            Save this combo
                          </Text>
                        </Pressable>
                      ) : null}
                      {composition.length ? (
                        <Pressable onPress={() => setComposition([])}>
                          <Text
                            style={[styles.link, { color: colors.mutedForeground }]}
                          >
                            Clear
                          </Text>
                        </Pressable>
                      ) : null}
                    </View>
                    {savingPreset && composition.length ? (
                      <>
                        <TextField
                          label="Combo name"
                          value={presetName}
                          onChangeText={(t) => setPresetName(t.slice(0, 60))}
                          placeholder="e.g. Event kit"
                        />
                        <View style={styles.row}>
                          <Button
                            label={
                              savePresetMut.isPending ? "Saving…" : "Save combo"
                            }
                            disabled={
                              savePresetMut.isPending || !presetName.trim()
                            }
                            onPress={() =>
                              savePresetMut.mutate({
                                name: presetName.trim(),
                                rows: composition.map((r) => ({
                                  ...r,
                                  purpose: (r.purpose || "").trim(),
                                })),
                              })
                            }
                          />
                          <Pressable onPress={() => setSavingPreset(false)}>
                            <Text
                              style={[styles.link, { color: colors.mutedForeground }]}
                            >
                              Cancel
                            </Text>
                          </Pressable>
                        </View>
                      </>
                    ) : null}
                    {compositionError ? (
                      <Text style={[styles.small, { color: colors.warning }]}>
                        {compositionError}
                      </Text>
                    ) : null}
                  </>
                ) : null}

                <Button
                  label={planMut.isPending ? "Planning your kit…" : "Generate plan"}
                  disabled={planMut.isPending || !canGenerate}
                  onPress={() => planMut.mutate()}
                />
                <Button
                  label={
                    estimate != null
                      ? `≈ ${estimate} coins (you have ${availableCredits})`
                      : estimateMut.isPending
                        ? "Estimating…"
                        : "Estimate cost"
                  }
                  variant="outline"
                  disabled={estimateMut.isPending || !canGenerate}
                  onPress={() => estimateMut.mutate(undefined)}
                />
                {estimate != null && mode === "bulk" ? (
                  <Text style={[styles.small, { color: colors.mutedForeground }]}>
                    {bulkVariants} variant{bulkVariants === 1 ? "" : "s"} × ~
                    {perVariantCredits} coins each ≈ {estimate} coins total
                  </Text>
                ) : null}
                {lowBalance ? (
                  <View
                    style={[
                      styles.warnBox,
                      {
                        borderColor: colors.warning,
                        backgroundColor: `${colors.warning}1A`,
                      },
                    ]}
                  >
                    <Feather name="alert-triangle" size={14} color={colors.warning} />
                    <Text
                      style={[styles.small, styles.flex, { color: colors.warning }]}
                    >
                      This run needs about {estimate} coins but you only
                      have {availableCredits}. Top up your coins before
                      generating, or reduce the scope.
                    </Text>
                  </View>
                ) : null}
                <Text style={[styles.small, { color: colors.mutedForeground }]}>
                  You'll review the full plan before anything is created. A
                  failed run is automatically refunded.
                </Text>
              </Card>
            )}

            {data?.kits.length ? (
              <>
                <Text style={[styles.label, { color: colors.foreground }]}>
                  Your kits
                </Text>
                {data.kits.map((k) => (
                  <Pressable
                    key={k.id}
                    onPress={() => {
                      setDropped([]);
                      setOpenKitId(k.id);
                    }}
                  >
                    <Card style={styles.card}>
                      <View style={styles.row}>
                        <Feather
                          name={k.status === "created" ? "check-circle" : "clock"}
                          size={18}
                          color={
                            k.status === "created" ? colors.success : colors.warning
                          }
                        />
                        <View style={styles.flex}>
                          <Text style={[styles.body, { color: colors.foreground }]}>
                            {k.name}
                          </Text>
                          <Text style={[styles.small, { color: colors.mutedForeground }]}>
                            {k.mode === "bulk" ? "Bulk variations" : "Full kit"} ·{" "}
                            {k.asset_count} asset{k.asset_count === 1 ? "" : "s"} ·{" "}
                            {k.status === "created" ? "Created" : "Awaiting review"}
                          </Text>
                        </View>
                        <Feather
                          name="chevron-right"
                          size={18}
                          color={colors.mutedForeground}
                        />
                      </View>
                    </Card>
                  </Pressable>
                ))}
              </>
            ) : null}
          </>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  content: { padding: 16, gap: 12, paddingBottom: 48 },
  center: { paddingVertical: 48, alignItems: "center" },
  title: { fontSize: 22, fontWeight: "700" },
  subtitle: { fontSize: 14, lineHeight: 20 },
  card: { gap: 10, padding: 14 },
  cardOff: { opacity: 0.5 },
  row: { flexDirection: "row", alignItems: "center", gap: 10 },
  notice: { flexDirection: "row", gap: 8, alignItems: "flex-start" },
  body: { fontSize: 15 },
  small: { fontSize: 12 },
  label: { fontSize: 13, fontWeight: "600" },
  link: { fontSize: 13, fontWeight: "600" },
  chips: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  compRow: { gap: 8, paddingVertical: 6 },
  warnBox: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 8,
    borderWidth: 1,
    borderRadius: 12,
    padding: 10,
  },
  chip: {
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  savedChip: { flexDirection: "row", alignItems: "center", gap: 6 },
  backRow: { flexDirection: "row", alignItems: "center", gap: 6 },
});
