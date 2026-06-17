import { Header } from "./header";
import { Footer } from "./footer";
import { SEO } from "../seo";

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
      <Header />
      <main className="flex-1 pt-16">{children}</main>
      <Footer />
    </div>
  );
}
