import { describe, it, expect } from "vitest";
import {
  VIEWS_REL,
  THEME_STYLES_REL,
  THEME_STYLES_VIEW_REL,
  stripComments,
  declaresOwnDocument,
  parseIncludes,
  parseExtends,
  parseComponents,
  includesThemeStyles,
} from "./blade-theme-scope.js";

/**
 * Unit suite for the shared Blade theme-scope detection.
 *
 * Both the undefined-css-var guard and the light-mode pairing guard depend on
 * this module answering ONE question consistently — "does this blade page load
 * the app's light/dark theme system (theme-styles)?". These tests pin that
 * shared contract so the two guards can never quietly disagree on scope again.
 *
 * Run: pnpm --filter @workspace/scripts run test
 */

describe("path constants", () => {
  it("keys theme-styles both repo-relative and views-relative", () => {
    expect(VIEWS_REL).toBe("artifacts/1inme/resources/views");
    expect(THEME_STYLES_VIEW_REL).toBe("common/partials/theme-styles.blade.php");
    expect(THEME_STYLES_REL).toBe(`${VIEWS_REL}/${THEME_STYLES_VIEW_REL}`);
  });
});

describe("stripComments", () => {
  it("removes blade {{-- --}}, HTML <!-- --> and CSS /* */ comments", () => {
    expect(stripComments("a{{-- x --}}b")).not.toContain("x");
    expect(stripComments("a<!-- x -->b")).not.toContain("x");
    expect(stripComments("a/* x */b")).not.toContain("x");
  });

  it("does not touch a real var() reference outside a comment", () => {
    expect(stripComments("color: var(--surface, #fff);")).toContain("var(--surface, #fff)");
  });
});

describe("declaresOwnDocument", () => {
  it("is true when the source opens its own <html> or <head>", () => {
    expect(declaresOwnDocument("<html><head></head></html>")).toBe(true);
    expect(declaresOwnDocument("<head><title>x</title></head>")).toBe(true);
  });

  it("is false for a partial/section-only fragment", () => {
    expect(declaresOwnDocument("@extends('layouts.app')<style>.a{}</style>")).toBe(false);
  });
});

describe("parseIncludes", () => {
  it("maps every @include-family directive to a views-relative key", () => {
    const src = [
      "@include('common.partials.theme-styles')",
      "@includeIf('common.partials.head')",
      "@includeWhen($cond, 'common.partials.foot')",
    ].join("\n");
    expect(parseIncludes(src)).toEqual([
      "common/partials/theme-styles.blade.php",
      "common/partials/head.blade.php",
      "common/partials/foot.blade.php",
    ]);
  });

  it("skips dynamic and namespaced view names", () => {
    expect(parseIncludes("@include($view)@include('pkg::thing')")).toEqual([]);
  });
});

describe("parseExtends", () => {
  it("maps the layout name to a views-relative key", () => {
    expect(parseExtends("@extends('user.layouts.app')")).toEqual(["user/layouts/app.blade.php"]);
  });
});

describe("parseComponents", () => {
  it("resolves the @component directive form", () => {
    expect(parseComponents("@component('components.card')")).toEqual(["components/card.blade.php"]);
  });

  it("resolves <x-...> tags into the components/ tree", () => {
    expect(parseComponents("<x-app-layout><x-forms.input/></x-app-layout>")).toEqual([
      "components/app-layout.blade.php",
      "components/forms/input.blade.php",
    ]);
  });

  it("skips namespaced and dynamic components", () => {
    expect(parseComponents("<x-pkg::foo/><x-dynamic-component/>")).toEqual([]);
  });
});

describe("includesThemeStyles", () => {
  it("finds theme-styles reached directly", () => {
    const files = new Map<string, string>([
      ["p.blade.php", "@include('common.partials.theme-styles')"],
      [THEME_STYLES_VIEW_REL, ":root{--x:#000}"],
    ]);
    expect(includesThemeStyles("p.blade.php", files)).toBe(true);
  });

  it("finds theme-styles reached transitively through @extends and @include", () => {
    const files = new Map<string, string>([
      ["page.blade.php", "@extends('layouts.app')"],
      ["layouts/app.blade.php", "@include('common.partials.head')"],
      ["common/partials/head.blade.php", "@include('common.partials.theme-styles')"],
      [THEME_STYLES_VIEW_REL, ":root{--x:#000}"],
    ]);
    expect(includesThemeStyles("page.blade.php", files)).toBe(true);
  });

  it("returns false when theme-styles is never pulled in", () => {
    const files = new Map<string, string>([
      ["page.blade.php", "@extends('layouts.bare')"],
      ["layouts/bare.blade.php", "<html><head></head></html>"],
    ]);
    expect(includesThemeStyles("page.blade.php", files)).toBe(false);
  });

  it("is cycle-safe when partials include each other", () => {
    const files = new Map<string, string>([
      ["a.blade.php", "@include('b')"],
      ["b.blade.php", "@include('a')"],
    ]);
    expect(includesThemeStyles("a.blade.php", files)).toBe(false);
  });
});
