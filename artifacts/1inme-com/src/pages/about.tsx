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

  const milestones = [
    {
      date: "Apr 2023",
      title: "Idea on a whiteboard",
      description:
        "An offhand conversation about how messy social bios are turns into the first sketch of 1INME.",
    },
    {
      date: "Sep 2023",
      title: "First public beta",
      description:
        "We open the doors to a handful of friends and creators. Biolinks and short links only — but it works.",
    },
    {
      date: "Mar 2024",
      title: "Crossed 10,000 users",
      description:
        "Word spreads. Creators across India and South-East Asia start moving their link-in-bio to 1INME.",
    },
    {
      date: "Nov 2024",
      title: "Analytics & QR codes",
      description:
        "Live analytics, the Performance Coach and dynamic QR codes ship — turning 1INME into a real growth tool.",
    },
    {
      date: "Jun 2025",
      title: "Workspaces for teams",
      description:
        "Agencies and small teams get proper workspaces, roles and per-workspace billing.",
    },
    {
      date: "Feb 2026",
      title: "Hello, world",
      description:
        "1INME crosses 100k creators across more than 60 countries. We're just getting started.",
    },
  ];

  return (
    <PageLayout
      title="About 1INME"
      description="We help creators, freelancers, agencies and small businesses turn one link into a complete online presence."
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

          <div className="max-w-3xl mx-auto text-center mb-16">
            <h2 className="text-2xl lg:text-3xl font-bold tracking-tight mb-4">
              What we believe in
            </h2>
            <p className="text-lg text-muted-foreground">
              Four ideas that show up in every line of code, support reply, and
              roadmap call.
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

          <div className="grid grid-cols-3 gap-8 max-w-3xl mx-auto mb-24 text-center">
            {[
              { stat: "120,000+", label: "Creators served" },
              { stat: "3", label: "Years young" },
              { stat: "9", label: "Teammates" },
            ].map((item) => (
              <div key={item.label}>
                <div className="text-3xl lg:text-4xl font-bold text-primary mb-1">
                  {item.stat}
                </div>
                <div className="text-sm text-muted-foreground">{item.label}</div>
              </div>
            ))}
          </div>

          <div className="max-w-3xl mx-auto mb-24 space-y-12">
            <div>
              <h2 className="text-2xl lg:text-3xl font-bold tracking-tight mb-4">
                Our story
              </h2>
              <p className="text-muted-foreground leading-relaxed mb-4">
                1INME started in 2023 in a tiny workspace in Hyderabad. Our
                founder kept watching small businesses and creators juggle five
                different tools to do one simple thing: share their work and
                capture leads. We thought there was a better way.
              </p>
              <p className="text-muted-foreground leading-relaxed">
                We shipped the first version of 1INME — just biolinks and short
                links — to a handful of friends. They loved it, broke it, told us
                what was missing, and we kept iterating. Today, thousands of
                creators across the world use 1INME to run their online presence
                from one URL.
              </p>
            </div>
            <div>
              <h2 className="text-2xl lg:text-3xl font-bold tracking-tight mb-4">
                What we believe
              </h2>
              <p className="text-muted-foreground leading-relaxed">
                Software should respect your time and your audience. We don't sell
                your data, we don't bolt on features that don't earn their keep,
                and we ship every week. If something's broken or unclear, our team
                is one message away.
              </p>
            </div>
          </div>

          <div className="max-w-3xl mx-auto glass-card p-10 rounded-3xl mb-24">
            <p className="text-sm font-semibold uppercase tracking-widest text-primary mb-4">
              Part of EEFind
            </p>
            <h2 className="text-2xl lg:text-3xl font-bold tracking-tight mb-4">
              Built by EEFind Private Limited
            </h2>
            <p className="text-muted-foreground leading-relaxed mb-6">
              1INME is a brand and product of EEFIND PVT LTD (EEFind Private
              Limited) — an aggregator marketplace on a mission to be "The All in
              One App for everything essential." From groceries home-delivered by
              neighbourhood stores to trusted home help like carpentry, plumbing
              and home cleaning, EEFind brings everyday essentials together in one
              place. Their promise sums up the philosophy 1INME is built on: "We
              are not in a hurry to deliver in 10 mins. We drive safe."
            </p>
            <div className="grid grid-cols-3 gap-4 mb-8">
              {[
                { stat: "4,000+", label: "Products" },
                { stat: "2,000+", label: "Merchants" },
                { stat: "35+", label: "Cities live" },
              ].map((item) => (
                <div
                  key={item.label}
                  className="rounded-2xl bg-primary/5 border border-primary/10 p-4 text-center"
                >
                  <div className="text-2xl lg:text-3xl font-bold text-primary mb-1">
                    {item.stat}
                  </div>
                  <div className="text-xs uppercase tracking-wider text-muted-foreground">
                    {item.label}
                  </div>
                </div>
              ))}
            </div>
            <div className="grid sm:grid-cols-2 gap-3 text-sm text-muted-foreground">
              <div>
                <span className="font-semibold text-foreground">
                  Registered office
                </span>
                <br />8 Amrutha Nilayam, Banjara Hills, Hyderabad, Telangana
                500034
              </div>
              <div className="space-y-1">
                <div>
                  <span className="font-semibold text-foreground">Email</span>{" "}
                  <a
                    href="mailto:support@eefind.com"
                    className="hover:text-primary transition-colors"
                  >
                    support@eefind.com
                  </a>
                </div>
                <div>
                  <span className="font-semibold text-foreground">WhatsApp</span>{" "}
                  +91 81210 57755
                </div>
                <div>
                  <span className="font-semibold text-foreground">Website</span>{" "}
                  <a
                    href="https://eefind.com"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="hover:text-primary transition-colors"
                  >
                    eefind.com
                  </a>
                </div>
              </div>
            </div>
          </div>

          <div className="max-w-3xl mx-auto glass-card p-10 rounded-3xl mb-24">
            <p className="text-sm font-semibold uppercase tracking-widest text-primary mb-4">
              Meet the founder
            </p>
            <h2 className="text-2xl font-bold mb-1">Sandeep Sana</h2>
            <p className="text-muted-foreground mb-6">Founder &amp; CEO</p>
            <p className="text-muted-foreground leading-relaxed">
              Guided by this belief, Sandeep Sana, Founder &amp; CEO of 1INME, has
              dedicated more than 16 years to building digital products that
              empower businesses and creators. His journey from developer to
              entrepreneur led to the creation of 1INME, an all-in-one platform
              that helps users build their digital identity, engage audiences, and
              unlock new growth opportunities. Through innovation and a relentless
              focus on user needs, he continues to shape solutions that make online
              success more accessible to everyone.
            </p>
          </div>

          <div className="max-w-3xl mx-auto mb-24">
            <div className="text-center mb-12">
              <h2 className="text-2xl lg:text-3xl font-bold tracking-tight mb-4">
                Milestones
              </h2>
              <p className="text-lg text-muted-foreground">
                A short history of how we got here.
              </p>
            </div>
            <div className="space-y-6">
              {milestones.map((m, index) => (
                <motion.div
                  key={m.title}
                  initial={{ opacity: 0, y: 20 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true, margin: "-80px" }}
                  transition={{ duration: 0.4, delay: (index % 3) * 0.08 }}
                  className="glass-card p-6 rounded-3xl flex flex-col sm:flex-row sm:items-baseline gap-2 sm:gap-6"
                >
                  <div className="text-sm font-semibold text-primary shrink-0 sm:w-24">
                    {m.date}
                  </div>
                  <div>
                    <h3 className="font-semibold mb-1">{m.title}</h3>
                    <p className="text-sm text-muted-foreground leading-relaxed">
                      {m.description}
                    </p>
                  </div>
                </motion.div>
              ))}
            </div>
          </div>

          <div className="max-w-3xl mx-auto glass-card p-10 rounded-3xl text-center">
            <h2 className="text-2xl lg:text-3xl font-bold mb-4">
              Want to build with us?
            </h2>
            <p className="text-muted-foreground mb-8 max-w-xl mx-auto">
              Whether you are a creator with feedback or a developer who wants to
              join, we love hearing from you.
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Button asChild size="lg" className="rounded-full h-14 px-8 text-base">
                <a href={SIGNUP_URL}>
                  Try 1INME free <ArrowRight className="ml-2 w-5 h-5" />
                </a>
              </Button>
              <Button
                asChild
                variant="outline"
                size="lg"
                className="rounded-full h-14 px-8 text-base bg-transparent border-primary/20 hover:bg-primary/5"
              >
                <Link href="/contact">Say hello</Link>
              </Button>
            </div>
          </div>
        </div>
      </section>
    </PageLayout>
  );
}
