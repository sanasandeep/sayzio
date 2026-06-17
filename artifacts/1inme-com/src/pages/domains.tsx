import { PageLayout } from "@/components/layout/page-layout";
import {
  MarketingHero,
  SectionHeading,
  FeatureGrid,
  CTABand,
} from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { Link2, Globe, QrCode, Tag, Filter, ShieldCheck } from "lucide-react";

const items = [
  { icon: Link2, name: "Branded short links", description: "Turn long URLs into clean, on-brand short links you can repoint any time — no reprinting needed." },
  { icon: Globe, name: "Custom domains", description: "Connect your own domain or subdomain via a CNAME and we provision a free SSL certificate automatically." },
  { icon: QrCode, name: "Dynamic QR codes", description: "Every link gets a styled, scannable QR you can repoint forever — perfect for packaging and print." },
  { icon: Tag, name: "Free branded domains", description: "1in.me, bizs.club, getbio.one and Sayzio.app — ready to use with zero DNS setup." },
  { icon: Filter, name: "Smart routing & rules", description: "Route by country, device or language, add UTMs automatically, and expire links by date or click count." },
  { icon: ShieldCheck, name: "Connection health", description: "Each custom domain shows its verification status so you always know exactly when it's ready." },
];

export default function Domains() {
  return (
    <PageLayout
      title="Domains & links"
      description="Branded short links, dynamic QR codes and custom domains — repointable any time, with free SSL and smart routing."
    >
      <MarketingHero
        eyebrow="Domains & links"
        title="Your links, on your"
        highlight="brand."
        subtitle="Branded short links, dynamic QR codes and custom domains with free SSL — all repointable any time, all under one login."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      />

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="What you get" title="A branded experience, end to end." />
          <FeatureGrid items={items} />
        </div>
      </section>

      <CTABand
        title="Put your brand on every link."
        subtitle="Free forever to start. Custom domains and SSL included on paid plans."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
