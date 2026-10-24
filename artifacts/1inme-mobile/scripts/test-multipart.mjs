#!/usr/bin/env node
import assert from "node:assert/strict";
import fs from "node:fs";
import vm from "node:vm";
import ts from "typescript";
const root = new URL("../", import.meta.url);
let request;
function load(file, requireFn) {
  const output = ts.transpileModule(
    fs.readFileSync(new URL(file, root), "utf8"),
    {
      compilerOptions: {
        module: ts.ModuleKind.CommonJS,
        target: ts.ScriptTarget.ES2022,
        esModuleInterop: true,
      },
    },
  ).outputText;
  const exports = {};
  const context = {
    exports,
    require: requireFn,
    FormData,
    Blob,
    URLSearchParams,
    __DEV__: false,
    process: { env: { EXPO_PUBLIC_API_BASE_URL: "http://localhost:9999" } },
    console,
    fetch: async (url, init) => {
      request = { url, init };
      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify({ data: { ok: true } }),
      };
    },
  };
  vm.runInNewContext(output, context);
  return exports;
}
const api = load("lib/api.ts", (name) =>
  name === "react-native"
    ? { Platform: { OS: "web" } }
    : name === "expo-constants"
      ? { expoConfig: { version: "test" } }
      : name === "@/lib/secure"
        ? { getToken: async () => "local-test-token" }
        : (() => {
            throw new Error(name);
          })(),
);
const form = new FormData();
form.append(
  "proof_files[]",
  new Blob(["evidence"], { type: "application/pdf" }),
  "proof.pdf",
);
await api.apiFetch("/profile-verification", { method: "POST", body: form });
assert.equal(
  request.init.headers["Content-Type"],
  undefined,
  "Browser/RN must generate multipart boundary",
);
assert.equal(request.init.headers.Authorization, "Bearer local-test-token");
assert.equal(request.init.body, form);
await api.apiFetch("/me/creator-profile", {
  method: "PATCH",
  body: JSON.stringify({ sections: { posts: "0" } }),
});
assert.equal(request.init.headers["Content-Type"], "application/json");
const { evidenceForm } = load("lib/evidenceForm.ts", (name) => {
  throw new Error(name);
});
const evidence = await evidenceForm(
  {
    context: "workspace",
    creator_profile_id: 7,
    supporting_urls: ["https://example.com/a", "https://example.com/b"],
    message: null,
  },
  [],
);
assert.equal(evidence.get("creator_profile_id"), "7");
assert.deepEqual(evidence.getAll("supporting_urls[]"), [
  "https://example.com/a",
  "https://example.com/b",
]);
assert.equal(evidence.has("message"), false);
console.log(
  "PASS: multipart boundaries, bearer auth, JSON requests and evidence arrays",
);
