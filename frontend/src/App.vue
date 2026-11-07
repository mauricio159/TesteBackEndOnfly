<script setup>
import { useRouter } from 'vue-router'
import { api } from './api'
import { sessao, encerrarSessao } from './sessao'

const router = useRouter()

async function sair() {
  // Revoga o token no servidor; mesmo se falhar, limpamos a sessão local.
  await api('/auth/logout', { metodo: 'POST' }).catch(() => {})
  encerrarSessao()
  router.push('/login')
}
</script>

<template>
  <header v-if="sessao.token" class="topo">
    <div class="topo-conteudo">
      <RouterLink to="/pedidos" class="marca">✈ Pedidos de Viagem</RouterLink>

      <nav class="menu">
        <RouterLink to="/pedidos">Pedidos</RouterLink>
        <RouterLink to="/pedidos/novo">Novo pedido</RouterLink>
        <RouterLink to="/notificacoes">Notificações</RouterLink>
        <RouterLink v-if="sessao.usuario?.administrador" to="/equipe">Equipe</RouterLink>
      </nav>

      <div class="usuario">
        <span>{{ sessao.usuario?.nome }}</span>
        <span v-if="sessao.usuario?.administrador" class="etiqueta admin">Administrador</span>
        <button class="botao secundario pequeno" @click="sair">Sair</button>
      </div>
    </div>
  </header>

  <main class="conteudo">
    <RouterView />
  </main>
</template>
