<script setup>
import { tempoDesde } from '../relogio'

/*
 * Etiqueta colorida do status: amarelo = solicitado, verde = aprovado,
 * vermelho = cancelado. Enquanto o pedido aguarda decisão, mostra embaixo
 * há quanto tempo ele espera (em vermelho e com ⚠ quando está atrasado).
 */
defineProps({
  pedido: { type: Object, required: true },
})
</script>

<template>
  <div class="status">
    <span class="etiqueta" :class="pedido.status">{{ pedido.status_rotulo }}</span>
    <small
      v-if="pedido.status === 'solicitado'"
      class="aguardando"
      :class="{ atrasado: pedido.atrasado }"
      :title="pedido.atrasado ? 'Passou do prazo esperado para uma decisão' : ''"
    >
      {{ pedido.atrasado ? '⚠ ' : '' }}aguardando {{ tempoDesde(pedido.criado_em) }}
    </small>
  </div>
</template>
