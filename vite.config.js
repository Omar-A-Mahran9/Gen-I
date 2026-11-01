import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Non-admin pages (Tailwind).
                'resources/css/app.css',
                'resources/js/app.js',
                // Metronic admin (Bootstrap 5.3) — must never import Tailwind.
                'resources/css/admin.css',
                'resources/js/admin/app.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        // Only processes files that import "tailwindcss" (resources/css/app.css).
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
