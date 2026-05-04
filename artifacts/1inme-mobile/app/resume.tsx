import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack } from "expo-router";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { Card } from "@/components/Card";
import { TextField } from "@/components/TextField";
import { useColors } from "@/hooks/useColors";
import {
  createResumeItem,
  deleteResumeItem,
  getResume,
  reorderResumeItems,
  updateResumeColorTheme,
  updateResumeHeader,
  updateResumeItem,
  updateResumeSummary,
  updateResumeTemplate,
  type Resume,
  type ResumeBundle,
  type ResumeItem,
  type ResumeSectionType,
} from "@/lib/api/resume";

/**
 * Native resume editor + live preview.
 *
 * Talks to the new `/api/v1/resume*` endpoints (which the web editor
 * also reads through, via the shared ResumePresenter), so the same data
 * model backs both clients. Header + summary autosave with a short
 * debounce; section items are managed through dedicated CRUD calls and
 * a single inline editor card.
 */
export default function ResumeScreen() {
  const colors = useColors();
  const qc = useQueryClient();

  const q = useQuery({ queryKey: ["resume"], queryFn: getResume });
  const resume = q.data?.resume;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Resume" }} />
      {q.isLoading || !resume ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <ResumeEditor resume={resume} bundle={q.data!} qc={qc} />
      )}
    </View>
  );
}

function ResumeEditor({
  resume,
  bundle,
  qc,
}: {
  resume: Resume;
  bundle: ResumeBundle;
  qc: ReturnType<typeof useQueryClient>;
}) {
  const colors = useColors();

  // ── Header autosave ────────────────────────────────────────────
  const [header, setHeader] = useState(resume.sections.header);
  const [summary, setSummary] = useState(resume.sections.summary);
  const headerInit = useRef(false);
  // Last server snapshot we accepted into local state. We adopt new
  // server values only for fields the user hasn't touched since — i.e.
  // local still equals what the server last gave us. This lets remote
  // changes (template switches, second-device edits) flow in without
  // clobbering an in-flight edit the user hasn't sent yet.
  const lastServerHeader = useRef(resume.sections.header);
  const lastServerSummary = useRef(resume.sections.summary);
  useEffect(() => {
    if (!headerInit.current) {
      setHeader(resume.sections.header);
      setSummary(resume.sections.summary);
      lastServerHeader.current = resume.sections.header;
      lastServerSummary.current = resume.sections.summary;
      headerInit.current = true;
      return;
    }
    const remoteHeader = resume.sections.header;
    const remoteSummary = resume.sections.summary;
    setHeader((current) => {
      const next = { ...current };
      let changed = false;
      (Object.keys(remoteHeader) as (keyof typeof remoteHeader)[]).forEach((k) => {
        const remoteVal = remoteHeader[k];
        const lastVal = lastServerHeader.current[k];
        // Only adopt the server value if the user hasn't typed past
        // what we last hydrated for this field.
        if (remoteVal !== lastVal && current[k] === lastVal) {
          (next as Record<string, unknown>)[k as string] = remoteVal;
          changed = true;
        }
      });
      return changed ? next : current;
    });
    setSummary((current) =>
      remoteSummary !== lastServerSummary.current && current === lastServerSummary.current
        ? remoteSummary
        : current,
    );
    lastServerHeader.current = remoteHeader;
    lastServerSummary.current = remoteSummary;
  }, [resume]);

  const headerMut = useMutation({
    mutationFn: (h: typeof header) =>
      updateResumeHeader({
        name: h.name,
        headline: h.headline,
        location: h.location,
        email: h.email || undefined,
        phone: h.phone,
        website: h.website || undefined,
      }),
    onSuccess: (r) => qc.setQueryData(["resume"], { ...bundle, resume: r }),
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't save header", e?.message ?? "Try again."),
  });

  const summaryMut = useMutation({
    mutationFn: (s: string) => updateResumeSummary(s),
    onSuccess: (r) => qc.setQueryData(["resume"], { ...bundle, resume: r }),
  });

  useDebouncedEffect(() => {
    if (!headerInit.current) return;
    headerMut.mutate(header);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [header.name, header.headline, header.location, header.email, header.phone, header.website], 600);

  useDebouncedEffect(() => {
    if (!headerInit.current) return;
    summaryMut.mutate(summary);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [summary], 600);

  // ── Section CRUD ───────────────────────────────────────────────
  const refresh = useCallback(() => qc.invalidateQueries({ queryKey: ["resume"] }), [qc]);

  const itemDelete = useMutation({
    mutationFn: (id: number) => deleteResumeItem(id),
    onSuccess: refresh,
    onError: (e: { message?: string }) =>
      Alert.alert("Couldn't delete", e?.message ?? "Try again."),
  });

  const itemReorder = useMutation({
    mutationFn: ({ type, ids }: { type: ResumeSectionType; ids: number[] }) =>
      reorderResumeItems(type, ids),
    onSuccess: (r) => qc.setQueryData(["resume"], { ...bundle, resume: r }),
  });

  const templateMut = useMutation({
    mutationFn: (id: string) => updateResumeTemplate(id),
    onSuccess: (r) => qc.setQueryData(["resume"], { ...bundle, resume: r }),
    onError: (e: { message?: string }) =>
      Alert.alert("Template locked", e?.message ?? "Upgrade to use this template."),
  });

  const themeMut = useMutation({
    mutationFn: (id: string) => updateResumeColorTheme(id),
    onSuccess: (r) => qc.setQueryData(["resume"], { ...bundle, resume: r }),
  });

  const [showStyle, setShowStyle] = useState(false);
  const saving = headerMut.isPending || summaryMut.isPending;

  return (
    <ScrollView
      contentContainerStyle={styles.body}
      keyboardShouldPersistTaps="handled"
    >
      {/* Status strip */}
      <View style={styles.statusRow}>
        <View
          style={[
            styles.statusDot,
            { backgroundColor: saving ? "#f59e0b" : "#10b981" },
          ]}
        />
        <Text style={[styles.statusText, { color: colors.mutedForeground }]}>
          {saving ? "Saving…" : "All changes saved"}
        </Text>
        <Pressable
          onPress={() => setShowStyle((s) => !s)}
          hitSlop={8}
          style={{ marginLeft: "auto" }}
        >
          <Text style={[styles.linkText, { color: colors.primary }]}>
            {showStyle ? "Hide style" : "Style"}
          </Text>
        </Pressable>
      </View>

      {showStyle ? (
        <StylePanel
          resume={resume}
          templates={bundle.registries.templates}
          themes={bundle.registries.color_themes}
          onPickTemplate={(id) => templateMut.mutate(id)}
          onPickTheme={(id) => themeMut.mutate(id)}
        />
      ) : null}

      {/* Header section */}
      <SectionTitle text="Header" />
      <Card>
        <TextField
          label="Display name"
          value={header.name}
          onChangeText={(v) => setHeader((h) => ({ ...h, name: v }))}
          placeholder="Your name"
        />
        <TextField
          label="Headline"
          value={header.headline}
          onChangeText={(v) => setHeader((h) => ({ ...h, headline: v }))}
          placeholder="What you do, in one line"
        />
        <TextField
          label="Location"
          value={header.location}
          onChangeText={(v) => setHeader((h) => ({ ...h, location: v }))}
          placeholder="City, Country"
        />
        <TextField
          label="Email"
          value={header.email}
          onChangeText={(v) => setHeader((h) => ({ ...h, email: v }))}
          autoCapitalize="none"
          keyboardType="email-address"
          placeholder="you@example.com"
        />
        <TextField
          label="Phone"
          value={header.phone}
          onChangeText={(v) => setHeader((h) => ({ ...h, phone: v }))}
          keyboardType="phone-pad"
          placeholder="+1 555 123 4567"
        />
        <TextField
          label="Website"
          value={header.website}
          onChangeText={(v) => setHeader((h) => ({ ...h, website: v }))}
          autoCapitalize="none"
          keyboardType="url"
          placeholder="https://yourdomain.com"
        />
      </Card>

      {/* Summary */}
      <SectionTitle text="Summary" />
      <Card>
        <TextField
          label="Professional summary"
          value={summary}
          onChangeText={setSummary}
          multiline
          numberOfLines={5}
          placeholder="A short paragraph that introduces who you are."
        />
      </Card>

      {/* Section editors */}
      {SECTION_DEFS.map((def) => (
        <SectionEditor
          key={def.type}
          def={def}
          items={resume.items[def.type] ?? []}
          onAdd={(data) => createResumeItem(def.type, data).then(refresh)}
          onUpdate={(id, data) => updateResumeItem(id, data).then(refresh)}
          onDelete={(id) => itemDelete.mutate(id)}
          onMove={(type, ids) => itemReorder.mutate({ type, ids })}
        />
      ))}

      {/* Live preview */}
      <SectionTitle text="Preview" />
      <PreviewCard resume={{ ...resume, sections: { ...resume.sections, header, summary } }} />

      <Text style={[styles.hint, { color: colors.mutedForeground }]}>
        Your resume is reachable at {resume.handle ? `1inme.com/${resume.handle}/resume` : "your public profile"} once you publish it from the web. PDF download and templates with photos are managed there too.
      </Text>
    </ScrollView>
  );
}

// ── Reusable bits ────────────────────────────────────────────────

function SectionTitle({ text }: { text: string }) {
  const colors = useColors();
  return (
    <Text style={[styles.sectionTitle, { color: colors.foreground }]}>{text}</Text>
  );
}

function StylePanel({
  resume,
  templates,
  themes,
  onPickTemplate,
  onPickTheme,
}: {
  resume: Resume;
  templates: Resume["template"][];
  themes: Resume["color_theme"][];
  onPickTemplate: (id: string) => void;
  onPickTheme: (id: string) => void;
}) {
  const colors = useColors();
  return (
    <Card style={{ gap: 14 }}>
      <Text style={[styles.subhead, { color: colors.mutedForeground }]}>Template</Text>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
        {templates.map((t) => {
          const active = t.id === resume.template_id;
          return (
            <Pressable
              key={t.id}
              onPress={() => !active && onPickTemplate(t.id)}
              style={[
                styles.chip,
                {
                  borderColor: active ? colors.primary : colors.border,
                  backgroundColor: active ? colors.primary + "22" : "transparent",
                },
              ]}
            >
              <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 }}>
                {t.name ?? t.id}
              </Text>
              {t.premium ? (
                <Text style={{ color: colors.primary, marginLeft: 6, fontSize: 10 }}>PRO</Text>
              ) : null}
            </Pressable>
          );
        })}
      </ScrollView>

      <Text style={[styles.subhead, { color: colors.mutedForeground }]}>Color theme</Text>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
        {themes.map((th) => {
          const active = th.id === resume.color_theme_id;
          return (
            <Pressable
              key={th.id}
              onPress={() => !active && onPickTheme(th.id)}
              style={[
                styles.swatch,
                {
                  backgroundColor: th.accent ?? "#7c3aed",
                  borderColor: active ? colors.foreground : "transparent",
                },
              ]}
              accessibilityLabel={th.name ?? th.id}
            />
          );
        })}
      </ScrollView>
    </Card>
  );
}

// ── Section item editor ─────────────────────────────────────────

type FieldKind = "text" | "multiline" | "url" | "email" | "month" | "switch" | "number";
type FieldDef = {
  key: string;
  label: string;
  kind?: FieldKind;
  required?: boolean;
  placeholder?: string;
};
type SectionDef = {
  type: ResumeSectionType;
  title: string;
  emptyHint: string;
  fields: FieldDef[];
  /** Render a one-line label for the saved item in the list. */
  summarize: (data: Record<string, unknown>) => string;
};

const SECTION_DEFS: SectionDef[] = [
  {
    type: "experience",
    title: "Experience",
    emptyHint: "Add your roles, most recent first.",
    fields: [
      { key: "company", label: "Company", required: true },
      { key: "role", label: "Role", required: true },
      { key: "location", label: "Location" },
      { key: "start_date", label: "Start (YYYY-MM)", kind: "month", placeholder: "2024-01" },
      { key: "end_date", label: "End (YYYY-MM)", kind: "month", placeholder: "Leave blank if current" },
      { key: "is_current", label: "I currently work here", kind: "switch" },
      { key: "description", label: "Description", kind: "multiline" },
    ],
    summarize: (d) => `${d.role || "Role"} · ${d.company || "Company"}`,
  },
  {
    type: "education",
    title: "Education",
    emptyHint: "Add schools, certifications, courses.",
    fields: [
      { key: "school", label: "School", required: true },
      { key: "degree", label: "Degree" },
      { key: "field", label: "Field of study" },
      { key: "start_date", label: "Start (YYYY-MM)", kind: "month" },
      { key: "end_date", label: "End (YYYY-MM)", kind: "month" },
      { key: "description", label: "Notes", kind: "multiline" },
    ],
    summarize: (d) => `${d.school || "School"}${d.degree ? ` · ${d.degree}` : ""}`,
  },
  {
    type: "skills",
    title: "Skills",
    emptyHint: "Tag the skills you want to highlight.",
    fields: [
      { key: "name", label: "Skill", required: true },
      { key: "group", label: "Group (e.g. Languages, Tools)" },
      { key: "level", label: "Level (1–5)", kind: "number" },
    ],
    summarize: (d) => `${d.name || "Skill"}${d.group ? ` (${d.group})` : ""}`,
  },
  {
    type: "projects",
    title: "Projects",
    emptyHint: "Showcase work you're proud of.",
    fields: [
      { key: "name", label: "Project", required: true },
      { key: "role", label: "Your role" },
      { key: "url", label: "URL", kind: "url" },
      { key: "start_date", label: "Start (YYYY-MM)", kind: "month" },
      { key: "end_date", label: "End (YYYY-MM)", kind: "month" },
      { key: "description", label: "Description", kind: "multiline" },
    ],
    summarize: (d) => `${d.name || "Project"}`,
  },
  {
    type: "certifications",
    title: "Certifications",
    emptyHint: "Add credentials and certifications.",
    fields: [
      { key: "name", label: "Name", required: true },
      { key: "issuer", label: "Issuer" },
      { key: "issued_on", label: "Issued (YYYY-MM)", kind: "month" },
      { key: "expires_on", label: "Expires (YYYY-MM)", kind: "month" },
      { key: "credential_url", label: "Credential URL", kind: "url" },
    ],
    summarize: (d) => `${d.name || "Certification"}${d.issuer ? ` · ${d.issuer}` : ""}`,
  },
  {
    type: "awards",
    title: "Awards",
    emptyHint: "Recognition and achievements.",
    fields: [
      { key: "title", label: "Title", required: true },
      { key: "issuer", label: "Issuer" },
      { key: "date", label: "Date (YYYY-MM)", kind: "month" },
      { key: "description", label: "Description", kind: "multiline" },
    ],
    summarize: (d) => `${d.title || "Award"}`,
  },
  {
    type: "languages",
    title: "Languages",
    emptyHint: "Languages you speak.",
    fields: [
      { key: "name", label: "Language", required: true },
      { key: "proficiency", label: "Proficiency (basic/conversational/professional/fluent/native)" },
    ],
    summarize: (d) => `${d.name || "Language"}${d.proficiency ? ` · ${d.proficiency}` : ""}`,
  },
  {
    type: "links",
    title: "Links",
    emptyHint: "GitHub, LinkedIn, portfolio…",
    fields: [
      { key: "label", label: "Label", required: true },
      { key: "url", label: "URL", required: true, kind: "url" },
    ],
    summarize: (d) => `${d.label || "Link"}`,
  },
];

function SectionEditor({
  def,
  items,
  onAdd,
  onUpdate,
  onDelete,
  onMove,
}: {
  def: SectionDef;
  items: ResumeItem[];
  onAdd: (data: Record<string, unknown>) => Promise<unknown>;
  onUpdate: (id: number, data: Record<string, unknown>) => Promise<unknown>;
  onDelete: (id: number) => void;
  onMove: (type: ResumeSectionType, ids: number[]) => void;
}) {
  const colors = useColors();
  const [editing, setEditing] = useState<{ id: number | null; data: Record<string, unknown> } | null>(null);
  const [busy, setBusy] = useState(false);

  const startNew = () => {
    const blank: Record<string, unknown> = {};
    def.fields.forEach((f) => {
      blank[f.key] = f.kind === "switch" ? false : "";
    });
    setEditing({ id: null, data: blank });
  };

  const move = (id: number, dir: -1 | 1) => {
    const ids = items.map((i) => i.id);
    const idx = ids.indexOf(id);
    const target = idx + dir;
    if (idx < 0 || target < 0 || target >= ids.length) return;
    [ids[idx], ids[target]] = [ids[target], ids[idx]];
    onMove(def.type, ids);
  };

  const save = async () => {
    if (!editing) return;
    setBusy(true);
    try {
      const cleaned: Record<string, unknown> = {};
      for (const [k, v] of Object.entries(editing.data)) {
        if (v === "" || v === null || v === undefined) continue;
        const f = def.fields.find((x) => x.key === k);
        if (f?.kind === "number") {
          const n = Number(v);
          if (Number.isFinite(n)) cleaned[k] = n;
        } else if (f?.kind === "switch") {
          if (v) cleaned[k] = true;
        } else {
          cleaned[k] = v;
        }
      }
      if (editing.id == null) await onAdd(cleaned);
      else await onUpdate(editing.id, cleaned);
      setEditing(null);
    } catch (e) {
      const err = e as { message?: string; errors?: Record<string, string[]> };
      const detail = err.errors
        ? Object.values(err.errors).flat().join("\n")
        : err.message ?? "Try again.";
      Alert.alert("Couldn't save", detail);
    } finally {
      setBusy(false);
    }
  };

  return (
    <View>
      <View style={styles.sectionHeadRow}>
        <SectionTitle text={def.title} />
        <Pressable onPress={startNew} hitSlop={8} style={{ flexDirection: "row", alignItems: "center", gap: 4 }}>
          <Feather name="plus" size={16} color={colors.primary} />
          <Text style={[styles.linkText, { color: colors.primary }]}>Add</Text>
        </Pressable>
      </View>

      {items.length === 0 && !editing ? (
        <Card>
          <Text style={[styles.hint, { color: colors.mutedForeground, textAlign: "left", marginTop: 0 }]}>
            {def.emptyHint}
          </Text>
        </Card>
      ) : null}

      {items.map((it, idx) => {
        const isEditing = editing?.id === it.id;
        if (isEditing) {
          return (
            <Card key={it.id} style={{ gap: 8 }}>
              <FieldStack
                def={def}
                data={editing!.data}
                onChange={(k, v) => setEditing({ ...editing!, data: { ...editing!.data, [k]: v } })}
              />
              <View style={styles.rowGap}>
                <Button label="Cancel" variant="outline" onPress={() => setEditing(null)} style={{ flex: 1 }} />
                <Button label={busy ? "Saving…" : "Save"} onPress={save} loading={busy} style={{ flex: 1 }} />
              </View>
            </Card>
          );
        }
        return (
          <Card key={it.id} style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
            <View style={{ flex: 1 }}>
              <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14 }}>
                {def.summarize(it.data)}
              </Text>
            </View>
            <Pressable hitSlop={6} onPress={() => move(it.id, -1)} disabled={idx === 0}>
              <Feather name="chevron-up" size={18} color={idx === 0 ? colors.border : colors.mutedForeground} />
            </Pressable>
            <Pressable hitSlop={6} onPress={() => move(it.id, 1)} disabled={idx === items.length - 1}>
              <Feather name="chevron-down" size={18} color={idx === items.length - 1 ? colors.border : colors.mutedForeground} />
            </Pressable>
            <Pressable hitSlop={6} onPress={() => setEditing({ id: it.id, data: { ...it.data } })}>
              <Feather name="edit-2" size={16} color={colors.primary} />
            </Pressable>
            <Pressable
              hitSlop={6}
              onPress={() =>
                Alert.alert("Delete entry?", def.summarize(it.data), [
                  { text: "Cancel", style: "cancel" },
                  { text: "Delete", style: "destructive", onPress: () => onDelete(it.id) },
                ])
              }
            >
              <Feather name="trash-2" size={16} color={colors.destructive} />
            </Pressable>
          </Card>
        );
      })}

      {editing && editing.id == null ? (
        <Card style={{ gap: 8 }}>
          <FieldStack
            def={def}
            data={editing.data}
            onChange={(k, v) => setEditing({ ...editing, data: { ...editing.data, [k]: v } })}
          />
          <View style={styles.rowGap}>
            <Button label="Cancel" variant="outline" onPress={() => setEditing(null)} style={{ flex: 1 }} />
            <Button label={busy ? "Saving…" : "Add"} onPress={save} loading={busy} style={{ flex: 1 }} />
          </View>
        </Card>
      ) : null}
    </View>
  );
}

function FieldStack({
  def,
  data,
  onChange,
}: {
  def: SectionDef;
  data: Record<string, unknown>;
  onChange: (k: string, v: unknown) => void;
}) {
  const colors = useColors();
  return (
    <>
      {def.fields.map((f) => {
        if (f.kind === "switch") {
          return (
            <View key={f.key} style={styles.switchRow}>
              <Text style={{ color: colors.foreground, fontFamily: "SpaceGrotesk_500Medium", fontSize: 13, flex: 1 }}>
                {f.label}
              </Text>
              <Switch
                value={!!data[f.key]}
                onValueChange={(v) => onChange(f.key, v)}
                trackColor={{ true: colors.primary }}
              />
            </View>
          );
        }
        return (
          <TextField
            key={f.key}
            label={f.label + (f.required ? " *" : "")}
            value={String(data[f.key] ?? "")}
            onChangeText={(v) => onChange(f.key, v)}
            placeholder={f.placeholder}
            multiline={f.kind === "multiline"}
            numberOfLines={f.kind === "multiline" ? 4 : undefined}
            keyboardType={
              f.kind === "url" ? "url" : f.kind === "email" ? "email-address" : f.kind === "number" ? "number-pad" : "default"
            }
            autoCapitalize={f.kind === "url" || f.kind === "email" ? "none" : undefined}
          />
        );
      })}
    </>
  );
}

// ── Live preview ────────────────────────────────────────────────

function PreviewCard({ resume }: { resume: Resume }) {
  const colors = useColors();
  const accent = resume.color_theme?.accent ?? colors.primary;
  const h = resume.sections.header;
  const items = resume.items;

  const contact = useMemo(
    () => [h.location, h.email, h.phone, h.website].filter(Boolean).join(" · "),
    [h.location, h.email, h.phone, h.website],
  );

  const renderGroup = (title: string, type: ResumeSectionType, render: (it: ResumeItem) => React.ReactNode) => {
    const list = items[type] ?? [];
    if (list.length === 0) return null;
    return (
      <View style={{ marginTop: 12 }}>
        <Text style={[styles.pvSectionTitle, { color: accent, borderColor: accent }]}>{title.toUpperCase()}</Text>
        {list.map((it) => (
          <View key={it.id} style={{ marginTop: 6 }}>
            {render(it)}
          </View>
        ))}
      </View>
    );
  };

  return (
    <Card style={{ backgroundColor: "#fff", padding: 18 }}>
      <Text style={{ fontFamily: "SpaceGrotesk_700Bold", fontSize: 22, color: "#111" }}>
        {h.name || "Your name"}
      </Text>
      {h.headline ? (
        <Text style={{ fontSize: 13, color: "#444", marginTop: 2 }}>{h.headline}</Text>
      ) : null}
      {contact ? (
        <Text style={{ fontSize: 11, color: "#666", marginTop: 4 }}>{contact}</Text>
      ) : null}

      {resume.sections.summary ? (
        <View style={{ marginTop: 12 }}>
          <Text style={[styles.pvSectionTitle, { color: accent, borderColor: accent }]}>SUMMARY</Text>
          <Text style={{ fontSize: 12, color: "#222", lineHeight: 18, marginTop: 4 }}>
            {resume.sections.summary}
          </Text>
        </View>
      ) : null}

      {renderGroup("Experience", "experience", (it) => (
        <>
          <Text style={{ fontFamily: "SpaceGrotesk_700Bold", fontSize: 13, color: "#111" }}>
            {String(it.data.role || "Role")} · {String(it.data.company || "")}
          </Text>
          {(it.data.start_date || it.data.end_date) ? (
            <Text style={{ fontSize: 10, color: "#666" }}>
              {String(it.data.start_date || "")} – {it.data.is_current ? "Present" : String(it.data.end_date || "")}
            </Text>
          ) : null}
          {it.data.description ? (
            <Text style={{ fontSize: 11, color: "#222", marginTop: 2 }}>{String(it.data.description)}</Text>
          ) : null}
        </>
      ))}

      {renderGroup("Education", "education", (it) => (
        <>
          <Text style={{ fontFamily: "SpaceGrotesk_700Bold", fontSize: 13, color: "#111" }}>
            {String(it.data.school || "School")}
          </Text>
          {(it.data.degree || it.data.field) ? (
            <Text style={{ fontSize: 11, color: "#444" }}>
              {[it.data.degree, it.data.field].filter(Boolean).join(", ") as string}
            </Text>
          ) : null}
        </>
      ))}

      {renderGroup("Projects", "projects", (it) => (
        <>
          <Text style={{ fontFamily: "SpaceGrotesk_700Bold", fontSize: 13, color: "#111" }}>
            {String(it.data.name || "Project")}
          </Text>
          {it.data.description ? (
            <Text style={{ fontSize: 11, color: "#222" }}>{String(it.data.description)}</Text>
          ) : null}
        </>
      ))}

      {(items.skills?.length ?? 0) > 0 ? (
        <View style={{ marginTop: 12 }}>
          <Text style={[styles.pvSectionTitle, { color: accent, borderColor: accent }]}>SKILLS</Text>
          <Text style={{ fontSize: 12, color: "#222", marginTop: 4 }}>
            {(items.skills ?? []).map((s) => String(s.data.name)).join(" · ")}
          </Text>
        </View>
      ) : null}

      {renderGroup("Certifications", "certifications", (it) => (
        <Text style={{ fontSize: 12, color: "#222" }}>
          {String(it.data.name || "")}{it.data.issuer ? ` — ${String(it.data.issuer)}` : ""}
        </Text>
      ))}

      {renderGroup("Awards", "awards", (it) => (
        <Text style={{ fontSize: 12, color: "#222" }}>{String(it.data.title || "")}</Text>
      ))}

      {(items.languages?.length ?? 0) > 0 ? (
        <View style={{ marginTop: 12 }}>
          <Text style={[styles.pvSectionTitle, { color: accent, borderColor: accent }]}>LANGUAGES</Text>
          <Text style={{ fontSize: 12, color: "#222", marginTop: 4 }}>
            {(items.languages ?? [])
              .map((s) => `${String(s.data.name)}${s.data.proficiency ? ` (${String(s.data.proficiency)})` : ""}`)
              .join(" · ")}
          </Text>
        </View>
      ) : null}

      {(items.links?.length ?? 0) > 0 ? (
        <View style={{ marginTop: 12 }}>
          <Text style={[styles.pvSectionTitle, { color: accent, borderColor: accent }]}>LINKS</Text>
          {(items.links ?? []).map((l) => (
            <Text key={l.id} style={{ fontSize: 11, color: "#1d4ed8", marginTop: 2 }}>
              {String(l.data.label)} — {String(l.data.url)}
            </Text>
          ))}
        </View>
      ) : null}
    </Card>
  );
}

// ── Helpers ─────────────────────────────────────────────────────

function useDebouncedEffect(effect: () => void, deps: unknown[], delay: number) {
  useEffect(() => {
    const t = setTimeout(effect, delay);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  body: { padding: 16, gap: 10, paddingBottom: 80 },
  sectionTitle: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 14,
    letterSpacing: 0.4,
    textTransform: "uppercase",
    marginTop: 14,
    marginBottom: 6,
  },
  sectionHeadRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  subhead: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 11,
    letterSpacing: 0.5,
    textTransform: "uppercase",
  },
  statusRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingHorizontal: 4,
    paddingTop: 4,
  },
  statusDot: { width: 8, height: 8, borderRadius: 4 },
  statusText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
  linkText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 13 },
  rowGap: { flexDirection: "row", gap: 8, marginTop: 4 },
  switchRow: { flexDirection: "row", alignItems: "center", paddingVertical: 6 },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
    borderWidth: 1,
    flexDirection: "row",
    alignItems: "center",
  },
  swatch: { width: 28, height: 28, borderRadius: 14, borderWidth: 2 },
  pvSectionTitle: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 11,
    letterSpacing: 1.5,
    paddingBottom: 2,
    borderBottomWidth: 1.2,
  },
  hint: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 11,
    lineHeight: 16,
    paddingHorizontal: 4,
    textAlign: "center",
    marginTop: 14,
  },
});
