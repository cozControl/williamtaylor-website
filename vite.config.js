import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/admin.css',
                'resources/css/admin-access.css',
                'resources/css/admin-media.css',
                'resources/css/admin-cms.css',
                'resources/css/cms-preview.css',
                'resources/js/app.js',
                'resources/js/admin.js',
                'resources/js/media-upload-queue.js',
                'resources/js/media-replacement.js',
                'resources/js/cms-editor.js',
                'resources/js/passkeys.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
