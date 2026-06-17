import { Link } from "wouter";
import { LOGIN_URL, SIGNUP_URL } from "@/config";
import { Button } from "@/components/ui/button";

export function Footer() {
  return (
    <footer className="border-t bg-card/50 mt-24">
      <div className="container mx-auto px-6 py-16">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-12 mb-16">
          <div className="col-span-1 md:col-span-2">
            <Link href="/" className="text-2xl font-bold text-primary mb-4 block">
              1INME
            </Link>
            <p className="text-muted-foreground mb-6 max-w-sm">
              Your biolink, reimagined. Everything you need to build, grow, and monetize your audience — without juggling ten different tools.
            </p>
            <div className="flex gap-4">
              <Button asChild>
                <a href={SIGNUP_URL}>Get started for free</a>
              </Button>
              <Button asChild variant="secondary">
                <a href={LOGIN_URL}>Log in</a>
              </Button>
            </div>
          </div>
          
          <div>
            <h4 className="font-semibold mb-4 text-foreground">Product</h4>
            <ul className="space-y-3">
              <li><Link href="/features" className="text-muted-foreground hover:text-primary transition-colors text-sm">Features</Link></li>
              <li><Link href="/pricing" className="text-muted-foreground hover:text-primary transition-colors text-sm">Pricing</Link></li>
              <li><Link href="/changelog" className="text-muted-foreground hover:text-primary transition-colors text-sm">Changelog</Link></li>
              <li><Link href="/about" className="text-muted-foreground hover:text-primary transition-colors text-sm">About us</Link></li>
              <li><Link href="/blog" className="text-muted-foreground hover:text-primary transition-colors text-sm">Blog</Link></li>
              <li><Link href="/faq" className="text-muted-foreground hover:text-primary transition-colors text-sm">FAQ</Link></li>
            </ul>
          </div>

          <div>
            <h4 className="font-semibold mb-4 text-foreground">Support & Legal</h4>
            <ul className="space-y-3">
              <li><Link href="/contact" className="text-muted-foreground hover:text-primary transition-colors text-sm">Contact</Link></li>
              <li><Link href="/privacy" className="text-muted-foreground hover:text-primary transition-colors text-sm">Privacy Policy</Link></li>
              <li><Link href="/terms" className="text-muted-foreground hover:text-primary transition-colors text-sm">Terms of Service</Link></li>
            </ul>
          </div>
        </div>
        
        <div className="pt-8 border-t border-border flex flex-col md:flex-row justify-between items-center gap-4">
          <p className="text-sm text-muted-foreground">
            © {new Date().getFullYear()} 1INME. All rights reserved.
          </p>
          <div className="flex gap-4 text-sm text-muted-foreground">
            Built for creators, coaches, freelancers, agencies, and businesses.
          </div>
        </div>
      </div>
    </footer>
  );
}
