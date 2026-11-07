<script setup>
import { onMounted, ref } from 'vue'
import { api, formatarData, formatarDataHora } from '../api'
import StatusEtiqueta from '../componentes/StatusEtiqueta.vue'
import AcoesDoAdministrador from '../componentes/AcoesDoAdministrador.vue'

// O ":id" da rota chega aqui como prop (props: true no router).
const props = defineProps({ id: { type: String, required: true } })

const pedido = ref(null)
const mensagem = ref(null)

onMounted(async () => {
  try {
    pedido.value = (await api(`/pedidos-viagem/${props.id}`)).data
  } catch (erro) {
    // 403 (pedido de outra pessoa) ou 404 (não existe): a API já explica.
    mensagem.value = { tipo: 'erro', texto: erro.message }
  }
})

function aoAlterar(atualizado) {
  pedido.value = atualizado
  mensagem.value = { tipo: 'sucesso', texto: `Pedido ${atualizado.status_rotulo.toLowerCase()} e solicitante notificado(a).` }
}
</script>

<template>
  <RouterLink to="/pedidos" class="voltar">‹ Voltar para a lista</RouterLink>

  <p v-if="mensagem" class="alerta" :class="mensagem.tipo">{{ mensagem.texto }}</p>

  <div v-if="pedido" class="cartao">
    <div class="cabecalho-pagina">
      <h1>{{ pedido.destino }}</h1>
      <StatusEtiqueta :status="pedido.status" :rotulo="pedido.status_rotulo" />
    </div>

    <dl class="detalhes">
      <dt>Solicitante</dt>
      <dd>{{ pedido.solicitante.nome }}</dd>
      <dt>Ida</dt>
      <dd>{{ formatarData(pedido.data_ida) }}</dd>
      <dt>Volta</dt>
      <dd>{{ formatarData(pedido.data_volta) }}</dd>
      <dt>Pedido em</dt>
      <dd>{{ formatarDataHora(pedido.criado_em) }}</dd>
      <dt>Última atualização</dt>
      <dd>{{ formatarDataHora(pedido.atualizado_em) }}</dd>
      <dt>Código</dt>
      <dd><code>{{ pedido.id }}</code></dd>
    </dl>

    <AcoesDoAdministrador
      :pedido="pedido"
      @alterado="aoAlterar"
      @erro="(texto) => (mensagem = { tipo: 'erro', texto })"
    />
  </div>
</template>
