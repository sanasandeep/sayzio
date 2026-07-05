import {
  Link2,
  File,
  Calendar,
  CalendarDays,
  Contact,
  IdCard,
  GalleryHorizontalEnd,
  Utensils,
  Store,
  FileText,
  Crown,
  Star,
  Palette,
  Lock,
  Bot,
  MessagesSquare,
  QrCode,
  ListChecks,
  type LucideIcon,
} from "lucide-react";

/**
 * Marketing mirror of the Laravel app's full link-type catalog
 * (`SitePagesContent::homeLinkTypesDefault()` /
 * `artifacts/1inme/app/Modules/User/Support/LinkTypeCategories.php`).
 *
 * The marketing site is a standalone React app with no runtime access to the
 * PHP catalog, so the full grouped copy is mirrored here. Keep the
 * categories, labels and descriptions — and above all the total count — in
 * step with the Laravel showcase; adding or renaming a type there should be
 * reflected here too.
 *
 * Font Awesome icons in the PHP catalog map to the closest lucide-react icon.
 */
export interface LinkTypeItem {
  icon: LucideIcon;
  label: string;
  desc: string;
}

export interface LinkTypeCategory {
  label: string;
  desc: string;
  types: LinkTypeItem[];
}

export const linkTypeCategories: LinkTypeCategory[] = [
  {
    label: "Everyday links",
    desc: "Quick, single-purpose links you can share anywhere in seconds.",
    types: [
      { icon: Link2, label: "Short Link", desc: "Shorten any URL with a custom alias and click tracking." },
      { icon: File, label: "File Share", desc: "Share a downloadable file behind a short link." },
      { icon: Calendar, label: "Event", desc: "A calendar event visitors can add in a single tap." },
      { icon: CalendarDays, label: "Calendar", desc: "A followable calendar of your events visitors can subscribe to." },
      { icon: Contact, label: "Contact Card", desc: "A digital business card visitors can save instantly." },
      { icon: QrCode, label: "QR Code", desc: "A dynamic, styleable QR code you can re-point any time." },
    ],
  },
  {
    label: "Pages & mini-sites",
    desc: "Full, customizable pages that live at a single link — no website needed.",
    types: [
      { icon: IdCard, label: "Link in Bio", desc: "A mini-site of your links, blocks and media on one page." },
      { icon: GalleryHorizontalEnd, label: "Slides", desc: "Present a swipeable deck of slides from a single link." },
      { icon: Utensils, label: "Restaurant Menu", desc: "A digital menu with sections, items and prices." },
      { icon: Store, label: "Store Menu", desc: "A product catalog with categories and an order-request cart." },
      { icon: FileText, label: "Resume / Portfolio", desc: "A shareable resume / portfolio page with PDF download." },
    ],
  },
  {
    label: "Business & monetization",
    desc: "Grow your reputation and earn from your audience.",
    types: [
      { icon: Crown, label: "Bizs Profile", desc: "A themeable home that automatically shows all your posts, tiers & tips — no linking needed." },
      { icon: Star, label: "Reviews Page", desc: "Collect and showcase reviews from your audience." },
      { icon: Palette, label: "Brand / Press Kit", desc: "A shareable press kit with logo downloads, colours, fonts and brand voice." },
      { icon: Lock, label: "Paid Page", desc: "A gated page that unlocks after a one-time payment or subscription." },
      { icon: ListChecks, label: "Forms", desc: "A standalone form page with conditional logic and instant notifications." },
    ],
  },
  {
    label: "AI-powered",
    desc: "Let AI answer and guide your visitors for you.",
    types: [
      { icon: Bot, label: "AI Chatbot", desc: "An AI assistant that answers your visitors for you." },
      { icon: MessagesSquare, label: "Conversational", desc: "A guided, chat-style page that responds as visitors tap." },
    ],
  },
];

export const LINK_TYPE_COUNT = linkTypeCategories.reduce(
  (total, category) => total + category.types.length,
  0,
);
