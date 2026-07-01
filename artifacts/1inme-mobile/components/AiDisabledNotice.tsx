import { Feather } from "@expo/vector-icons";
import { ScrollView, StyleSheet, Text, View } from "react-native";

import { useColors } from "@/hooks/useColors";

/**
 * Per-feature one-liners mirroring the web app's
 * `resources/views/user/ai/disabled.blade.php` so a user who opens an
 * AI surface on mobile gets the exact same explanation of what that
 * particular feature does, even while the engine is switched off.
 */
const FEATURE_BLURBS: Record<string, string> = {
  "Note Summarizer":
    "Note Summarizer is your personal AI knowledge base — it learns from your sources so the assistant can answer in your voice.",
  "AI Knowledge Bases":
    "AI Knowledge Bases let you build and manage several AI knowledge bases, each trained on its own set of sources.",
  "AI Persona Generator":
    "AI Persona Generator shapes the tone and personality your AI uses when it writes or replies on your behalf.",
  "AI Agents":
    "AI Agents let you create and switch between different AI voices for different audiences.",
  "AI Chat":
    "AI Chat is a chat assistant that helps you draft content and answer questions about your account.",
  "Chat Widgets":
    "Chat Widgets are embeddable AI chatbots you can drop into your pages, widgets and inbox.",
  "Growth Coach":
    "Growth Coach gives you AI-powered suggestions to grow and fine-tune your links and pages.",
  "AI Coach":
    "AI Coach lets you chat with an AI advisor for tips on improving your account.",
  "Marketing Strategist":
    "AI Marketing Strategist is your AI digital performer — it grounds an organic + paid plan in your own Sayzio data and lets you act on it with one tap.",
  Voice:
    "Voice lets you talk to Sayzio hands-free to look things up and get around the app.",
  "Performer Specialist":
    "Performer Specialist studies your links, audience and brand to draft a full marketing strategy — organic and paid plays with one-tap actions.",
};

export function aiFeatureBlurb(feature?: string): string | null {
  if (!feature) return null;
  return FEATURE_BLURBS[feature] ?? null;
}

type Props = {
  /** Feature label used to pick the matching one-liner (e.g. "Account Assistant"). */
  feature?: string;
  /**
   * "engine" — the admin-controlled master switch is off (default).
   * "plan"   — the engine is on but this user's plan doesn't unlock it.
   */
  variant?: "engine" | "plan";
  /** Render in a tight padding mode for use inside sheets/modals. */
  compact?: boolean;
};

/**
 * Friendly "AI is off" explainer for the mobile app, mirroring the web
 * disabled page. The "engine" variant explains that AI is gated behind
 * an admin master switch the user can't flip themselves; the "plan"
 * variant explains it's gated by their plan instead.
 */
export function AiDisabledNotice({
  feature,
  variant = "engine",
  compact = false,
}: Props) {
  const colors = useColors();
  const blurb = aiFeatureBlurb(feature);
  const label = feature ?? "AI features";

  const heading =
    variant === "plan"
      ? `${label} isn’t included on your plan yet`
      : "AI features are currently turned off";

  const message =
    variant === "plan"
      ? `${label} is available on Sayzio, but your current plan doesn’t unlock it yet. Manage your plan on the web app to switch it on.`
      : "The AI engine isn’t enabled on this account right now. AI is controlled by a master switch that only an administrator can turn on. Once it’s switched on, this feature will be ready to use here.";

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: colors.background }}
      contentContainerStyle={[
        styles.container,
        compact && { paddingVertical: 8, paddingHorizontal: 0 },
      ]}
    >
      <View
        style={[
          styles.card,
          {
            backgroundColor: colors.card,
            borderColor: colors.border,
            borderRadius: colors.radius,
          },
        ]}
      >
        <View style={[styles.iconWrap, { backgroundColor: colors.primary + "1c" }]}>
          <Feather name="cpu" size={26} color={colors.primary} />
        </View>

        <Text style={[styles.heading, { color: colors.foreground }]}>
          {heading}
        </Text>
        <Text style={[styles.message, { color: colors.mutedForeground }]}>
          {message}
        </Text>

        {blurb ? (
          <View style={styles.blurbRow}>
            <Feather name="info" size={13} color={colors.primary} />
            <Text style={[styles.blurb, { color: colors.primary }]}>{blurb}</Text>
          </View>
        ) : null}

        {variant === "engine" ? (
          <View
            style={[
              styles.infoBox,
              {
                backgroundColor: colors.background,
                borderColor: colors.border,
                borderRadius: colors.radius - 4,
              },
            ]}
          >
            <Text style={[styles.infoTitle, { color: colors.foreground }]}>
              What you’re missing
            </Text>
            <Text style={[styles.infoBody, { color: colors.mutedForeground }]}>
              AI features on Sayzio — like AI Knowledge Bases, AI Agents, AI Chat
              and Growth Coach — help you draft content, answer questions about your account
              and build pages faster. They run on your coin balance once an
              administrator enables the engine.
            </Text>
            <Text style={[styles.infoBody, { color: colors.mutedForeground }]}>
              You can’t switch this on yourself — it’s controlled by an
              administrator. Ask them to turn AI on for your account.
            </Text>
          </View>
        ) : null}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flexGrow: 1,
    justifyContent: "center",
    padding: 20,
  },
  card: {
    borderWidth: 1,
    padding: 24,
    alignItems: "center",
    gap: 10,
  },
  iconWrap: {
    width: 56,
    height: 56,
    borderRadius: 18,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 4,
  },
  heading: {
    fontFamily: "SpaceGrotesk_700Bold",
    fontSize: 17,
    textAlign: "center",
  },
  message: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 13,
    lineHeight: 19,
    textAlign: "center",
  },
  blurbRow: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 6,
    marginTop: 4,
    paddingHorizontal: 4,
  },
  blurb: {
    flex: 1,
    fontFamily: "SpaceGrotesk_500Medium",
    fontSize: 12.5,
    lineHeight: 18,
  },
  infoBox: {
    borderWidth: 1,
    padding: 14,
    gap: 8,
    marginTop: 10,
    alignSelf: "stretch",
  },
  infoTitle: {
    fontFamily: "SpaceGrotesk_600SemiBold",
    fontSize: 13,
  },
  infoBody: {
    fontFamily: "SpaceGrotesk_400Regular",
    fontSize: 12.5,
    lineHeight: 18,
  },
});
