import { Link, useLocation } from "wouter";
import { LOGIN_URL, SIGNUP_URL } from "@/config";
import { useTheme } from "@/components/theme-provider";
import { BrandLogo } from "@/components/layout/brand-logo";
import { aiProducts } from "@/content/ai-products";
import { useCases } from "@/content/use-cases";
import { LINK_TYPE_COUNT } from "@/content/link-types";
import {
  Moon,
  Sun,
  Menu,
  X,
  ChevronDown,
  LayoutGrid,
  Rocket,
  BarChart3,
  Boxes,
  Globe,
  Users,
  FileText,
  Code2,
  Bot,
  Workflow,
  PhoneCall,
  Sparkles,
  Building2,
  GraduationCap,
  Music,
  Store,
  Compass,
  Rss,
  ArrowRight,
  type LucideIcon,
} from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";

interface MegaItem {
  href: string;
  label: string;
  desc?: string;
  icon: LucideIcon;
  /** Optional per-item accent (hex) — used by the AI Suite. */
  accent?: string;
}

interface FeaturedCard {
  eyebrow: string;
  title: string;
  desc: string;
  ctaLabel: string;
  ctaHref: string;
  /** External CTAs (sign-up) render as <a>; internal ones use the router. */
  external?: boolean;
}

interface MegaGroup {
  label: string;
  items: MegaItem[];
  featured: FeaturedCard;
}

interface SimpleLink {
  href: string;
  label: string;
}

/* --- Product: no content-file source, defined here with icons --- */
const productGroup: MegaGroup = {
  label: "Product",
  items: [
    { href: "/features", label: "All features", desc: "The complete 1INME toolkit", icon: LayoutGrid },
    { href: "/how-it-works", label: "How it works", desc: "Live in under 2 minutes", icon: Rocket },
    { href: "/analytics", label: "Analytics", desc: "Live maps, heatmaps & AI coach", icon: BarChart3 },
    { href: "/integrations", label: "Integrations", desc: "Connect every network", icon: Boxes },
    { href: "/domains", label: "Domains & links", desc: "Branded short links & custom domains", icon: Globe },
    { href: "/workspace-team", label: "Workspace & team", desc: "Roles, seats & billing", icon: Users },
    { href: "/resume-builder", label: "Resume builder", desc: "A CV that lands the interview", icon: FileText },
    { href: "/api-docs", label: "API & developers", desc: "REST API and webhooks", icon: Code2 },
  ],
  featured: {
    eyebrow: "What you can create",
    title: `${LINK_TYPE_COUNT}+ link types`,
    desc: "Short links, biolinks, menus, resumes, QR codes and AI pages — all from one place.",
    ctaLabel: "Explore features",
    ctaHref: "/features",
  },
};

/* --- AI Suite: sourced from ai-products.ts (navDesc + accent) --- */
const aiIcons: Record<string, LucideIcon> = {
  "ai-chatbot": Bot,
  "ai-agent": Workflow,
  "ai-widget": Code2,
  "ai-voice-assistant": PhoneCall,
};

const aiGroup: MegaGroup = {
  label: "AI Suite",
  items: aiProducts.map((product) => ({
    href: `/ai/${product.slug}`,
    label: product.title,
    desc: product.navDesc,
    icon: aiIcons[product.slug] ?? Sparkles,
    accent: product.accent,
  })),
  featured: {
    eyebrow: "The AI Suite",
    title: "Put AI to work",
    desc: "Answer visitors, run playbooks and pick up calls — on-brand, 24/7.",
    ctaLabel: "Start free",
    ctaHref: SIGNUP_URL,
    external: true,
  },
};

/* --- Solutions: "For X" sourced from use-cases.ts (eyebrow + navDesc) --- */
const useCaseIcons: Record<string, LucideIcon> = {
  creators: Sparkles,
  agencies: Building2,
  coaches: GraduationCap,
  musicians: Music,
  "small-business": Store,
};

const solutionsGroup: MegaGroup = {
  label: "Solutions",
  items: [
    ...useCases.map((useCase) => ({
      href: `/for/${useCase.slug}`,
      label: useCase.eyebrow,
      desc: useCase.navDesc,
      icon: useCaseIcons[useCase.slug] ?? Sparkles,
    })),
    { href: "/discovery", label: "Discovery", desc: "Find creators on 1INME", icon: Compass },
    { href: "/creators-feed", label: "Creators feed", desc: "Fresh posts from creators", icon: Rss },
  ],
  featured: {
    eyebrow: "Not sure where to start?",
    title: "One link for every goal",
    desc: "Built for creators, agencies, coaches, musicians and local business.",
    ctaLabel: "Start free",
    ctaHref: SIGNUP_URL,
    external: true,
  },
};

const simpleLinks: SimpleLink[] = [
  { href: "/pricing", label: "Pricing" },
  { href: "/compare", label: "Compare" },
  { href: "/about", label: "About" },
];

const megaGroups = [productGroup, aiGroup, solutionsGroup];

function chipClass(accent?: string) {
  return accent ? "" : "bg-primary/10 text-primary";
}

function chipStyle(accent?: string): React.CSSProperties | undefined {
  return accent ? { color: accent, backgroundColor: `${accent}24` } : undefined;
}

function MegaPanel({ group }: { group: MegaGroup }) {
  const { featured } = group;
  return (
    <div className="glass-card rounded-2xl p-3 shadow-2xl w-[min(56rem,calc(100vw-3rem))] grid grid-cols-[1fr_14rem] gap-3">
      <div className="grid grid-cols-2 gap-1" role="menu" aria-label={group.label}>
        {group.items.map((item) => {
          const Icon = item.icon;
          return (
            <Link
              key={item.href}
              href={item.href}
              role="menuitem"
              className="group/item flex items-start gap-3 rounded-xl p-3 hover:bg-secondary transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              <span
                className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${chipClass(item.accent)}`}
                style={chipStyle(item.accent)}
              >
                <Icon className="h-[18px] w-[18px]" />
              </span>
              <span className="min-w-0">
                <span className="block text-sm font-semibold text-foreground group-hover/item:text-primary transition-colors">
                  {item.label}
                </span>
                {item.desc && (
                  <span className="block text-xs text-muted-foreground mt-0.5 leading-snug">
                    {item.desc}
                  </span>
                )}
              </span>
            </Link>
          );
        })}
      </div>

      <div className="rounded-xl p-4 flex flex-col bg-gradient-to-br from-primary/10 via-accent/20 to-transparent border border-primary-border/30">
        <div className="text-[11px] font-semibold uppercase tracking-wider text-primary">
          {featured.eyebrow}
        </div>
        <div className="text-base font-bold text-foreground mt-1">{featured.title}</div>
        <p className="text-xs text-muted-foreground mt-1.5 leading-snug flex-1">{featured.desc}</p>
        {featured.external ? (
          <a
            href={featured.ctaHref}
            className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:gap-2.5 transition-all"
          >
            {featured.ctaLabel}
            <ArrowRight className="h-4 w-4" />
          </a>
        ) : (
          <Link
            href={featured.ctaHref}
            className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:gap-2.5 transition-all"
          >
            {featured.ctaLabel}
            <ArrowRight className="h-4 w-4" />
          </Link>
        )}
      </div>
    </div>
  );
}

export function Header() {
  const { theme, setTheme } = useTheme();
  const [location] = useLocation();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [openMenu, setOpenMenu] = useState<string | null>(null);
  const [mobileOpenGroup, setMobileOpenGroup] = useState<string | null>(null);

  const activeGroup = megaGroups.find((group) => group.label === openMenu) ?? null;
  const isGroupActive = (group: MegaGroup) =>
    group.items.some((item) => item.href === location);

  const closeMobile = () => {
    setMobileMenuOpen(false);
    setMobileOpenGroup(null);
  };

  return (
    <header
      className="fixed inset-x-0 z-50 glass-card border-x-0 border-t-0"
      style={{ top: "var(--inme-anno-h, 0px)" }}
    >
      <div className="container mx-auto px-6 h-16 flex items-center justify-between">
        <div className="flex items-center gap-6">
          <Link href="/" className="flex items-center" aria-label="1INME home">
            <BrandLogo imgHeight={28} textClassName="text-xl font-bold tracking-tight text-primary" />
          </Link>

          <div
            className="relative hidden lg:block"
            onMouseLeave={() => setOpenMenu(null)}
            onBlur={(e) => {
              if (!e.currentTarget.contains(e.relatedTarget as Node)) {
                setOpenMenu(null);
              }
            }}
            onKeyDown={(e) => {
              if (e.key === "Escape") setOpenMenu(null);
            }}
          >
            <nav className="flex items-center gap-1">
              {megaGroups.map((group) => (
                <div key={group.label} onMouseEnter={() => setOpenMenu(group.label)}>
                  <button
                    type="button"
                    className={`flex items-center gap-1 px-3 py-2 text-sm font-medium transition-colors hover:text-primary ${
                      openMenu === group.label || isGroupActive(group)
                        ? "text-primary"
                        : "text-muted-foreground"
                    }`}
                    aria-expanded={openMenu === group.label}
                    aria-haspopup="true"
                    onClick={() =>
                      setOpenMenu((current) => (current === group.label ? null : group.label))
                    }
                    onFocus={() => setOpenMenu(group.label)}
                  >
                    {group.label}
                    <ChevronDown
                      className={`w-4 h-4 transition-transform ${
                        openMenu === group.label ? "rotate-180" : ""
                      }`}
                    />
                  </button>
                </div>
              ))}
              {simpleLinks.map((link) => (
                <Link
                  key={link.href}
                  href={link.href}
                  className={`px-3 py-2 text-sm font-medium transition-colors hover:text-primary ${
                    location === link.href ? "text-primary" : "text-muted-foreground"
                  }`}
                >
                  {link.label}
                </Link>
              ))}
            </nav>

            {activeGroup && (
              <div className="absolute top-full left-0 pt-3">
                <MegaPanel group={activeGroup} />
              </div>
            )}
          </div>
        </div>

        <div className="flex items-center gap-3">
          <button
            onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
            className="p-2 rounded-full hover:bg-secondary transition-colors text-muted-foreground hover:text-foreground"
            aria-label="Toggle theme"
          >
            {theme === "dark" ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
          </button>

          <div className="hidden lg:flex items-center gap-3">
            <a href={LOGIN_URL} className="text-sm font-medium text-foreground hover:text-primary transition-colors">
              Log in
            </a>
            <Button asChild className="rounded-full px-6">
              <a href={SIGNUP_URL}>Sign up free</a>
            </Button>
          </div>

          <button
            className="lg:hidden p-2 -mr-2 text-foreground"
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
            aria-expanded={mobileMenuOpen}
            aria-label="Toggle menu"
          >
            {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
          </button>
        </div>
      </div>

      {/* Mobile Menu — collapsible accordion */}
      {mobileMenuOpen && (
        <div className="lg:hidden glass-card absolute top-16 left-0 right-0 p-4 flex flex-col gap-2 border-b max-h-[calc(100dvh-4rem)] overflow-y-auto">
          <nav className="flex flex-col gap-1.5">
            {megaGroups.map((group) => {
              const expanded = mobileOpenGroup === group.label;
              return (
                <div key={group.label} className="rounded-xl overflow-hidden">
                  <button
                    type="button"
                    className={`w-full flex items-center justify-between p-3 rounded-xl text-sm font-semibold transition-colors ${
                      expanded ? "bg-secondary text-primary" : "text-foreground hover:bg-secondary"
                    }`}
                    aria-expanded={expanded}
                    onClick={() =>
                      setMobileOpenGroup((current) =>
                        current === group.label ? null : group.label,
                      )
                    }
                  >
                    {group.label}
                    <ChevronDown
                      className={`w-4 h-4 transition-transform ${expanded ? "rotate-180" : ""}`}
                    />
                  </button>
                  {expanded && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-1 px-1 pt-1 pb-2">
                      {group.items.map((item) => {
                        const Icon = item.icon;
                        return (
                          <Link
                            key={item.href}
                            href={item.href}
                            className="flex items-start gap-3 p-2.5 rounded-lg hover:bg-secondary transition-colors"
                            onClick={closeMobile}
                          >
                            <span
                              className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${chipClass(item.accent)}`}
                              style={chipStyle(item.accent)}
                            >
                              <Icon className="h-4 w-4" />
                            </span>
                            <span className="min-w-0">
                              <span className="block text-sm font-medium text-foreground">
                                {item.label}
                              </span>
                              {item.desc && (
                                <span className="block text-xs text-muted-foreground leading-snug">
                                  {item.desc}
                                </span>
                              )}
                            </span>
                          </Link>
                        );
                      })}
                    </div>
                  )}
                </div>
              );
            })}
          </nav>

          <div className="h-px bg-border my-1" />

          <nav className="flex flex-col">
            {simpleLinks.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                className={`p-3 rounded-xl text-sm font-semibold transition-colors hover:bg-secondary ${
                  location === link.href ? "text-primary" : "text-foreground"
                }`}
                onClick={closeMobile}
              >
                {link.label}
              </Link>
            ))}
          </nav>

          <div className="h-px bg-border my-1" />

          <div className="flex flex-col gap-3 pb-4">
            <Button asChild variant="outline" className="w-full justify-center">
              <a href={LOGIN_URL}>Log in</a>
            </Button>
            <Button asChild className="w-full justify-center">
              <a href={SIGNUP_URL}>Sign up free</a>
            </Button>
          </div>
        </div>
      )}
    </header>
  );
}
