import { LinearGradient } from "expo-linear-gradient";
import { StyleSheet, Text, View } from "react-native";

import { getPaidPageTemplate, type PaidPageTemplate } from "@/lib/paidPage";

// A faithful mini-mock of the public Paid Page
// (resources/views/public/paid-page.blade.php) rendered from the same design
// tokens (mirrored in lib/paidPage.ts). Selecting a template in the mobile
// editor updates this preview live so owners see the real hero gradient,
// page background, and accent before saving — matching the web editor's
// live preview instead of just an accent swatch.

// Translucent hero overlay that approximates each template's `hero_style`
// ambient layer from the blade view. Static (no animation) so it stays a
// lightweight thumbnail.
function HeroOverlay({ template }: { template: PaidPageTemplate }) {
  const style = template.heroStyle;
  if (style === "aurora" || style === "glow") {
    return (
      <>
        <View
          style={[
            styles.orb,
            { top: -22, left: -16, width: 70, height: 70 },
          ]}
        />
        <View
          style={[
            styles.orb,
            {
              top: 8,
              right: -10,
              width: 84,
              height: 84,
              backgroundColor: "rgba(255,255,255,0.22)",
            },
          ]}
        />
      </>
    );
  }
  if (style === "wave") {
    return (
      <>
        <View
          style={[
            styles.orb,
            { bottom: -28, left: -14, width: 84, height: 84 },
          ]}
        />
        <View
          style={[
            styles.orb,
            {
              top: -20,
              right: 8,
              width: 66,
              height: 66,
              backgroundColor: "rgba(255,255,255,0.2)",
            },
          ]}
        />
      </>
    );
  }
  if (style === "spotlight") {
    return (
      <View
        style={[
          styles.orb,
          {
            top: -34,
            right: -24,
            width: 110,
            height: 110,
            backgroundColor: "rgba(255,255,255,0.28)",
          },
        ]}
      />
    );
  }
  // grid: faint crosshatch via two thin lines as a cheap approximation.
  return (
    <View style={styles.gridWrap} pointerEvents="none">
      {[0.25, 0.5, 0.75].map((p) => (
        <View
          key={`h${p}`}
          style={[styles.gridLine, { top: `${p * 100}%`, height: 1, left: 0, right: 0 }]}
        />
      ))}
      {[0.25, 0.5, 0.75].map((p) => (
        <View
          key={`v${p}`}
          style={[styles.gridLine, { left: `${p * 100}%`, width: 1, top: 0, bottom: 0 }]}
        />
      ))}
    </View>
  );
}

export function PaidPageTemplatePreview({
  templateId,
}: {
  templateId: string;
}) {
  const t = getPaidPageTemplate(templateId);
  const radius = Math.min(t.radius, 22);

  return (
    <LinearGradient
      colors={(t.pageBg.length >= 2 ? t.pageBg : [t.pageBg[0], t.pageBg[0]]) as [string, string, ...string[]]}
      start={{ x: 0.5, y: 0 }}
      end={{ x: 0.5, y: 1 }}
      style={styles.page}
    >
      {/* Hero banner */}
      <LinearGradient
        colors={(t.heroBg.length >= 2 ? t.heroBg : [t.heroBg[0], t.heroBg[0]]) as [string, string, ...string[]]}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={[styles.hero, { borderRadius: radius }]}
      >
        <HeroOverlay template={t} />
        <View style={styles.heroRow}>
          <View
            style={[
              styles.avatar,
              { borderRadius: Math.max(8, radius - 6) },
            ]}
          />
          <View style={{ flex: 1 }}>
            <View style={styles.titleBar} />
            <View style={styles.handleBar} />
          </View>
        </View>
        <View style={styles.ctaRow}>
          <View
            style={[
              styles.subscribePill,
              { backgroundColor: t.accent },
            ]}
          >
            <Text style={styles.subscribeText}>Subscribe</Text>
          </View>
          <View style={styles.glassPill} />
        </View>
      </LinearGradient>

      {/* Stats strip */}
      <View style={[styles.stats, { borderRadius: radius }]}>
        {[0, 1, 2].map((i) => (
          <View key={i} style={styles.statCell}>
            <View style={[styles.statNum, { backgroundColor: t.text }]} />
            <View style={[styles.statLabel, { backgroundColor: t.textMuted }]} />
          </View>
        ))}
      </View>

      {/* Sample post card */}
      <View
        style={[
          styles.card,
          { backgroundColor: t.cardBg, borderRadius: radius },
        ]}
      >
        <View style={[styles.cardLine, { backgroundColor: t.cardText, width: "70%" }]} />
        <View style={[styles.cardLine, { backgroundColor: t.cardText, opacity: 0.55, width: "95%" }]} />
        <View style={[styles.cardLine, { backgroundColor: t.cardText, opacity: 0.55, width: "85%" }]} />
        <View style={styles.cardFooter}>
          <View style={[styles.reactionChip, { backgroundColor: t.accentSoft }]} />
          <View style={[styles.reactionChip, { backgroundColor: t.accentSoft }]} />
        </View>
      </View>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  page: {
    padding: 12,
    gap: 10,
    overflow: "hidden",
  },
  hero: {
    overflow: "hidden",
    padding: 12,
    gap: 12,
    minHeight: 96,
  },
  orb: {
    position: "absolute",
    borderRadius: 999,
    backgroundColor: "rgba(255,255,255,0.32)",
  },
  gridWrap: {
    ...StyleSheet.absoluteFillObject,
  },
  gridLine: {
    position: "absolute",
    backgroundColor: "rgba(255,255,255,0.16)",
  },
  heroRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  avatar: {
    width: 40,
    height: 40,
    backgroundColor: "rgba(255,255,255,0.85)",
    borderWidth: 2,
    borderColor: "rgba(255,255,255,0.7)",
  },
  titleBar: {
    height: 10,
    width: "65%",
    borderRadius: 5,
    backgroundColor: "rgba(255,255,255,0.92)",
  },
  handleBar: {
    height: 7,
    width: "40%",
    borderRadius: 4,
    marginTop: 6,
    backgroundColor: "rgba(255,255,255,0.55)",
  },
  ctaRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  subscribePill: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
  },
  subscribeText: {
    color: "#fff",
    fontSize: 10,
    fontFamily: "SpaceGrotesk_700Bold",
  },
  glassPill: {
    height: 22,
    width: 54,
    borderRadius: 999,
    backgroundColor: "rgba(255,255,255,0.18)",
  },
  stats: {
    flexDirection: "row",
    backgroundColor: "rgba(255,255,255,0.08)",
    paddingVertical: 10,
    paddingHorizontal: 6,
  },
  statCell: {
    flex: 1,
    alignItems: "center",
    gap: 5,
  },
  statNum: {
    height: 9,
    width: 24,
    borderRadius: 3,
  },
  statLabel: {
    height: 5,
    width: 32,
    borderRadius: 2,
  },
  card: {
    padding: 12,
    gap: 7,
  },
  cardLine: {
    height: 7,
    borderRadius: 3.5,
    opacity: 0.85,
  },
  cardFooter: {
    flexDirection: "row",
    gap: 6,
    marginTop: 4,
  },
  reactionChip: {
    height: 16,
    width: 38,
    borderRadius: 999,
  },
});
