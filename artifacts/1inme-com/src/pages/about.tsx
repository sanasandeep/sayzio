import { PageLayout } from "@/components/layout/page-layout";
import { Button } from "@/components/ui/button";
import { SIGNUP_URL } from "@/config";
import { motion } from "framer-motion";
import { Link } from "wouter";
import { Rocket, Heart, ShieldCheck, Globe2, ArrowRight } from "lucide-react";

export default function About() {
  const values = [
    {
      icon: Rocket,
      title: "Ship fast, ship calm",
      description: "New things every week, never on a Friday at 5pm.",
    },
    {
      icon: Heart,
      title: "Creators first",
      description: "Every line of code earns its keep by helping a creator.",
    },
    {
      icon: ShieldCheck,
      title: "Privacy by default",
      description: "No spying, no shady resale, no dark patterns.",
    },
    {
      icon: Globe2,
      title: "Built remote-first",
      description: "A small team across three timezones, talking by writing.",
    },
  ];

  return (
    <PageLayout
      title="About"
      description="Built for the people doing the work. We started 1INME because juggling ten different tools to run your audience felt absurd."
    >
      <section className="py-20 lg:py-32">
        <div className="container mx-auto px-6">
          <div className="max-w-3xl mx-auto text-center mb-20">
            <p className="text-sm font-semibold uppercase tracking-widest text-primary mb-4">
              Our mission
            </p>
            <h1 className="text-4xl lg:text-6xl font-bold tracking-tight mb-6">
              Built for the people{" "}
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-primary to-accent-foreground">
                doing the work.
              </span>
            </h1>
            <p className="text-xl text-muted-foreground leading-relaxed">
              One link should do everything: show your work, capture leads, sell,
              message and tell your story. We started 1INME because juggling ten
              different tools to do that felt absurd, and the existing biolink
              tools stopped at a list of buttons.
            </p>
          </div>

          <div className="grid sm:grid-cols-2 gap-8 max-w-4xl mx-auto mb-24">
            {values.map((value, index) => (
              <motion.div
                key={value.title}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: "-80px" }}
                transition={{ duration: 0.5, delay: index * 0.1 }}
                className="glass-card p-8 rounded-3xl"
              >
                <div className="w-14 h-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mb-6">
                  <value.icon className="w-7 h-7" />
                </div>
                <h3 className="text-xl font-semibold mb-3">{value.title}</h3>
                <p className="text-muted-foreground leading-relaxed">
                  {value.description}
                </p>
              </motion.div>
            ))}
          </div>

          <div className="grid grid-cols-2 lg:grid-cols-4 gap-8 max-w-4xl mx-auto mb-24 text-center">
            {[
              { stat: "120,000+", label: "creators served" },
              { stat: "40+", label: "block types" },
              { stat: "9", label: "teammates" },
              { stat: "3 years", label: "young" },
            ].map((item) => (
              <div key={item.label}>
                <div className="text-3xl lg:text-4xl font-bold text-primary mb-1">
                  {item.stat}
                </div>
                <div className="text-sm text-muted-foreground">{item.label}</div>
              </div>
            ))}
          </div>

          <div className="max-w-3xl mx-auto glass-card p-10 rounded-3xl text-center">
            <h2 className="text-2xl lg:text-3xl font-bold mb-4">
              Ready to bring it all into one link?
            </h2>
            <p className="text-muted-foreground mb-8 max-w-xl mx-auto">
              Build, grow, and monetize your audience without juggling ten
              different tools.
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Button asChild size="lg" className="rounded-full h-14 px-8 text-base">
                <a href={SIGNUP_URL}>
                  Sign up free <ArrowRight className="ml-2 w-5 h-5" />
                </a>
              </Button>
              <Button
                asChild
                variant="outline"
                size="lg"
                className="rounded-full h-14 px-8 text-base bg-transparent border-primary/20 hover:bg-primary/5"
              >
                <Link href="/features">Explore features</Link>
              </Button>
            </div>
          </div>
        </div>
      </section>
    </PageLayout>
  );
}
