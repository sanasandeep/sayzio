import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  root: 'src/renderer',
  base: './',
  plugins: [react()],
  server: {
    port: parseInt(process.env['VITE_PORT'] ?? '5173', 10),
    strictPort: true,
  },
  build: {
    outDir: path.resolve(__dirname, 'dist/main/renderer'),
    emptyOutDir: true,
  },
  resolve: {
    alias: {
      '@shared': path.resolve(__dirname, 'src/shared'),
    },
  },
});
