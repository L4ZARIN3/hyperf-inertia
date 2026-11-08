import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig(({ command }) => {
  const isBuild = command === 'build';
  return {
    base: isBuild ? '/public/build/' : '/',
    plugins: [
      react(),
      laravel({
        publicDirectory: 'storage/public',
        input: ['storage/inertia/css/app.css', 'storage/inertia/js/app.jsx'],
        ssr: 'storage/inertia/js/ssr.jsx',
        refresh: true,

      }),
    ],
  }
});
