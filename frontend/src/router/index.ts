import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

import { useAuth } from '@/composables/useAuth'

/**
 * Duas rotas: login e a tela de clientes.
 *
 * O requisito permite que a autenticação tenha tela própria, desde que não
 * comprometa a apresentação do CRUD em tela única — e todo o gerenciamento
 * de clientes continua acontecendo em `/`, com modais sobre a mesma página.
 */
const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { public: true, title: 'Entrar' },
  },
  {
    path: '/',
    name: 'customers',
    component: () => import('@/views/CustomersView.vue'),
    meta: { title: 'Clientes' },
  },
  {
    // Qualquer outro caminho volta para a listagem, em vez de deixar a
    // aplicação em branco.
    path: '/:pathMatch(.*)*',
    redirect: { name: 'customers' },
  },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})

/**
 * Guard de acesso.
 *
 * Verifica a sessão no servidor antes da primeira navegação. Confiar em
 * estado do cliente aqui seria inútil: quem quisesse burlar bastaria editar
 * a memória do navegador. O que protege de fato é a API, que recusa cada
 * requisição sem sessão — este guard apenas evita mostrar uma tela que não
 * carregaria dado algum.
 */
router.beforeEach(async (to) => {
  const auth = useAuth()

  await auth.initialize()

  if (!to.meta.public && !auth.isAuthenticated.value) {
    // Guarda o destino para voltar a ele após o login, em vez de sempre
    // cair na listagem.
    return { name: 'login', query: to.fullPath === '/' ? {} : { redirect: to.fullPath } }
  }

  if (to.meta.public && auth.isAuthenticated.value) {
    return { name: 'customers' }
  }

  return true
})

router.afterEach((to) => {
  const title = typeof to.meta.title === 'string' ? to.meta.title : null
  document.title = title ? `${title} — Nogle CRUD` : 'Nogle CRUD'
})
