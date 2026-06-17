import { Link, useLocation } from "wouter";
import { LOGIN_URL, SIGNUP_URL } from "@/config";
import { useTheme } from "@/components/theme-provider";
import { Moon, Sun, Menu, X } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";

export function Header() {
  const { theme, setTheme } = useTheme();
  const [location] = useLocation();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const navLinks = [
    { href: "/features", label: "Features" },
    { href: "/pricing", label: "Pricing" },
    { href: "/about", label: "About" },
    { href: "/contact", label: "Contact" },
    { href: "/faq", label: "FAQ" },
  ];

  return (
    <header className="fixed top-0 inset-x-0 z-50 glass-card border-x-0 border-t-0">
      <div className="container mx-auto px-6 h-16 flex items-center justify-between">
        <div className="flex items-center gap-8">
          <Link href="/" className="text-xl font-bold tracking-tight text-primary">
            1INME
          </Link>
          <nav className="hidden md:flex items-center gap-6">
            {navLinks.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                className={`text-sm font-medium transition-colors hover:text-primary ${
                  location === link.href ? "text-primary" : "text-muted-foreground"
                }`}
              >
                {link.label}
              </Link>
            ))}
          </nav>
        </div>

        <div className="flex items-center gap-4">
          <button
            onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
            className="p-2 rounded-full hover:bg-secondary transition-colors text-muted-foreground hover:text-foreground"
            aria-label="Toggle theme"
          >
            {theme === "dark" ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
          </button>
          
          <div className="hidden md:flex items-center gap-3">
            <a href={LOGIN_URL} className="text-sm font-medium text-foreground hover:text-primary transition-colors">
              Log in
            </a>
            <Button asChild className="rounded-full px-6">
              <a href={SIGNUP_URL}>Sign up free</a>
            </Button>
          </div>

          <button 
            className="md:hidden p-2 -mr-2 text-foreground"
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
          >
            {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
          </button>
        </div>
      </div>

      {/* Mobile Menu */}
      {mobileMenuOpen && (
        <div className="md:hidden glass-card absolute top-16 left-0 right-0 p-4 flex flex-col gap-4 border-b">
          <nav className="flex flex-col gap-2">
            {navLinks.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                className="p-3 rounded-lg hover:bg-secondary text-sm font-medium"
                onClick={() => setMobileMenuOpen(false)}
              >
                {link.label}
              </Link>
            ))}
          </nav>
          <div className="h-px bg-border my-2" />
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
