import { PageLayout } from "@/components/layout/page-layout";
import { motion } from "framer-motion";
import { Box, QrCode, LineChart, Link as LinkIcon, FormInput, Mail, Users } from "lucide-react";

export default function Features() {
  const features = [
    {
      icon: Box,
      name: "Biolink builder",
      description: "Drag, drop, ship. Stack blocks for text, images, video, audio, embeds, products, donations and forms. Reorder by dragging, swap themes in a click, and publish a polished page in minutes — no design skills needed."
    },
    {
      icon: QrCode,
      name: "Dynamic QR",
      description: "Scannable. Trackable. Every link gets a high-resolution QR code you can style with your logo and colours. Because the destination is editable, the same printed code can be repurposed forever — change the target without reprinting."
    },
    {
      icon: LineChart,
      name: "Live analytics",
      description: "Numbers that move. See visitors arrive in real time, with country, city, device, referrer and conversion breakdowns. The Performance Coach watches your numbers and surfaces concrete fixes — slow pages, dead blocks, broken links, missing CTAs."
    },
    {
      icon: LinkIcon,
      name: "Branded short links",
      description: "Turn long URLs into clean, on-brand short links you can repoint at any time. Add UTMs automatically, password-protect sensitive links, expire them on a date or after N clicks, and route visitors by country, device or language."
    },
    {
      icon: FormInput,
      name: "Forms & contact capture",
      description: "Build forms with conditional logic, embed them anywhere, and pipe submissions straight into your contact list. Tag, segment and export contacts to power broadcasts, the dialer or your favourite CRM."
    },
    {
      icon: Mail,
      name: "Broadcasts & follow-ups",
      description: "Send email and SMS broadcasts to segmented audiences, schedule follow-ups, and track delivery, opens and replies."
    },
    {
      icon: Users,
      name: "Workspaces & team roles",
      description: "Create a workspace per brand or client, invite teammates with the right role (Owner, Admin, Editor, Viewer), and keep billing, analytics and contacts cleanly separated."
    }
  ];

  return (
    <PageLayout
      title="Features"
      description="A complete tour of every capability inside 1INME — from your biolink and short links to inboxes, teams, billing, and beyond."
    >
      <section className="py-20 lg:py-32">
        <div className="container mx-auto px-6">
          <div className="max-w-3xl mx-auto text-center mb-20">
            <h1 className="text-4xl lg:text-6xl font-bold tracking-tight mb-6">
              Your link, <span className="text-transparent bg-clip-text bg-gradient-to-r from-primary to-accent-foreground">supercharged.</span>
            </h1>
            <p className="text-xl text-muted-foreground">
              A complete tour of every capability inside 1INME — from your biolink and short links to inboxes, teams, billing, and beyond.
            </p>
          </div>

          <div className="grid md:grid-cols-2 gap-8 lg:gap-12">
            {features.map((feature, index) => (
              <motion.div
                key={feature.name}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: "-100px" }}
                transition={{ duration: 0.5, delay: index * 0.1 }}
                className="glass-card p-8 rounded-3xl"
              >
                <div className="w-14 h-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mb-6">
                  <feature.icon className="w-7 h-7" />
                </div>
                <h3 className="text-2xl font-semibold mb-4">{feature.name}</h3>
                <p className="text-muted-foreground leading-relaxed">
                  {feature.description}
                </p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>
    </PageLayout>
  );
}
