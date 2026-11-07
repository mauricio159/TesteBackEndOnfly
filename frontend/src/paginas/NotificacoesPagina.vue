<script setup>
import { onMounted, ref } from 'vue'
import { api, formatarDataHora } from '../api'

// Avisos que o colaborador recebe quando um pedido dele é aprovado ou cancelado.
const notificacoes = ref([])
const carregando = ref(true)

onMounted(async () => {
  try {
    notificacoes.value = (await api('/notificacoes')).data
  } finally {
    carregando.value = false
  }
})
</script>

<template>
  <h1>Notificações</h1>
  <p class="suave">
    Os mesmos avisos também chegam por e-mail. Em desenvolvimento, veja no
    <a href="http://localhost:8025" target="_blank" rel="noopener">Mailpit</a>.
  </p>

  <div class="cartao sem-espaco">
    <p v-if="carregando" class="vazio">Carregando...</p>
    <p v-else-if="!notificacoes.length" class="vazio">Nenhuma notificação por enquanto.</p>

    <ul v-else class="lista-notificacoes">
      <li v-for="notificacao in notificacoes" :key="notificacao.id">
        <span class="etiqueta" :class="notificacao.dados.status">{{ notificacao.dados.status }}</span>
        <RouterLink :to="`/pedidos/${notificacao.dados.pedido_id}`">{{ notificacao.dados.mensagem }}</RouterLink>
        <span class="suave">{{ formatarDataHora(notificacao.criada_em) }}</span>
      </li>
    </ul>
  </div>
</template>
