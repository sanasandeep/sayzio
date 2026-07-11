import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // Project-authored helper scripts live here (not public/js) so Vite
            // content-hashes them and lists them in the build manifest. Blade
            // loads them via @vite([...]); a missing/renamed file then FAILS the
            // build instead of shipping a silent 404 + degraded page.
            //
            // Intentionally still loaded from public/js via asset('js/...') and
            // NOT bundled here:
            //   - public/js/vendor/* (alpine, alpine-collapse, chart.umd, jsqr,
            //     leaflet): third-party libraries, self-hosted + pinned on
            //     purpose (no CDN SPOF / SRI drift). Alpine in particular must
            //     stay a classic global <script defer>, not an ES module entry.
            //   - public/js/qr-studio/engine.js: large standalone public QR
            //     engine wanted as one stable, globally-cached URL across the
            //     many public QR pages that embed it.
            //   - public/js/social-proof-widget.js: embeddable widget served to
            //     third-party sites, needs a stable non-hashed URL.
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/auth-ajax.js',
                'resources/js/analytics-charts.js',
                'resources/js/map-pin-picker.js',
                'resources/js/marketing-anim.js',
                'resources/js/community-public.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    // Dev-only: when the watch-cycle loop sets VITE_KEEP_OUTDIR=1 (see
    // artifact.toml [services.development].run), we periodically restart the
    // `vite build --watch` process to flush Tailwind v4's ADDITIVE candidate set
    // (so classes deleted/renamed in a Blade file stop lingering in the served
    // CSS). Without this flag every restart's initial build would empty
    // public/build for ~1-2s and 500 the page on a missing @vite manifest; with
    // it the previous build's hashed assets stay in place until the fresh build
    // overwrites the manifest, so the restart is gap-free. Unset in production,
    // where the default (empty outDir) gives each deploy a clean build dir.
    build: {
        emptyOutDir: process.env.VITE_KEEP_OUTDIR === '1' ? false : undefined,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
