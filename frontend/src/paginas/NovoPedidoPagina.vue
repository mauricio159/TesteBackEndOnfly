<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, hoje } from '../api'
import { sessao } from '../sessao'

const router = useRouter()

// Só destino e datas: o nome do solicitante e o status quem define é o back-end.
const formulario = reactive({ destino: '', data_ida: '', data_volta: '' })
const erros = ref({})
const erroGeral = ref('')
const enviando = ref(false)

async function enviar() {
  erros.value = {}
  erroGeral.value = ''
  enviando.value = true
  try {
    const { data } = await api('/pedidos-viagem', { metodo: 'POST', corpo: formulario })
    router.push(`/pedidos/${data.id}`)
  } catch (erro) {
    // 422: mostramos a mensagem do Laravel embaixo de cada campo.
    erros.value = erro.erros
    if (!Object.keys(erro.erros).length) erroGeral.value = erro.message
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <h1>Novo pedido de viagem</h1>

  <form class="cartao formulario" @submit.prevent="enviar">
    <p class="suave">Solicitante: <strong>{{ sessao.usuario?.nome }}</strong></p>

    <label>
      Destino
      <input v-model="formulario.destino" placeholder="Ex.: Salvador, BA" />
      <small v-if="erros.destino" class="erro-campo">{{ erros.destino[0] }}</small>
    </label>

    <div class="lado-a-lado">
      <label>
        Data de ida
        <input v-model="formulario.data_ida" type="date" :min="hoje()" />
        <small v-if="erros.data_ida" class="erro-campo">{{ erros.data_ida[0] }}</small>
      </label>
      <label>
        Data de volta
        <input v-model="formulario.data_volta" type="date" :min="formulario.data_ida || hoje()" />
        <small v-if="erros.data_volta" class="erro-campo">{{ erros.data_volta[0] }}</small>
      </label>
    </div>

    <p v-if="erroGeral" class="alerta erro">{{ erroGeral }}</p>

    <div class="acoes">
      <button class="botao" :disabled="enviando">{{ enviando ? 'Enviando...' : 'Solicitar viagem' }}</button>
      <RouterLink to="/pedidos" class="botao secundario">Voltar</RouterLink>
    </div>
  </form>
</template>
