import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/**
 * NODELESS BUILD — runs on developer machines ONLY.
 * Production server has NO Node.js/npm.
 * Build output (public/build/) is committed and uploaded to the server.
 * Laravel reads public/build/manifest.json to serve pre-compiled assets.
 */
export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
        }),
    ],
});
