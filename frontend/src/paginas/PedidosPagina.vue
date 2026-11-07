<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api, formatarData, formatarDataHora } from '../api'
import { sessao } from '../sessao'
import StatusEtiqueta from '../componentes/StatusEtiqueta.vue'
import AcoesDoAdministrador from '../componentes/AcoesDoAdministrador.vue'
import DecisaoDoPedido from '../componentes/DecisaoDoPedido.vue'

/*
 * Listagem com os filtros pedidos no desafio: status, destino e período
 * (de criação do pedido ou das datas da viagem). Os nomes dos campos são
 * exatamente os parâmetros que a API espera.
 */
const filtrosVazios = { status: '', destino: '', criado_de: '', criado_ate: '', viagem_de: '', viagem_ate: '', atrasados: false }

const filtros = reactive({ ...filtrosVazios })
const pedidos = ref([])
const paginacao = ref(null)
const pagina = ref(1)
const carregando = ref(false)
const mensagem = ref(null)

async function carregar() {
  carregando.value = true
  try {
    const resposta = await api('/pedidos-viagem', {
      // "atrasados" só vai na URL quando marcado (a API espera 1).
      consulta: { ...filtros, atrasados: filtros.atrasados ? 1 : '', pagina: pagina.value, por_pagina: 10 },
    })
    pedidos.value = resposta.data
    paginacao.value = resposta.meta
  } catch (erro) {
    // Ex.: período com fim antes do início → a API devolve 422 explicando.
    const detalhes = Object.values(erro.erros).flat().join(' ')
    mensagem.value = { tipo: 'erro', texto: detalhes || erro.message }
  } finally {
    carregando.value = false
  }
}

// Quantos pedidos estão parados há tempo demais. Os atrasados costumam ficar
// no fim da lista (ela é ordenada dos mais novos para os mais antigos), então
// o aviso no topo garante que o administrador não deixe nenhum passar.
const totalAtrasados = ref(0)

async function contarAtrasados() {
  if (!sessao.usuario?.administrador) return
  const resposta = await api('/pedidos-viagem', { consulta: { atrasados: 1, por_pagina: 1 } })
  totalAtrasados.value = resposta.meta.total
}

function verAtrasados() {
  Object.assign(filtros, filtrosVazios, { atrasados: true })
  filtrar()
}

function filtrar() {
  mensagem.value = null
  pagina.value = 1
  carregar()
}

function limpar() {
  Object.assign(filtros, filtrosVazios)
  filtrar()
}

function irParaPagina(numero) {
  pagina.value = numero
  carregar()
}

function aoAlterar(pedidoAtualizado) {
  const indice = pedidos.value.findIndex((p) => p.id === pedidoAtualizado.id)
  pedidos.value[indice] = pedidoAtualizado
  contarAtrasados()
  mensagem.value = {
    tipo: 'sucesso',
    texto: `Pedido ${pedidoAtualizado.status_rotulo.toLowerCase()}. ${pedidoAtualizado.solicitante.nome} será notificado(a).`,
  }
}

onMounted(() => {
  carregar()
  contarAtrasados()
})
</script>

<template>
  <div class="cabecalho-pagina">
    <h1>{{ sessao.usuario?.administrador ? 'Todos os pedidos' : 'Meus pedidos' }}</h1>
    <RouterLink to="/pedidos/novo" class="botao">+ Novo pedido</RouterLink>
  </div>

  <div v-if="totalAtrasados" class="alerta aviso faixa-atrasados">
    <span>
      ⚠ <strong>{{ totalAtrasados }} pedido{{ totalAtrasados > 1 ? 's' : '' }}</strong>
      aguardando aprovação além do prazo.
    </span>
    <button class="botao pequeno" @click="verAtrasados">Ver atrasados</button>
  </div>

  <form class="cartao filtros" @submit.prevent="filtrar">
    <label>
      Status
      <select v-model="filtros.status">
        <option value="">Todos</option>
        <option value="solicitado">Solicitado</option>
        <option value="aprovado">Aprovado</option>
        <option value="cancelado">Cancelado</option>
      </select>
    </label>
    <label>
      Destino
      <input v-model="filtros.destino" placeholder="Ex.: Recife" />
    </label>
    <fieldset>
      <legend>Pedido feito entre</legend>
      <input v-model="filtros.criado_de" type="date" aria-label="Criado de" />
      <input v-model="filtros.criado_ate" type="date" aria-label="Criado até" />
    </fieldset>
    <fieldset>
      <legend>Viagem entre</legend>
      <input v-model="filtros.viagem_de" type="date" aria-label="Viagem de" />
      <input v-model="filtros.viagem_ate" type="date" aria-label="Viagem até" />
    </fieldset>
    <label class="opcao">
      <input v-model="filtros.atrasados" type="checkbox" />
      Só atrasados
    </label>
    <div class="acoes">
      <button class="botao">Filtrar</button>
      <button type="button" class="botao secundario" @click="limpar">Limpar</button>
    </div>
  </form>

  <p v-if="mensagem" class="alerta" :class="mensagem.tipo">{{ mensagem.texto }}</p>

  <div class="cartao sem-espaco">
    <p v-if="carregando" class="vazio">Carregando...</p>
    <p v-else-if="!pedidos.length" class="vazio">Nenhum pedido encontrado.</p>

    <div v-else class="tabela-rolagem">
      <table>
        <thead>
          <tr>
            <th>Destino</th>
            <th v-if="sessao.usuario?.administrador">Solicitante</th>
            <th>Ida</th>
            <th>Volta</th>
            <th>Status</th>
            <th>Pedido em</th>
            <th>Decidido por</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <!-- Pedido parado há tempo demais ganha destaque (classe "atrasado"). -->
          <tr v-for="pedido in pedidos" :key="pedido.id" :class="{ atrasado: pedido.atrasado }">
            <td>
              <RouterLink :to="`/pedidos/${pedido.id}`">{{ pedido.destino }}</RouterLink>
            </td>
            <td v-if="sessao.usuario?.administrador">{{ pedido.solicitante.nome }}</td>
            <td>{{ formatarData(pedido.data_ida) }}</td>
            <td>{{ formatarData(pedido.data_volta) }}</td>
            <td><StatusEtiqueta :pedido="pedido" /></td>
            <td class="suave">{{ formatarDataHora(pedido.criado_em) }}</td>
            <td><DecisaoDoPedido :pedido="pedido" /></td>
            <td>
              <AcoesDoAdministrador
                :pedido="pedido"
                @alterado="aoAlterar"
                @erro="(texto) => (mensagem = { tipo: 'erro', texto })"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div v-if="paginacao && paginacao.last_page > 1" class="paginacao">
    <button class="botao secundario pequeno" :disabled="pagina <= 1" @click="irParaPagina(pagina - 1)">‹ Anterior</button>
    <span>Página {{ paginacao.current_page }} de {{ paginacao.last_page }} · {{ paginacao.total }} pedidos</span>
    <button class="botao secundario pequeno" :disabled="pagina >= paginacao.last_page" @click="irParaPagina(pagina + 1)">Próxima ›</button>
  </div>
</template>
