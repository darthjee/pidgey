import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  base: '/admin/',
  server: {
    port: 8080,
    host: '0.0.0.0',
    strictPort: false,
    proxy: {
      '/v1': {
        target: 'http://app:80',
        changeOrigin: false,
      },
      '^/.*\\.json$': {
        target: 'http://app:80',
        changeOrigin: false,
      },
    },
  },
  plugins: [
    react({
      babel: {
        plugins: [['babel-plugin-react-compiler']],
      },
    }),
  ],
});
