<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../api'

/*
 * Equipe: o administrador cadastra quem pode usar o sistema.
 *
 *  - Colaborador: pede viagens e acompanha os próprios pedidos.
 *  - Administrador: também aprova e cancela, mas nunca os próprios pedidos.
 *    Por isso vale ter mais de um, para que um aprove as viagens do outro.
 *
 * Não existe cadastro público: só entra quem o administrador cadastrar aqui.
 */
const usuarios = ref([])
const filtroPerfil = ref('')
const formularioVazio = { nome: '', email: '', senha: '', senha_confirmacao: '', administrador: false }
const formulario = reactive({ ...formularioVazio })
const erros = ref({})
const mensagem = ref(null)
const enviando = ref(false)

const totais = computed(() => ({
  administradores: usuarios.value.filter((u) => u.administrador).length,
  colaboradores: usuarios.value.filter((u) => !u.administrador).length,
}))

const visiveis = computed(() =>
  usuarios.value.filter((u) =>
    filtroPerfil.value === '' ? true : u.administrador === (filtroPerfil.value === 'administrador'),
  ),
)

async function carregar() {
  usuarios.value = (await api('/usuarios')).data
}

async function cadastrar() {
  erros.value = {}
  mensagem.value = null
  enviando.value = true
  try {
    const { data } = await api('/usuarios', { metodo: 'POST', corpo: formulario })
    const perfil = data.administrador ? 'administrador(a)' : 'colaborador(a)'
    mensagem.value = {
      tipo: 'sucesso',
      texto: `${data.nome} foi cadastrado(a) como ${perfil} e já pode entrar com ${data.email} e a senha inicial informada.`,
    }
    Object.assign(formulario, formularioVazio)
    await carregar()
  } catch (erro) {
    erros.value = erro.erros
    if (!Object.keys(erro.erros).length) mensagem.value = { tipo: 'erro', texto: erro.message }
  } finally {
    enviando.value = false
  }
}

onMounted(carregar)
</script>

<template>
  <h1>Equipe</h1>
  <p class="suave">
    Só entra no sistema quem for cadastrado aqui. Colaboradores pedem viagens; administradores também aprovam e
    cancelam, mas nunca os próprios pedidos.
  </p>

  <div class="lado-a-lado-grande">
    <div class="cartao sem-espaco">
      <div class="cabecalho-tabela">
        <span class="suave">
          {{ totais.administradores }} administrador(es) · {{ totais.colaboradores }} colaborador(es)
        </span>
        <select v-model="filtroPerfil" aria-label="Filtrar por perfil">
          <option value="">Todos</option>
          <option value="colaborador">Colaboradores</option>
          <option value="administrador">Administradores</option>
        </select>
      </div>
      <table>
        <thead>
          <tr><th>Nome</th><th>E-mail</th><th>Perfil</th></tr>
        </thead>
        <tbody>
          <tr v-for="usuario in visiveis" :key="usuario.id">
            <td>{{ usuario.nome }}</td>
            <td class="suave">{{ usuario.email }}</td>
            <td>
              <span v-if="usuario.administrador" class="etiqueta admin">Administrador</span>
              <span v-else class="etiqueta">Colaborador</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <form class="cartao formulario" @submit.prevent="cadastrar">
      <h2>Cadastrar pessoa</h2>

      <fieldset class="perfil">
        <legend>Perfil</legend>
        <label class="opcao">
          <input v-model="formulario.administrador" type="radio" :value="false" />
          Colaborador <small class="suave">(pede viagens)</small>
        </label>
        <label class="opcao">
          <input v-model="formulario.administrador" type="radio" :value="true" />
          Administrador <small class="suave">(também aprova e cancela)</small>
        </label>
      </fieldset>

      <label>
        Nome
        <input v-model="formulario.nome" />
        <small v-if="erros.nome" class="erro-campo">{{ erros.nome[0] }}</small>
      </label>
      <label>
        E-mail
        <input v-model="formulario.email" type="email" />
        <small v-if="erros.email" class="erro-campo">{{ erros.email[0] }}</small>
      </label>
      <label>
        Senha inicial
        <input v-model="formulario.senha" type="password" autocomplete="new-password" />
        <small v-if="erros.senha" class="erro-campo">{{ erros.senha[0] }}</small>
      </label>
      <label>
        Confirme a senha
        <input v-model="formulario.senha_confirmacao" type="password" autocomplete="new-password" />
      </label>

      <p v-if="mensagem" class="alerta" :class="mensagem.tipo">{{ mensagem.texto }}</p>

      <button class="botao" :disabled="enviando">
        {{ enviando ? 'Cadastrando...' : formulario.administrador ? 'Cadastrar administrador' : 'Cadastrar colaborador' }}
      </button>
    </form>
  </div>
</template>
