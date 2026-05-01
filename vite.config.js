import { defineConfig, loadEnv } from "vite";

// import fs from 'fs';
import laravel from "laravel-vite-plugin";
import mkcert from "vite-plugin-mkcert";
import { resolve } from "path";
// import path from 'path';
import vuePlugin from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";
import { VitePWA } from "vite-plugin-pwa";

export default ({ mode }) => {
    process.env = { ...process.env, ...loadEnv(mode, process.cwd()) };

    return defineConfig({
        base: "./",
        server: {
            port: 3331,
            host: "127.0.0.1",
            hmr: {
                protocol: "wss",
                host: "localhost",
            },
            https: true,
        },
        resolve: {
            alias: [
                {
                    find: "&",
                    replacement: resolve(__dirname, "resources"),
                },
                {
                    find: "~",
                    replacement: resolve(__dirname, "resources/ts"),
                },
            ],
        },
        plugins: [
            tailwindcss(),
            laravel({
                input: [
                    "resources/ts/bootstrap.ts",
                    "resources/ts/app-init.ts",
                    "resources/ts/app.ts",
                    "resources/ts/auth.ts",
                    "resources/ts/pos.ts",
                    "resources/ts/pos-init.ts",
                    "resources/ts/setup.ts",
                    "resources/ts/update.ts",
                    "resources/ts/cashier.ts",
                    "resources/ts/lang-loader.ts",
                    "resources/ts/dev.ts",
                    "resources/ts/popups.ts",
                    "resources/ts/widgets.ts",
                    "resources/ts/wizard.ts",

                    "resources/css/app.css",
                    "resources/css/grid.css",
                    "resources/css/animations.css",
                    "resources/css/fonts.css",
                    "resources/scss/line-awesome/1.3.0/scss/line-awesome.scss",

                    // themes
                    "resources/css/light.css",
                    "resources/css/dark.css",
                    "resources/css/phosphor.css",
                ],
                refresh: true,
            }),
            mkcert(),
            VitePWA({
                registerType: "autoUpdate",
                injectRegister: null,
                includeAssets: ["favicon.ico"],
                manifest: {
                    name: "DrCare",
                    short_name: "DrCare",
                    description: "POS & Inventory Management System",
                    theme_color: "#4f46e5",
                    background_color: "#ffffff",
                    display: "standalone",
                    orientation: "any",
                    scope: "/",
                    start_url: "/",
                    icons: [
                        {
                            src: "/images/icons/icon-192x192.png",
                            sizes: "192x192",
                            type: "image/png",
                        },
                        {
                            src: "/images/icons/icon-512x512.png",
                            sizes: "512x512",
                            type: "image/png",
                        },
                        {
                            src: "/images/icons/icon-512x512.png",
                            sizes: "512x512",
                            type: "image/png",
                            purpose: "maskable",
                        },
                    ],
                },
                workbox: {
                    globPatterns: [
                        "**/*.{js,css,html,ico,png,svg,woff2,woff,ttf,eot}",
                    ],
                    runtimeCaching: [
                        {
                            urlPattern: /^https?:\/\/.*\/api\//,
                            handler: "NetworkFirst",
                            options: {
                                cacheName: "api-cache",
                                expiration: {
                                    maxEntries: 100,
                                    maxAgeSeconds: 60 * 60 * 24,
                                },
                            },
                        },
                    ],
                },
            }),
            vuePlugin({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],
    });
};
