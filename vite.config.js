import { defineConfig } from 'vite'
import Symfony from '@symfony/reprise/vite'
import tailwindcss from '@tailwindcss/vite'

// One entry for now. Adding another means a line here plus its own index.js — vite bundles
// each on its own, so a marketing bundle would never carry the application's CSS.
export default defineConfig({
    input: {
        app: './assets/app/index.js',
    },
    plugins: [
        Symfony({
            // Turns the Stimulus feature on: every assets/controllers/*_controller.js is
            // registered under its filename, third-party UX packages come from here.
            stimulus: 'assets/controllers.json',

            // Images referenced straight from templates, not imported by JS or CSS. Each one
            // is copied into the build under a hashed name and keyed in manifest.json, so
            // asset('build/images/…') resolves to the hashed URL — and the year-long cache
            // header applies to them too.
            copy: [
                { from: 'assets/images', to: 'images' },
            ],
        }),
        tailwindcss(),
    ],
    server: {
        // Listen on every interface: the server runs in a container, the browser is outside.
        host: true,
        port: 5173,
        // Fail instead of drifting to 5174, which the compose file does not publish.
        strictPort: true,
        // What the browser must call. Without it vite derives the origin from the listen
        // address and writes https://0.0.0.0:5173 into entrypoints.json.
        origin: 'https://localhost:5173',
        // Same mkcert certificate the app uses — the page is https, so its assets must be too.
        https: {
            key: './frankenphp/certs/tls.key',
            cert: './frankenphp/certs/tls.pem',
        },
    },
})
