<script setup>
// Etiqueta colorida do status: amarelo = solicitado, verde = aprovado, vermelho = cancelado.
defineProps({
  status: { type: String, required: true },
  rotulo: { type: String, required: true },
})
</script>

<template>
  <span class="etiqueta" :class="status">{{ rotulo }}</span>
</template>
