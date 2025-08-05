import { defineConfig, loadEnv } from "vite";
import react from "@vitejs/plugin-react";
import path from "path";
import { nodePolyfills } from "vite-plugin-node-polyfills";
import { createHtmlPlugin } from "vite-plugin-html";
// import { VitePWA } from "vite-plugin-pwa";

export default ({ mode }) => {
  // Load all environment variables into process.env
  process.env = { ...process.env, ...loadEnv(mode, process.cwd()) };

  const plugins = [
    react(),
    nodePolyfills(),
    createHtmlPlugin({
      inject: {
        data: {
          VITE_FAVICON: process.env.VITE_FAVICON,
        },
      },
    }),
    // VitePWA({
    //   registerType: 'autoUpdate',
    //   includeAssets: ['favicon.ico', 'robots.txt', 'apple-touch-icon.png'],
    //   workbox: {
    //     maximumFileSizeToCacheInBytes: 5 * 1024 * 1024,
    //   },
    //   manifest: {
    //     name: 'Motor Insurance',
    //     short_name: 'Motor Insurance',
    //     description: 'Motor Insurance.',
    //     theme_color: '#ffffff',
    //     background_color: '#ffffff',
    //     display: 'standalone',
    //     start_url: '/',
    //     icons: [
    //       {
    //         src: 'pwa-192x192.png',
    //         sizes: '192x192',
    //         type: 'image/png',
    //       },
    //       {
    //         src: 'pwa-512x512.png',
    //         sizes: '512x512',
    //         type: 'image/png',
    //       },
    //       {
    //         src: 'pwa-512x512.png',
    //         sizes: '512x512',
    //         type: 'image/png',
    //         purpose: 'any maskable',
    //       },
    //     ],
    //   },
    // }),
  ];

  return defineConfig({
    base: `/${process.env.VITE_BASENAME !== "NA" ? process.env.VITE_BASENAME : ""}`,
    plugins,
    resolve: {
      alias: {
        "@": path.resolve(__dirname, "./src"),
        modules: path.resolve(__dirname, "./src/modules"),
        components: path.resolve(__dirname, "./src/components"),
        utils: path.resolve(__dirname, "./src/utils"),
        api: path.resolve(__dirname, "./src/api"),
        app: path.resolve(__dirname, "./src/app"),
        config: path.resolve(__dirname, "./src/config"),
        css: path.resolve(__dirname, "./src/css"),
        assets: path.resolve(__dirname, "./src/assets"),
        hoc: path.resolve(__dirname, "./src/hoc"),
        analytics: path.resolve(__dirname, "./src/analytics"),
      },
    },
    define: {
      "import.meta.env": JSON.stringify(process.env),
      "process.env": process.env,
      "process.version": JSON.stringify("18.0.0"),
      "process.versions": JSON.stringify({ node: "18.0.0" }),
    },
    build: {
      outDir: "build",
      emptyOutDir: true,
      sourcemap: false,
      cssCodeSplit: true,
      rollupOptions: {
        output: {
          manualChunks: (id) => {
            // Optional: Customize vendor chunking here
          },
        },
      },
    },
  });
};
