import { PageLayout } from "@/components/layout/page-layout";
import { Button } from "@/components/ui/button";
import { OrbitalUniverse } from "@/components/marketing/orbital-universe";
import { PRICING_URL, SIGNUP_URL } from "@/config";
import { motion, useReducedMotion } from "framer-motion";
import { ArrowRight, Check, Sparkles } from "lucide-react";

export default function Pricing() {
  const prefersReducedMotion = useReducedMotion();
  const plans = [
    {
      name: "Free",
      price: "$0",
      period: "/mo",
      description: "Everything you need to get started.",
      features: [
        "Basic Link in Bio",
        "10 short links",
        "Standard QR codes",
        "Basic analytics"
      ],
      cta: "Get started",
      variant: "outline" as const,
    },
    {
      name: "Starter",
      price: "$5",
      period: "/mo",
      description: "For rising creators and personal brands.",
      features: [
        "Custom domains",
        "Removed branding",
        "50 short links",
        "AI Minds, Personas & Companions"
      ],
      cta: "Get started",
      variant: "outline" as const,
    },
    {
      name: "Pro",
      price: "$12",
      period: "/mo",
      description: "For professionals and growing businesses.",
      popular: true,
      features: [
        "Priority support",
        "Full analytics",
        "500 short links",
        "Site Assistant widget & AI coach"
      ],
      cta: "Upgrade now",
      variant: "default" as const,
    },
    {
      name: "Business",
      price: "$39",
      period: "/mo",
      description: "For agencies and high-volume operations.",
      features: [
        "API access",
        "White-labeling",
        "Unlimited short links",
        "AI Voice Assistant & unlimited AI"
      ],
      cta: "Upgrade now",
      variant: "outline" as const,
    }
  ];

  return (
    <PageLayout
      title="Pricing"
      description="Plans for steady use, coins for one-off boosts — all in one place."
    >
      <section className="relative pt-28 pb-16 lg:pt-36 lg:pb-20 overflow-hidden">
        <div className="mesh-bg" aria-hidden />
        <div className="grid-bg" aria-hidden />
        <div className="container mx-auto px-6 relative z-10">
          <div className="grid lg:grid-cols-2 gap-12 lg:gap-8 items-center mb-20">
            <motion.div
              initial={prefersReducedMotion ? false : { opacity: 0, y: 20 }}
              animate={prefersReducedMotion ? undefined : { opacity: 1, y: 0 }}
              transition={{ duration: 0.6 }}
              className="max-w-2xl"
            >
              <span className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-1.5 text-sm font-semibold text-primary mb-6">
                <Sparkles className="w-4 h-4" />
                Every plan includes Zio
              </span>

              <h1 className="text-4xl lg:text-6xl font-bold tracking-tight text-foreground mb-6 leading-[1.05]">
                Pick a plan. <span className="grad-text">Keep the whole universe.</span>
              </h1>

              <p className="text-lg lg:text-xl text-muted-foreground mb-10 max-w-xl">
                Every tier orbits the same AI. Pages, short links, QR codes,
                analytics and Zio's AI suite come on every plan — you upgrade for
                higher limits and pro tools, not to unlock the basics. Top up
                coins any time for one-off boosts.
              </p>

              <div className="flex flex-col sm:flex-row gap-4">
                <Button asChild size="lg" className="rounded-full h-14 px-8 text-base">
                  <a href={SIGNUP_URL}>
                    Start free <ArrowRight className="ml-2 w-5 h-5" />
                  </a>
                </Button>
                <Button
                  asChild
                  variant="outline"
                  size="lg"
                  className="rounded-full h-14 px-8 text-base bg-transparent border-primary/20 hover:bg-primary/5"
                >
                  <a href="#plans">Compare plans</a>
                </Button>
              </div>
            </motion.div>

            {/* Zio universe — orbital feature planets */}
            <motion.div
              initial={prefersReducedMotion ? false : { opacity: 0, scale: 0.92 }}
              animate={prefersReducedMotion ? undefined : { opacity: 1, scale: 1 }}
              transition={{ duration: 0.8, delay: 0.2 }}
              className="relative flex justify-center lg:justify-end"
            >
              <OrbitalUniverse />
            </motion.div>
          </div>

          <div id="plans" className="grid md:grid-cols-2 lg:grid-cols-4 gap-8 mb-16 scroll-mt-24">
            {plans.map((plan, index) => (
              <motion.div
                key={plan.name}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: index * 0.1 }}
                className={`relative glass-card p-8 rounded-3xl flex flex-col ${
                  plan.popular
                    ? "ring-2 ring-primary/60 shadow-[0_0_60px_-15px_hsl(var(--primary)/0.55)]"
                    : ""
                }`}
              >
                {plan.popular && (
                  <div className="absolute -top-4 left-1/2 -translate-x-1/2 bg-gradient-to-r from-primary to-[hsl(267_84%_66%)] text-primary-foreground text-xs font-bold px-4 py-1 rounded-full uppercase tracking-wider shadow-lg shadow-primary/40">
                    Most popular
                  </div>
                )}
                
                <div className="mb-8">
                  <h3 className="text-2xl font-bold mb-2">{plan.name}</h3>
                  <p className="text-muted-foreground text-sm min-h-[40px]">{plan.description}</p>
                </div>
                
                <div className="mb-8">
                  <span className="text-5xl font-bold">{plan.price}</span>
                  <span className="text-muted-foreground">{plan.period}</span>
                </div>
                
                <ul className="space-y-4 mb-8 flex-1">
                  {plan.features.map((feature) => (
                    <li key={feature} className="flex items-start gap-3">
                      <Check className="w-5 h-5 text-primary shrink-0" />
                      <span className="text-sm font-medium">{feature}</span>
                    </li>
                  ))}
                </ul>
                
                <Button 
                  asChild 
                  variant={plan.variant} 
                  className="w-full rounded-full h-12"
                >
                  <a href={PRICING_URL}>{plan.cta}</a>
                </Button>
              </motion.div>
            ))}
          </div>

          <div className="max-w-3xl mx-auto glass-card p-8 rounded-3xl text-center">
            <h3 className="text-xl font-bold mb-3">Coins for one-off boosts</h3>
            <p className="text-muted-foreground">
              Top up for AI credits, SMS broadcasts, or extra storage without changing your plan.
            </p>
          </div>
        </div>
      </section>
    </PageLayout>
  );
}
