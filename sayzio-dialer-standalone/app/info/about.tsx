import { useEffect, useState } from "react";

import {
  InfoPage,
  type EefindBlock,
  type FounderBlock,
  type InfoSection,
} from "@/components/InfoPage";
import { getBaseUrl } from "@/lib/api";
import { fetchAboutContent } from "@/lib/api/siteContent";

const INTRO =
  "Sayzio is the modern way to gather every link, contact, and channel that represents you into a single tap-shareable profile — on the web and in your pocket.";

// Static fallback copy used until (and if) the backend content loads. These
// sections are the mobile-specific About copy (shown when offline / the
// endpoint is unavailable); the backend swaps in the admin-editable web
// sections once loaded.
const FALLBACK_SECTIONS: InfoSection[] = [
  {
    heading: "Built for creators and teams",
    body: "Whether you're a solo creator sharing a link tree, a small business publishing a digital storefront, or an enterprise distributing employee profiles by NFC card, Sayzio gives you one canonical link that works everywhere.",
  },
  {
    heading: "One account, every surface",
    body: "Sign in once and your profile, links, contacts, and analytics stay in sync between the Sayzio web dashboard and this mobile app.",
  },
  {
    heading: "Made for sharing",
    body: "Built-in NFC writer, QR codes, universal links, and a fast in-app dialer turn every moment into an opportunity to connect.",
  },
];

// "Meet the founder" spotlight, mirroring the web/marketing About page
// (SitePagesContent::aboutExtraDefault() → 'founder'). The runtime About
// content endpoint does not yet return founder copy, so this stays static.
const FOUNDER: FounderBlock = {
  eyebrow: "Meet the founder",
  name: "Sandeep Sana",
  role: "Founder & CEO",
  photo: `${getBaseUrl()}/images/marketing/about/founder.png`,
  bio: "Guided by this belief, Sandeep Sana, Founder & CEO of Sayzio, has dedicated more than 16 years to building digital products that empower businesses and creators. His journey from developer to entrepreneur led to the creation of Sayzio, an all-in-one platform that helps users build their digital identity, engage audiences, and unlock new growth opportunities. Through innovation and a relentless focus on user needs, he continues to shape solutions that make online success more accessible to everyone.",
};

const FALLBACK_EEFIND: EefindBlock = {
  eyebrow: "Part of EEFind",
  heading: "Built by EEFind Private Limited",
  body: 'Sayzio is a brand and product of EEFIND PVT LTD (EEFind Private Limited) — an aggregator marketplace on a mission to be "The All in One App for everything essential." From groceries home-delivered by neighbourhood stores to trusted home help like carpentry, plumbing and home cleaning, EEFind brings everyday essentials together in one place. Their promise sums up the philosophy Sayzio is built on: "We are not in a hurry to deliver in 10 mins. We drive safe."',
  address: "8 Amrutha Nilayam, Banjara Hills, Hyderabad, Telangana 500034",
  email: "support@eefind.com",
  whatsapp: "+91 81210 57755",
  website: "eefind.com",
  websiteUrl: "https://eefind.com",
};

export default function About() {
  const [sections, setSections] = useState<InfoSection[]>(FALLBACK_SECTIONS);
  const [eefind, setEefind] = useState<EefindBlock>(FALLBACK_EEFIND);

  useEffect(() => {
    let active = true;
    fetchAboutContent()
      .then((content) => {
        if (!active || !content) return;
        if (content.sections.length > 0) setSections(content.sections);
        setEefind(content.eefind);
      })
      .catch(() => {});
    return () => {
      active = false;
    };
  }, []);

  return (
    <InfoPage
      title="About Sayzio"
      intro={INTRO}
      sections={sections}
      founder={FOUNDER}
      eefind={eefind}
    />
  );
}
