import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/tokens.css',
                'resources/css/base.css',
                'resources/css/guest.css',
                'resources/css/staff.css',
                'resources/js/app.js',
                'resources/js/core/i18n.js',
                'resources/js/main-guest.js',
                'resources/js/main-staff.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        cors: true,
        hmr: {
            host: 'afternoon-salvaging-unfounded.ngrok-free.dev',
        },
    },
});
