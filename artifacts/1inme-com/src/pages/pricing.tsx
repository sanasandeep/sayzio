import { PageLayout } from "@/components/layout/page-layout";
import { Button } from "@/components/ui/button";
import { SIGNUP_URL } from "@/config";
import { motion } from "framer-motion";
import { Check } from "lucide-react";

export default function Pricing() {
  const plans = [
    {
      name: "Free",
      price: "$0",
      period: "/mo",
      description: "Everything you need to get started.",
      features: [
        "Basic biolink",
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
        "Advanced blocks"
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
        "Team seats (up to 3)"
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
        "10 team seats"
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
      <section className="py-20 lg:py-32">
        <div className="container mx-auto px-6">
          <div className="text-center max-w-3xl mx-auto mb-20">
            <h1 className="text-4xl lg:text-6xl font-bold tracking-tight mb-6">
              Pick a plan. Top up coins.
            </h1>
            <p className="text-xl text-muted-foreground">
              Plans for steady use, coins for one-off boosts — all in one place.
            </p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-8 mb-16">
            {plans.map((plan, index) => (
              <motion.div
                key={plan.name}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: index * 0.1 }}
                className={`relative glass-card p-8 rounded-3xl flex flex-col ${
                  plan.popular ? "border-primary shadow-primary/20" : ""
                }`}
              >
                {plan.popular && (
                  <div className="absolute -top-4 left-1/2 -translate-x-1/2 bg-primary text-primary-foreground text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                    Popular
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
                  <a href={SIGNUP_URL}>{plan.cta}</a>
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
