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
  MessageCircle,
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
import { useEffect, useRef, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
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
  /** One-line summary shown in the panel header + mobile sub-label. */
  blurb: string;
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
  blurb: "Everything you need to build, brand and track your links.",
  items: [
    { href: "/features", label: "All features", desc: "The complete Sayzio toolkit", icon: LayoutGrid },
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
    desc: "Short links, Link in Bio pages, menus, resumes, QR codes and AI pages — all from one place.",
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
  "whatsapp-agent": MessageCircle,
};

const aiGroup: MegaGroup = {
  label: "AI Suite",
  blurb: "On-brand AI that answers, acts and picks up the phone — 24/7.",
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
  blurb: "One link, tuned for how you actually work.",
  items: [
    ...useCases.map((useCase) => ({
      href: `/for/${useCase.slug}`,
      label: useCase.eyebrow,
      desc: useCase.navDesc,
      icon: useCaseIcons[useCase.slug] ?? Sparkles,
    })),
    { href: "/discovery", label: "Discovery", desc: "Find creators on Sayzio", icon: Compass },
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

/* ---------------------------------------------------------------- *
 * Desktop mega panel — animated, with a staggered item grid and a
 * featured highlight card. Content is keyed by group so switching
 * between groups cross-fades fluidly inside a single panel shell.
 * ---------------------------------------------------------------- */
function MegaPanel({ group }: { group: MegaGroup }) {
  const { featured } = group;
  return (
    <motion.div
      key={group.label}
      initial={{ opacity: 0, y: 6 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -6 }}
      transition={{ duration: 0.18, ease: [0.2, 0.7, 0.2, 1] }}
      className="grid grid-cols-[1fr_15rem] gap-4 p-4"
    >
      <div>
        <div className="px-2 pb-2 mb-1 flex items-center gap-2 border-b border-border/60">
          <span className="text-sm font-semibold text-foreground">{group.label}</span>
          <span className="text-xs text-muted-foreground truncate">{group.blurb}</span>
        </div>
        <motion.div
          className="grid grid-cols-2 gap-1"
          role="menu"
          aria-label={group.label}
          initial="hidden"
          animate="show"
          variants={{
            hidden: {},
            show: { transition: { staggerChildren: 0.025, delayChildren: 0.02 } },
          }}
        >
          {group.items.map((item) => {
            const Icon = item.icon;
            return (
              <motion.div
                key={item.href}
                variants={{
                  hidden: { opacity: 0, y: 8 },
                  show: { opacity: 1, y: 0 },
                }}
                transition={{ duration: 0.2, ease: [0.2, 0.7, 0.2, 1] }}
              >
                <Link
                  href={item.href}
                  role="menuitem"
                  className="group/item relative flex items-start gap-3 rounded-xl p-3 transition-colors hover:bg-secondary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  <span
                    className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-transform duration-200 group-hover/item:scale-110 ${chipClass(item.accent)}`}
                    style={chipStyle(item.accent)}
                  >
                    <Icon className="h-[18px] w-[18px]" />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="flex items-center gap-1 text-sm font-semibold text-foreground transition-colors group-hover/item:text-primary">
                      {item.label}
                      <ArrowRight className="h-3.5 w-3.5 -translate-x-1 opacity-0 transition-all duration-200 group-hover/item:translate-x-0 group-hover/item:opacity-100" />
                    </span>
                    {item.desc && (
                      <span className="mt-0.5 block text-xs leading-snug text-muted-foreground">
                        {item.desc}
                      </span>
                    )}
                  </span>
                </Link>
              </motion.div>
            );
          })}
        </motion.div>
      </div>

      <div className="relative overflow-hidden rounded-xl border border-primary-border/30 bg-gradient-to-br from-primary/10 via-accent/20 to-transparent p-5 flex flex-col">
        <div
          aria-hidden
          className="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-primary/20 blur-2xl"
        />
        <div className="relative flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-primary">
          <Sparkles className="h-3.5 w-3.5" />
          {featured.eyebrow}
        </div>
        <div className="relative mt-2 text-lg font-bold leading-tight text-foreground">
          {featured.title}
        </div>
        <p className="relative mt-1.5 flex-1 text-xs leading-snug text-muted-foreground">
          {featured.desc}
        </p>
        {featured.external ? (
          <a
            href={featured.ctaHref}
            className="relative mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primary transition-all hover:gap-2.5"
          >
            {featured.ctaLabel}
            <ArrowRight className="h-4 w-4" />
          </a>
        ) : (
          <Link
            href={featured.ctaHref}
            className="relative mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primary transition-all hover:gap-2.5"
          >
            {featured.ctaLabel}
            <ArrowRight className="h-4 w-4" />
          </Link>
        )}
      </div>
    </motion.div>
  );
}

export function Header() {
  const { theme, setTheme } = useTheme();
  const [location] = useLocation();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [openMenu, setOpenMenu] = useState<string | null>(null);
  const [mobileOpenGroup, setMobileOpenGroup] = useState<string | null>(null);
  const [scrolled, setScrolled] = useState(false);
  const [navHidden, setNavHidden] = useState(false);
  const lastScrollY = useRef(0);
  const downAccum = useRef(0);
  const closeTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const activeGroup = megaGroups.find((group) => group.label === openMenu) ?? null;
  const isGroupActive = (group: MegaGroup) =>
    group.items.some((item) => item.href === location);

  /* Small open/close grace so moving the cursor across the gap between the
     trigger and the panel never flickers the menu shut. */
  const scheduleClose = () => {
    if (closeTimer.current) clearTimeout(closeTimer.current);
    closeTimer.current = setTimeout(() => setOpenMenu(null), 120);
  };
  const cancelClose = () => {
    if (closeTimer.current) clearTimeout(closeTimer.current);
  };
  useEffect(() => () => cancelClose(), []);

  const closeMobile = () => {
    setMobileMenuOpen(false);
    setMobileOpenGroup(null);
  };

  // Lock body scroll while the full-screen mobile menu is open.
  useEffect(() => {
    if (!mobileMenuOpen) return;
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = prev;
    };
  }, [mobileMenuOpen]);

  // Snap the floating pill solid to the top once the page scrolls, and
  // auto-hide the header while scrolling down (after a small accumulated
  // threshold so tiny jitters don't trip it), bringing it back the instant
  // the visitor scrolls up. Always visible near the very top. Mirrors the
  // Laravel public header so both surfaces feel identical.
  useEffect(() => {
    lastScrollY.current = window.scrollY;
    const onScroll = () => {
      const y = window.scrollY;
      setScrolled(y > 8);
      const delta = y - lastScrollY.current;
      if (y <= 96) {
        downAccum.current = 0;
        setNavHidden(false);
      } else if (delta > 0) {
        downAccum.current += delta;
        if (downAccum.current > 24) setNavHidden(true);
      } else if (delta < 0) {
        downAccum.current = 0;
        setNavHidden(false);
      }
      lastScrollY.current = y;
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  // Escape closes whichever surface is open.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key !== "Escape") return;
      setOpenMenu(null);
      setMobileMenuOpen(false);
      setMobileOpenGroup(null);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  return (
    <header
      className={`fixed inset-x-0 z-50 mkt-nav-autohide ${
        navHidden && !openMenu && !mobileMenuOpen ? "mkt-nav-hidden" : ""
      }`}
      style={{ top: "var(--inme-anno-h, 0px)" }}
    >
      <div className={`mkt-navbar-bar ${scrolled ? "is-stuck" : ""}`}>
      <div className="container relative mx-auto px-6 h-16 flex items-center justify-between">
        <div className="flex items-center gap-6">
          {/* Desktop wordmark */}
          <Link
            href="/"
            className="hidden lg:flex items-center"
            aria-label="Sayzio home"
          >
            <BrandLogo imgHeight={28} textClassName="text-xl font-bold tracking-tight text-primary" />
          </Link>

          {/* Mobile centered brand icon */}
          <Link
            href="/"
            className="lg:hidden absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 flex items-center"
            aria-label="Sayzio home"
          >
            <BrandLogo
              variant="icon"
              imgHeight={30}
              textClassName="text-xl font-bold tracking-tight text-primary"
            />
          </Link>

          <div
            className="relative hidden lg:block"
            onMouseLeave={scheduleClose}
            onMouseEnter={cancelClose}
            onBlur={(e) => {
              if (!e.currentTarget.contains(e.relatedTarget as Node)) {
                setOpenMenu(null);
              }
            }}
          >
            <nav className="flex items-center gap-1">
              {megaGroups.map((group) => {
                const open = openMenu === group.label;
                const active = open || isGroupActive(group);
                // Only one shared-layout pill may be mounted at a time, so when
                // any menu is open the pill follows the open group, not the page.
                const showPill = openMenu ? open : isGroupActive(group);
                return (
                  <div
                    key={group.label}
                    className="relative"
                    onMouseEnter={() => {
                      cancelClose();
                      setOpenMenu(group.label);
                    }}
                  >
                    <button
                      type="button"
                      className={`relative flex items-center gap-1 rounded-full px-3.5 py-2 text-sm font-medium transition-colors hover:text-primary ${
                        active ? "text-primary" : "text-muted-foreground"
                      }`}
                      aria-expanded={open}
                      aria-haspopup="true"
                      onClick={() =>
                        setOpenMenu((current) => (current === group.label ? null : group.label))
                      }
                      onFocus={() => setOpenMenu(group.label)}
                    >
                      {showPill && (
                        <motion.span
                          layoutId="nav-pill"
                          className="absolute inset-0 -z-10 rounded-full bg-primary/10"
                          transition={{ type: "spring", stiffness: 500, damping: 38 }}
                        />
                      )}
                      {group.label}
                      <ChevronDown
                        className={`h-4 w-4 transition-transform duration-200 ${
                          open ? "rotate-180" : ""
                        }`}
                      />
                    </button>
                  </div>
                );
              })}
              {simpleLinks.map((link) => {
                const active = location === link.href;
                const showPill = !openMenu && active;
                return (
                  <Link
                    key={link.href}
                    href={link.href}
                    className={`relative rounded-full px-3.5 py-2 text-sm font-medium transition-colors hover:text-primary ${
                      active ? "text-primary" : "text-muted-foreground"
                    }`}
                  >
                    {showPill && (
                      <motion.span
                        layoutId="nav-pill"
                        className="absolute inset-0 -z-10 rounded-full bg-primary/10"
                        transition={{ type: "spring", stiffness: 500, damping: 38 }}
                      />
                    )}
                    {link.label}
                  </Link>
                );
              })}
            </nav>

            <AnimatePresence>
              {activeGroup && (
                <motion.div
                  key="mega-shell"
                  initial={{ opacity: 0, y: 8, scale: 0.985 }}
                  animate={{ opacity: 1, y: 0, scale: 1 }}
                  exit={{ opacity: 0, y: 8, scale: 0.985 }}
                  transition={{ duration: 0.2, ease: [0.2, 0.7, 0.2, 1] }}
                  className="absolute top-full left-0 pt-3"
                  onMouseEnter={cancelClose}
                  onMouseLeave={scheduleClose}
                >
                  <div className="glass-card w-[min(58rem,calc(100vw-3rem))] overflow-hidden rounded-2xl shadow-2xl">
                    <span
                      aria-hidden
                      className="block h-1 w-full bg-gradient-to-r from-primary via-accent-foreground/60 to-primary"
                    />
                    <AnimatePresence mode="wait">
                      <MegaPanel key={activeGroup.label} group={activeGroup} />
                    </AnimatePresence>
                  </div>
                </motion.div>
              )}
            </AnimatePresence>
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
            onClick={() => setMobileMenuOpen((open) => !open)}
            aria-expanded={mobileMenuOpen}
            aria-label="Toggle menu"
          >
            {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
          </button>
        </div>
      </div>

      {/* Mobile / tablet — full-screen overlay with animated accordions */}
      <AnimatePresence>
        {mobileMenuOpen && (
          <motion.div
            key="mobile-overlay"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
            className="lg:hidden fixed inset-x-0 bottom-0 z-40 flex flex-col"
            style={{ top: "calc(4rem + var(--inme-anno-h, 0px))" }}
          >
            {/* Backdrop */}
            <div
              className="absolute inset-0 bg-background/80 backdrop-blur-xl"
              aria-hidden
              onClick={closeMobile}
            />

            <motion.div
              initial={{ y: -12, opacity: 0 }}
              animate={{ y: 0, opacity: 1 }}
              exit={{ y: -12, opacity: 0 }}
              transition={{ duration: 0.22, ease: [0.2, 0.7, 0.2, 1] }}
              className="relative flex min-h-0 flex-1 flex-col"
            >
              <div className="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                <nav className="flex flex-col gap-2">
                  {megaGroups.map((group) => {
                    const expanded = mobileOpenGroup === group.label;
                    return (
                      <div
                        key={group.label}
                        className="glass-card overflow-hidden rounded-2xl"
                      >
                        <button
                          type="button"
                          className={`flex w-full items-center justify-between gap-3 p-4 text-left transition-colors ${
                            expanded ? "text-primary" : "text-foreground"
                          }`}
                          aria-expanded={expanded}
                          onClick={() =>
                            setMobileOpenGroup((current) =>
                              current === group.label ? null : group.label,
                            )
                          }
                        >
                          <span className="min-w-0">
                            <span className="block text-base font-semibold">{group.label}</span>
                            <span className="mt-0.5 block truncate text-xs text-muted-foreground">
                              {group.blurb}
                            </span>
                          </span>
                          <ChevronDown
                            className={`h-5 w-5 shrink-0 transition-transform duration-200 ${
                              expanded ? "rotate-180" : ""
                            }`}
                          />
                        </button>
                        <AnimatePresence initial={false}>
                          {expanded && (
                            <motion.div
                              key="content"
                              initial={{ height: 0, opacity: 0 }}
                              animate={{ height: "auto", opacity: 1 }}
                              exit={{ height: 0, opacity: 0 }}
                              transition={{ duration: 0.25, ease: [0.2, 0.7, 0.2, 1] }}
                              className="overflow-hidden"
                            >
                              <div className="flex flex-col gap-1 px-2 pb-3">
                                {group.items.map((item) => {
                                  const Icon = item.icon;
                                  return (
                                    <Link
                                      key={item.href}
                                      href={item.href}
                                      className="flex items-center gap-3 rounded-xl p-3 transition-colors hover:bg-secondary active:bg-secondary"
                                      onClick={closeMobile}
                                    >
                                      <span
                                        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${chipClass(item.accent)}`}
                                        style={chipStyle(item.accent)}
                                      >
                                        <Icon className="h-5 w-5" />
                                      </span>
                                      <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-semibold text-foreground">
                                          {item.label}
                                        </span>
                                        {item.desc && (
                                          <span className="block text-xs leading-snug text-muted-foreground">
                                            {item.desc}
                                          </span>
                                        )}
                                      </span>
                                      <ArrowRight className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    </Link>
                                  );
                                })}
                                {group.featured.external ? (
                                  <a
                                    href={group.featured.ctaHref}
                                    className="mx-1 mt-1 inline-flex items-center gap-1.5 rounded-xl bg-primary/10 px-3 py-2.5 text-sm font-semibold text-primary"
                                    onClick={closeMobile}
                                  >
                                    {group.featured.ctaLabel}
                                    <ArrowRight className="h-4 w-4" />
                                  </a>
                                ) : (
                                  <Link
                                    href={group.featured.ctaHref}
                                    className="mx-1 mt-1 inline-flex items-center gap-1.5 rounded-xl bg-primary/10 px-3 py-2.5 text-sm font-semibold text-primary"
                                    onClick={closeMobile}
                                  >
                                    {group.featured.ctaLabel}
                                    <ArrowRight className="h-4 w-4" />
                                  </Link>
                                )}
                              </div>
                            </motion.div>
                          )}
                        </AnimatePresence>
                      </div>
                    );
                  })}

                  <div className="mt-1 grid grid-cols-1 gap-1 sm:grid-cols-3">
                    {simpleLinks.map((link) => (
                      <Link
                        key={link.href}
                        href={link.href}
                        className={`rounded-xl p-3.5 text-center text-sm font-semibold transition-colors hover:bg-secondary ${
                          location === link.href ? "text-primary" : "text-foreground"
                        }`}
                        onClick={closeMobile}
                      >
                        {link.label}
                      </Link>
                    ))}
                  </div>
                </nav>
              </div>

              {/* Pinned CTAs */}
              <div className="glass-card border-x-0 border-b-0 px-5 py-4">
                <div className="flex flex-col gap-3">
                  <Button asChild variant="outline" className="w-full justify-center">
                    <a href={LOGIN_URL}>Log in</a>
                  </Button>
                  <Button asChild className="w-full justify-center">
                    <a href={SIGNUP_URL}>Sign up free</a>
                  </Button>
                </div>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
      </div>
    </header>
  );
}
