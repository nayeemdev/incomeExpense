/// <reference types="vitest/config" />
import path from 'node:path'
import tailwindcss from '@tailwindcss/vite'
import { tanstackRouter } from '@tanstack/router-plugin/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const apiTarget = env.API_PROXY_TARGET

  return {
    plugins: [
      tanstackRouter({ target: 'react', autoCodeSplitting: true }),
      react(),
      tailwindcss(),
    ],
    resolve: {
      alias: { '@': path.resolve(__dirname, './src') },
    },
    server: apiTarget
      ? {
          proxy: Object.fromEntries(
            ['/api', '/sanctum', '/storage', '/docs'].map((prefix) => [
              prefix,
              { target: apiTarget, changeOrigin: false },
            ]),
          ),
        }
      : undefined,
    test: {
      environment: 'jsdom',
      passWithNoTests: true,
      setupFiles: ['./src/test/setup.ts'],
    },
  }
})
