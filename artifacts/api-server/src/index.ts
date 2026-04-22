import { readdir, readFile, readlink } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import app from "./app";
import { logger } from "./lib/logger";

const rawPort = process.env["PORT"];

if (!rawPort) {
  throw new Error(
    "PORT environment variable is required but was not provided.",
  );
}

const port = Number(rawPort);

if (Number.isNaN(port) || port <= 0) {
  throw new Error(`Invalid PORT value: "${rawPort}"`);
}

const isProduction = process.env["NODE_ENV"] === "production";
const selfEntry = fileURLToPath(import.meta.url);
const selfEntryBasename = path.basename(selfEntry);
const selfArtifactDir = path.resolve(path.dirname(selfEntry), "..");

function exitPortHeld(boundPort: number): never {
  // Use console.error (synchronous to stderr) so the message reaches the workflow
  // log even though pino's worker may not flush before process.exit.
  console.error(
    `[api-server] Port ${boundPort} is already in use by another process. ` +
      `Restart the API Server workflow, or free the port (e.g. \`fuser -k ${boundPort}/tcp\`) and try again.`,
  );
  process.exit(1);
}

async function findStaleApiServerPids(): Promise<number[]> {
  if (process.platform !== "linux") return [];
  const myPid = process.pid;
  const matches: number[] = [];
  let entries: string[];
  try {
    entries = await readdir("/proc");
  } catch {
    return [];
  }
  await Promise.all(
    entries.map(async (entry) => {
      const pid = Number(entry);
      if (!Number.isInteger(pid) || pid <= 0 || pid === myPid) return;
      try {
        const cmdlineRaw = await readFile(`/proc/${pid}/cmdline`);
        // /proc/<pid>/cmdline is NUL-separated argv.
        const argv = cmdlineRaw
          .toString("utf8")
          .split("\0")
          .filter((s) => s.length > 0);
        if (argv.length === 0) return;
        // First arg should be the node interpreter.
        if (!path.basename(argv[0]!).startsWith("node")) return;
        // Some other argv entry should resolve (relative to the process's cwd)
        // to *this exact* compiled entry file.
        let cwd: string;
        try {
          cwd = await readlink(`/proc/${pid}/cwd`);
        } catch {
          return;
        }
        const cwdAbs = path.resolve(cwd);
        if (cwdAbs !== selfArtifactDir) return;
        const matchesEntry = argv
          .slice(1)
          .some((arg) => path.resolve(cwdAbs, arg) === selfEntry);
        if (!matchesEntry) return;
        matches.push(pid);
      } catch {
        // Process may have exited or be inaccessible; ignore.
      }
    }),
  );
  return matches;
}

async function killStalePeersAndWait(): Promise<boolean> {
  const pids = await findStaleApiServerPids();
  if (pids.length === 0) return false;
  logger.warn(
    { pids, port, entry: path.basename(selfEntry) },
    "Port in use; terminating stale API server process(es) from a previous run.",
  );
  for (const pid of pids) {
    try {
      process.kill(pid, "SIGTERM");
    } catch {
      // Already gone.
    }
  }
  // Wait for them to release the port (max ~2s).
  const deadline = Date.now() + 2000;
  while (Date.now() < deadline) {
    await new Promise((r) => setTimeout(r, 100));
    const stillAlive = pids.filter((pid) => {
      try {
        process.kill(pid, 0);
        return true;
      } catch {
        return false;
      }
    });
    if (stillAlive.length === 0) break;
  }
  // Force-kill any survivors.
  for (const pid of pids) {
    try {
      process.kill(pid, "SIGKILL");
    } catch {
      // Already gone.
    }
  }
  await new Promise((r) => setTimeout(r, 200));
  return true;
}

function listenOnce(): Promise<void> {
  return new Promise((resolve, reject) => {
    const server = app.listen(port);
    server.once("listening", () => {
      server.removeListener("error", reject);
      logger.info({ port }, "Server listening");
      resolve();
    });
    server.once("error", reject);
  });
}

async function start() {
  try {
    await listenOnce();
    return;
  } catch (err) {
    const code = (err as NodeJS.ErrnoException).code;
    if (code !== "EADDRINUSE") {
      logger.error({ err }, "Error listening on port");
      process.exit(1);
    }
    if (isProduction) {
      exitPortHeld(port);
    }
    const killed = await killStalePeersAndWait();
    if (!killed) {
      exitPortHeld(port);
    }
    try {
      await listenOnce();
      // Friendly, plain-English notice so creators notice the self-heal in
      // the workflow log alongside the structured warning above.
      console.log(
        `[api-server] Recovered port ${port} from a previous run.`,
      );
    } catch (retryErr) {
      const retryCode = (retryErr as NodeJS.ErrnoException).code;
      if (retryCode === "EADDRINUSE") {
        exitPortHeld(port);
      }
      logger.error({ err: retryErr }, "Error listening on port");
      process.exit(1);
    }
  }
}

void start();
