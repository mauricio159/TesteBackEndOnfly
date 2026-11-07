<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { iniciarSessao } from '../sessao'

const router = useRouter()

const formulario = reactive({ email: '', senha: '' })
const erro = ref('')
const enviando = ref(false)

// Usuários criados pelo seeder, para testar sem precisar digitar.
const usuariosDemo = [
  { nome: 'Carla (administradora)', email: 'admin@viagens.test' },
  { nome: 'Diego (administrador)', email: 'diretor@viagens.test' },
  { nome: 'Ana (colaboradora)', email: 'ana@viagens.test' },
  { nome: 'Bruno (colaborador)', email: 'bruno@viagens.test' },
]

function preencher(email) {
  formulario.email = email
  formulario.senha = 'senha123'
}

async function entrar() {
  erro.value = ''
  enviando.value = true
  try {
    const { token, usuario } = await api('/auth/login', { metodo: 'POST', corpo: formulario })
    iniciarSessao(token, usuario)
    router.push('/pedidos')
  } catch (e) {
    erro.value = e.message
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <div class="cartao login">
    <h1>✈ Pedidos de Viagem</h1>
    <p class="suave">Entre para solicitar ou aprovar viagens corporativas.</p>
    <p class="suave pequeno">Ainda não tem acesso? Peça ao administrador para cadastrar você.</p>

    <form @submit.prevent="entrar">
      <label>
        E-mail
        <input v-model="formulario.email" type="email" required autocomplete="username" />
      </label>
      <label>
        Senha
        <input v-model="formulario.senha" type="password" required autocomplete="current-password" />
      </label>

      <p v-if="erro" class="alerta erro">{{ erro }}</p>

      <button class="botao" :disabled="enviando">{{ enviando ? 'Entrando...' : 'Entrar' }}</button>
    </form>

    <div class="demo">
      <p class="suave">Acesso rápido (senha <code>senha123</code>):</p>
      <button
        v-for="usuario in usuariosDemo"
        :key="usuario.email"
        type="button"
        class="botao secundario pequeno"
        @click="preencher(usuario.email)"
      >
        {{ usuario.nome }}
      </button>
    </div>
  </div>
</template>
