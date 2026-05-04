import { WebFeatureRedirect } from "@/components/WebFeatureRedirect";

export default function LeaderboardScreen() {
  return (
    <WebFeatureRedirect
      title="Leaderboard"
      iconName="award"
      blurb="See top creators by referrals, clicks and engagement."
      webPath="/user/leaderboard"
    />
  );
}
