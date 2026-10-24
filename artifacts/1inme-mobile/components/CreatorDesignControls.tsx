import { Pressable, Text, View } from "react-native";
import { useColors } from "@/hooks/useColors";
import type { CreatorPresentation } from "@/lib/api/creatorProfile";
import type { Link } from "@/lib/api/links";

export function CreatorDesignControls(p: {
  presentation: CreatorPresentation;
  onPresentation: (p: CreatorPresentation) => void;
  primaryLink: number | null;
  onPrimaryLink: (id: number | null) => void;
  destination: "profile" | "redirect";
  onDestination: (v: "profile" | "redirect") => void;
  redirectLink: number | null;
  onRedirectLink: (id: number | null) => void;
  links: Link[];
}) {
  const colors = useColors();
  function choices<T extends string | number | null>(
    label: string,
    value: T,
    options: { value: T; label: string }[],
    select: (value: T) => void,
  ) {
    return (
      <View style={{ gap: 8 }}>
        <Text style={{ color: colors.foreground, fontWeight: "700" }}>
          {label}
        </Text>
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
          {options.map((o) => (
            <Pressable
              key={String(o.value)}
              accessibilityRole="radio"
              accessibilityState={{ checked: value === o.value }}
              aria-checked={value === o.value}
              accessibilityLabel={`${label}: ${o.label}`}
              onPress={() => select(o.value)}
              style={{
                padding: 12,
                borderRadius: colors.radius,
                borderWidth: 1,
                borderColor: value === o.value ? colors.primary : colors.border,
                backgroundColor: colors.card,
              }}
            >
              <Text
                style={{
                  color: value === o.value ? colors.primary : colors.foreground,
                }}
              >
                {o.label}
              </Text>
            </Pressable>
          ))}
        </View>
      </View>
    );
  }
  function presentation<K extends keyof CreatorPresentation>(
    key: K,
    value: CreatorPresentation[K],
  ) {
    p.onPresentation({ ...p.presentation, [key]: value });
  }
  const links = p.links.filter((l) => l.is_active);
  return (
    <View style={{ gap: 20 }}>
      {choices(
        "Profile layout",
        p.presentation.layout,
        [
          { value: "classic", label: "Classic · left portrait" },
          { value: "centered", label: "Spotlight · centered" },
          { value: "editorial", label: "Editorial · right portrait" },
          { value: "minimal", label: "Compact · inline" },
        ],
        (v) => presentation("layout", v),
      )}
      {choices(
        "Gradient",
        p.presentation.theme,
        [
          { value: "paper", label: "Aurora" },
          { value: "warm", label: "Sunset" },
          { value: "mint", label: "Lagoon" },
          { value: "midnight", label: "Twilight" },
        ],
        (v) => presentation("theme", v),
      )}
      {choices(
        "Card appearance",
        p.presentation.mode,
        [
          { value: "light", label: "Light" },
          { value: "dark", label: "Dark" },
        ],
        (v) => presentation("mode", v),
      )}
      {choices(
        "Photo shape",
        p.presentation.avatar_shape,
        [
          { value: "circle", label: "Circle" },
          { value: "rounded", label: "Rounded" },
          { value: "square", label: "Square" },
        ],
        (v) => presentation("avatar_shape", v),
      )}
      {choices(
        "Photo size",
        p.presentation.avatar_size,
        [
          { value: 80, label: "Small" },
          { value: 96, label: "Medium" },
          { value: 128, label: "Large" },
        ],
        (v) => presentation("avatar_size", v),
      )}
      {choices(
        "When someone opens your handle",
        p.destination,
        [
          { value: "profile", label: "Show creator profile" },
          { value: "redirect", label: "Redirect to a link" },
        ],
        p.onDestination,
      )}
      {p.destination === "redirect"
        ? choices(
            "Redirect link",
            p.redirectLink,
            [
              { value: null, label: "Choose a link" },
              ...links.map((l) => ({ value: l.id, label: l.title || l.alias })),
            ],
            p.onRedirectLink,
          )
        : choices(
            "Main biolink button",
            p.primaryLink,
            [
              { value: null, label: "Automatic · first active biolink" },
              ...links
                .filter((l) =>
                  [
                    "biolink",
                    "conversational",
                    "slides",
                    "ai_chat",
                    "restaurant_menu",
                    "store_menu",
                    "service_booking",
                    "salon_spa",
                    "contact_directory",
                    "real_estate",
                    "education",
                    "community",
                    "nonprofit",
                  ].includes(l.type),
                )
                .map((l) => ({ value: l.id, label: l.title || l.alias })),
            ],
            p.onPrimaryLink,
          )}
      <Text style={{ color: colors.mutedForeground }}>
        Your design stays saved in redirect mode. Only active links from this
        workspace can be selected.
      </Text>
    </View>
  );
}
