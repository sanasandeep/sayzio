import { Text, View } from "react-native";

import { useColors } from "@/hooks/useColors";

/**
 * Shared "cost + balance + disabled trigger" affordability pattern for
 * coin-charged AI features (Task #4178). The mobile analytics screen's
 * audience-estimate warning pioneered this; every coin-spending surface
 * (QR art, AI builder, Ask Coach, Marketing Strategist, …) should use
 * the same hook + hint so a creator never hits a dead-end tap that only
 * fails AFTER the run with an "insufficient credits" error.
 *
 * Server contract: the feature's loader endpoint exposes the worst-case
 * coin cost plus the caller's wallet balance (the analytics payload's
 * `audience_estimate_coins` + `coin_balance` pattern). Pass those two
 * numbers here; `null` balance (older server) disables the check.
 */
export function insufficientCoins(
  cost: number | null | undefined,
  balance: number | null | undefined,
): boolean {
  return (
    typeof cost === "number" &&
    cost > 0 &&
    typeof balance === "number" &&
    balance < cost
  );
}

export function CoinCostHint({
  cost,
  balance,
  actionLabel = "this",
  verb = "run",
  testID,
}: {
  /** Worst-case coins one run may spend (0/null hides the hint). */
  cost: number | null | undefined;
  /** Caller's wallet balance; null when the server didn't send one. */
  balance: number | null | undefined;
  /** Feature noun for the warning line, e.g. "this scan", "this artwork". */
  actionLabel?: string;
  /** Verb for the warning line, e.g. "run", "generate". */
  verb?: string;
  testID?: string;
}) {
  const colors = useColors();
  if (typeof cost !== "number" || cost <= 0) return null;

  const short = insufficientCoins(cost, balance);

  return (
    <View style={{ gap: 4 }}>
      <Text
        style={{ color: colors.mutedForeground, fontSize: 11 }}
        testID={testID ? `${testID}-cost` : undefined}
      >
        Uses up to {cost} coin{cost === 1 ? "" : "s"} per {verb}
        {typeof balance === "number"
          ? ` · Balance: ${balance} coin${balance === 1 ? "" : "s"}`
          : ""}
      </Text>
      {short ? (
        <Text
          style={{ color: "#fbbf24", fontSize: 12 }}
          testID={testID ? `${testID}-insufficient` : "text-insufficient-coins"}
        >
          You don&apos;t have enough coins to {verb} {actionLabel}. Top up
          coins in your wallet first.
        </Text>
      ) : null}
    </View>
  );
}
