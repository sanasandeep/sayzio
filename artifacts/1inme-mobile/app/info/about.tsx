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
      eefind={{
        eyebrow: "Part of EEFind",
        heading: "Built by EEFind Private Limited",
        body: '1INME is a brand and product of EEFIND PVT LTD (EEFind Private Limited) — an aggregator marketplace on a mission to be "The All in One App for everything essential." From groceries home-delivered by neighbourhood stores to trusted home help like carpentry, plumbing and home cleaning, EEFind brings everyday essentials together in one place. Their promise sums up the philosophy 1INME is built on: "We are not in a hurry to deliver in 10 mins. We drive safe."',
        stats: [
          { value: "4,000+", label: "Products" },
          { value: "2,000+", label: "Merchants" },
          { value: "35+", label: "Cities live" },
        ],
        address:
          "8 Amrutha Nilayam, Banjara Hills, Hyderabad, Telangana 500034",
        email: "support@eefind.com",
        whatsapp: "+91 81210 57755",
        website: "eefind.com",
        websiteUrl: "https://eefind.com",
      }}
    />
  );
}
