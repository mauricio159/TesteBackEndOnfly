<script setup>
import { formatarDataHora } from '../api'

// Quem aprovou/cancelou e quando. Mostra "—" enquanto ninguém decidiu.
defineProps({
  pedido: { type: Object, required: true },
})
</script>

<template>
  <span v-if="pedido.decisao">
    {{ pedido.decisao.por.nome }}
    <small class="suave">· {{ formatarDataHora(pedido.decisao.em) }}</small>
  </span>
  <span v-else class="suave">—</span>
</template>
