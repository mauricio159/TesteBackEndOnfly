import { ref } from 'vue'

/**
 * "Agora" reativo, atualizado a cada 30 segundos.
 *
 * As telas usam isto para mostrar há quanto tempo um pedido espera ("há 3 dias
 * e 5 h") sempre atualizado, sem precisar recarregar a página nem chamar a API.
 */
export const agora = ref(Date.now())
setInterval(() => (agora.value = Date.now()), 30_000)

/** Tempo decorrido desde uma data ISO, em português: "há 2 dias e 3 h", "há 15 min". */
export function tempoDesde(dataIso, referencia = agora.value) {
  const minutos = Math.max(0, Math.floor((referencia - new Date(dataIso)) / 60_000))
  const dias = Math.floor(minutos / 1440)
  const horas = Math.floor((minutos % 1440) / 60)
  const resto = minutos % 60

  if (dias) return `há ${dias} dia${dias > 1 ? 's' : ''}${horas ? ` e ${horas} h` : ''}`
  if (horas) return `há ${horas} h${resto ? ` e ${resto} min` : ''}`
  return resto ? `há ${resto} min` : 'agora mesmo'
}
