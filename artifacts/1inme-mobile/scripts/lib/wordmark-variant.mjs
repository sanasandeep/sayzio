// Shared helpers for asserting which Sayzio wordmark PNG a screen rendered.
//
// The brand wordmark (components/Brand.tsx) ships as two PNGs:
//   - wordmark-dark-text.png  → dark ink, for LIGHT backgrounds
//   - wordmark-white-text.png → white ink, for DARK backgrounds
// <BrandWordmark> normally picks the variant from the OS color scheme
// (useColorScheme). Screens sitting on a FIXED dark surface regardless of
// theme (e.g. the onboarding splash top bar) pass forceVariant="dark-bg" so
// they always get the white PNG — otherwise a light-mode device would render
// the dark-text logo, invisible on the dark bar.
//
// On Expo web the bundled require() resolves each asset to a URL that keeps
// the original filename, so we can prove which variant is on screen by scanning
// every <img src>/currentSrc and CSS background-image for the two filenames.
// Only the SELECTED variant is passed to <Image source>, so only its filename
// appears in the DOM — the unselected one stays a JS module URL, never rendered.

// Scan the whole document for the two wordmark filenames. Returns which
// variant(s) are actually present in the rendered DOM.
export async function scanWordmarkVariants(page) {
  return page.evaluate(() => {
    const found = { white: false, darkText: false, srcs: [] };
    const scan = (val) => {
      if (!val) return;
      let d = val;
      try {
        d = decodeURIComponent(val);
      } catch {}
      if (d.includes("wordmark-white-text")) {
        found.white = true;
        found.srcs.push(d);
      }
      if (d.includes("wordmark-dark-text")) {
        found.darkText = true;
        found.srcs.push(d);
      }
    };
    document.querySelectorAll("img").forEach((img) => {
      scan(img.getAttribute("src"));
      scan(img.currentSrc);
    });
    document.querySelectorAll("*").forEach((el) => {
      const bg = window.getComputedStyle(el).backgroundImage;
      if (bg && bg !== "none") scan(bg);
    });
    return found;
  });
}

// Default reporters so callers can use these without wiring fail/log. `fail`
// throws (the caller's top-level catch decides the exit code); `log` prints.
const defaultFail = (msg) => {
  throw new Error(msg);
};
const defaultLog = (...args) => console.log(...args);

// Assert the rendered wordmark is the WHITE (dark-background) variant and NOT
// the dark-text one. For screens on a FIXED dark surface (onboarding splash):
// the white PNG must win even in light mode (the forceVariant="dark-bg" guard).
export async function assertWordmarkWhiteVariant(
  page,
  { label, fail = defaultFail, log = defaultLog } = {},
) {
  const hits = await scanWordmarkVariants(page);
  if (!hits.white) {
    fail(
      `${label}: white/dark-bg wordmark (wordmark-white-text.png) not found ` +
        `in the DOM; srcs=${JSON.stringify(hits.srcs)}`,
    );
  }
  if (hits.darkText) {
    fail(
      `${label}: dark-text wordmark (wordmark-dark-text.png) rendered — it is ` +
        `invisible on the dark top bar; srcs=${JSON.stringify(hits.srcs)}`,
    );
  }
  log(`${label}: brand wordmark resolved to the white/dark-bg variant`);
}

// Assert the rendered wordmark is the VISIBLE variant for a THEME-ADAPTIVE
// surface (one whose background follows the OS color scheme — colors.background
// on the sign-in / setup screens). There the adaptive default is correct, so
// the rendered variant must MATCH the scheme:
//   - light scheme → white surface → dark-text PNG must show, white must not
//   - dark scheme  → dark surface  → white PNG must show, dark-text must not
// Either mismatch means the logo is the wrong ink for the surface, i.e.
// effectively invisible — the exact failure this guards against.
export async function assertWordmarkVisibleVariant(
  page,
  { colorScheme, label, fail = defaultFail, log = defaultLog },
) {
  const hits = await scanWordmarkVariants(page);
  const wantWhite = colorScheme === "dark";
  if (!hits.white && !hits.darkText) {
    fail(
      `${label}: no wordmark PNG found in the DOM at all ` +
        `(srcs=${JSON.stringify(hits.srcs)})`,
    );
  }
  if (wantWhite) {
    if (!hits.white) {
      fail(
        `${label}: expected the white wordmark (wordmark-white-text.png) on a ` +
          `dark-mode surface but it was not rendered; ` +
          `srcs=${JSON.stringify(hits.srcs)}`,
      );
    }
    if (hits.darkText) {
      fail(
        `${label}: dark-text wordmark rendered on a dark-mode surface — it is ` +
          `invisible; srcs=${JSON.stringify(hits.srcs)}`,
      );
    }
  } else {
    if (!hits.darkText) {
      fail(
        `${label}: expected the dark-text wordmark (wordmark-dark-text.png) on ` +
          `a light-mode surface but it was not rendered; ` +
          `srcs=${JSON.stringify(hits.srcs)}`,
      );
    }
    if (hits.white) {
      fail(
        `${label}: white wordmark rendered on a light-mode surface — it is ` +
          `invisible on the light background; srcs=${JSON.stringify(hits.srcs)}`,
      );
    }
  }
  log(
    `${label}: brand wordmark resolved to the visible variant for the ` +
      `${colorScheme} surface (${wantWhite ? "white" : "dark-text"})`,
  );
}
