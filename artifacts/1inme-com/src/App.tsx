import { Switch, Route, Router as WouterRouter } from "wouter";
import { MotionConfig } from "framer-motion";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import { ThemeProvider } from "@/components/theme-provider";
import SiteAssistant from "@/components/site-assistant";

import Home from "@/pages/home";
import Features from "@/pages/features";
import Pricing from "@/pages/pricing";
import About from "@/pages/about";
import Contact from "@/pages/contact";
import Faq from "@/pages/faq";
import Terms from "@/pages/terms";
import Privacy from "@/pages/privacy";
import Refunds from "@/pages/refunds";
import Gdpr from "@/pages/gdpr";
import Cookies from "@/pages/cookies";
import Blog from "@/pages/blog";
import BlogPost from "@/pages/blog-post";
import Changelog from "@/pages/changelog";
import Inbox from "@/pages/inbox";
import HowItWorks from "@/pages/how-it-works";
import Analytics from "@/pages/analytics";
import Integrations from "@/pages/integrations";
import Domains from "@/pages/domains";
import WorkspaceTeam from "@/pages/workspace-team";
import ApiDocs from "@/pages/api-docs";
import ResumeBuilder from "@/pages/resume-builder";
import Discovery from "@/pages/discovery";
import CreatorsFeed from "@/pages/creators-feed";
import PremiumFeatures from "@/pages/premium-features";
import Buzz from "@/pages/buzz";
import Services from "@/pages/services";
import UseCase from "@/pages/use-case";
import AiProduct from "@/pages/ai-product";
import Compare from "@/pages/compare";
import CompareDetail from "@/pages/compare-detail";
import NotFound from "@/pages/not-found";

const queryClient = new QueryClient();

function Router() {
  return (
    <Switch>
      <Route path="/" component={Home} />
      <Route path="/features" component={Features} />
      <Route path="/pricing" component={Pricing} />
      <Route path="/premium-features" component={PremiumFeatures} />
      <Route path="/how-it-works" component={HowItWorks} />
      <Route path="/analytics" component={Analytics} />
      <Route path="/integrations" component={Integrations} />
      <Route path="/domains" component={Domains} />
      <Route path="/workspace-team" component={WorkspaceTeam} />
      <Route path="/api-docs" component={ApiDocs} />
      <Route path="/resume-builder" component={ResumeBuilder} />
      <Route path="/discovery" component={Discovery} />
      <Route path="/creators-feed" component={CreatorsFeed} />
      <Route path="/buzz" component={Buzz} />
      <Route path="/services" component={Services} />
      <Route path="/for/:slug" component={UseCase} />
      <Route path="/ai/:slug" component={AiProduct} />
      <Route path="/compare" component={Compare} />
      <Route path="/compare/:slug" component={CompareDetail} />
      <Route path="/about" component={About} />
      <Route path="/contact" component={Contact} />
      <Route path="/faq" component={Faq} />
      <Route path="/terms" component={Terms} />
      <Route path="/privacy" component={Privacy} />
      <Route path="/refunds" component={Refunds} />
      <Route path="/gdpr" component={Gdpr} />
      <Route path="/cookies" component={Cookies} />
      <Route path="/blog" component={Blog} />
      <Route path="/blog/:slug" component={BlogPost} />
      <Route path="/changelog" component={Changelog} />
      <Route path="/admin/inbox" component={Inbox} />
      <Route component={NotFound} />
    </Switch>
  );
}

function App() {
  return (
    <ThemeProvider defaultTheme="dark" storageKey="1inme-theme">
      <MotionConfig reducedMotion="user">
        <QueryClientProvider client={queryClient}>
          <TooltipProvider>
            <WouterRouter base={import.meta.env.BASE_URL.replace(/\/$/, "")}>
              <Router />
            </WouterRouter>
            <Toaster />
            <SiteAssistant />
          </TooltipProvider>
        </QueryClientProvider>
      </MotionConfig>
    </ThemeProvider>
  );
}

export default App;
