import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useLocalSearchParams } from "expo-router";
import { useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  getConversationalFlow,
  saveConversationalFlow,
  type CvAction,
  type CvActionKind,
  type CvActionPayload,
  type CvCondition,
  type CvEditor,
  type CvMeta,
  type CvStep,
} from "@/lib/api/conversational";

type Option = { label: string; value: string };

function payloadDisplay(a: CvAction): string {
  const p = a.payload ?? {};
  if (a.kind === "open_link") return p.url ?? "";
  if (a.kind === "book_calendar") return p.booking_url ?? "";
  if (a.kind === "show_block") return p.block_id != null ? String(p.block_id) : "";
  if (a.kind === "message") return p.text ?? "";
  if (a.kind === "capture_email") return p.cta ?? "";
  return "";
}

function payloadFromInput(kind: CvActionKind, val: string): CvActionPayload {
  if (kind === "open_link") return { url: val };
  if (kind === "book_calendar") return { booking_url: val };
  if (kind === "show_block") return { block_id: parseInt(val, 10) || null };
  if (kind === "message") return { text: val };
  if (kind === "capture_email") return { cta: val };
  return {};
}

function sanitizeKey(v: string): string {
  return (v || "").toLowerCase().replace(/[^a-z0-9_]/g, "_");
}

export default function ConversationalEditorScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = useQuery({
    queryKey: ["conversational", id],
    queryFn: () => getConversationalFlow(id),
    enabled: Number.isFinite(id),
  });

  const [name, setName] = useState("");
  const [intro, setIntro] = useState("");
  const [published, setPublished] = useState(false);
  const [typingMs, setTypingMs] = useState("600");
  const [steps, setSteps] = useState<CvStep[]>([]);
  const [actions, setActions] = useState<CvAction[]>([]);
  const [saved, setSaved] = useState(false);
  const newActionCounter = useRef(0);
  const newStepCounter = useRef(0);

  useEffect(() => {
    const d = q.data;
    if (!d) return;
    setName(d.flow.name ?? "");
    setIntro(d.flow.intro_message ?? "");
    setPublished(d.flow.is_published);
    setTypingMs(String(d.flow.settings?.default_typing_ms ?? 600));
    setSteps(d.flow.steps);
    setActions(d.flow.actions);
  }, [q.data]);

  const save = useMutation({
    mutationFn: () => {
      if (!steps.length) throw new Error("Add at least one step.");
      return saveConversationalFlow(id, {
        name: name.trim() || null,
        intro_message: intro.trim() || null,
        is_published: published,
        settings: { default_typing_ms: Number(typingMs) || 600 },
        actions,
        steps,
      });
    },
    onSuccess: (data: CvEditor) => {
      qc.setQueryData(["conversational", id], data);
      qc.invalidateQueries({ queryKey: ["link", id] });
      qc.invalidateQueries({ queryKey: ["links"] });
      setPublished(data.flow.is_published);
      setSaved(true);
      setTimeout(() => setSaved(false), 2500);
    },
  });

  const updateStepAt = (i: number, fn: (s: CvStep) => CvStep) =>
    setSteps((prev) => prev.map((s, idx) => (idx === i ? fn(s) : s)));
  const updateActionAt = (i: number, fn: (a: CvAction) => CvAction) =>
    setActions((prev) => prev.map((a, idx) => (idx === i ? fn(a) : a)));

  const addStep = () =>
    setSteps((prev) => [
      ...prev,
      {
        key: `step_${++newStepCounter.current}_${prev.length + 1}`,
        kind: "question",
        message_text: "New question?",
        answer_field: null,
        is_entry: false,
        skip_if_known: true,
        next_step_key: null,
        action_client_id: null,
        settings: {},
        choices: [],
      },
    ]);

  const addAction = () =>
    setActions((prev) => [
      ...prev,
      {
        client_id: `new_${++newActionCounter.current}`,
        kind: "open_link",
        label: "New action",
        payload: {},
      },
    ]);

  const setEntry = (i: number) =>
    setSteps((prev) =>
      prev.map((s, idx) => ({ ...s, is_entry: idx === i })),
    );

  const stepKeyOptions = (
    excludeKey?: string,
    emptyLabel = "— ends here —",
  ): Option[] => [
    { label: emptyLabel, value: "" },
    ...steps
      .filter((s) => s.key !== excludeKey)
      .map((s) => ({ label: s.key, value: s.key })),
  ];

  const actionOptions = (emptyLabel = "— No action —"): Option[] => [
    { label: emptyLabel, value: "" },
    ...actions.map((a) => ({
      label: `⚡ ${a.label || a.kind}`,
      value: a.client_id,
    })),
  ];

  if (!Number.isFinite(id)) return null;
  if (q.isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }
  if (q.error || !q.data) {
    return (
      <View style={styles.center}>
        <Text style={{ color: colors.destructive }}>
          Couldn't load this conversational flow.
        </Text>
      </View>
    );
  }

  const meta = q.data.meta;
  const stepKindOptions: Option[] = Object.entries(meta.step_kinds).map(
    ([k, l]) => ({ label: l, value: k }),
  );

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ headerShown: true, title: "Conversation flow" }} />
      <ScrollView contentContainerStyle={styles.body}>
        <Pressable
          onPress={() => Linking.openURL(meta.public_url)}
          style={[
            styles.publicCard,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              borderRadius: colors.radius,
            },
          ]}
        >
          <View style={{ flex: 1 }}>
            <Text
              style={[styles.publicLabel, { color: colors.mutedForeground }]}
            >
              Visitors chat at
            </Text>
            <Text
              numberOfLines={1}
              style={[styles.publicUrl, { color: colors.primary }]}
            >
              {meta.public_url}
            </Text>
          </View>
          <Feather name="external-link" size={18} color={colors.primary} />
        </Pressable>

        {/* ── Flow basics ─────────────────────────────────────── */}
        <View style={styles.section}>
          <Text style={[styles.sectionLabel, { color: colors.mutedForeground }]}>
            Flow basics
          </Text>
          <TextField
            label="Flow name"
            value={name}
            onChangeText={setName}
            maxLength={120}
            placeholder="Internal name (optional)"
          />
          <TextField
            label="Intro message"
            value={intro}
            onChangeText={setIntro}
            multiline
            numberOfLines={3}
            maxLength={2000}
            placeholder="First thing the bot says…"
            style={{ height: 84, textAlignVertical: "top", paddingTop: 12 }}
          />
          <TextField
            label="Default typing pause (ms)"
            value={typingMs}
            onChangeText={(v) => setTypingMs(v.replace(/[^0-9]/g, ""))}
            keyboardType="number-pad"
            maxLength={4}
            placeholder="600"
          />
          <RowSwitch
            label="Published"
            hint="Visitors only see the flow once it's published."
            value={published}
            onValueChange={setPublished}
          />
        </View>

        {/* ── Actions ─────────────────────────────────────────── */}
        <View style={styles.section}>
          <View style={styles.rowBetween}>
            <Text
              style={[styles.sectionLabel, { color: colors.mutedForeground }]}
            >
              End actions
            </Text>
            <Pressable onPress={addAction} hitSlop={8} style={styles.addLink}>
              <Feather name="plus" size={14} color={colors.primary} />
              <Text style={[styles.addLinkText, { color: colors.primary }]}>
                Action
              </Text>
            </Pressable>
          </View>
          <Text style={[styles.hint, { color: colors.mutedForeground }]}>
            Reusable actions you can attach to a step or choice on completion.
          </Text>
          {actions.length === 0 ? (
            <Empty label="No actions yet." />
          ) : (
            actions.map((a, i) => (
              <View
                key={a.client_id}
                style={[
                  styles.card,
                  {
                    backgroundColor: colors.card,
                    borderColor: colors.border,
                    borderRadius: colors.radius,
                  },
                ]}
              >
                <View style={styles.rowBetween}>
                  <Text style={[styles.cardTag, { color: colors.primary }]}>
                    ⚡ Action
                  </Text>
                  <Pressable
                    onPress={() =>
                      setActions((prev) => prev.filter((_, idx) => idx !== i))
                    }
                    hitSlop={8}
                  >
                    <Feather name="trash-2" size={16} color={colors.destructive} />
                  </Pressable>
                </View>
                <SelectField
                  label="Kind"
                  value={a.kind}
                  options={Object.entries(meta.action_kinds).map(([k, l]) => ({
                    label: l,
                    value: k,
                  }))}
                  onChange={(v) =>
                    updateActionAt(i, (x) => ({
                      ...x,
                      kind: v as CvActionKind,
                      payload: payloadFromInput(
                        v as CvActionKind,
                        payloadDisplay(x),
                      ),
                    }))
                  }
                />
                <TextField
                  label="Label"
                  value={a.label ?? ""}
                  onChangeText={(v) =>
                    updateActionAt(i, (x) => ({ ...x, label: v }))
                  }
                  maxLength={160}
                  placeholder="Button label"
                />
                {a.kind === "show_block" ? (
                  <SelectField
                    label="Target block"
                    value={a.payload?.block_id != null ? String(a.payload.block_id) : ""}
                    options={[
                      { label: "— pick a block —", value: "" },
                      ...meta.blocks.map((b) => ({
                        label: b.label,
                        value: String(b.id),
                      })),
                    ]}
                    onChange={(v) =>
                      updateActionAt(i, (x) => ({
                        ...x,
                        payload: { block_id: parseInt(v, 10) || null },
                      }))
                    }
                  />
                ) : (
                  <TextField
                    label={
                      a.kind === "open_link"
                        ? "URL"
                        : a.kind === "book_calendar"
                          ? "Booking URL"
                          : a.kind === "message"
                            ? "Message text"
                            : "Call to action"
                    }
                    value={payloadDisplay(a)}
                    onChangeText={(v) =>
                      updateActionAt(i, (x) => ({
                        ...x,
                        payload: payloadFromInput(x.kind, v),
                      }))
                    }
                    autoCapitalize="none"
                    placeholder={
                      a.kind === "open_link" || a.kind === "book_calendar"
                        ? "https://…"
                        : "Text"
                    }
                  />
                )}
              </View>
            ))
          )}
        </View>

        {/* ── Steps ───────────────────────────────────────────── */}
        <View style={styles.section}>
          <View style={styles.rowBetween}>
            <Text
              style={[styles.sectionLabel, { color: colors.mutedForeground }]}
            >
              Steps
            </Text>
            <Pressable onPress={addStep} hitSlop={8} style={styles.addLink}>
              <Feather name="plus" size={14} color={colors.primary} />
              <Text style={[styles.addLinkText, { color: colors.primary }]}>
                Step
              </Text>
            </Pressable>
          </View>
          {steps.length === 0 ? (
            <Empty label="No steps yet. Add one to get started." />
          ) : (
            steps.map((s, i) => (
              <StepCard
                key={`${i}-${s.key}`}
                step={s}
                index={i}
                meta={meta}
                stepKindOptions={stepKindOptions}
                stepKeyOptions={stepKeyOptions}
                actionOptions={actionOptions}
                onUpdate={updateStepAt}
                onSetEntry={setEntry}
                onRemove={() =>
                  setSteps((prev) => prev.filter((_, idx) => idx !== i))
                }
                allKeys={steps.map((x) => x.key)}
              />
            ))
          )}
        </View>

        {save.error ? (
          <Text style={{ color: colors.destructive }}>
            {(save.error as Error).message}
          </Text>
        ) : null}
        {saved ? (
          <Text style={{ color: colors.primary }}>Flow saved.</Text>
        ) : null}

        <Button
          label={published ? "Save & publish" : "Save flow"}
          onPress={() => save.mutate()}
          loading={save.isPending}
        />
      </ScrollView>
    </View>
  );
}

/* ── Step card ─────────────────────────────────────────────── */

function StepCard({
  step,
  index,
  meta,
  stepKindOptions,
  stepKeyOptions,
  actionOptions,
  onUpdate,
  onSetEntry,
  onRemove,
  allKeys,
}: {
  step: CvStep;
  index: number;
  meta: CvMeta;
  stepKindOptions: Option[];
  stepKeyOptions: (excludeKey?: string, emptyLabel?: string) => Option[];
  actionOptions: (emptyLabel?: string) => Option[];
  onUpdate: (i: number, fn: (s: CvStep) => CvStep) => void;
  onSetEntry: (i: number) => void;
  onRemove: () => void;
  allKeys: string[];
}) {
  const colors = useColors();
  const s = step;
  const set = (fn: (x: CvStep) => CvStep) => onUpdate(index, fn);
  const patchSettings = (patch: Partial<CvStep["settings"]>) =>
    set((x) => ({ ...x, settings: { ...x.settings, ...patch } }));

  const numOrUndef = (v: string): number | undefined =>
    v === "" ? undefined : Number(v);

  return (
    <View
      style={[
        styles.stepCard,
        {
          backgroundColor: colors.card,
          borderColor: s.is_entry ? colors.primary : colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      <View style={styles.rowBetween}>
        <View style={styles.rowCenter}>
          <Text style={[styles.stepKey, { color: colors.primary }]}>{s.key}</Text>
          {s.is_entry ? (
            <View
              style={[styles.entryPill, { backgroundColor: colors.primary + "22" }]}
            >
              <Text style={[styles.entryPillText, { color: colors.primary }]}>
                ENTRY
              </Text>
            </View>
          ) : null}
        </View>
        <Pressable onPress={onRemove} hitSlop={8}>
          <Feather name="trash-2" size={16} color={colors.destructive} />
        </Pressable>
      </View>

      <SelectField
        label="Step kind"
        value={s.kind}
        options={stepKindOptions}
        onChange={(v) =>
          set((x) => ({ ...x, kind: v as CvStep["kind"] }))
        }
      />

      <TextField
        label="Step key"
        value={s.key}
        autoCapitalize="none"
        onChangeText={(v) => set((x) => ({ ...x, key: v }))}
        onBlur={() =>
          set((x) => {
            const k = sanitizeKey(x.key);
            const dupe = allKeys.some((o, oi) => oi !== index && o === k);
            return { ...x, key: !k || dupe ? step.key : k };
          })
        }
        placeholder="step_key"
      />
      <TextField
        label="Answer field"
        value={s.answer_field ?? ""}
        autoCapitalize="none"
        onChangeText={(v) =>
          set((x) => ({ ...x, answer_field: v || null }))
        }
        placeholder="e.g. intent"
      />
      <TextField
        label="Bot message"
        hint="Supports {{name}} and {{answer:field}}"
        value={s.message_text}
        onChangeText={(v) => set((x) => ({ ...x, message_text: v }))}
        multiline
        numberOfLines={3}
        maxLength={2000}
        style={{ height: 80, textAlignVertical: "top", paddingTop: 12 }}
        placeholder="What the bot says…"
      />

      <SelectField
        label="Next step (default)"
        value={s.next_step_key ?? ""}
        options={stepKeyOptions(s.key, "— ends here —")}
        onChange={(v) => set((x) => ({ ...x, next_step_key: v || null }))}
      />
      <SelectField
        label="Action on completion"
        value={s.action_client_id ?? ""}
        options={actionOptions("— No action —")}
        onChange={(v) => set((x) => ({ ...x, action_client_id: v || null }))}
      />
      <TextField
        label="Typing pause (ms)"
        value={
          s.settings.typing_delay_ms != null
            ? String(s.settings.typing_delay_ms)
            : ""
        }
        keyboardType="number-pad"
        maxLength={4}
        onChangeText={(v) => {
          const clean = v.replace(/[^0-9]/g, "");
          patchSettings({
            typing_delay_ms: clean === "" ? null : Number(clean),
          });
        }}
        placeholder="default"
      />

      <View style={styles.toggleRow}>
        <Pressable
          onPress={() => onSetEntry(index)}
          style={styles.checkLine}
          hitSlop={6}
        >
          <Feather
            name={s.is_entry ? "check-square" : "square"}
            size={18}
            color={s.is_entry ? colors.primary : colors.mutedForeground}
          />
          <Text style={[styles.checkText, { color: colors.foreground }]}>
            Entry
          </Text>
        </Pressable>
        <Pressable
          onPress={() =>
            set((x) => ({ ...x, skip_if_known: !x.skip_if_known }))
          }
          style={styles.checkLine}
          hitSlop={6}
        >
          <Feather
            name={s.skip_if_known ? "check-square" : "square"}
            size={18}
            color={s.skip_if_known ? colors.primary : colors.mutedForeground}
          />
          <Text style={[styles.checkText, { color: colors.foreground }]}>
            Skip if known
          </Text>
        </Pressable>
      </View>

      {/* Per-kind settings */}
      {s.kind === "input" ? (
        <View style={styles.subSection}>
          <SubTitle text="Input settings" />
          <SelectField
            label="Input kind"
            value={s.settings.input_kind ?? "text"}
            options={meta.input_kinds.map((k) => ({ label: k, value: k }))}
            onChange={(v) => patchSettings({ input_kind: v })}
          />
          <TextField
            label="Placeholder"
            value={s.settings.placeholder ?? ""}
            onChangeText={(v) => patchSettings({ placeholder: v })}
            placeholder="Type your answer…"
          />
          <View style={styles.row2}>
            <View style={styles.flex1}>
              <TextField
                label="Min length"
                value={
                  s.settings.validation?.min_length != null
                    ? String(s.settings.validation.min_length)
                    : ""
                }
                keyboardType="number-pad"
                onChangeText={(v) =>
                  patchSettings({
                    validation: {
                      ...(s.settings.validation ?? {}),
                      min_length: numOrUndef(v.replace(/[^0-9]/g, "")) ?? null,
                    },
                  })
                }
                placeholder="0"
              />
            </View>
            <View style={styles.flex1}>
              <TextField
                label="Max length"
                value={
                  s.settings.validation?.max_length != null
                    ? String(s.settings.validation.max_length)
                    : ""
                }
                keyboardType="number-pad"
                onChangeText={(v) =>
                  patchSettings({
                    validation: {
                      ...(s.settings.validation ?? {}),
                      max_length: numOrUndef(v.replace(/[^0-9]/g, "")) ?? null,
                    },
                  })
                }
                placeholder="∞"
              />
            </View>
          </View>
          <TextField
            label="Regex pattern"
            value={s.settings.validation?.regex ?? ""}
            autoCapitalize="none"
            autoCorrect={false}
            onChangeText={(v) =>
              patchSettings({
                validation: { ...(s.settings.validation ?? {}), regex: v },
              })
            }
            placeholder="^[A-Z]{2}\d+$"
          />
          <TextField
            label="Error message"
            value={s.settings.validation?.error_message ?? ""}
            onChangeText={(v) =>
              patchSettings({
                validation: {
                  ...(s.settings.validation ?? {}),
                  error_message: v,
                },
              })
            }
            placeholder="That doesn't look right…"
          />
        </View>
      ) : null}

      {s.kind === "media" ? (
        <View style={styles.subSection}>
          <SubTitle text="Media settings" />
          <SelectField
            label="Media kind"
            value={s.settings.media?.kind ?? "image"}
            options={meta.media_kinds.map((k) => ({ label: k, value: k }))}
            onChange={(v) =>
              patchSettings({ media: { ...(s.settings.media ?? {}), kind: v } })
            }
          />
          <TextField
            label="URL"
            value={s.settings.media?.url ?? ""}
            autoCapitalize="none"
            onChangeText={(v) =>
              patchSettings({ media: { ...(s.settings.media ?? {}), url: v } })
            }
            placeholder="https://…"
          />
          <TextField
            label="Alt text"
            value={s.settings.media?.alt ?? ""}
            onChangeText={(v) =>
              patchSettings({ media: { ...(s.settings.media ?? {}), alt: v } })
            }
            placeholder="Describe the media"
          />
        </View>
      ) : null}

      {s.kind === "file_upload" ? (
        <View style={styles.subSection}>
          <SubTitle text="File upload" />
          <View style={styles.row2}>
            <View style={styles.flex1}>
              <TextField
                label="Max size (MB)"
                value={
                  s.settings.file?.max_mb != null
                    ? String(s.settings.file.max_mb)
                    : ""
                }
                keyboardType="number-pad"
                onChangeText={(v) =>
                  patchSettings({
                    file: {
                      ...(s.settings.file ?? {}),
                      max_mb: numOrUndef(v.replace(/[^0-9]/g, "")) ?? 10,
                    },
                  })
                }
                placeholder="10"
              />
            </View>
            <View style={styles.flex1}>
              <TextField
                label="Accepted ext."
                value={s.settings.file?.accept ?? ""}
                autoCapitalize="none"
                onChangeText={(v) =>
                  patchSettings({
                    file: { ...(s.settings.file ?? {}), accept: v },
                  })
                }
                placeholder="pdf,jpg,png"
              />
            </View>
          </View>
        </View>
      ) : null}

      {s.kind === "rating" ? (
        <View style={styles.subSection}>
          <SubTitle text="Rating settings" />
          <SelectField
            label="Scale"
            value={s.settings.rating?.scale ?? "star"}
            options={meta.rating_scales.map((k) => ({ label: k, value: k }))}
            onChange={(v) =>
              patchSettings({
                rating: { ...(s.settings.rating ?? {}), scale: v },
              })
            }
          />
          <View style={styles.row2}>
            <View style={styles.flex1}>
              <TextField
                label="Min"
                value={
                  s.settings.rating?.min != null
                    ? String(s.settings.rating.min)
                    : ""
                }
                keyboardType="number-pad"
                onChangeText={(v) =>
                  patchSettings({
                    rating: {
                      ...(s.settings.rating ?? {}),
                      min: numOrUndef(v.replace(/[^0-9]/g, "")) ?? 1,
                    },
                  })
                }
                placeholder="1"
              />
            </View>
            <View style={styles.flex1}>
              <TextField
                label="Max"
                value={
                  s.settings.rating?.max != null
                    ? String(s.settings.rating.max)
                    : ""
                }
                keyboardType="number-pad"
                onChangeText={(v) =>
                  patchSettings({
                    rating: {
                      ...(s.settings.rating ?? {}),
                      max: numOrUndef(v.replace(/[^0-9]/g, "")) ?? 5,
                    },
                  })
                }
                placeholder="5"
              />
            </View>
          </View>
        </View>
      ) : null}

      {s.kind === "datetime" ? (
        <View style={styles.subSection}>
          <SubTitle text="Date / time settings" />
          <SelectField
            label="Mode"
            value={s.settings.datetime?.mode ?? "datetime"}
            options={meta.datetime_modes.map((k) => ({ label: k, value: k }))}
            onChange={(v) =>
              patchSettings({
                datetime: { ...(s.settings.datetime ?? {}), mode: v },
              })
            }
          />
          <View style={styles.row2}>
            <View style={styles.flex1}>
              <TextField
                label="Min (ISO)"
                value={s.settings.datetime?.min ?? ""}
                autoCapitalize="none"
                onChangeText={(v) =>
                  patchSettings({
                    datetime: { ...(s.settings.datetime ?? {}), min: v },
                  })
                }
                placeholder="2027-01-01"
              />
            </View>
            <View style={styles.flex1}>
              <TextField
                label="Max (ISO)"
                value={s.settings.datetime?.max ?? ""}
                autoCapitalize="none"
                onChangeText={(v) =>
                  patchSettings({
                    datetime: { ...(s.settings.datetime ?? {}), max: v },
                  })
                }
                placeholder="2027-12-31"
              />
            </View>
          </View>
        </View>
      ) : null}

      {s.kind === "ai_freetext" ? (
        <AiPanel
          step={s}
          stepKeyOptions={stepKeyOptions}
          onPatch={patchSettings}
        />
      ) : null}

      {/* Quick-reply choices (question only) */}
      {s.kind === "question" ? (
        <ChoicesEditor
          step={s}
          index={index}
          meta={meta}
          stepKeyOptions={stepKeyOptions}
          actionOptions={actionOptions}
          onUpdate={onUpdate}
        />
      ) : null}

      {/* Branch conditions (any kind) */}
      <BranchConditions
        step={s}
        meta={meta}
        stepKeyOptions={stepKeyOptions}
        onPatch={patchSettings}
      />
    </View>
  );
}

/* ── AI free-text panel ────────────────────────────────────── */

function AiPanel({
  step,
  stepKeyOptions,
  onPatch,
}: {
  step: CvStep;
  stepKeyOptions: (excludeKey?: string, emptyLabel?: string) => Option[];
  onPatch: (patch: Partial<CvStep["settings"]>) => void;
}) {
  const colors = useColors();
  const ai = step.settings.ai ?? {};
  const intents = ai.intents ?? [];

  const setIntents = (next: typeof intents) =>
    onPatch({ ai: { ...ai, intents: next } });

  return (
    <View style={styles.subSection}>
      <SubTitle text="AI free-text routing" />
      <Text style={[styles.hint, { color: colors.mutedForeground }]}>
        The reply is classified into an intent and routed. Falls back if
        confidence is low.
      </Text>
      {intents.map((it, ii) => (
        <View
          key={ii}
          style={[
            styles.innerCard,
            { borderColor: colors.border, borderRadius: colors.radius },
          ]}
        >
          <View style={styles.rowBetween}>
            <Text style={[styles.cardTag, { color: colors.mutedForeground }]}>
              Intent {ii + 1}
            </Text>
            <Pressable
              onPress={() => setIntents(intents.filter((_, x) => x !== ii))}
              hitSlop={8}
            >
              <Feather name="x" size={16} color={colors.destructive} />
            </Pressable>
          </View>
          <TextField
            label="Value"
            value={it.value ?? ""}
            autoCapitalize="none"
            onChangeText={(v) =>
              setIntents(
                intents.map((x, idx) =>
                  idx === ii ? { ...x, value: v || undefined } : x,
                ),
              )
            }
            placeholder="pricing"
          />
          <TextField
            label="Label"
            value={it.label ?? ""}
            onChangeText={(v) =>
              setIntents(
                intents.map((x, idx) =>
                  idx === ii ? { ...x, label: v || undefined } : x,
                ),
              )
            }
            placeholder="Pricing question"
          />
          <TextField
            label="Example utterances"
            value={it.examples ?? ""}
            onChangeText={(v) =>
              setIntents(
                intents.map((x, idx) =>
                  idx === ii ? { ...x, examples: v || undefined } : x,
                ),
              )
            }
            placeholder="how much, cost, price (comma list)"
          />
          <SelectField
            label="Route to"
            value={it.next_step_key ?? ""}
            options={stepKeyOptions(step.key, "— route to —")}
            onChange={(v) =>
              setIntents(
                intents.map((x, idx) =>
                  idx === ii ? { ...x, next_step_key: v || null } : x,
                ),
              )
            }
          />
        </View>
      ))}
      <Pressable
        onPress={() =>
          setIntents([
            ...intents,
            {
              value: `intent_${intents.length + 1}`,
              label: "New intent",
              examples: "",
              next_step_key: null,
            },
          ])
        }
        style={styles.addLink}
        hitSlop={8}
      >
        <Feather name="plus" size={14} color={colors.primary} />
        <Text style={[styles.addLinkText, { color: colors.primary }]}>
          Add intent
        </Text>
      </Pressable>
      <SelectField
        label="Fallback step"
        value={ai.fallback_step_key ?? ""}
        options={stepKeyOptions(step.key, "— pick a fallback step —")}
        onChange={(v) => onPatch({ ai: { ...ai, fallback_step_key: v } })}
      />
      <TextField
        label="Min confidence (0–1)"
        value={ai.min_confidence != null ? String(ai.min_confidence) : ""}
        keyboardType="decimal-pad"
        onChangeText={(v) =>
          onPatch({
            ai: {
              ...ai,
              min_confidence: v === "" ? undefined : Number(v),
            },
          })
        }
        placeholder="0.4"
      />
    </View>
  );
}

/* ── Choices editor ────────────────────────────────────────── */

function ChoicesEditor({
  step,
  index,
  meta,
  stepKeyOptions,
  actionOptions,
  onUpdate,
}: {
  step: CvStep;
  index: number;
  meta: CvMeta;
  stepKeyOptions: (excludeKey?: string, emptyLabel?: string) => Option[];
  actionOptions: (emptyLabel?: string) => Option[];
  onUpdate: (i: number, fn: (s: CvStep) => CvStep) => void;
}) {
  const colors = useColors();
  const choices = step.choices;
  const setChoices = (next: CvStep["choices"]) =>
    onUpdate(index, (x) => ({ ...x, choices: next }));
  const condOps = useMemo<Option[]>(
    () => [
      { label: "(always)", value: "" },
      ...meta.condition_ops.map((o) => ({ label: o, value: o })),
    ],
    [meta.condition_ops],
  );

  const patchCond = (ci: number, patch: Partial<CvCondition>) =>
    setChoices(
      choices.map((c, idx) => {
        if (idx !== ci) return c;
        const condition = { ...(c.settings.condition ?? {}), ...patch };
        const cleaned = condition.op
          ? condition
          : undefined;
        return { ...c, settings: { ...c.settings, condition: cleaned } };
      }),
    );

  return (
    <View style={styles.subSection}>
      <View style={styles.rowBetween}>
        <SubTitle text="Quick-reply choices" />
        <Pressable
          onPress={() =>
            setChoices([
              ...choices,
              {
                label: "New choice",
                value: `choice_${choices.length + 1}`,
                next_step_key: null,
                action_client_id: null,
                settings: {},
              },
            ])
          }
          style={styles.addLink}
          hitSlop={8}
        >
          <Feather name="plus" size={14} color={colors.primary} />
          <Text style={[styles.addLinkText, { color: colors.primary }]}>
            Choice
          </Text>
        </Pressable>
      </View>
      {choices.length === 0 ? (
        <Empty label="No choices yet." />
      ) : (
        choices.map((c, ci) => (
          <View
            key={ci}
            style={[
              styles.innerCard,
              { borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            <View style={styles.rowBetween}>
              <Text style={[styles.cardTag, { color: colors.mutedForeground }]}>
                Choice {ci + 1}
              </Text>
              <Pressable
                onPress={() => setChoices(choices.filter((_, x) => x !== ci))}
                hitSlop={8}
              >
                <Feather name="x" size={16} color={colors.destructive} />
              </Pressable>
            </View>
            <TextField
              label="Label"
              value={c.label}
              onChangeText={(v) =>
                setChoices(
                  choices.map((x, idx) => (idx === ci ? { ...x, label: v } : x)),
                )
              }
              placeholder="Tappable label"
            />
            <TextField
              label="Value"
              value={c.value}
              autoCapitalize="none"
              onChangeText={(v) =>
                setChoices(
                  choices.map((x, idx) => (idx === ci ? { ...x, value: v } : x)),
                )
              }
              placeholder="value"
            />
            <SelectField
              label="Next step"
              value={c.next_step_key ?? ""}
              options={stepKeyOptions(step.key, "— next step —")}
              onChange={(v) =>
                setChoices(
                  choices.map((x, idx) =>
                    idx === ci ? { ...x, next_step_key: v || null } : x,
                  ),
                )
              }
            />
            <SelectField
              label="Action"
              value={c.action_client_id ?? ""}
              options={actionOptions("— action —")}
              onChange={(v) =>
                setChoices(
                  choices.map((x, idx) =>
                    idx === ci ? { ...x, action_client_id: v || null } : x,
                  ),
                )
              }
            />
            <Text style={[styles.condLabel, { color: colors.mutedForeground }]}>
              Show only when (optional)
            </Text>
            <TextField
              label="Field"
              value={c.settings.condition?.field ?? ""}
              autoCapitalize="none"
              onChangeText={(v) => patchCond(ci, { field: v || undefined })}
              placeholder="answer field"
            />
            <SelectField
              label="Operator"
              value={c.settings.condition?.op ?? ""}
              options={condOps}
              onChange={(v) => patchCond(ci, { op: v || undefined })}
            />
            <TextField
              label="Value"
              value={c.settings.condition?.value ?? ""}
              onChangeText={(v) => patchCond(ci, { value: v || undefined })}
              placeholder="match value"
            />
            <SelectField
              label="Override goto"
              value={c.settings.condition?.goto ?? ""}
              options={stepKeyOptions(step.key, "— override goto —")}
              onChange={(v) => patchCond(ci, { goto: v || null })}
            />
          </View>
        ))
      )}
    </View>
  );
}

/* ── Step branch conditions ────────────────────────────────── */

function BranchConditions({
  step,
  meta,
  stepKeyOptions,
  onPatch,
}: {
  step: CvStep;
  meta: CvMeta;
  stepKeyOptions: (excludeKey?: string, emptyLabel?: string) => Option[];
  onPatch: (patch: Partial<CvStep["settings"]>) => void;
}) {
  const colors = useColors();
  const conds = step.settings.conditions ?? [];
  const setConds = (next: CvCondition[]) => onPatch({ conditions: next });
  const ops: Option[] = meta.condition_ops.map((o) => ({ label: o, value: o }));

  return (
    <View style={styles.subSection}>
      <View style={styles.rowBetween}>
        <SubTitle text="Branch conditions" />
        <Pressable
          onPress={() =>
            setConds([
              ...conds,
              {
                field: step.answer_field ?? step.key,
                op: "eq",
                value: "",
                goto: "",
              },
            ])
          }
          style={styles.addLink}
          hitSlop={8}
        >
          <Feather name="plus" size={14} color={colors.primary} />
          <Text style={[styles.addLinkText, { color: colors.primary }]}>
            Condition
          </Text>
        </Pressable>
      </View>
      <Text style={[styles.hint, { color: colors.mutedForeground }]}>
        First match wins, otherwise uses the default next step.
      </Text>
      {conds.length === 0 ? (
        <Empty label="No conditions." />
      ) : (
        conds.map((c, ci) => (
          <View
            key={ci}
            style={[
              styles.innerCard,
              { borderColor: colors.border, borderRadius: colors.radius },
            ]}
          >
            <View style={styles.rowBetween}>
              <Text style={[styles.cardTag, { color: colors.mutedForeground }]}>
                Rule {ci + 1}
              </Text>
              <Pressable
                onPress={() => setConds(conds.filter((_, x) => x !== ci))}
                hitSlop={8}
              >
                <Feather name="x" size={16} color={colors.destructive} />
              </Pressable>
            </View>
            <TextField
              label="Field"
              value={c.field ?? ""}
              autoCapitalize="none"
              onChangeText={(v) =>
                setConds(
                  conds.map((x, idx) => (idx === ci ? { ...x, field: v } : x)),
                )
              }
              placeholder="answer field"
            />
            <SelectField
              label="Operator"
              value={c.op ?? "eq"}
              options={ops}
              onChange={(v) =>
                setConds(
                  conds.map((x, idx) => (idx === ci ? { ...x, op: v } : x)),
                )
              }
            />
            <TextField
              label="Value"
              value={c.value ?? ""}
              onChangeText={(v) =>
                setConds(
                  conds.map((x, idx) => (idx === ci ? { ...x, value: v } : x)),
                )
              }
              placeholder="match value"
            />
            <SelectField
              label="Go to"
              value={c.goto ?? ""}
              options={stepKeyOptions(step.key, "— go to —")}
              onChange={(v) =>
                setConds(
                  conds.map((x, idx) =>
                    idx === ci ? { ...x, goto: v || null } : x,
                  ),
                )
              }
            />
          </View>
        ))
      )}
    </View>
  );
}

/* ── Reusable bits ─────────────────────────────────────────── */

function SelectField({
  label,
  value,
  options,
  onChange,
}: {
  label: string;
  value: string;
  options: Option[];
  onChange: (value: string) => void;
}) {
  const colors = useColors();
  const [open, setOpen] = useState(false);
  const current = options.find((o) => o.value === value);

  return (
    <View style={styles.fieldWrap}>
      <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
        {label}
      </Text>
      <Pressable
        onPress={() => setOpen(true)}
        style={[
          styles.select,
          {
            backgroundColor: colors.card,
            borderColor: colors.border,
            borderRadius: colors.radius,
          },
        ]}
      >
        <Text
          numberOfLines={1}
          style={[
            styles.selectText,
            {
              color: current ? colors.foreground : colors.mutedForeground,
              flex: 1,
            },
          ]}
        >
          {current?.label ?? "Select…"}
        </Text>
        <Feather name="chevron-down" size={16} color={colors.mutedForeground} />
      </Pressable>

      <Modal
        visible={open}
        transparent
        animationType="fade"
        onRequestClose={() => setOpen(false)}
      >
        <Pressable style={styles.modalBackdrop} onPress={() => setOpen(false)}>
          <Pressable
            style={[
              styles.modalSheet,
              { backgroundColor: colors.background, borderColor: colors.border },
            ]}
          >
            <Text style={[styles.modalTitle, { color: colors.foreground }]}>
              {label}
            </Text>
            <ScrollView style={{ maxHeight: 360 }}>
              {options.map((o) => {
                const on = o.value === value;
                return (
                  <Pressable
                    key={o.value || "__empty"}
                    onPress={() => {
                      onChange(o.value);
                      setOpen(false);
                    }}
                    style={[
                      styles.optionRow,
                      {
                        backgroundColor: on
                          ? colors.primary + "1c"
                          : "transparent",
                        borderRadius: colors.radius - 4,
                      },
                    ]}
                  >
                    <Text
                      style={[
                        styles.optionText,
                        { color: on ? colors.primary : colors.foreground },
                      ]}
                    >
                      {o.label}
                    </Text>
                    {on ? (
                      <Feather name="check" size={16} color={colors.primary} />
                    ) : null}
                  </Pressable>
                );
              })}
            </ScrollView>
          </Pressable>
        </Pressable>
      </Modal>
    </View>
  );
}

function RowSwitch({
  label,
  hint,
  value,
  onValueChange,
}: {
  label: string;
  hint?: string;
  value: boolean;
  onValueChange: (v: boolean) => void;
}) {
  const colors = useColors();
  return (
    <View
      style={[
        styles.switchRow,
        {
          backgroundColor: colors.card,
          borderColor: colors.border,
          borderRadius: colors.radius,
        },
      ]}
    >
      <View style={{ flex: 1, paddingRight: 12 }}>
        <Text style={[styles.switchLabel, { color: colors.foreground }]}>
          {label}
        </Text>
        {hint ? (
          <Text style={[styles.hint, { color: colors.mutedForeground }]}>
            {hint}
          </Text>
        ) : null}
      </View>
      <Switch
        value={value}
        onValueChange={onValueChange}
        trackColor={{ true: colors.primary, false: colors.border }}
      />
    </View>
  );
}

function SubTitle({ text }: { text: string }) {
  const colors = useColors();
  return (
    <Text style={[styles.subTitle, { color: colors.foreground }]}>{text}</Text>
  );
}

function Empty({ label }: { label: string }) {
  const colors = useColors();
  return (
    <Text style={[styles.empty, { color: colors.mutedForeground }]}>
      {label}
    </Text>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  body: { padding: 20, gap: 18, paddingBottom: 64 },
  publicCard: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    padding: 14,
    borderWidth: 1,
  },
  publicLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 11,
    textTransform: "uppercase",
    letterSpacing: 0.4,
  },
  publicUrl: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 14,
    marginTop: 2,
  },
  section: { gap: 10 },
  sectionLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  rowBetween: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  rowCenter: { flexDirection: "row", alignItems: "center", gap: 8 },
  addLink: { flexDirection: "row", alignItems: "center", gap: 4 },
  addLinkText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  card: { padding: 14, borderWidth: 1, gap: 8 },
  cardTag: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 12 },
  stepCard: { padding: 14, borderWidth: 1, gap: 8 },
  stepKey: { fontFamily: "SpaceGrotesk_700Bold", fontSize: 15 },
  entryPill: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 999,
  },
  entryPillText: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 9,
    letterSpacing: 0.5,
  },
  toggleRow: { flexDirection: "row", gap: 24, marginTop: 4 },
  checkLine: { flexDirection: "row", alignItems: "center", gap: 8 },
  checkText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  subSection: {
    gap: 8,
    marginTop: 6,
    paddingTop: 10,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: "rgba(127,127,127,0.25)",
  },
  subTitle: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  innerCard: { padding: 10, borderWidth: 1, gap: 6 },
  condLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 11,
    marginTop: 4,
    textTransform: "uppercase",
    letterSpacing: 0.4,
  },
  row2: { flexDirection: "row", gap: 10 },
  flex1: { flex: 1 },
  hint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 11,
    lineHeight: 16,
  },
  empty: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    paddingVertical: 8,
  },
  fieldWrap: { gap: 6 },
  fieldLabel: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 13 },
  select: {
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    paddingHorizontal: 14,
    minHeight: 48,
  },
  selectText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 14 },
  modalBackdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.45)",
    justifyContent: "center",
    padding: 24,
  },
  modalSheet: {
    borderWidth: 1,
    borderRadius: 16,
    padding: 16,
    gap: 8,
  },
  modalTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 15,
    marginBottom: 4,
  },
  optionRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingVertical: 12,
    paddingHorizontal: 12,
  },
  optionText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 14 },
  switchRow: {
    flexDirection: "row",
    alignItems: "center",
    padding: 14,
    borderWidth: 1,
  },
  switchLabel: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 },
});
