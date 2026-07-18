import { useState, useEffect, useCallback } from 'react';
import { ChromeBar } from './components/ChromeBar';
import { ZioPanel } from './components/ZioPanel';
import { NewTabPage } from './components/NewTabPage';
import { AuthModal } from './components/AuthModal';
import { ModePicker } from './components/ModePicker';
import { DashboardLayout } from './components/DashboardLayout';
import { SplitLayout } from './components/SplitLayout';
import { useTabStore } from './store/tab-store';
import { useAuthStore } from './store/auth-store';
import { useModeStore } from './store/mode-store';
import type { WindowMode } from '../shared/window-mode';

const FIRST_LAUNCH_KEY = 'zio_mode_picker_shown';

export default function App() {
  const [zioPanelOpen, setZioPanelOpen] = useState(false);
  const [authModalOpen, setAuthModalOpen] = useState(false);
  const [showModePicker, setShowModePicker] = useState(false);
  const { tabs, activeTabId, initTabs } = useTabStore();
  const { init: initAuth, user } = useAuthStore();
  const { mode, splitRatio, isInitialized, setMode, setSplitRatio, init: initMode } = useModeStore();

  useEffect(() => {
    void Promise.all([initAuth(), initTabs(), initMode()]).then(() => {
      // Show the mode picker on first launch (no persisted mode choice)
      const shown = localStorage.getItem(FIRST_LAUNCH_KEY);
      if (!shown) {
        setShowModePicker(true);
      }
    });
  }, [initAuth, initTabs, initMode]);

  const handlePickMode = useCallback((picked: WindowMode) => {
    localStorage.setItem(FIRST_LAUNCH_KEY, '1');
    setShowModePicker(false);
    void setMode(picked);
  }, [setMode]);

  const activeTab = activeTabId ? tabs[activeTabId] : null;
  const showNewTab = !activeTab || activeTab.url === '' || activeTab.url === 'about:newtab';

  const handleToggleZio = useCallback(() => {
    if (!user) {
      setAuthModalOpen(true);
      return;
    }
    setZioPanelOpen(prev => !prev);
  }, [user]);

  // Show mode picker before content is ready
  if (!isInitialized) {
    return <div style={{ width: '100%', height: '100%', background: 'var(--color-bg)' }} />;
  }

  if (showModePicker) {
    return <ModePicker defaultMode={mode} onPick={handlePickMode} />;
  }

  // ── Dashboard mode ────────────────────────────────────────────────────────
  if (mode === 'dashboard') {
    return (
      <DashboardLayout
        mode={mode}
        onSetMode={(m) => void setMode(m)}
        authModalOpen={authModalOpen}
        onOpenAuth={() => setAuthModalOpen(true)}
        onCloseAuth={() => setAuthModalOpen(false)}
      />
    );
  }

  // ── Split mode ────────────────────────────────────────────────────────────
  if (mode === 'split') {
    return (
      <SplitLayout
        mode={mode}
        splitRatio={splitRatio}
        onSetMode={(m) => void setMode(m)}
        onSetSplitRatio={(r) => void setSplitRatio(r)}
        authModalOpen={authModalOpen}
        onOpenAuth={() => setAuthModalOpen(true)}
        onCloseAuth={() => setAuthModalOpen(false)}
      />
    );
  }

  // ── Browser mode (default) ────────────────────────────────────────────────
  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      <ChromeBar
        zioPanelOpen={zioPanelOpen}
        onToggleZio={handleToggleZio}
        onOpenAuth={() => setAuthModalOpen(true)}
        showModeSwitcher={true}
      />

      <div style={{ flex: 1, display: 'flex', overflow: 'hidden' }}>
        {showNewTab && (
          <div style={{ flex: 1, display: 'flex', alignItems: 'stretch' }}>
            <NewTabPage onNavigate={(url) => {
              if (activeTabId) {
                void window.zio.tabs.navigate(activeTabId, url);
              }
            }} />
          </div>
        )}

        {zioPanelOpen && (
          <ZioPanel
            pageContext={activeTab ? { url: activeTab.url, title: activeTab.title } : null}
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
