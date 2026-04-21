import { useColorScheme as useSystemColorScheme } from "react-native";

import colors from "@/constants/colors";
import { useThemePreference } from "@/contexts/ThemeContext";

export function useResolvedScheme(): "light" | "dark" {
  const system = useSystemColorScheme();
  const pref = useThemePreference();
  if (pref === "system") return system === "dark" ? "dark" : "light";
  return pref;
}

export function useColors() {
  const scheme = useResolvedScheme();
  const palette = scheme === "dark" ? colors.dark : colors.light;
  return { ...palette, radius: colors.radius, scheme };
}
