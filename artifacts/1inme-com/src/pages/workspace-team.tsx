import { PageLayout } from "@/components/layout/page-layout";
import {
  MarketingHero,
  SectionHeading,
  FeatureGrid,
  CTABand,
} from "@/components/marketing/marketing";
import { SIGNUP_URL, PRICING_URL } from "@/config";
import { Users, FolderKanban, ShieldCheck, History, Palette, KeyRound } from "lucide-react";

const items = [
  { icon: FolderKanban, name: "Projects & workspaces", description: "Group links, Link in Bio pages and assets into projects, then switch context in one click — keep client and personal work cleanly separated." },
  { icon: Users, name: "Invite your team", description: "Bring teammates into a workspace with the right access, so everyone works from the same source of truth." },
  { icon: ShieldCheck, name: "Roles & permissions", description: "Owner, admin and member roles control who can publish, edit and view — no more accidental edits." },
  { icon: History, name: "Activity & audit log", description: "See who changed what and when across every workspace, with a clear trail for accountability." },
  { icon: Palette, name: "Shared brand kit", description: "Lock fonts, colours and logos at the workspace level so everything your team ships stays on brand." },
  { icon: KeyRound, name: "Centralised billing", description: "One plan covers the whole workspace, with seats and limits managed from a single place." },
];

export default function WorkspaceTeam() {
  return (
    <PageLayout
      title="Workspaces & teams"
      description="Projects, roles, shared brand kits and an audit log — everything a team needs to ship on brand from one workspace."
    >
      <MarketingHero
        eyebrow="Workspaces & teams"
        title="Built for teams &"
        highlight="agencies."
        subtitle="Organise work into projects, invite your team with the right roles, and keep everything on brand — all from one workspace."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "See pricing", href: PRICING_URL }}
      />

      <section className="py-12">
        <div className="container mx-auto px-6">
          <SectionHeading eyebrow="What you get" title="Collaboration without the chaos." />
          <FeatureGrid items={items} />
        </div>
      </section>

      <CTABand
        title="Bring your whole team along."
        subtitle="Free forever to start. Add workspaces and seats as you grow."
        primary={{ label: "Get started free", href: SIGNUP_URL }}
        secondary={{ label: "Compare all plans", href: PRICING_URL }}
      />
    </PageLayout>
  );
}
