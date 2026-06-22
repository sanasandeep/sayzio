import { useEffect, useState } from "react";
import { Image, StyleSheet, View } from "react-native";

import { useColorScheme } from "react-native";

import { fetchBrandLogos } from "@/lib/api/branding";

const wordmarkLight = require("../assets/images/wordmark-dark-text.png");
const wordmarkDark = require("../assets/images/wordmark-white-text.png");

export function BrandWordmark({
  size = 36,
  align = "left",
}: {
  size?: number;
  /** Horizontal alignment within the parent container. */
  align?: "left" | "center" | "right";
}) {
  const scheme = useColorScheme();
  const [remote, setRemote] = useState<{
    light: string | null;
    dark: string | null;
  } | null>(null);

  useEffect(() => {
    let alive = true;
    fetchBrandLogos().then((logos) => {
      if (alive && logos) {
        setRemote({ light: logos.logoLight, dark: logos.logoDark });
      }
    });
    return () => {
      alive = false;
    };
  }, []);

  const isDark = scheme === "dark";
  // Admin logo wins when available; bundled PNG is the offline/unset fallback.
  const remoteUri = isDark ? remote?.dark : remote?.light;
  const fallback = isDark ? wordmarkDark : wordmarkLight;
  const source = remoteUri ? { uri: remoteUri } : fallback;

  const height = size;
  const width = size * 3.4;
  const aligned = align !== "left";
  const justifyContent =
    align === "center" ? "center" : align === "right" ? "flex-end" : "flex-start";
  return (
    <View
      style={[
        styles.row,
        aligned ? { width: "100%", justifyContent } : null,
      ]}
    >
      <Image
        source={source}
        style={{ width, height }}
        resizeMode="contain"
        accessibilityLabel="1INME"
      />
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: "row", alignItems: "center" },
});
