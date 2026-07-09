// Shared resilient evaluation for source-driven tests that lift REAL
// expressions / statement blocks / function bodies out of shipped screens.
//
// The lifted code references free variables from component scope (url,
// evTitle, setBusy, ...). Historically each test hard-listed them as Function
// parameters, so any NEW variable added to the screen threw a raw
// ReferenceError and broke the whole mobile-unit chain — looking like a test
// bug instead of a product change.
//
// runExtractedCall / runExtractedStatements evaluate the lifted code inside
// `with (proxy)`: known variables come from the provided scope, real globals
// (JSON, Date, Promise, ...) fall through to globalThis, and any UNKNOWN
// identifier defaults to null while being recorded. Unknowns produce a loud,
// actionable warning naming the new variable(s); if evaluation still throws,
// the error message says exactly which new screen variables to add to the
// test instead of a bare ReferenceError.
//
// When the evaluated result is itself a FUNCTION (a lifted callback the test
// invokes later), the returned function is wrapped so unknown-variable
// detection and the actionable hint also cover those later invocations —
// including rejected promises from async bodies. Non-ReferenceError failures
// (e.g. an ApiError a test asserts on) pass through untouched.

/**
 * Evaluate a lifted call expression, e.g. `createQrCode(<real args>)`.
 *
 * @param {string} expr  the expression source text
 * @param {object} scope known free variables (name -> value)
 * @param {string} label human label for messages, e.g. "createQrCode"
 * @param {object} [opts]
 * @param {string} [opts.test] test name for message prefixes, e.g. "test-import-url"
 * @returns the expression's value (functions/promises are hint-wrapped)
 */
export function runExtractedCall(expr, scope, label, opts = {}) {
  return evaluate(`return (${expr});`, scope, label, opts);
}

/**
 * Evaluate lifted statements verbatim, then return an expression, e.g.
 *   runExtractedStatements(`${eventsUsedLine}\n${createLockedExpr}`,
 *                          "createLocked", { calQ, plan, isEdit }, ...)
 *
 * @param {string} stmts      the statement block source text
 * @param {string} returnExpr expression evaluated after the statements
 * @param {object} scope      known free variables
 * @param {string} label      human label for messages
 * @param {object} [opts]     see runExtractedCall
 */
export function runExtractedStatements(stmts, returnExpr, scope, label, opts = {}) {
  return evaluate(`${stmts}\nreturn (${returnExpr});`, scope, label, opts);
}

function evaluate(body, scope, label, opts) {
  const test = opts.test ?? "source-driven test";
  const unknowns = new Set();
  const warned = new Set();

  const proxy = new Proxy(Object.create(null), {
    has: () => true, // shadow everything so `get` decides resolution
    get: (_t, key) => {
      if (key === Symbol.unscopables) return undefined;
      if (typeof key !== "string") return undefined;
      if (Object.prototype.hasOwnProperty.call(scope, key)) return scope[key];
      if (key in globalThis) return globalThis[key];
      unknowns.add(key);
      return null;
    },
  });

  const advise = (names) =>
    `new variable(s) [${names.join(", ")}] appeared in the screen's ` +
    `${label} code — extend the scope passed to the test (${test}) ` +
    `to pin their contract`;

  // Warn once per newly-seen unknown (fresh ones can appear on each lazy
  // invocation of a lifted callback).
  const flushWarnings = () => {
    const fresh = [...unknowns].filter((k) => !warned.has(k));
    if (!fresh.length) return;
    fresh.forEach((k) => warned.add(k));
    console.warn(`[${test}] WARNING: ${advise(fresh)} (defaulted to null)`);
  };

  const wrapError = (e) =>
    new Error(
      `[${test}] evaluating the screen's ${label} code failed: ${e.message}` +
        (unknowns.size ? ` — likely cause: ${advise([...unknowns])}` : ""),
      { cause: e },
    );

  let result;
  try {
    // eslint-disable-next-line no-new-func
    result = new Function("__scope__", `with (__scope__) { ${body} }`)(proxy);
  } catch (e) {
    throw wrapError(e);
  }
  flushWarnings();
  return hintWrap(result, { flushWarnings, wrapError });
}

// Wrap functions/promises so lazy invocations of lifted callbacks still get
// unknown-variable warnings and an actionable hint on ReferenceErrors.
// Deliberate throws (ApiError, assertion helpers, ...) pass through untouched.
function hintWrap(value, ctx) {
  const { flushWarnings, wrapError } = ctx;
  if (typeof value === "function") {
    const wrapped = (...args) => {
      let out;
      try {
        out = value(...args);
      } catch (e) {
        flushWarnings();
        throw e instanceof ReferenceError ? wrapError(e) : e;
      }
      flushWarnings();
      return hintWrap(out, ctx);
    };
    return wrapped;
  }
  if (value && typeof value.then === "function") {
    return value.then(
      (v) => {
        flushWarnings();
        return v;
      },
      (e) => {
        flushWarnings();
        throw e instanceof ReferenceError ? wrapError(e) : e;
      },
    );
  }
  return value;
}
