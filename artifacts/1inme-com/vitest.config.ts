import { defineConfig } from "vitest/config";
import react from "@vitejs/plugin-react";
import path from "path";

// Standalone test config — deliberately NOT importing vite.config.ts, which
// throws unless PORT/BASE_PATH are set (those are supplied by the dev/build
// workflow, not the test runner). We only need the same `@` / `@assets`
// aliases so the Zio Bot widget and its bundled mascot PNGs resolve in jsdom.
export default defineConfig({
  plugins: [react()],
  // vitest pulls in vite 8 (rolldown/oxc) even though the app builds on vite 7.
  // Under vite 8 `config.oxc` is populated by default, so @vitejs/plugin-react's
  // esbuild JSX handoff is ignored and `.tsx` JSX reaches import-analysis
  // untransformed. Configure oxc's JSX transform directly so test files compile.
  oxc: {
    jsx: {
      runtime: "automatic",
      importSource: "react",
    },
  },
  resolve: {
    alias: {
      "@": path.resolve(import.meta.dirname, "src"),
      "@assets": path.resolve(import.meta.dirname, "..", "..", "attached_assets"),
    },
    dedupe: ["react", "react-dom"],
  },
  test: {
    environment: "jsdom",
    globals: true,
    setupFiles: ["./vitest.setup.ts"],
    include: ["src/**/*.test.{ts,tsx}"],
  },
});
