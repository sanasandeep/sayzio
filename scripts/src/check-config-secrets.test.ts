import { describe, it, expect } from "vitest";
import {
  scanForTokenPatterns,
  scanReplitUserenv,
  looksLikeSecretValue,
  isBenignConfigValue,
  isConfigFile,
  isAcknowledged,
  shannonEntropy,
  ACKNOWLEDGED_FINDINGS,
  type Finding,
} from "./check-config-secrets.js";

/**
 * Regression suite for the config-secrets guard.
 *
 * Both directions are pinned: the guard must FIRE on known secret token
 * shapes and high-entropy `[userenv]` values (so a future refactor cannot
 * silently disable it), and must STAY QUIET on legit config values —
 * bucket names, regions, URLs, flags — so it never becomes noise.
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

const rules = (fs: Finding[]) => fs.map((f) => f.rule);

describe("scanForTokenPatterns — flags known secret shapes", () => {
  it("flags a fine-grained GitHub PAT", () => {
    const fake = "github_pat_11ABCDEFG0" + "abcdefghijklmnopqrstuv1234567890";
    const out = scanForTokenPatterns("x.toml", `token = "${fake}"`);
    expect(rules(out)).toContain("github-fine-grained-pat");
    expect(out[0].line).toBe(1);
  });

  it("flags a classic ghp_ token", () => {
    const fake = "ghp_" + "A1b2C3d4E5f6G7h8I9j0K1l2M3n4O5p6Q7r8";
    expect(rules(scanForTokenPatterns("f.yml", `t: ${fake}`))).toContain("github-token");
  });

  it("flags an AWS access key id", () => {
    expect(rules(scanForTokenPatterns("f.sh", "export K=AKIA" + "IOSFODNN7EXAMPL0"))).toContain(
      "aws-access-key-id",
    );
  });

  it("flags an OpenAI-style sk- key", () => {
    const fake = "sk-" + "proj4bCdEfGhIjKlMnOpQrStUvWx";
    expect(rules(scanForTokenPatterns("f.json", `"key": "${fake}"`))).toContain(
      "openai-style-key",
    );
  });

  it("flags a Slack xox token", () => {
    const fake = "xoxb-" + "123456789012-abcdefABCDEF";
    expect(rules(scanForTokenPatterns("f.yaml", fake))).toContain("slack-token");
  });

  it("flags a PEM private key header", () => {
    expect(
      rules(scanForTokenPatterns("f.conf", "-----BEGIN RSA PRIVATE KEY-----")),
    ).toContain("private-key-block");
  });

  it("flags credentials embedded in a URL", () => {
    expect(
      rules(scanForTokenPatterns("f.toml", 'db = "postgres://admin:S3cr3tPass99@host/db"')),
    ).toContain("url-with-credentials");
  });

  it("reports the correct line number and never prints the full value", () => {
    const fake = "ghp_" + "A1b2C3d4E5f6G7h8I9j0K1l2M3n4O5p6Q7r8";
    const [f] = scanForTokenPatterns("f.yml", `a: 1\nb: ${fake}\n`);
    expect(f.line).toBe(2);
    expect(f.excerpt).not.toContain(fake);
  });
});

describe("scanForTokenPatterns — stays quiet on benign content", () => {
  it("ignores placeholder-shaped tokens", () => {
    expect(scanForTokenPatterns("f.md.json", '"k": "ghp_yourTokenGoesRightHerePlaceholder0000"')).toEqual([]);
  });

  it("ignores ordinary config", () => {
    const src = 'AWS_BUCKET = "1in.me"\nAWS_DEFAULT_REGION = "ap-south-2"\nflag = true';
    expect(scanForTokenPatterns(".replit", src)).toEqual([]);
  });

  it("ignores URLs without credentials", () => {
    expect(
      scanForTokenPatterns(".replit", 'AWS_URL = "https://d3l7wvr1shk1cg.cloudfront.net"'),
    ).toEqual([]);
  });
});

describe("userenv high-entropy heuristic", () => {
  it("flags a long mixed-case random token value", () => {
    expect(looksLikeSecretValue("qN4pHcj3SDKCnQRrNcTyWOXdsxQMmeyhRy4Mf1")).toBe(true);
  });

  it("does not flag bucket names, regions, domains, flags, numbers", () => {
    for (const v of [
      "1in.me",
      "ap-south-2",
      "true",
      "12345",
      "https://d3l7wvr1shk1cg.cloudfront.net",
      "my-bucket-name-with-many-segments",
      "Sayzio",
      "A human readable sentence value here",
    ]) {
      expect(looksLikeSecretValue(v), v).toBe(false);
    }
  });

  it("only scans inside [userenv*] sections", () => {
    const src = [
      "[deployment]",
      'X = "qN4pHcj3SDKCnQRrNcTyWOXdsxQMmeyhRy4Mf1"',
      "[userenv.shared]",
      'Y = "qN4pHcj3SDKCnQRrNcTyWOXdsxQMmeyhRy4Mf1"',
      'AWS_BUCKET = "1in.me"',
      "[[ports]]",
      'Z = "qN4pHcj3SDKCnQRrNcTyWOXdsxQMmeyhRy4Mf1"',
    ].join("\n");
    const out = scanReplitUserenv(".replit", src);
    expect(out).toHaveLength(1);
    expect(out[0].key).toBe("Y");
    expect(out[0].line).toBe(4);
  });

  it("entropy helper behaves sanely", () => {
    expect(shannonEntropy("aaaa")).toBe(0);
    expect(shannonEntropy("abcd")).toBeGreaterThan(1.9);
  });

  it("short values are always benign", () => {
    expect(isBenignConfigValue("Xy9z")).toBe(true);
  });
});

describe("file scoping", () => {
  it("includes .replit, replit.nix, github workflows, toml/yaml/json/sh/env", () => {
    for (const f of [
      ".replit",
      "replit.nix",
      ".github/workflows/tests.yml",
      "pnpm-workspace.yaml",
      "deploy/ec2/deploy.sh",
      "sayzio-dialer-standalone/.env.example",
      "artifacts/api-server/package.json",
    ]) {
      expect(isConfigFile(f), f).toBe(true);
    }
  });

  it("excludes source code and assets", () => {
    for (const f of [
      "scripts/src/check-config-secrets.ts",
      "artifacts/1inme/app/Models/User.php",
      "attached_assets/shot.png",
      "docs/api.md",
    ]) {
      expect(isConfigFile(f), f).toBe(false);
    }
  });
});

describe("acknowledged findings", () => {
  it("acknowledgments are pinned by exact value hash — a different value is NOT acknowledged", () => {
    const f: Finding = {
      file: ".replit",
      line: 1,
      rule: "userenv-high-entropy-value",
      excerpt: "CONTACT_ADMIN_TOKEN = q…",
      value: "someRotatedNewValue1234567890ABCDEF",
      key: "CONTACT_ADMIN_TOKEN",
    };
    expect(isAcknowledged(f)).toBe(false);
  });

  it("every acknowledgment entry has a full sha256 fingerprint", () => {
    for (const a of ACKNOWLEDGED_FINDINGS) {
      expect(a.sha256).toMatch(/^[0-9a-f]{64}$/);
      expect(a.file.length).toBeGreaterThan(0);
      expect(a.key.length).toBeGreaterThan(0);
    }
  });
});
