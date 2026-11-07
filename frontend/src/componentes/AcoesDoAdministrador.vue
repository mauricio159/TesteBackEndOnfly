<script setup>
import { computed, ref } from 'vue'
import { api } from '../api'
import { sessao } from '../sessao'

/**
 * Botões "Aprovar" e "Cancelar".
 *
 * Só aparecem para administrador, em pedido "solicitado" e que não seja dele.
 * Esconder o botão é só conforto visual: quem garante a regra de verdade é o
 * back-end (Policy + regra de status), que responde 403/422 se algo escapar.
 *
 * A confirmação é feita na própria página (e não com window.confirm), porque
 * alguns navegadores e visualizadores embutidos bloqueiam janelas nativas e
 * respondem "não" sozinhos. Assim o botão funciona em qualquer navegador.
 */
const props = defineProps({
  pedido: { type: Object, required: true },
})
const emit = defineEmits(['alterado', 'erro'])

const enviando = ref(false)
// null = mostrando os botões; 'aprovado' ou 'cancelado' = pedindo confirmação.
const confirmando = ref(null)

const podeDecidir = computed(
  () =>
    sessao.usuario?.administrador &&
    props.pedido.status === 'solicitado' &&
    props.pedido.solicitante.id !== sessao.usuario.id,
)

async function confirmar() {
  enviando.value = true
  try {
    const { data } = await api(`/pedidos-viagem/${props.pedido.id}/status`, {
      metodo: 'PATCH',
      corpo: { status: confirmando.value },
    })
    emit('alterado', data)
  } catch (erro) {
    emit('erro', erro.message)
  } finally {
    enviando.value = false
    confirmando.value = null
  }
}
</script>

<template>
  <div v-if="podeDecidir" class="acoes">
    <template v-if="!confirmando">
      <button class="botao sucesso pequeno" @click="confirmando = 'aprovado'">Aprovar</button>
      <button class="botao perigo pequeno" @click="confirmando = 'cancelado'">Cancelar</button>
    </template>

    <template v-else>
      <span class="pergunta">{{ confirmando === 'aprovado' ? 'Aprovar' : 'Cancelar' }} esta viagem?</span>
      <button
        class="botao pequeno"
        :class="confirmando === 'aprovado' ? 'sucesso' : 'perigo'"
        :disabled="enviando"
        @click="confirmar"
      >
        {{ enviando ? 'Enviando...' : 'Sim' }}
      </button>
      <button class="botao secundario pequeno" :disabled="enviando" @click="confirmando = null">Não</button>
    </template>
  </div>
</template>
