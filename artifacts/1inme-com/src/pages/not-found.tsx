import { PageLayout } from "@/components/layout/page-layout";
import { Button } from "@/components/ui/button";
import { Link } from "wouter";
import { Compass, Home } from "lucide-react";

export default function NotFound() {
  return (
    <PageLayout
      title="Page not found"
      description="The page you're looking for doesn't exist or has moved."
    >
      <section className="py-20 lg:py-32">
        <div className="container mx-auto px-6">
          <div className="max-w-2xl mx-auto text-center">
            <p className="text-sm font-semibold uppercase tracking-widest text-primary mb-4">
              Error 404
            </p>
            <h1 className="text-5xl lg:text-7xl font-bold tracking-tight mb-6">
              Lost the{" "}
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-primary to-accent-foreground">
                thread.
              </span>
            </h1>
            <p className="text-xl text-muted-foreground leading-relaxed mb-10">
              We couldn't find the page you were looking for. It may have moved,
              or the link might be out of date.
            </p>
            <div className="flex flex-wrap justify-center gap-4">
              <Button asChild size="lg" className="rounded-full h-12 px-8">
                <Link href="/">
                  <Home className="w-4 h-4" />
                  Back to home
                </Link>
              </Button>
              <Button
                asChild
                size="lg"
                variant="outline"
                className="rounded-full h-12 px-8"
              >
                <Link href="/features">
                  <Compass className="w-4 h-4" />
                  Explore features
                </Link>
              </Button>
            </div>
          </div>
        </div>
      </section>
    </PageLayout>
  );
}
