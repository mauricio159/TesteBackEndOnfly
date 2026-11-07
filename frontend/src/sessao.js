import { reactive } from 'vue'

/**
 * Quem está logado. Fica no localStorage para o login sobreviver a um F5.
 *
 * Num sistema de produção, guardar token no localStorage tem o risco de XSS.
 * Para um front de demonstração, é a forma mais simples e fácil de entender.
 */
const CHAVE = 'pedidos-viagem:sessao'

const salva = JSON.parse(localStorage.getItem(CHAVE) ?? 'null')

export const sessao = reactive({
  token: salva?.token ?? null,
  usuario: salva?.usuario ?? null,
})

export function iniciarSessao(token, usuario) {
  sessao.token = token
  sessao.usuario = usuario
  localStorage.setItem(CHAVE, JSON.stringify({ token, usuario }))
}

export function encerrarSessao() {
  sessao.token = null
  sessao.usuario = null
  localStorage.removeItem(CHAVE)
}
