import { defineConfig } from "vite";
import { svelte } from "@sveltejs/vite-plugin-svelte";
import fs from "node:fs";
import path from "node:path";

const hotFile = "storage/public/hot";

/**
 * Keeps `storage/public/hot` in sync with the dev server so the Hyperf
 * backend can detect HMR mode and resolve assets from the Vite URL.
 */
function hyperfHotFile() {
  return {
    name: 'hyperf:hot-file',
    apply: 'serve',
    configureServer(server) {
      server.httpServer?.once('listening', () => {
        const address = server.httpServer?.address();
        const host =
          typeof address === 'object' && address
            ? address.address === '127.0.0.1' || address.address === '::1'
              ? 'localhost'
              : address.address
            : 'localhost';
        const port = typeof address === 'object' && address ? address.port : server.config.server.port;
        fs.mkdirSync(path.dirname(hotFile), { recursive: true });
        fs.writeFileSync(hotFile, `http://${host}:${port}`);
      });
      const clean = () => fs.rmSync(hotFile, { force: true });
      process.on('exit', clean);
      process.on('SIGINT', () => process.exit());
      process.on('SIGTERM', () => process.exit());
    },
  };
}

export default defineConfig(({ command }) => {
  const isBuild = command === 'build';
  return {
    base: isBuild ? '/public/build/' : '/',
    plugins: [
      svelte({
        compilerOptions: {
          hydratable: true,
        },
      }),
      hyperfHotFile(),
    ],
    build: {
      manifest: true,
      outDir: 'storage/public/build',
      emptyOutDir: true,
      rollupOptions: {
        input: ['storage/inertia/css/app.css', 'storage/inertia/js/app.js'],
      },
    },
  }
});
