import { defineConfig } from "vitest/config";

// Node-only unit tests for the guard scripts (no DOM / React needed).
export default defineConfig({
  test: {
    environment: "node",
    include: ["src/**/*.test.ts"],
  },
});
