import { Header } from "./header";
import { Footer } from "./footer";
import { SEO } from "../seo";
import { AnnouncementBanner } from "./announcement-banner";

interface PageLayoutProps {
  children: React.ReactNode;
  title: string;
  description: string;
}

export function PageLayout({ children, title, description }: PageLayoutProps) {
  return (
    <div className="min-h-[100dvh] flex flex-col relative">
      <SEO title={title} description={description} />
      <div className="aurora-bg" />
      <AnnouncementBanner />
      <Header />
      <main
        className="flex-1"
        style={{ paddingTop: "calc(4rem + var(--inme-anno-h, 0px))" }}
      >
        {children}
      </main>
      <Footer />
    </div>
  );
}
