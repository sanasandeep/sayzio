import { PageLayout } from "@/components/layout/page-layout";
import { Button } from "@/components/ui/button";
import { CTABand } from "@/components/marketing/marketing";
import { motion, useReducedMotion } from "framer-motion";
import { SIGNUP_URL } from "@/config";
import { Link } from "wouter";
import { ArrowRight } from "lucide-react";
import { useState, useEffect } from "react";

const ROLES = [
  "Creator",
  "Artist",
  "Businessman",
  "Musician",
  "Coach",
  "Photographer",
  "Influencer",
  "Podcaster",
  "Writer",
  "Designer",
];

const LINK_TYPES = [
  {
    name: "Short Link",
    new: false,
    desc: "Clean, branded short links you can repoint anytime — with click analytics and expiry controls.",
  },
  {
    name: "Link in Bio",
    new: false,
    desc: "A drag-and-drop one-link page with a deep block library, custom themes and a guided wizard.",
  },
  {
    name: "Conversational",
    new: true,
    desc: "A chat-style page that greets visitors and guides them through your links one message at a time.",
  },
  {
    name: "Slides",
    new: true,
    desc: "A swipeable, story-style page that presents your content as full-screen slides.",
  },
  {
    name: "AI Chatbot",
    new: false,
    desc: "An AI page that answers visitor questions about you using your own content, around the clock.",
  },
  {
    name: "Restaurant Menu",
    new: true,
    desc: "A digital menu with categories, photos and prices — plus optional table-side ordering by QR.",
  },
  {
    name: "File Share",
    new: true,
    desc: "Upload a file and share it through a short link that streams the download to visitors.",
  },
  {
    name: "Event",
    new: false,
    desc: "A shareable calendar event visitors can add to their own calendar in a single tap.",
  },
  {
    name: "Contact Card",
    new: true,
    desc: "A downloadable vCard so people can save your full contact details with one tap.",
  },
  {
    name: "Reviews Page",
    new: true,
    desc: "A review wall that collects and shows star ratings and feedback from your visitors.",
  },
];

export default function Home() {
  const [roleIndex, setRoleIndex] = useState(0);
  const prefersReducedMotion = useReducedMotion();

  useEffect(() => {
    if (prefersReducedMotion) return;
    const interval = setInterval(() => {
      setRoleIndex((current) => (current + 1) % ROLES.length);
    }, 2000);
    return () => clearInterval(interval);
  }, [prefersReducedMotion]);

  return (
    <PageLayout
      title="All-in-one Link Platform"
      description="Whoever you are, 1INME is the all-in-one link, monetization & growth stack — free forever, no card required."
    >
      {/* Hero Section */}
      <section className="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
        <div className="mesh-bg" aria-hidden />
        <div className="grid-bg" aria-hidden />
        <div className="container mx-auto px-6 relative z-10">
          <div className="grid lg:grid-cols-2 gap-12 items-center">
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6 }}
              className="max-w-2xl"
            >
              <p className="text-sm font-medium text-muted-foreground mb-6">
                Analytics · Followers · Social integrations · Free Forever · Native mobile app
              </p>

              <h1 className="text-5xl lg:text-7xl font-bold tracking-tight text-foreground mb-6 leading-tight">
                I am a{" "}
                <span className="grad-text transition-all duration-300">
                  {ROLES[roleIndex]}
                </span>
              </h1>

              <p className="text-lg lg:text-xl text-muted-foreground mb-10 max-w-xl">
                Whoever you are, 1INME is the all-in-one link, monetization &amp;
                growth stack: drag-and-drop biolink pages, branded short links,
                dynamic QR codes, NFC tags, built-in DMs, an AI Performance Coach
                and a native mobile app — free forever, no card required.
              </p>

              <div className="flex flex-col sm:flex-row gap-4">
                <Button asChild size="lg" className="rounded-full h-14 px-8 text-base">
                  <a href={SIGNUP_URL}>
                    Make mine free <ArrowRight className="ml-2 w-5 h-5" />
                  </a>
                </Button>
                <Button asChild variant="outline" size="lg" className="rounded-full h-14 px-8 text-base bg-transparent border-primary/20 hover:bg-primary/5">
                  <Link href="/features">See it live</Link>
                </Button>
              </div>

              <div className="mt-12 flex flex-wrap gap-x-8 gap-y-4 text-sm font-medium text-muted-foreground">
                <div className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-green-500" />
                  120,000+ creators served
                </div>
                <div>9 teammates</div>
                <div>3 years young</div>
              </div>
            </motion.div>

            <motion.div
              initial={{ opacity: 0, scale: 0.9 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ duration: 0.8, delay: 0.2 }}
              className="relative lg:ml-auto"
            >
              <div className="relative w-full max-w-[340px] mx-auto">
                <img 
                  src={`${import.meta.env.BASE_URL}hero-mockup.png`} 
                  alt="1INME Biolink Profile" 
                  className="w-full h-auto drop-shadow-2xl rounded-[3rem]"
                />
                
                {/* Floating Badges */}
                <motion.div 
                  animate={{ y: [0, -10, 0] }}
                  transition={{ repeat: Infinity, duration: 4, ease: "easeInOut" }}
                  className="absolute top-1/4 -left-12 glass-card py-2 px-4 rounded-full text-sm font-semibold flex items-center gap-2 whitespace-nowrap"
                >
                  <span className="w-2 h-2 rounded-full bg-red-500 animate-pulse" />
                  Live · 247 visitors
                </motion.div>

                <motion.div 
                  animate={{ y: [0, 10, 0] }}
                  transition={{ repeat: Infinity, duration: 5, ease: "easeInOut", delay: 1 }}
                  className="absolute bottom-1/3 -right-16 glass-card py-3 px-5 rounded-2xl text-sm font-semibold flex flex-col gap-1 whitespace-nowrap"
                >
                  <span className="text-muted-foreground text-xs">Top link today</span>
                  <span className="text-primary flex items-center gap-2">
                    /new-album <span className="text-green-500">+18%</span>
                  </span>
                </motion.div>
              </div>
            </motion.div>
          </div>
        </div>
      </section>

      {/* What you can create */}
      <section className="py-24 bg-card/50 border-y">
        <div className="container mx-auto px-6">
          <div className="text-center mb-16">
            <h2 className="text-3xl font-bold mb-4">Ten kinds of link. One simple dashboard.</h2>
            <p className="text-muted-foreground max-w-2xl mx-auto">
              From a classic link-in-bio to short links, QR codes, menus, events
              and AI chat — create whatever your audience needs from a single place.
            </p>
          </div>

          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {LINK_TYPES.map((type, i) => (
              <motion.div
                key={type.name}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: "-80px" }}
                transition={{ duration: 0.45, delay: (i % 3) * 0.08 }}
                className="glass-card p-6 rounded-2xl"
              >
                <div className="flex items-center gap-3 mb-2">
                  <h3 className="text-lg font-semibold">{type.name}</h3>
                  {type.new && (
                    <span className="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-primary/10 text-primary">
                      New
                    </span>
                  )}
                </div>
                <p className="text-muted-foreground text-sm leading-relaxed">{type.desc}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      <CTABand
        title="Your audience is already searching for you."
        subtitle="Build the page. Share the link. Watch them show up — live on a map."
        primary={{ label: "Make mine free", href: SIGNUP_URL }}
        secondary={{ label: "See features", href: "/features" }}
      />
    </PageLayout>
  );
}
