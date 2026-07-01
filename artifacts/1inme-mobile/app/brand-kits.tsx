import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Modal,
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
import { handlePlanLockedError, showUpgradePrompt } from "@/lib/upgradePrompt";
import {
  applyBrandKitToBiolink,
  applyBrandKitToQr,
  deleteBrandKit,
  estimateBrandKit,
  generateBrandKit,
  getBrandConsistency,
  getBrandKits,
  type BrandConsistencyFinding,
  type BrandConsistencyResponse,
  type BrandKit,
  type BrandKitsIndex,
} from "@/lib/api/brandKits";

/**
 * Mobile parity for the web "AI Brand Kit" feature
 * (App\Modules\User\Controllers\BrandKitController, /user/brand-kits).
 *
 * A creator describes their brand (optionally adding a website or logo URL),
 * the AI crafts a cohesive identity — palette, font pairing, voice, taglines,
 * bio and a recommended Link-in-Bio block theme — and the resulting kit can be
 * applied to one of their biolinks or QR codes. Generation is plan-gated
 * (`max_brand_kits`) and charged in AI credits, with the same auto-refund on
 * failure as the web (handled server-side in AiBrandKitService).
 */
export default function BrandKitsScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const [prompt, setPrompt] = useState("");
  const [website, setWebsite] = useState("");
  const [logo, setLogo] = useState("");
  const [estimate, setEstimate] = useState<number | null>(null);

  // Which kit the user is applying, and to which kind of target.
  const [applyKit, setApplyKit] = useState<BrandKit | null>(null);
  const [applyKind, setApplyKind] = useState<"biolink" | "qr" | null>(null);

  const query = useQuery<BrandKitsIndex>({
    queryKey: ["brand-kits"],
    queryFn: getBrandKits,
  });
  const data = query.data;

  // Brand Consistency Score — audit of the creator's biolinks against their
  // default kit. Plan-gated server-side; hidden when unavailable or no kit.
  const consistencyQuery = useQuery<BrandConsistencyResponse>({
    queryKey: ["brand-consistency"],
    queryFn: getBrandConsistency,
  });
  const consistency = consistencyQuery.data;

  const hasInput = useMemo(
    () =>
      prompt.trim().length > 0 ||
      website.trim().length > 0 ||
      logo.trim().length > 0,
    [prompt, website, logo],
  );

  const invalidate = () => qc.invalidateQueries({ queryKey: ["brand-kits"] });

  const estimateMut = useMutation({
    mutationFn: () =>
      estimateBrandKit({
        prompt: prompt.trim(),
        website_url: website.trim() || null,
        logo_url: logo.trim() || null,
      }),
    onSuccess: (r) => setEstimate(r.estimated_credits),
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      Alert.alert("Couldn't estimate", e?.message ?? "Please try again.");
    },
  });

  const generateMut = useMutation({
    mutationFn: () =>
      generateBrandKit({
        prompt: prompt.trim(),
        website_url: website.trim() || null,
        logo_url: logo.trim() || null,
      }),
    onSuccess: (r) => {
      setPrompt("");
      setWebsite("");
      setLogo("");
      setEstimate(null);
      invalidate();
      Alert.alert(
        "Brand kit created",
        r.credits_spent > 0
          ? `“${r.kit.name}” is ready. ${r.credits_spent} credit${r.credits_spent === 1 ? "" : "s"} used.`
          : `“${r.kit.name}” is ready.`,
      );
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      Alert.alert("Couldn't generate", e?.message ?? "Please try again.");
    },
  });

  const deleteMut = useMutation({
    mutationFn: (id: number) => deleteBrandKit(id),
    onSuccess: invalidate,
    onError: (e: any) =>
      Alert.alert("Couldn't delete", e?.message ?? "Please try again."),
  });

  const applyMut = useMutation({
    mutationFn: async (vars: {
      kitId: number;
      kind: "biolink" | "qr";
      targetId: number;
    }): Promise<void> => {
      if (vars.kind === "biolink") {
        await applyBrandKitToBiolink(vars.kitId, vars.targetId);
      } else {
        await applyBrandKitToQr(vars.kitId, vars.targetId);
      }
    },
    onSuccess: () => {
      setApplyKit(null);
      setApplyKind(null);
      qc.invalidateQueries({ queryKey: ["brand-consistency"] });
      Alert.alert("Brand kit applied", "Your changes have been saved.");
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      Alert.alert("Couldn't apply", e?.message ?? "Please try again.");
    },
  });

  // One-tap "Apply fix" from a Brand Consistency finding: applies the default
  // kit to that off-brand biolink, then re-audits (the page now scores 100).
  const fixMut = useMutation({
    mutationFn: (vars: { kitId: number; linkId: number }) =>
      applyBrandKitToBiolink(vars.kitId, vars.linkId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["brand-consistency"] });
    },
    onError: (e: any) => {
      if (handlePlanLockedError(e)) return;
      Alert.alert("Couldn't apply fix", e?.message ?? "Please try again.");
    },
  });

  const confirmDelete = (kit: BrandKit) => {
    Alert.alert("Delete brand kit?", `“${kit.name}” will be removed.`, [
      { text: "Cancel", style: "cancel" },
      {
        text: "Delete",
        style: "destructive",
        onPress: () => deleteMut.mutate(kit.id),
      },
    ]);
  };

  const openApply = (kit: BrandKit, kind: "biolink" | "qr") => {
    setApplyKit(kit);
    setApplyKind(kind);
  };

  const aiDisabled = data ? !data.ai_enabled : false;
  const capReached = data ? !data.can_create : false;

  return (
    <View style={[styles.flex, { backgroundColor: colors.background }]}>
      <Stack.Screen options={{ title: "AI Brand Kit" }} />
      <ScrollView contentContainerStyle={styles.content}>
        {query.isLoading ? (
          <View style={styles.center}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : query.isError ? (
          <Card style={styles.card}>
            <Text style={[styles.body, { color: colors.foreground }]}>
              Couldn't load your brand kits.
            </Text>
            <Button label="Retry" variant="outline" onPress={() => query.refetch()} />
          </Card>
        ) : (
          <>
            <Text style={[styles.title, { color: colors.foreground }]}>
              AI Brand Kit
            </Text>
            <Text style={[styles.subtitle, { color: colors.mutedForeground }]}>
              Describe your brand and let AI craft a palette, fonts, voice and
              taglines you can apply to a Link in Bio or QR code.
            </Text>

            {/* Brand Consistency Score */}
            {consistency?.available && consistency.has_kit && consistency.audit ? (
              <ConsistencyCard
                audit={consistency.audit}
                colors={colors}
                onFix={(linkId) =>
                  fixMut.mutate({ kitId: consistency.audit!.kit_id, linkId })
                }
                fixingLinkId={
                  fixMut.isPending ? fixMut.variables?.linkId ?? null : null
                }
              />
            ) : null}

            {/* Generate form */}
            <Card style={styles.card}>
              {aiDisabled ? (
                <View style={styles.notice}>
                  <Feather name="info" size={16} color={colors.mutedForeground} />
                  <Text style={[styles.noticeText, { color: colors.mutedForeground }]}>
                    AI features are currently unavailable. Please try again
                    later.
                  </Text>
                </View>
              ) : capReached ? (
                <View style={styles.notice}>
                  <Feather name="lock" size={16} color={colors.warning} />
                  <View style={styles.flex}>
                    <Text style={[styles.noticeText, { color: colors.foreground }]}>
                      You've reached your plan's brand kit limit
                      {data ? ` (${data.cap})` : ""}.
                    </Text>
                    <Pressable
                      onPress={() =>
                        showUpgradePrompt({
                          message:
                            "Upgrade your plan to create more brand kits.",
                          hint: data?.upgrade_plan
                            ? {
                                planSlug: data.upgrade_plan.slug,
                                planName: data.upgrade_plan.name,
                                feature: "brand_kits",
                              }
                            : undefined,
                        })
                      }
                    >
                      <Text style={[styles.link, { color: colors.primary }]}>
                        {data?.upgrade_plan
                          ? `Upgrade to ${data.upgrade_plan.name}`
                          : "See upgrade options"}
                      </Text>
                    </Pressable>
                  </View>
                </View>
              ) : (
                <>
                  <TextField
                    label="Describe your brand"
                    placeholder="e.g. A calm, premium skincare brand for busy parents…"
                    value={prompt}
                    onChangeText={(t) => {
                      setPrompt(t);
                      setEstimate(null);
                    }}
                    multiline
                    numberOfLines={4}
                    style={styles.textarea}
                  />
                  <TextField
                    label="Website (optional)"
                    placeholder="https://yourbrand.com"
                    value={website}
                    onChangeText={(t) => {
                      setWebsite(t);
                      setEstimate(null);
                    }}
                    autoCapitalize="none"
                    keyboardType="url"
                  />
                  <TextField
                    label="Logo URL (optional)"
                    placeholder="https://…/logo.png"
                    value={logo}
                    onChangeText={(t) => {
                      setLogo(t);
                      setEstimate(null);
                    }}
                    autoCapitalize="none"
                    keyboardType="url"
                  />

                  <View style={styles.balanceRow}>
                    <Text style={[styles.meta, { color: colors.mutedForeground }]}>
                      Balance: {data?.balance ?? 0} credits
                    </Text>
                    {estimate !== null ? (
                      <Text style={[styles.meta, { color: colors.foreground }]}>
                        Est. cost: ~{estimate} credit{estimate === 1 ? "" : "s"}
                      </Text>
                    ) : null}
                  </View>

                  <View style={styles.actions}>
                    <Button
                      label="Estimate cost"
                      variant="outline"
                      style={styles.flex}
                      disabled={!hasInput || estimateMut.isPending}
                      loading={estimateMut.isPending}
                      onPress={() => estimateMut.mutate()}
                    />
                    <Button
                      label="Generate"
                      style={styles.flex}
                      disabled={!hasInput || generateMut.isPending}
                      loading={generateMut.isPending}
                      onPress={() => generateMut.mutate()}
                    />
                  </View>
                </>
              )}
            </Card>

            {/* Existing kits */}
            <Text style={[styles.sectionTitle, { color: colors.foreground }]}>
              Your brand kits {data ? `(${data.count})` : ""}
            </Text>

            {data && data.kits.length === 0 ? (
              <Card style={styles.card}>
                <Text style={[styles.body, { color: colors.mutedForeground }]}>
                  No brand kits yet. Generate your first one above.
                </Text>
              </Card>
            ) : (
              data?.kits.map((kit) => (
                <BrandKitCard
                  key={kit.id}
                  kit={kit}
                  colors={colors}
                  canApplyBiolink={(data?.biolinks.length ?? 0) > 0}
                  canApplyQr={(data?.qr_codes.length ?? 0) > 0}
                  onApplyBiolink={() => openApply(kit, "biolink")}
                  onApplyQr={() => openApply(kit, "qr")}
                  onDelete={() => confirmDelete(kit)}
                  deleting={deleteMut.isPending && deleteMut.variables === kit.id}
                />
              ))
            )}
          </>
        )}
      </ScrollView>

      {/* Apply-target picker */}
      <Modal
        visible={!!applyKit && !!applyKind}
        animationType="slide"
        transparent
        onRequestClose={() => {
          setApplyKit(null);
          setApplyKind(null);
        }}
      >
        <Pressable
          style={styles.backdrop}
          onPress={() => {
            setApplyKit(null);
            setApplyKind(null);
          }}
        >
          <Pressable
            style={[styles.sheet, { backgroundColor: colors.background }]}
            onPress={(e) => e.stopPropagation()}
          >
            <Text style={[styles.sheetTitle, { color: colors.foreground }]}>
              {applyKind === "biolink"
                ? "Apply to a Link in Bio"
                : "Apply to a QR code"}
            </Text>
            <Text style={[styles.meta, { color: colors.mutedForeground }]}>
              {applyKit ? `Using “${applyKit.name}”` : ""}
            </Text>
            <ScrollView style={styles.sheetList}>
              {(applyKind === "biolink" ? data?.biolinks ?? [] : data?.qr_codes ?? []).map(
                (target: any) => (
                  <Pressable
                    key={target.id}
                    style={[styles.targetRow, { borderColor: colors.border }]}
                    disabled={applyMut.isPending}
                    onPress={() =>
                      applyMut.mutate({
                        kitId: applyKit!.id,
                        kind: applyKind!,
                        targetId: target.id,
                      })
                    }
                  >
                    <Feather
                      name={applyKind === "biolink" ? "link" : "grid"}
                      size={18}
                      color={colors.primary}
                    />
                    <Text style={[styles.targetLabel, { color: colors.foreground }]}>
                      {applyKind === "biolink"
                        ? target.title || target.alias
                        : target.name}
                    </Text>
                    {applyMut.isPending &&
                    applyMut.variables?.targetId === target.id ? (
                      <ActivityIndicator size="small" color={colors.primary} />
                    ) : (
                      <Feather
                        name="chevron-right"
                        size={18}
                        color={colors.mutedForeground}
                      />
                    )}
                  </Pressable>
                ),
              )}
            </ScrollView>
            <Button
              label="Cancel"
              variant="ghost"
              onPress={() => {
                setApplyKit(null);
                setApplyKind(null);
              }}
            />
          </Pressable>
        </Pressable>
      </Modal>
    </View>
  );
}

function scoreColor(
  score: number,
  colors: ReturnType<typeof useColors>,
): string {
  if (score >= 90) return colors.success;
  if (score >= 75) return colors.primary;
  if (score >= 50) return colors.warning;
  return colors.destructive;
}

function ConsistencyCard({
  audit,
  colors,
  onFix,
  fixingLinkId,
}: {
  audit: NonNullable<BrandConsistencyResponse["audit"]>;
  colors: ReturnType<typeof useColors>;
  onFix: (linkId: number) => void;
  fixingLinkId: number | null;
}) {
  const tint = scoreColor(audit.score, colors);
  const allOnBrand =
    audit.findings.length === 0 && audit.links_total > 0;

  return (
    <Card style={styles.card}>
      <View style={styles.scoreHeader}>
        <View style={[styles.scoreBadge, { borderColor: tint }]}>
          <Text style={[styles.scoreNumber, { color: tint }]}>
            {audit.score}
          </Text>
          <Text style={[styles.scoreOutOf, { color: colors.mutedForeground }]}>
            /100
          </Text>
        </View>
        <View style={styles.flex}>
          <Text style={[styles.scoreLabel, { color: colors.foreground }]}>
            {audit.label}
          </Text>
          <Text style={[styles.meta, { color: colors.mutedForeground }]}>
            {audit.links_total === 0
              ? "No Link in Bio pages to check yet."
              : `${audit.links_on_brand} of ${audit.links_total} page${
                  audit.links_total === 1 ? "" : "s"
                } on-brand · ${audit.kit_name}`}
          </Text>
        </View>
      </View>

      {audit.links_total === 0 ? (
        <Text style={[styles.body, { color: colors.mutedForeground }]}>
          Create a Link in Bio to see how on-brand it is.
        </Text>
      ) : allOnBrand ? (
        <View style={styles.notice}>
          <Feather name="check-circle" size={16} color={colors.success} />
          <Text style={[styles.noticeText, { color: colors.foreground }]}>
            Every page matches your AI Brand Kit. Nice work!
          </Text>
        </View>
      ) : (
        <>
          <Text style={[styles.findingsTitle, { color: colors.foreground }]}>
            Off-brand pages ({audit.findings.length})
          </Text>
          {audit.findings.map((finding) => (
            <FindingRow
              key={finding.link_id}
              finding={finding}
              colors={colors}
              onFix={() => onFix(finding.link_id)}
              fixing={fixingLinkId === finding.link_id}
            />
          ))}
        </>
      )}
    </Card>
  );
}

function FindingRow({
  finding,
  colors,
  onFix,
  fixing,
}: {
  finding: BrandConsistencyFinding;
  colors: ReturnType<typeof useColors>;
  onFix: () => void;
  fixing: boolean;
}) {
  const tint = scoreColor(finding.score, colors);
  return (
    <View style={[styles.finding, { borderColor: colors.border }]}>
      <View style={styles.findingHeader}>
        <View
          style={[styles.findingDot, { backgroundColor: tint }]}
        />
        <Text
          style={[styles.findingTitle, { color: colors.foreground }]}
          numberOfLines={1}
        >
          {finding.title}
        </Text>
        <Text style={[styles.findingScore, { color: tint }]}>
          {finding.score}%
        </Text>
      </View>
      <Text style={[styles.body, { color: colors.mutedForeground }]}>
        {finding.reason}
      </Text>
      <Button
        label={fixing ? "Applying…" : "Apply fix"}
        variant="outline"
        loading={fixing}
        disabled={fixing}
        onPress={onFix}
      />
    </View>
  );
}

function BrandKitCard({
  kit,
  colors,
  canApplyBiolink,
  canApplyQr,
  onApplyBiolink,
  onApplyQr,
  onDelete,
  deleting,
}: {
  kit: BrandKit;
  colors: ReturnType<typeof useColors>;
  canApplyBiolink: boolean;
  canApplyQr: boolean;
  onApplyBiolink: () => void;
  onApplyQr: () => void;
  onDelete: () => void;
  deleting: boolean;
}) {
  const palette = kit.config.palette ?? {};
  const swatches = [
    palette.primary,
    palette.secondary,
    palette.accent,
    ...(palette.neutrals ?? []),
  ].filter((c): c is string => typeof c === "string" && c.length > 0);
  const fonts = kit.config.fonts ?? {};
  const taglines = kit.config.taglines ?? [];

  return (
    <Card style={styles.card}>
      <View style={styles.kitHeader}>
        <Text style={[styles.kitName, { color: colors.foreground }]}>
          {kit.name}
        </Text>
        {kit.is_default ? (
          <View style={[styles.badge, { backgroundColor: colors.muted }]}>
            <Text style={[styles.badgeText, { color: colors.mutedForeground }]}>
              Default
            </Text>
          </View>
        ) : null}
      </View>

      {swatches.length > 0 ? (
        <View style={styles.swatchRow}>
          {swatches.slice(0, 7).map((c, i) => (
            <View
              key={`${c}-${i}`}
              style={[styles.swatch, { backgroundColor: c, borderColor: colors.border }]}
            />
          ))}
        </View>
      ) : null}

      {(fonts.heading || fonts.body) ? (
        <Text style={[styles.meta, { color: colors.mutedForeground }]}>
          {fonts.heading ?? "—"} / {fonts.body ?? "—"}
        </Text>
      ) : null}

      {kit.config.bio ? (
        <Text style={[styles.body, { color: colors.foreground }]} numberOfLines={3}>
          {kit.config.bio}
        </Text>
      ) : null}

      {taglines.length > 0 ? (
        <View style={styles.tagWrap}>
          {taglines.slice(0, 3).map((t, i) => (
            <View key={i} style={[styles.tag, { backgroundColor: colors.muted }]}>
              <Text style={[styles.tagText, { color: colors.foreground }]} numberOfLines={1}>
                {t}
              </Text>
            </View>
          ))}
        </View>
      ) : null}

      <View style={styles.kitActions}>
        <Button
          label="Apply to bio"
          variant="outline"
          style={styles.flex}
          disabled={!canApplyBiolink}
          onPress={onApplyBiolink}
        />
        <Button
          label="Apply to QR"
          variant="outline"
          style={styles.flex}
          disabled={!canApplyQr}
          onPress={onApplyQr}
        />
      </View>
      <Pressable
        onPress={onDelete}
        disabled={deleting}
        style={styles.deleteRow}
        hitSlop={8}
      >
        {deleting ? (
          <ActivityIndicator size="small" color={colors.destructive} />
        ) : (
          <Feather name="trash-2" size={16} color={colors.destructive} />
        )}
        <Text style={[styles.deleteText, { color: colors.destructive }]}>
          Delete
        </Text>
      </Pressable>
    </Card>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  content: { padding: 16, gap: 12, paddingBottom: 48 },
  center: { paddingVertical: 64, alignItems: "center" },
  title: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 24,
  },
  subtitle: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 14,
    lineHeight: 20,
  },
  sectionTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 18,
    marginTop: 8,
  },
  card: { padding: 16, gap: 12 },
  textarea: { minHeight: 96, paddingTop: 14, textAlignVertical: "top" },
  balanceRow: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  actions: { flexDirection: "row", gap: 10 },
  meta: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  body: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 14, lineHeight: 20 },
  link: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14, marginTop: 4 },
  notice: { flexDirection: "row", gap: 10, alignItems: "flex-start" },
  noticeText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 14, lineHeight: 20 },
  kitHeader: { flexDirection: "row", alignItems: "center", gap: 8 },
  kitName: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 17, flex: 1 },
  badge: { borderRadius: 999, paddingHorizontal: 10, paddingVertical: 3 },
  badgeText: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 11,
    textTransform: "uppercase",
    letterSpacing: 0.4,
  },
  scoreHeader: { flexDirection: "row", alignItems: "center", gap: 14 },
  scoreBadge: {
    width: 64,
    height: 64,
    borderRadius: 32,
    borderWidth: 3,
    alignItems: "center",
    justifyContent: "center",
  },
  scoreNumber: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 22 },
  scoreOutOf: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 11, marginTop: -2 },
  scoreLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 17 },
  findingsTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 14,
    marginTop: 4,
  },
  finding: {
    borderWidth: 1,
    borderRadius: 12,
    padding: 12,
    gap: 8,
  },
  findingHeader: { flexDirection: "row", alignItems: "center", gap: 8 },
  findingDot: { width: 8, height: 8, borderRadius: 4 },
  findingTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 15, flex: 1 },
  findingScore: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 14 },
  swatchRow: { flexDirection: "row", gap: 6 },
  swatch: { width: 32, height: 32, borderRadius: 8, borderWidth: 1 },
  tagWrap: { flexDirection: "row", flexWrap: "wrap", gap: 6 },
  tag: { borderRadius: 999, paddingHorizontal: 10, paddingVertical: 5, maxWidth: "100%" },
  tagText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  kitActions: { flexDirection: "row", gap: 10 },
  deleteRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    alignSelf: "flex-start",
  },
  deleteText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  backdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.4)",
    justifyContent: "flex-end",
  },
  sheet: {
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: 20,
    gap: 10,
    maxHeight: "70%",
  },
  sheetTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 18 },
  sheetList: { marginVertical: 8 },
  targetRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingVertical: 14,
    borderBottomWidth: 1,
  },
  targetLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 15, flex: 1 },
});
