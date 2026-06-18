const TYPES = [
  { slug: "short-link", name: "Short Link", icon: "fa-link", accent: "#7c3aed",
    whatIs: "Clean, branded short links you can repoint anytime — with click analytics and expiry controls.",
    howHelps: "Drop one tidy link in your bio or ad, swap where it points anytime, and watch every click roll in." },
  { slug: "link-in-bio", name: "Link in Bio", icon: "fa-share-nodes", accent: "#1bd4d9",
    whatIs: "A drag-and-drop one-link page with a deep block library, custom themes and a guided wizard.",
    howHelps: "Put your whole world behind one link — shop, socials, music and more — in a page you design in minutes." },
  { slug: "conversational", name: "Conversational", icon: "fa-comments", accent: "#34d399",
    whatIs: "A chat-style page that greets visitors and guides them through your links one message at a time.",
    howHelps: "Welcome every visitor like a real chat and guide them step by step to the exact link they need." },
  { slug: "slides", name: "Slides", icon: "fa-images", accent: "#fbbf24",
    whatIs: "A swipeable, story-style page that presents your content as full-screen slides.",
    howHelps: "Tell your story full-screen — let fans swipe through your launch, lookbook or portfolio like a reel." },
  { slug: "ai-chatbot", name: "AI Chatbot", icon: "fa-robot", accent: "#a855f7",
    whatIs: "An AI page that answers visitor questions about you using your own content, around the clock.",
    howHelps: "Answer fans 24/7 without lifting a finger — your AI knows your content and never sleeps." },
  { slug: "restaurant-menu", name: "Restaurant Menu", icon: "fa-utensils", accent: "#fb7185",
    whatIs: "A digital menu with categories, photos and prices — plus optional table-side ordering by QR.",
    howHelps: "Turn every table into a QR menu — guests browse photos and order without waiting for staff." },
  { slug: "file-share", name: "File Share", icon: "fa-file-arrow-down", accent: "#38bdf8",
    whatIs: "Upload a file and share it through a short link that streams the download to visitors.",
    howHelps: "Send your menu, PDF or media kit as one clean link — no attachments, just a tap to download." },
  { slug: "event", name: "Event", icon: "fa-calendar-day", accent: "#f472b6",
    whatIs: "A shareable calendar event visitors can add to their own calendar in a single tap.",
    howHelps: "Fill your next show — visitors add your event to their calendar in one tap so nobody forgets." },
  { slug: "contact-card", name: "Contact Card", icon: "fa-address-card", accent: "#ff8a3c",
    whatIs: "A downloadable vCard so people can save your full contact details with one tap.",
    howHelps: "Get saved in everyone's phone instantly — one tap drops your full contact card into their contacts." },
  { slug: "reviews-page", name: "Reviews Page", icon: "fa-star", accent: "#ffc845",
    whatIs: "A review wall that collects and shows star ratings and feedback from your visitors.",
    howHelps: "Show off your five-star love — collect fresh reviews and display social proof that wins trust." },
];

const SETS = [
  { key: "what-it-is", letter: "a", eyebrow: "WHAT IT IS", field: "whatIs" },
  { key: "how-1inme-helps", letter: "b", eyebrow: "HOW 1IN.ME HELPS", field: "howHelps" },
];

module.exports = { TYPES, SETS };
