import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/auth-ajax.js'],
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
