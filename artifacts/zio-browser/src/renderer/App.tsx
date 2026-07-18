import { useState, useEffect, useCallback } from 'react';
import { ChromeBar } from './components/ChromeBar';
import { ZioPanel } from './components/ZioPanel';
import { NewTabPage } from './components/NewTabPage';
import { AuthModal } from './components/AuthModal';
import { useTabStore } from './store/tab-store';
import { useAuthStore } from './store/auth-store';

export default function App() {
  const [zioPanelOpen, setZioPanelOpen] = useState(false);
  const [authModalOpen, setAuthModalOpen] = useState(false);
  const { tabs, activeTabId, initTabs } = useTabStore();
  const { init: initAuth, user } = useAuthStore();

  useEffect(() => {
    void initAuth();
    void initTabs();
  }, [initAuth, initTabs]);

  const activeTab = activeTabId ? tabs[activeTabId] : null;
  const showNewTab = !activeTab || activeTab.url === '' || activeTab.url === 'about:newtab';

  const handleToggleZio = useCallback(() => {
    if (!user) {
      setAuthModalOpen(true);
      return;
    }
    setZioPanelOpen(prev => !prev);
  }, [user]);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      <ChromeBar
        zioPanelOpen={zioPanelOpen}
        onToggleZio={handleToggleZio}
        onOpenAuth={() => setAuthModalOpen(true)}
      />

      <div style={{ flex: 1, display: 'flex', overflow: 'hidden' }}>
        {/* The actual web content is rendered in WebContentsView by Electron main.
            We only show UI overlays here (new tab page, etc.) */}
        {showNewTab && (
          <div style={{
            flex: 1,
            display: 'flex',
            alignItems: 'stretch',
          }}>
            <NewTabPage onNavigate={(url) => {
              if (activeTabId) {
                void window.zio.tabs.navigate(activeTabId, url);
              }
            }} />
          </div>
        )}

        {zioPanelOpen && (
          <ZioPanel
            pageContext={activeTab ? {
              url: activeTab.url,
              title: activeTab.title,
            } : null}
            onClose={() => setZioPanelOpen(false)}
          />
        )}
      </div>

      {authModalOpen && (
        <AuthModal onClose={() => setAuthModalOpen(false)} />
      )}
    </div>
  );
}
