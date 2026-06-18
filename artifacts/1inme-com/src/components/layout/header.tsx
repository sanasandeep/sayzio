import { Link, useLocation } from "wouter";
import { LOGIN_URL, SIGNUP_URL } from "@/config";
import { useTheme } from "@/components/theme-provider";
import { Moon, Sun, Menu, X, ChevronDown } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";

interface NavItem {
  href: string;
  label: string;
  desc?: string;
}

interface NavGroup {
  label: string;
  items: NavItem[];
}

const productGroup: NavGroup = {
  label: "Product",
  items: [
    { href: "/features", label: "All features", desc: "The complete 1INME toolkit" },
    { href: "/how-it-works", label: "How it works", desc: "Live in under 2 minutes" },
    { href: "/analytics", label: "Analytics", desc: "Live maps, heatmaps & AI coach" },
    { href: "/integrations", label: "Integrations", desc: "Connect every network" },
    { href: "/domains", label: "Domains & links", desc: "Branded short links & custom domains" },
    { href: "/workspace-team", label: "Workspace & team", desc: "Roles, seats & billing" },
    { href: "/resume-builder", label: "Resume builder", desc: "A CV that lands the interview" },
    { href: "/api-docs", label: "API & developers", desc: "REST API and webhooks" },
  ],
};

const aiGroup: NavGroup = {
  label: "AI Suite",
  items: [
    { href: "/ai/ai-chatbot", label: "AI Chatbot", desc: "24/7 chatbot for your biolink" },
    { href: "/ai/ai-agent", label: "AI Agent", desc: "Runs multi-step playbooks" },
    { href: "/ai/ai-widget", label: "AI Widget", desc: "Embed on any website" },
    { href: "/ai/ai-voice-assistant", label: "AI Voice Assistant", desc: "Picks up your calls" },
  ],
};

const solutionsGroup: NavGroup = {
  label: "Solutions",
  items: [
    { href: "/for/creators", label: "For creators", desc: "Grow, sell and own your audience" },
    { href: "/for/agencies", label: "For agencies", desc: "Run every client in one place" },
    { href: "/for/coaches", label: "For coaches", desc: "Fill your calendar on autopilot" },
    { href: "/for/musicians", label: "For musicians", desc: "Every release, one smart link" },
    { href: "/for/small-business", label: "For small business", desc: "Your storefront behind one link" },
    { href: "/discovery", label: "Discovery", desc: "Find creators on 1INME" },
    { href: "/creators-feed", label: "Creators feed", desc: "Fresh posts from creators" },
  ],
};

const simpleLinks: NavItem[] = [
  { href: "/pricing", label: "Pricing" },
  { href: "/compare", label: "Compare" },
  { href: "/about", label: "About" },
];

const dropdowns = [productGroup, aiGroup, solutionsGroup];

export function Header() {
  const { theme, setTheme } = useTheme();
  const [location] = useLocation();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [openMenu, setOpenMenu] = useState<string | null>(null);

  return (
    <header
      className="fixed inset-x-0 z-50 glass-card border-x-0 border-t-0"
      style={{ top: "var(--inme-anno-h, 0px)" }}
    >
      <div className="container mx-auto px-6 h-16 flex items-center justify-between">
        <div className="flex items-center gap-6">
          <Link href="/" className="text-xl font-bold tracking-tight text-primary">
            1INME
          </Link>
          <nav className="hidden lg:flex items-center gap-1">
            {dropdowns.map((group) => (
              <div
                key={group.label}
                className="relative"
                onMouseEnter={() => setOpenMenu(group.label)}
                onMouseLeave={() => setOpenMenu(null)}
              >
                <button
                  className="flex items-center gap-1 px-3 py-2 text-sm font-medium text-muted-foreground hover:text-primary transition-colors"
                  aria-expanded={openMenu === group.label}
                >
                  {group.label}
                  <ChevronDown className="w-4 h-4" />
                </button>
                {openMenu === group.label && (
                  <div className="absolute top-full left-0 pt-2">
                    <div className="glass-card rounded-2xl p-2 w-72 shadow-xl">
                      {group.items.map((item) => (
                        <Link
                          key={item.href}
                          href={item.href}
                          className="block px-3 py-2.5 rounded-xl hover:bg-secondary transition-colors"
                        >
                          <div className="text-sm font-medium">{item.label}</div>
                          {item.desc && (
                            <div className="text-xs text-muted-foreground mt-0.5">{item.desc}</div>
                          )}
                        </Link>
                      ))}
                    </div>
                  </div>
                )}
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
            aria-label="Toggle menu"
          >
            {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
          </button>
        </div>
      </div>

      {/* Mobile Menu */}
      {mobileMenuOpen && (
        <div className="lg:hidden glass-card absolute top-16 left-0 right-0 p-4 flex flex-col gap-4 border-b max-h-[calc(100dvh-4rem)] overflow-y-auto">
          <nav className="flex flex-col gap-4">
            {[...dropdowns, { label: "More", items: simpleLinks }].map((group) => (
              <div key={group.label}>
                <div className="text-xs font-bold uppercase tracking-wider text-muted-foreground px-3 mb-1">
                  {group.label}
                </div>
                {group.items.map((item) => (
                  <Link
                    key={item.href}
                    href={item.href}
                    className="block p-3 rounded-lg hover:bg-secondary text-sm font-medium"
                    onClick={() => setMobileMenuOpen(false)}
                  >
                    {item.label}
                  </Link>
                ))}
              </div>
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
