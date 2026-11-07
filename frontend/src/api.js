import { sessao, encerrarSessao } from './sessao'

/**
 * Erro devolvido pela API, com o status HTTP e os erros por campo (422).
 */
export class ErroApi extends Error {
  constructor(status, corpo) {
    super(corpo?.message ?? 'Não foi possível falar com o servidor.')
    this.status = status
    this.erros = corpo?.errors ?? {}
  }
}

/**
 * Único ponto do front que conversa com o Laravel.
 * Coloca o token, monta a query string e transforma erros em ErroApi.
 */
export async function api(caminho, { metodo = 'GET', corpo, consulta } = {}) {
  const url = new URL(`/api/v1${caminho}`, window.location.origin)

  // Filtros vazios não vão para a URL (o back-end recusaria "status=").
  for (const [chave, valor] of Object.entries(consulta ?? {})) {
    if (valor !== '' && valor !== null && valor !== undefined) {
      url.searchParams.set(chave, valor)
    }
  }

  const resposta = await fetch(url, {
    method: metodo,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(sessao.token ? { Authorization: `Bearer ${sessao.token}` } : {}),
    },
    body: corpo ? JSON.stringify(corpo) : undefined,
  })

  if (resposta.status === 204) return null

  const dados = await resposta.json().catch(() => null)

  // Token expirou ou foi revogado: limpa a sessão e volta para o login.
  if (resposta.status === 401 && sessao.token) {
    encerrarSessao()
    window.location.assign('/login')
  }

  if (!resposta.ok) throw new ErroApi(resposta.status, dados)

  return dados
}

/** "2026-12-10" → "10/12/2026" (sem passar por Date, para não sofrer com fuso). */
export function formatarData(data) {
  return data ? data.split('-').reverse().join('/') : ''
}

/** Data e hora ISO → "06/11/2026 22:11". */
export function formatarDataHora(dataHora) {
  return dataHora
    ? new Date(dataHora).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
    : ''
}

/** Data de hoje no formato AAAA-MM-DD, no fuso do navegador. */
export function hoje() {
  const agora = new Date()
  agora.setMinutes(agora.getMinutes() - agora.getTimezoneOffset())
  return agora.toISOString().slice(0, 10)
}
