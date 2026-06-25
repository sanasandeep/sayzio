import { InfoPage } from "@/components/InfoPage";

export default function Terms() {
  return (
    <InfoPage
      title="Terms of Service"
      intro="By creating a Sayzio account or using this app you agree to the following terms. The full legal version lives at https://1inme.com/terms."
      sections={[
        {
          heading: "Your account",
          body: "You are responsible for the activity on your account and for keeping your sign-in credentials secure. Demo accounts are shared resources and may be reset at any time.",
        },
        {
          heading: "Acceptable use",
          body: "Don't use Sayzio to harass others, distribute malware, infringe copyright, or facilitate illegal activity. We may suspend accounts that violate these rules.",
        },
        {
          heading: "Your content",
          body: "You retain ownership of links, posts, and media you publish. You grant us a limited license to host and display them so we can run the service.",
        },
        {
          heading: "Changes",
          body: "We may update these terms when the product changes. Material changes will be highlighted in-app the next time you sign in.",
        },
      ]}
    />
  );
}
