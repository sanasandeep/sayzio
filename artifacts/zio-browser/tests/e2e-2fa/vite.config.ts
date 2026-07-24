/**
 * Vite config for the AuthModal 2FA e2e harness. Built on demand by the
 * Playwright spec (see artifacts/1inme/tests/Browser/zio-browser-2fa-auth.spec.ts):
 *   pnpm --filter @workspace/zio-browser exec vite build --config tests/e2e-2fa/vite.config.ts
 */
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  root: __dirname,
  base: './',
  plugins: [react()],
  build: {
    outDir: path.resolve(__dirname, 'dist'),
    emptyOutDir: true,
  },
  resolve: {
    alias: {
      '@shared': path.resolve(__dirname, '../../src/shared'),
    },
  },
});
