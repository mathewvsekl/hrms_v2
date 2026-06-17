import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '^/.*public/uploads': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^.*\/public\/uploads/, '/public/uploads')
      }
    }
  },
  build: {
    outDir: '../backend/public', 
    emptyOutDir: false
  }
})
