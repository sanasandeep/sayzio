import { Feather } from "@expo/vector-icons";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Stack, useFocusEffect, useLocalSearchParams, useRouter } from "expo-router";
import { useCallback, useRef } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  Platform,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from "react-native";

import { Button } from "@/components/Button";
import { EmptyState } from "@/components/EmptyState";
import { useColors } from "@/hooks/useColors";
import {
  cancelMySubscription,
  listMySubscriptions,
  resumeMySubscription,
  type SubscriptionState,
} from "@/lib/api/monetization";

/**
 * Native manage / cancel subscription screen (Task #3019). The
 * renewal-reminder notification deep-links a fan to the web page
 * /@{handle}/manage-subscription — nativeRouteFor routes here instead of
 * opening the in-app browser. Lists every creator subscription the fan
 * pays for and lets them cancel at period end or resume in-app. The
 * optional `handle` param (carried from the deep link) highlights the
 * relevant card so the fan lands on the right one.
 */
export default function ManageSubscriptionScreen() {
  const colors = useColors();
  const qc = useQueryClient();
  const router = useRouter();
  const { handle = "" } = useLocalSearchParams<{ handle?: string }>();
  const focusHandle = String(handle).replace(/^@/, "").toLowerCase();

  const q = useQuery({
    queryKey: ["my-subscriptions"],
    queryFn: listMySubscriptions,
  });

  // A tier switch happens in the creator's subscribe flow (often via the
  // provider's hosted checkout). Refetch when this screen regains focus so a
  // completed tier change is reflected, skipping the initial mount fetch.
  // Depend on the stable `refetch` reference, not the whole query result (`q`),
  // whose identity changes every render and would loop the focus effect.
  const { refetch } = q;
  const didMount = useRef(false);
  useFocusEffect(
    useCallback(() => {
      if (didMount.current) {
        refetch();
      } else {
        didMount.current = true;
      }
    }, [refetch]),
  );

  const cancel = useMutation({
    mutationFn: (h: string) => cancelMySubscription(h),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["my-subscriptions"] }),
    onError: (e: Error) =>
      Alert.alert("Couldn't cancel", e.message || "Please try again."),
  });

  const resume = useMutation({
    mutationFn: (h: string) => resumeMySubscription(h),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["my-subscriptions"] }),
    onError: (e: Error) =>
      Alert.alert("Couldn't resume", e.message || "Please try again."),
  });

  const confirmCancel = (sub: SubscriptionState) => {
    const h = sub.creator?.handle;
    if (!h) return;
    const name = sub.creator?.name || `@${h}`;
    const go = () => cancel.mutate(h);
    if (Platform.OS === "web") {
      if (confirm(`Cancel your subscription to ${name}?`)) go();
    } else {
      Alert.alert(
        "Cancel subscription?",
        `You'll keep access to ${name} until the current period ends.`,
        [
          { text: "Keep it", style: "cancel" },
          { text: "Cancel", style: "destructive", onPress: go },
        ],
      );
    }
  };

  const pendingHandle =
    cancel.isPending && cancel.variables
      ? cancel.variables
      : resume.isPending && resume.variables
        ? resume.variables
        : null;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <Stack.Screen options={{ title: "Manage subscriptions" }} />
      {q.isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.primary} />
        </View>
      ) : (
        <FlatList<SubscriptionState>
          data={q.data ?? []}
          keyExtractor={(s) => String(s.id)}
          contentContainerStyle={{ padding: 16, gap: 12 }}
          renderItem={({ item }) => (
            <SubscriptionCard
              sub={item}
              colors={colors}
              highlighted={
                !!focusHandle &&
                item.creator?.handle?.toLowerCase() === focusHandle
              }
              busy={pendingHandle === item.creator?.handle}
              onCancel={() => confirmCancel(item)}
              onResume={() =>
                item.creator?.handle && resume.mutate(item.creator.handle)
              }
              onSwitchTier={() =>
                item.creator?.handle &&
                router.push({
                  pathname: "/monetization/subscribe",
                  params: { handle: item.creator.handle },
                })
              }
            />
          )}
          ListEmptyComponent={
            <EmptyState
              icon="credit-card"
              title="No active subscriptions"
              body="When you subscribe to a creator, you can review and manage it here."
            />
          }
          refreshControl={
            <RefreshControl
              refreshing={q.isFetching && !q.isLoading}
              onRefresh={() => q.refetch()}
              tintColor={colors.primary}
            />
          }
        />
      )}
    </View>
  );
}

function SubscriptionCard({
  sub,
  colors,
  highlighted,
  busy,
  onCancel,
  onResume,
  onSwitchTier,
}: {
  sub: SubscriptionState;
  colors: ReturnType<typeof useColors>;
  highlighted: boolean;
  busy: boolean;
  onCancel: () => void;
  onResume: () => void;
  onSwitchTier: () => void;
}) {
  const creatorName = sub.creator?.name || sub.creator?.handle || "Creator";
  const renewLabel = sub.cancel_at_period_end ? "Ends" : "Renews";
  const renewDate = sub.current_period_end
    ? new Date(sub.current_period_end).toLocaleDateString()
    : "—";
  const price = `$${(sub.price_cents / 100).toFixed(2)} ${sub.currency.toUpperCase()}`;

  return (
    <View
      style={[
        styles.card,
        {
          backgroundColor: colors.card,
          borderColor: highlighted ? colors.primary : colors.border,
          borderWidth: highlighted ? 2 : 1,
          borderRadius: colors.radius,
        },
      ]}
    >
      <View style={styles.header}>
        {sub.creator?.avatar ? (
          <Image source={{ uri: sub.creator.avatar }} style={styles.avatar} />
        ) : (
          <View style={[styles.avatar, styles.avatarFallback, { backgroundColor: colors.primary + "1c" }]}>
            <Feather name="user" size={18} color={colors.primary} />
          </View>
        )}
        <View style={{ flex: 1 }}>
          <Text style={[styles.creator, { color: colors.foreground }]} numberOfLines={1}>
            {creatorName}
          </Text>
          <Text style={[styles.tier, { color: colors.mutedForeground }]} numberOfLines={1}>
            {sub.tier?.badge ? `${sub.tier.badge} ` : ""}
            {sub.tier?.name ?? "Subscriber"}
          </Text>
        </View>
      </View>

      <View style={styles.fields}>
        <Field label="Cycle" value={sub.billing_cycle} colors={colors} />
        <Field label="Price" value={price} colors={colors} />
        <Field label={renewLabel} value={renewDate} colors={colors} />
        <Field label="Status" value={sub.status_label} colors={colors} />
      </View>

      {sub.cancel_at_period_end ? (
        <>
          <View
            style={[
              styles.notice,
              { backgroundColor: colors.warning + "1f" },
            ]}
          >
            <Text style={[styles.noticeText, { color: colors.warning }]}>
              Your subscription will end on {renewDate}. You'll keep access until
              then.
            </Text>
          </View>
          <View style={{ marginTop: 12 }}>
            <Button label="Resume subscription" onPress={onResume} loading={busy} />
          </View>
        </>
      ) : (
        <View style={{ marginTop: 12 }}>
          <Button
            label="Cancel at period end"
            variant="ghost"
            onPress={onCancel}
            loading={busy}
          />
        </View>
      )}

      {sub.creator?.handle ? (
        <View style={{ marginTop: 8 }}>
          <Button label="Switch tier" variant="ghost" onPress={onSwitchTier} />
        </View>
      ) : null}
    </View>
  );
}

function Field({
  label,
  value,
  colors,
}: {
  label: string;
  value: string;
  colors: ReturnType<typeof useColors>;
}) {
  return (
    <View style={{ minWidth: 110 }}>
      <Text style={[styles.fieldLabel, { color: colors.mutedForeground }]}>
        {label}
      </Text>
      <Text style={[styles.fieldValue, { color: colors.foreground }]}>
        {value}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  card: { padding: 16 },
  header: { flexDirection: "row", alignItems: "center", gap: 12 },
  avatar: { width: 44, height: 44, borderRadius: 999 },
  avatarFallback: { alignItems: "center", justifyContent: "center" },
  creator: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 16 },
  tier: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13, marginTop: 2 },
  fields: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 14,
    marginTop: 14,
  },
  fieldLabel: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 11,
    textTransform: "uppercase",
    letterSpacing: 0.3,
  },
  fieldValue: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 14,
    marginTop: 2,
    textTransform: "capitalize",
  },
  notice: { marginTop: 14, padding: 10, borderRadius: 10 },
  noticeText: { fontFamily: "SpaceGrotesk_500Medium", fontSize: 12 },
});
