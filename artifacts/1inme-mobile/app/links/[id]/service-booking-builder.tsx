import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import * as ImagePicker from "expo-image-picker";
import { Stack, useLocalSearchParams } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from "react-native";

import { useColors } from "@/hooks/useColors";
import {
  createAvailabilityRule,
  createBlockedDate,
  createService,
  createServiceCategory,
  createStaffMember,
  deleteAvailabilityRule,
  deleteBlockedDate,
  deleteService,
  deleteServiceCategory,
  deleteStaffMember,
  getOwnerServiceBookingConfig,
  saveOwnerServiceBookingSettings,
  updateService,
  updateStaffMember,
  uploadServicePhoto,
  type OwnerServiceBookingConfig,
  type OwnerServiceItem,
  type OwnerStaffMember,
} from "@/lib/api/service-booking";

const DAY_LABELS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

export default function ServiceBookingBuilderScreen() {
  const colors = useColors();
  const params = useLocalSearchParams<{ id: string }>();
  const linkId = String(params.id ?? "");
  const qc = useQueryClient();

  const q = useQuery({
    queryKey: ["service-booking-config", linkId],
    queryFn: () => getOwnerServiceBookingConfig(linkId),
    enabled: linkId.length > 0,
  });

  const cfg = q.data;

  const refresh = (next: OwnerServiceBookingConfig) =>
    qc.setQueryData(["service-booking-config", linkId], next);
  const invalidate = () =>
    qc.invalidateQueries({ queryKey: ["service-booking-config", linkId] });

  if (q.isLoading) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Edit services" }} />
        <ActivityIndicator color={colors.primary} />
      </View>
    );
  }

  if (q.isError || !cfg) {
    return (
      <View style={[styles.center, { backgroundColor: colors.background }]}>
        <Stack.Screen options={{ title: "Edit services" }} />
        <Feather name="alert-circle" size={32} color={colors.mutedForeground} />
        <Text style={{ color: colors.mutedForeground, marginTop: 12 }}>
          This booking page could not be loaded.
        </Text>
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Edit services" }} />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 60 }}>
        <SettingsSection
          linkId={linkId}
          cfg={cfg}
          onSaved={refresh}
        />
        <ServicesSection
          linkId={linkId}
          cfg={cfg}
          onChanged={invalidate}
        />
        <StaffSection
          linkId={linkId}
          cfg={cfg}
          onChanged={invalidate}
        />
        <AvailabilitySection
          linkId={linkId}
          cfg={cfg}
          onChanged={invalidate}
        />
        <BlockedDatesSection
          linkId={linkId}
          cfg={cfg}
          onChanged={invalidate}
        />
      </ScrollView>
    </View>
  );
}

// ── Settings ─────────────────────────────────────────────────────

function SettingsSection({
  linkId,
  cfg,
  onSaved,
}: {
  linkId: string;
  cfg: OwnerServiceBookingConfig;
  onSaved: (next: OwnerServiceBookingConfig) => void;
}) {
  const colors = useColors();
  const [mode, setMode] = useState(cfg.mode);
  const [currency, setCurrency] = useState(cfg.currency);
  const [slotLen, setSlotLen] = useState(String(cfg.slot_length_minutes));
  const [lead, setLead] = useState(String(cfg.lead_time_minutes));
  const [maxDays, setMaxDays] = useState(String(cfg.max_days_ahead));
  const [taxEnabled, setTaxEnabled] = useState(cfg.tax.enabled);
  const [taxRate, setTaxRate] = useState(String(cfg.tax.rate));
  const [taxInclusive, setTaxInclusive] = useState(cfg.tax.inclusive);
  const [taxLabel, setTaxLabel] = useState(cfg.tax.label);
  const [bufBefore, setBufBefore] = useState(String(cfg.buffers?.before ?? 0));
  const [bufAfter, setBufAfter] = useState(String(cfg.buffers?.after ?? 0));
  const [allowCancel, setAllowCancel] = useState(
    cfg.self_service?.allow_cancel ?? true,
  );
  const [allowReschedule, setAllowReschedule] = useState(
    cfg.self_service?.allow_reschedule ?? true,
  );
  const [cutoffHours, setCutoffHours] = useState(
    String(cfg.self_service?.cutoff_hours ?? 24),
  );
  const [calSyncEnabled, setCalSyncEnabled] = useState(
    cfg.calendar_sync?.enabled ?? false,
  );
  const [calAccountId, setCalAccountId] = useState<number | null>(
    cfg.calendar_sync?.account_id ?? null,
  );

  const save = useMutation({
    mutationFn: () =>
      saveOwnerServiceBookingSettings(linkId, {
        mode,
        currency: currency.trim().toUpperCase() || "USD",
        slot_length_minutes: Number(slotLen) || 30,
        lead_time_minutes: Number(lead) || 0,
        max_days_ahead: Number(maxDays) || 30,
        tax_enabled: taxEnabled,
        tax_rate: Number(taxRate) || 0,
        tax_inclusive: taxInclusive,
        tax_label: taxLabel.trim() || "Tax",
        buffer_before_minutes: Number(bufBefore) || 0,
        buffer_after_minutes: Number(bufAfter) || 0,
        self_service_allow_cancel: allowCancel,
        self_service_allow_reschedule: allowReschedule,
        self_service_cutoff_hours: Number(cutoffHours) || 0,
        calendar_sync_enabled: calSyncEnabled,
        calendar_sync_account_id: calSyncEnabled ? calAccountId : null,
      }),
    onSuccess: onSaved,
  });

  return (
    <Card title="Settings">
      <Label>Accept booking requests</Label>
      <View style={styles.rowBetween}>
        <Text style={{ color: colors.mutedForeground, flex: 1, fontSize: 13 }}>
          {mode === "booking"
            ? "Visitors can request bookings."
            : "Display-only; no requests collected."}
        </Text>
        <Switch
          value={mode === "booking"}
          onValueChange={(v) => setMode(v ? "booking" : "display")}
        />
      </View>

      <Label>Currency</Label>
      <Input value={currency} onChangeText={setCurrency} autoCapitalize="characters" />

      <View style={styles.grid2}>
        <View style={{ flex: 1 }}>
          <Label>Slot length (min)</Label>
          <Input value={slotLen} onChangeText={setSlotLen} keyboardType="number-pad" />
        </View>
        <View style={{ flex: 1 }}>
          <Label>Lead time (min)</Label>
          <Input value={lead} onChangeText={setLead} keyboardType="number-pad" />
        </View>
      </View>

      <Label>Max days ahead</Label>
      <Input value={maxDays} onChangeText={setMaxDays} keyboardType="number-pad" />

      <View style={[styles.rowBetween, { marginTop: 14 }]}>
        <Label noMargin>Charge tax / GST</Label>
        <Switch value={taxEnabled} onValueChange={setTaxEnabled} />
      </View>
      {taxEnabled ? (
        <>
          <Label>Tax label</Label>
          <Input value={taxLabel} onChangeText={setTaxLabel} />
          <View style={styles.grid2}>
            <View style={{ flex: 1 }}>
              <Label>Rate (%)</Label>
              <Input value={taxRate} onChangeText={setTaxRate} keyboardType="decimal-pad" />
            </View>
            <View style={{ flex: 1, justifyContent: "flex-end" }}>
              <View style={[styles.rowBetween, { marginTop: 22 }]}>
                <Label noMargin>Inclusive</Label>
                <Switch value={taxInclusive} onValueChange={setTaxInclusive} />
              </View>
            </View>
          </View>
        </>
      ) : null}

      <View style={styles.grid2}>
        <View style={{ flex: 1 }}>
          <Label>Buffer before (min)</Label>
          <Input
            value={bufBefore}
            onChangeText={setBufBefore}
            keyboardType="number-pad"
          />
        </View>
        <View style={{ flex: 1 }}>
          <Label>Buffer after (min)</Label>
          <Input
            value={bufAfter}
            onChangeText={setBufAfter}
            keyboardType="number-pad"
          />
        </View>
      </View>

      <View style={[styles.rowBetween, { marginTop: 14 }]}>
        <Label noMargin>Visitors can cancel</Label>
        <Switch value={allowCancel} onValueChange={setAllowCancel} />
      </View>
      <View style={styles.rowBetween}>
        <Label noMargin>Visitors can reschedule</Label>
        <Switch value={allowReschedule} onValueChange={setAllowReschedule} />
      </View>
      {allowCancel || allowReschedule ? (
        <>
          <Label>Cutoff (hours before start)</Label>
          <Input
            value={cutoffHours}
            onChangeText={setCutoffHours}
            keyboardType="number-pad"
          />
        </>
      ) : null}

      {cfg.calendar_sync?.allowed ? (
        <>
          <View style={[styles.rowBetween, { marginTop: 14 }]}>
            <Label noMargin>Google Calendar sync</Label>
            <Switch value={calSyncEnabled} onValueChange={setCalSyncEnabled} />
          </View>
          {calSyncEnabled ? (
            (cfg.calendar_accounts ?? []).length === 0 ? (
              <Text
                style={{ color: colors.mutedForeground, fontSize: 12.5 }}
              >
                Connect a Google Calendar account on the web app first.
              </Text>
            ) : (
              <View style={[styles.dayRow, { marginTop: 6 }]}>
                {(cfg.calendar_accounts ?? []).map((a) => {
                  const active = calAccountId === a.id;
                  const label =
                    a.display_name ||
                    `${a.provider.charAt(0).toUpperCase()}${a.provider.slice(1)}${
                      a.account_email ? ` · ${a.account_email}` : ""
                    }`;
                  return (
                    <Pressable
                      key={a.id}
                      onPress={() => setCalAccountId(active ? null : a.id)}
                      style={[
                        styles.dayChip,
                        { borderColor: colors.border },
                        active && {
                          backgroundColor: colors.primary,
                          borderColor: colors.primary,
                        },
                      ]}
                    >
                      <Text
                        style={{
                          color: active ? "#fff" : colors.foreground,
                          fontSize: 12,
                          fontWeight: "600",
                        }}
                      >
                        {label}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
            )
          ) : null}
        </>
      ) : null}

      <Pressable
        disabled={save.isPending}
        onPress={() => save.mutate()}
        style={[styles.primaryBtn, { backgroundColor: colors.primary }]}
      >
        {save.isPending ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.primaryBtnText}>Save settings</Text>
        )}
      </Pressable>
      {save.isSuccess ? (
        <Text style={{ color: colors.success, marginTop: 8, fontSize: 12.5 }}>
          Saved.
        </Text>
      ) : null}
    </Card>
  );
}

// ── Services & categories ────────────────────────────────────────

function ServicesSection({
  linkId,
  cfg,
  onChanged,
}: {
  linkId: string;
  cfg: OwnerServiceBookingConfig;
  onChanged: () => void;
}) {
  const colors = useColors();
  const [catName, setCatName] = useState("");

  const addCat = useMutation({
    mutationFn: () => createServiceCategory(linkId, { name: catName.trim() }),
    onSuccess: () => {
      setCatName("");
      onChanged();
    },
  });
  const delCat = useMutation({
    mutationFn: (id: number) => deleteServiceCategory(linkId, id),
    onSuccess: onChanged,
  });

  const categories = cfg.categories;

  return (
    <Card title="Services">
      {categories.map((cat) => (
        <View key={cat.id} style={{ marginBottom: 16 }}>
          <View style={styles.rowBetween}>
            <Text style={[styles.catTitle, { color: colors.foreground }]}>
              {cat.name}
            </Text>
            <Pressable onPress={() => delCat.mutate(cat.id)} hitSlop={8}>
              <Feather name="trash-2" size={16} color={colors.destructive} />
            </Pressable>
          </View>
          {cat.services.map((s) => (
            <ServiceRow
              key={s.id}
              linkId={linkId}
              service={s}
              onChanged={onChanged}
            />
          ))}
          <ServiceAdder
            linkId={linkId}
            categoryId={cat.id}
            currency={cfg.currency}
            onChanged={onChanged}
          />
        </View>
      ))}

      {cfg.uncategorized.length > 0 ? (
        <View style={{ marginBottom: 16 }}>
          <Text style={[styles.catTitle, { color: colors.foreground }]}>
            Uncategorized
          </Text>
          {cfg.uncategorized.map((s) => (
            <ServiceRow
              key={s.id}
              linkId={linkId}
              service={s}
              onChanged={onChanged}
            />
          ))}
        </View>
      ) : null}

      <ServiceAdder
        linkId={linkId}
        categoryId={null}
        currency={cfg.currency}
        onChanged={onChanged}
      />

      <View style={[styles.divider, { borderColor: colors.border }]} />
      <Label>Add a category</Label>
      <View style={styles.rowInline}>
        <Input
          value={catName}
          onChangeText={setCatName}
          placeholder="e.g. Haircuts"
          style={{ flex: 1 }}
        />
        <Pressable
          disabled={!catName.trim() || addCat.isPending}
          onPress={() => addCat.mutate()}
          style={[styles.addBtn, { borderColor: colors.primary }]}
        >
          <Text style={{ color: colors.primary, fontWeight: "700" }}>Add</Text>
        </Pressable>
      </View>
    </Card>
  );
}

function ServiceRow({
  linkId,
  service,
  onChanged,
}: {
  linkId: string;
  service: OwnerServiceItem;
  onChanged: () => void;
}) {
  const colors = useColors();
  const del = useMutation({
    mutationFn: () => deleteService(linkId, service.id),
    onSuccess: onChanged,
  });
  const toggle = useMutation({
    mutationFn: () =>
      updateService(linkId, service.id, {
        is_unavailable: !service.is_unavailable,
      }),
    onSuccess: onChanged,
  });

  return (
    <View
      style={[
        styles.serviceRow,
        { borderColor: colors.border, backgroundColor: colors.background },
        service.is_unavailable && { opacity: 0.6 },
      ]}
    >
      {service.photo_url ? (
        <Image source={{ uri: service.photo_url }} style={styles.thumb} />
      ) : null}
      <View style={{ flex: 1 }}>
        <Text style={{ color: colors.foreground, fontWeight: "600" }}>
          {service.name}
        </Text>
        <Text style={{ color: colors.mutedForeground, fontSize: 12.5 }}>
          {service.currency || ""} {Number(service.price).toFixed(2)} ·{" "}
          {service.duration_minutes} min
        </Text>
      </View>
      <Pressable onPress={() => toggle.mutate()} hitSlop={8} style={{ marginRight: 14 }}>
        <Feather
          name={service.is_unavailable ? "eye-off" : "eye"}
          size={16}
          color={colors.mutedForeground}
        />
      </Pressable>
      <Pressable onPress={() => del.mutate()} hitSlop={8}>
        <Feather name="trash-2" size={16} color={colors.destructive} />
      </Pressable>
    </View>
  );
}

function ServiceAdder({
  linkId,
  categoryId,
  currency,
  onChanged,
}: {
  linkId: string;
  categoryId: number | null;
  currency: string;
  onChanged: () => void;
}) {
  const colors = useColors();
  const [open, setOpen] = useState(false);
  const [name, setName] = useState("");
  const [desc, setDesc] = useState("");
  const [price, setPrice] = useState("");
  const [duration, setDuration] = useState("30");
  const [capacity, setCapacity] = useState("1");
  const [photoUrl, setPhotoUrl] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);

  const add = useMutation({
    mutationFn: () =>
      createService(linkId, {
        category_id: categoryId,
        name: name.trim(),
        description: desc.trim() || null,
        price: Number(price) || 0,
        duration_minutes: Number(duration) || 30,
        capacity: Math.max(1, Number(capacity) || 1),
        photo_url: photoUrl,
      }),
    onSuccess: () => {
      setName("");
      setDesc("");
      setPrice("");
      setDuration("30");
      setCapacity("1");
      setPhotoUrl(null);
      setOpen(false);
      onChanged();
    },
  });

  async function pickPhoto() {
    const res = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      quality: 0.7,
    });
    if (res.canceled || !res.assets[0]) return;
    const asset = res.assets[0];
    setUploading(true);
    try {
      const url = await uploadServicePhoto(linkId, {
        uri: asset.uri,
        mime: asset.mimeType,
      });
      setPhotoUrl(url);
    } catch {
      // ignore upload error; user can retry
    } finally {
      setUploading(false);
    }
  }

  if (!open) {
    return (
      <Pressable
        onPress={() => setOpen(true)}
        style={[styles.ghostBtn, { borderColor: colors.border }]}
      >
        <Feather name="plus" size={15} color={colors.primary} />
        <Text style={{ color: colors.primary, fontWeight: "600", fontSize: 13 }}>
          Add service
        </Text>
      </Pressable>
    );
  }

  return (
    <View
      style={[
        styles.adderCard,
        { borderColor: colors.border, backgroundColor: colors.background },
      ]}
    >
      <Input value={name} onChangeText={setName} placeholder="Service name" />
      <Input
        value={desc}
        onChangeText={setDesc}
        placeholder="Description (optional)"
      />
      <View style={styles.grid2}>
        <View style={{ flex: 1 }}>
          <Input
            value={price}
            onChangeText={setPrice}
            placeholder={`Price (${currency})`}
            keyboardType="decimal-pad"
          />
        </View>
        <View style={{ flex: 1 }}>
          <Input
            value={duration}
            onChangeText={setDuration}
            placeholder="Minutes"
            keyboardType="number-pad"
          />
        </View>
      </View>
      <Input
        value={capacity}
        onChangeText={setCapacity}
        placeholder="Group size per slot (default 1)"
        keyboardType="number-pad"
      />
      <Pressable
        onPress={pickPhoto}
        style={[styles.ghostBtn, { borderColor: colors.border, marginTop: 4 }]}
      >
        {uploading ? (
          <ActivityIndicator color={colors.primary} size="small" />
        ) : (
          <>
            <Feather
              name={photoUrl ? "check" : "image"}
              size={15}
              color={colors.primary}
            />
            <Text style={{ color: colors.primary, fontSize: 13 }}>
              {photoUrl ? "Photo added" : "Add photo (optional)"}
            </Text>
          </>
        )}
      </Pressable>
      <View style={styles.rowInline}>
        <Pressable
          disabled={!name.trim() || add.isPending}
          onPress={() => add.mutate()}
          style={[styles.primaryBtn, { backgroundColor: colors.primary, flex: 1, marginTop: 8 }]}
        >
          {add.isPending ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.primaryBtnText}>Save service</Text>
          )}
        </Pressable>
        <Pressable
          onPress={() => setOpen(false)}
          style={[styles.cancelBtn, { borderColor: colors.border }]}
        >
          <Text style={{ color: colors.mutedForeground }}>Cancel</Text>
        </Pressable>
      </View>
    </View>
  );
}

// ── Staff / team ─────────────────────────────────────────────────

function StaffSection({
  linkId,
  cfg,
  onChanged,
}: {
  linkId: string;
  cfg: OwnerServiceBookingConfig;
  onChanged: () => void;
}) {
  const colors = useColors();
  const [name, setName] = useState("");
  const [title, setTitle] = useState("");
  const [email, setEmail] = useState("");

  const staff = cfg.staff ?? [];
  const cap = cfg.staff_cap ?? 0;
  const capReached = cap > 0 && staff.length >= cap;

  const add = useMutation({
    mutationFn: () =>
      createStaffMember(linkId, {
        name: name.trim(),
        title: title.trim() || null,
        email: email.trim() || null,
      }),
    onSuccess: () => {
      setName("");
      setTitle("");
      setEmail("");
      onChanged();
    },
  });
  const del = useMutation({
    mutationFn: (id: number) => deleteStaffMember(linkId, id),
    onSuccess: onChanged,
  });
  const toggle = useMutation({
    mutationFn: (m: OwnerStaffMember) =>
      updateStaffMember(linkId, m.id, { is_active: !m.is_active }),
    onSuccess: onChanged,
  });

  return (
    <Card title="Team members">
      {staff.length === 0 ? (
        <Text
          style={{ color: colors.mutedForeground, fontSize: 13, marginBottom: 10 }}
        >
          Add team members so visitors can pick who they book with. Assign
          services to each member on the web editor.
        </Text>
      ) : null}
      {staff.map((m) => (
        <View key={m.id} style={styles.rowBetween}>
          <View style={{ flex: 1 }}>
            <Text
              style={{
                color: colors.foreground,
                fontWeight: "600",
                opacity: m.is_active ? 1 : 0.5,
              }}
            >
              {m.name}
              {m.title ? ` · ${m.title}` : ""}
            </Text>
            <Text style={{ color: colors.mutedForeground, fontSize: 12 }}>
              {m.service_ids.length === 0
                ? "All services"
                : `${m.service_ids.length} service(s)`}
            </Text>
          </View>
          <Pressable
            onPress={() => toggle.mutate(m)}
            hitSlop={8}
            style={{ marginRight: 14 }}
          >
            <Feather
              name={m.is_active ? "eye" : "eye-off"}
              size={16}
              color={colors.mutedForeground}
            />
          </Pressable>
          <Pressable onPress={() => del.mutate(m.id)} hitSlop={8}>
            <Feather name="trash-2" size={16} color={colors.destructive} />
          </Pressable>
        </View>
      ))}

      {capReached ? (
        <Text style={{ color: colors.mutedForeground, fontSize: 12.5, marginTop: 8 }}>
          Your plan allows up to {cap} team member(s). Upgrade to add more.
        </Text>
      ) : (
        <>
          <View style={[styles.divider, { borderColor: colors.border }]} />
          <Label>Name</Label>
          <Input value={name} onChangeText={setName} placeholder="e.g. Priya" />
          <Label>Title (optional)</Label>
          <Input value={title} onChangeText={setTitle} placeholder="e.g. Senior stylist" />
          <Label>Notification email (optional)</Label>
          <Input
            value={email}
            onChangeText={setEmail}
            placeholder="member@example.com"
            keyboardType="email-address"
            autoCapitalize="none"
          />
          <Pressable
            disabled={!name.trim() || add.isPending}
            onPress={() => add.mutate()}
            style={[styles.primaryBtn, { backgroundColor: colors.primary }]}
          >
            {add.isPending ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.primaryBtnText}>Add team member</Text>
            )}
          </Pressable>
          {add.isError ? (
            <Text style={{ color: colors.destructive, marginTop: 8, fontSize: 12.5 }}>
              {(add.error as Error)?.message ?? "Could not add team member"}
            </Text>
          ) : null}
        </>
      )}
    </Card>
  );
}

// ── Availability ─────────────────────────────────────────────────

function AvailabilitySection({
  linkId,
  cfg,
  onChanged,
}: {
  linkId: string;
  cfg: OwnerServiceBookingConfig;
  onChanged: () => void;
}) {
  const colors = useColors();
  const [day, setDay] = useState(1);
  const [start, setStart] = useState("09:00");
  const [end, setEnd] = useState("17:00");
  const [staffId, setStaffId] = useState<number | null>(null);
  const staffList = cfg.staff ?? [];
  const staffName = (id: number | null) =>
    staffList.find((m) => m.id === id)?.name ?? "";

  const add = useMutation({
    mutationFn: () =>
      createAvailabilityRule(linkId, {
        day_of_week: day,
        start_time: start.trim(),
        end_time: end.trim(),
        staff_id: staffId,
      }),
    onSuccess: onChanged,
  });
  const del = useMutation({
    mutationFn: (id: number) => deleteAvailabilityRule(linkId, id),
    onSuccess: onChanged,
  });

  return (
    <Card title="Weekly availability">
      {cfg.availability_rules.length === 0 ? (
        <Text style={{ color: colors.mutedForeground, fontSize: 13, marginBottom: 10 }}>
          Add the hours you take bookings each day.
        </Text>
      ) : null}
      {cfg.availability_rules.map((r) => (
        <View key={r.id} style={styles.rowBetween}>
          <Text style={{ color: colors.foreground }}>
            {DAY_LABELS[r.day_of_week]} · {r.start_time}–{r.end_time}
            {r.staff_id ? ` · ${staffName(r.staff_id)}` : ""}
          </Text>
          <Pressable onPress={() => del.mutate(r.id)} hitSlop={8}>
            <Feather name="trash-2" size={16} color={colors.destructive} />
          </Pressable>
        </View>
      ))}

      <View style={[styles.divider, { borderColor: colors.border }]} />
      <Label>Day</Label>
      <View style={styles.dayRow}>
        {DAY_LABELS.map((d, i) => (
          <Pressable
            key={d}
            onPress={() => setDay(i)}
            style={[
              styles.dayChip,
              { borderColor: colors.border },
              day === i && { backgroundColor: colors.primary, borderColor: colors.primary },
            ]}
          >
            <Text
              style={{
                color: day === i ? "#fff" : colors.foreground,
                fontSize: 12,
                fontWeight: "600",
              }}
            >
              {d}
            </Text>
          </Pressable>
        ))}
      </View>
      <View style={styles.grid2}>
        <View style={{ flex: 1 }}>
          <Label>Start (HH:MM)</Label>
          <Input value={start} onChangeText={setStart} placeholder="09:00" />
        </View>
        <View style={{ flex: 1 }}>
          <Label>End (HH:MM)</Label>
          <Input value={end} onChangeText={setEnd} placeholder="17:00" />
        </View>
      </View>
      {staffList.length > 0 ? (
        <>
          <Label>Applies to</Label>
          <View style={styles.dayRow}>
            {[{ id: null as number | null, name: "Whole page" }]
              .concat(staffList.map((m) => ({ id: m.id as number | null, name: m.name })))
              .map((opt) => (
                <Pressable
                  key={opt.id ?? 0}
                  onPress={() => setStaffId(opt.id)}
                  style={[
                    styles.dayChip,
                    { borderColor: colors.border },
                    staffId === opt.id && {
                      backgroundColor: colors.primary,
                      borderColor: colors.primary,
                    },
                  ]}
                >
                  <Text
                    style={{
                      color: staffId === opt.id ? "#fff" : colors.foreground,
                      fontSize: 12,
                      fontWeight: "600",
                    }}
                  >
                    {opt.name}
                  </Text>
                </Pressable>
              ))}
          </View>
        </>
      ) : null}
      <Pressable
        disabled={add.isPending}
        onPress={() => add.mutate()}
        style={[styles.primaryBtn, { backgroundColor: colors.primary }]}
      >
        {add.isPending ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.primaryBtnText}>Add hours</Text>
        )}
      </Pressable>
      {add.isError ? (
        <Text style={{ color: colors.destructive, marginTop: 8, fontSize: 12.5 }}>
          {(add.error as Error)?.message ?? "Could not add hours"}
        </Text>
      ) : null}
    </Card>
  );
}

// ── Blocked dates ────────────────────────────────────────────────

function BlockedDatesSection({
  linkId,
  cfg,
  onChanged,
}: {
  linkId: string;
  cfg: OwnerServiceBookingConfig;
  onChanged: () => void;
}) {
  const colors = useColors();
  const [date, setDate] = useState("");
  const [reason, setReason] = useState("");
  const [staffId, setStaffId] = useState<number | null>(null);
  const staffList = cfg.staff ?? [];
  const staffName = (id: number | null) =>
    staffList.find((m) => m.id === id)?.name ?? "";

  const add = useMutation({
    mutationFn: () =>
      createBlockedDate(linkId, {
        date: date.trim(),
        reason: reason.trim() || null,
        staff_id: staffId,
      }),
    onSuccess: () => {
      setDate("");
      setReason("");
      setStaffId(null);
      onChanged();
    },
  });
  const del = useMutation({
    mutationFn: (id: number) => deleteBlockedDate(linkId, id),
    onSuccess: onChanged,
  });

  return (
    <Card title="Blocked dates">
      {cfg.blocked_dates.map((b) => (
        <View key={b.id} style={styles.rowBetween}>
          <Text style={{ color: colors.foreground }}>
            {b.date}
            {b.staff_id ? ` · ${staffName(b.staff_id)}` : ""}
            {b.reason ? ` · ${b.reason}` : ""}
          </Text>
          <Pressable onPress={() => del.mutate(b.id)} hitSlop={8}>
            <Feather name="trash-2" size={16} color={colors.destructive} />
          </Pressable>
        </View>
      ))}

      <View style={[styles.divider, { borderColor: colors.border }]} />
      <Label>Date (YYYY-MM-DD)</Label>
      <Input value={date} onChangeText={setDate} placeholder="2026-12-25" />
      <Label>Reason (optional)</Label>
      <Input value={reason} onChangeText={setReason} placeholder="Holiday" />
      {staffList.length > 0 ? (
        <>
          <Label>Applies to</Label>
          <View style={styles.dayRow}>
            {[{ id: null as number | null, name: "Whole page" }]
              .concat(staffList.map((m) => ({ id: m.id as number | null, name: m.name })))
              .map((opt) => (
                <Pressable
                  key={opt.id ?? 0}
                  onPress={() => setStaffId(opt.id)}
                  style={[
                    styles.dayChip,
                    { borderColor: colors.border },
                    staffId === opt.id && {
                      backgroundColor: colors.primary,
                      borderColor: colors.primary,
                    },
                  ]}
                >
                  <Text
                    style={{
                      color: staffId === opt.id ? "#fff" : colors.foreground,
                      fontSize: 12,
                      fontWeight: "600",
                    }}
                  >
                    {opt.name}
                  </Text>
                </Pressable>
              ))}
          </View>
        </>
      ) : null}
      <Pressable
        disabled={!date.trim() || add.isPending}
        onPress={() => add.mutate()}
        style={[styles.primaryBtn, { backgroundColor: colors.primary }]}
      >
        {add.isPending ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.primaryBtnText}>Block date</Text>
        )}
      </Pressable>
      {add.isError ? (
        <Text style={{ color: colors.destructive, marginTop: 8, fontSize: 12.5 }}>
          {(add.error as Error)?.message ?? "Could not block date"}
        </Text>
      ) : null}
    </Card>
  );
}

// ── Shared primitives ────────────────────────────────────────────

function Card({ title, children }: { title: string; children: React.ReactNode }) {
  const colors = useColors();
  return (
    <View
      style={[
        styles.card,
        { backgroundColor: colors.card, borderColor: colors.border },
      ]}
    >
      <Text style={[styles.cardTitle, { color: colors.foreground }]}>{title}</Text>
      {children}
    </View>
  );
}

function Label({
  children,
  noMargin,
}: {
  children: React.ReactNode;
  noMargin?: boolean;
}) {
  const colors = useColors();
  return (
    <Text
      style={[
        styles.label,
        { color: colors.mutedForeground },
        noMargin && { marginTop: 0 },
      ]}
    >
      {children}
    </Text>
  );
}

function Input({
  style,
  ...props
}: React.ComponentProps<typeof TextInput>) {
  const colors = useColors();
  return (
    <TextInput
      placeholderTextColor={colors.mutedForeground}
      {...props}
      style={[
        styles.input,
        { borderColor: colors.border, color: colors.foreground },
        style,
      ]}
    />
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  card: { borderWidth: 1, borderRadius: 16, padding: 16, marginBottom: 16 },
  cardTitle: { fontSize: 17, fontWeight: "800", marginBottom: 10 },
  label: { fontSize: 12.5, fontWeight: "600", marginTop: 12, marginBottom: 4 },
  input: {
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
  },
  rowBetween: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingVertical: 6,
  },
  rowInline: { flexDirection: "row", alignItems: "center", gap: 8, marginTop: 4 },
  grid2: { flexDirection: "row", gap: 12 },
  divider: { borderTopWidth: 1, marginVertical: 14 },
  catTitle: { fontSize: 15, fontWeight: "700", marginBottom: 6 },
  serviceRow: {
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    borderRadius: 12,
    padding: 10,
    marginBottom: 8,
    gap: 10,
  },
  thumb: { width: 40, height: 40, borderRadius: 8 },
  ghostBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    borderWidth: 1,
    borderStyle: "dashed",
    borderRadius: 12,
    paddingVertical: 10,
  },
  adderCard: { borderWidth: 1, borderRadius: 12, padding: 12, gap: 8, marginTop: 4 },
  addBtn: {
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 11,
    alignItems: "center",
    justifyContent: "center",
  },
  primaryBtn: {
    borderRadius: 999,
    paddingVertical: 13,
    alignItems: "center",
    justifyContent: "center",
    marginTop: 16,
  },
  primaryBtnText: { color: "#fff", fontWeight: "700", fontSize: 15 },
  cancelBtn: {
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 18,
    paddingVertical: 13,
    alignItems: "center",
    justifyContent: "center",
    marginTop: 8,
  },
  dayRow: { flexDirection: "row", gap: 6, flexWrap: "wrap" },
  dayChip: {
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 7,
  },
});
