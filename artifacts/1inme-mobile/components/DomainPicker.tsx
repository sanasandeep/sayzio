import { Feather } from "@expo/vector-icons";
import { Pressable, StyleSheet, Text, View } from "react-native";

import { useColors } from "@/hooks/useColors";
import type { AvailableDomains } from "@/lib/api/domains";

// Lets the create/edit link flows pick which host a short link lives on.
// `null` means the platform default host (env fallback). The list mirrors
// the web create/edit form: own verified domains + admin-global domains,
// with the admin-chosen primary global domain flagged.
export function DomainPicker({
  value,
  onChange,
  data,
  loading,
}: {
  value: number | null;
  onChange: (domainId: number | null) => void;
  data?: AvailableDomains;
  loading?: boolean;
}) {
  const colors = useColors();
  const items = data?.items ?? [];
  const defaultHost = data?.default_host ?? "default domain";

  const options: { id: number | null; label: string; primary: boolean }[] = [
    { id: null, label: `${defaultHost} (default)`, primary: false },
    ...items.map((d) => ({
      id: d.id,
      label: d.domain,
      primary: d.is_global && d.is_primary,
    })),
  ];

  return (
    <View style={{ gap: 8 }}>
      <Text style={[styles.label, { color: colors.mutedForeground }]}>
        Domain
      </Text>
      {loading ? (
        <Text style={[styles.hint, { color: colors.mutedForeground }]}>
          Loading domains…
        </Text>
      ) : (
        <View style={styles.options}>
          {options.map((opt) => {
            const on = value === opt.id;
            return (
              <Pressable
                key={opt.id ?? "default"}
                onPress={() => onChange(opt.id)}
                style={[
                  styles.chip,
                  {
                    backgroundColor: on ? colors.primary + "22" : colors.card,
                    borderColor: on ? colors.primary : colors.border,
                    borderRadius: colors.radius,
                  },
                ]}
              >
                {on ? (
                  <Feather name="check" size={13} color={colors.primary} />
                ) : null}
                <Text
                  style={[
                    styles.chipText,
                    { color: on ? colors.primary : colors.foreground },
                  ]}
                  numberOfLines={1}
                >
                  {opt.label}
                </Text>
                {opt.primary ? (
                  <View
                    style={[
                      styles.badge,
                      { backgroundColor: colors.primary + "33" },
                    ]}
                  >
                    <Text style={[styles.badgeText, { color: colors.primary }]}>
                      primary
                    </Text>
                  </View>
                ) : null}
              </Pressable>
            );
          })}
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  label: {
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12,
    letterSpacing: 0.4,
    textTransform: "uppercase",
  },
  hint: { fontFamily: "SpaceGrotesk_400Regular", fontSize: 13 },
  options: { gap: 8 },
  chip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderWidth: 1,
  },
  chipText: { fontFamily: "SpaceGrotesk_600SemiBold", fontSize: 14, flexShrink: 1 },
  badge: {
    marginLeft: "auto",
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
  },
  badgeText: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 10,
    letterSpacing: 0.3,
    textTransform: "uppercase",
  },
});
