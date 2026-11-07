<script setup>
import { onMounted, ref } from 'vue'
import { api, formatarData, formatarDataHora } from '../api'
import { tempoDesde } from '../relogio'
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

  <div v-if="pedido" class="cartao" :class="{ 'cartao-atrasado': pedido.atrasado }">
    <div class="cabecalho-pagina">
      <h1>{{ pedido.destino }}</h1>
      <StatusEtiqueta :pedido="pedido" />
    </div>

    <p v-if="pedido.atrasado" class="alerta aviso">
      ⚠ Este pedido aguarda uma decisão {{ tempoDesde(pedido.criado_em) }}, mais do que o prazo esperado.
    </p>

    <dl class="detalhes">
      <dt>Solicitante</dt>
      <dd>{{ pedido.solicitante.nome }}</dd>
      <dt>Ida</dt>
      <dd>{{ formatarData(pedido.data_ida) }}</dd>
      <dt>Volta</dt>
      <dd>{{ formatarData(pedido.data_volta) }}</dd>
      <dt>Pedido em</dt>
      <dd>{{ formatarDataHora(pedido.criado_em) }}</dd>
      <template v-if="pedido.decisao">
        <dt>{{ pedido.status_rotulo }} por</dt>
        <dd><strong>{{ pedido.decisao.por.nome }}</strong></dd>
        <dt>{{ pedido.status_rotulo }} em</dt>
        <dd>{{ formatarDataHora(pedido.decisao.em) }}</dd>
      </template>
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
