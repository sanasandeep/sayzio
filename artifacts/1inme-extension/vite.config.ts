import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import { resolve } from "node:path";

const browser = process.env.EXT_BROWSER || "chrome";
const outDir = resolve(__dirname, `dist/${browser}`);

export default defineConfig({
  plugins: [react()],
  define: {
    "process.env.NODE_ENV": JSON.stringify("production"),
  },
  build: {
    outDir,
    emptyOutDir: true,
    sourcemap: false,
    minify: "esbuild",
    target: "es2022",
    rollupOptions: {
      input: {
        popup: resolve(__dirname, "src/popup/index.html"),
        background: resolve(__dirname, "src/background/index.ts"),
        "content-extract": resolve(__dirname, "src/content/extract.ts"),
        "content-extract-contact": resolve(__dirname, "src/content/extract-contact.ts"),
        "content-handshake": resolve(__dirname, "src/content/handshake.ts"),
      },
      output: {
        entryFileNames: (chunk) => {
          if (chunk.name === "background") return "background.js";
          if (chunk.name === "content-extract") return "content-extract.js";
          if (chunk.name === "content-extract-contact") return "content-extract-contact.js";
          if (chunk.name === "content-handshake") return "content-handshake.js";
          return "assets/[name]-[hash].js";
        },
        chunkFileNames: "assets/[name]-[hash].js",
        assetFileNames: "assets/[name]-[hash].[ext]",
      },
    },
  },
});
