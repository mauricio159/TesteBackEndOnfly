import { createRouter, createWebHistory } from 'vue-router'
import { sessao } from './sessao'
import LoginPagina from './paginas/LoginPagina.vue'
import PedidosPagina from './paginas/PedidosPagina.vue'
import NovoPedidoPagina from './paginas/NovoPedidoPagina.vue'
import PedidoPagina from './paginas/PedidoPagina.vue'
import NotificacoesPagina from './paginas/NotificacoesPagina.vue'
import EquipePagina from './paginas/EquipePagina.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/pedidos' },
    { path: '/login', component: LoginPagina, meta: { publica: true } },
    { path: '/pedidos', component: PedidosPagina },
    { path: '/pedidos/novo', component: NovoPedidoPagina },
    { path: '/pedidos/:id', component: PedidoPagina, props: true },
    { path: '/notificacoes', component: NotificacoesPagina },
    { path: '/equipe', component: EquipePagina, meta: { somenteAdministrador: true } },
  ],
})

// Sem login, só a tela de login. Logado, a tela de login não faz sentido.
// Colaborador que digitar /equipe na mão volta para os pedidos
// (e, mesmo que passasse daqui, a API responderia 403).
router.beforeEach((destino) => {
  if (!destino.meta.publica && !sessao.token) return '/login'
  if (destino.path === '/login' && sessao.token) return '/pedidos'
  if (destino.meta.somenteAdministrador && !sessao.usuario?.administrador) return '/pedidos'
})
