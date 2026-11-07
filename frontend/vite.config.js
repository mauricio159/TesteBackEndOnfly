import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    // 0.0.0.0 para o servidor ser acessível de fora do container Docker.
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    // No Windows, o Docker não avisa o container quando um arquivo muda.
    // O polling faz o Vite perceber as alterações e recarregar a página.
    watch: { usePolling: true },
    // Toda chamada para /api é repassada para o Laravel. Para o navegador,
    // front e API estão na mesma origem, então não precisamos configurar CORS.
    // Dentro do Docker o alvo é o nginx; fora dele, o localhost:8000.
    proxy: {
      '/api': {
        target: process.env.API_URL ?? 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})
