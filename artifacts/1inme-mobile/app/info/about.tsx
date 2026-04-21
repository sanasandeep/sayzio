import { InfoPage } from "@/components/InfoPage";

export default function About() {
  return (
    <InfoPage
      title="About 1INME"
      intro="1INME is the modern way to gather every link, contact, and channel that represents you into a single tap-shareable profile — on the web and in your pocket."
      sections={[
        {
          heading: "Built for creators and teams",
          body: "Whether you're a solo creator sharing a link tree, a small business publishing a digital storefront, or an enterprise distributing employee profiles by NFC card, 1INME gives you one canonical link that works everywhere.",
        },
        {
          heading: "One account, every surface",
          body: "Sign in once and your profile, links, contacts, and analytics stay in sync between the 1INME web dashboard and this mobile app.",
        },
        {
          heading: "Made for sharing",
          body: "Built-in NFC writer, QR codes, universal links, and a fast in-app dialer turn every moment into an opportunity to connect.",
        },
      ]}
    />
  );
}
