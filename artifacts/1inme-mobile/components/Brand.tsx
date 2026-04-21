import { Image, StyleSheet, View } from "react-native";

import { useColorScheme } from "react-native";

const wordmarkLight = require("../assets/images/wordmark-dark-text.png");
const wordmarkDark = require("../assets/images/wordmark-white-text.png");

export function BrandWordmark({ size = 36 }: { size?: number }) {
  const scheme = useColorScheme();
  const source = scheme === "dark" ? wordmarkDark : wordmarkLight;
  const height = size;
  const width = size * 3.4;
  return (
    <View style={styles.row}>
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
