import { Header } from "./header";
import { Footer } from "./footer";
import { SEO } from "../seo";
import { AnnouncementBanner } from "./announcement-banner";

interface PageLayoutProps {
  children: React.ReactNode;
  title: string;
  description: string;
  /** Optional per-page Open Graph/Twitter image override. */
  image?: string;
}

export function PageLayout({ children, title, description, image }: PageLayoutProps) {
  return (
    <div className="min-h-[100dvh] flex flex-col relative">
      <SEO title={title} description={description} image={image} />
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
