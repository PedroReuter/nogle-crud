import { ref } from 'vue'

export type NotificationKind = 'success' | 'error' | 'info'

export interface Notification {
  id: number
  kind: NotificationKind
  message: string
}

/**
 * Fila de avisos de sucesso e erro.
 *
 * O estado vive em escopo de módulo — fora da função — para que seja
 * compartilhado: qualquer componente pode notificar e o `<v-snackbar>` do
 * layout exibe. Um `ref` dentro da função criaria uma fila nova por
 * componente, e os avisos nunca apareceriam.
 *
 * É também a razão de este projeto não usar Pinia: o estado realmente
 * compartilhado da aplicação se resume a isto e, na Etapa 3, ao usuário
 * autenticado. Uma store traria devtools e padronização que não se pagam em
 * duas variáveis.
 */
const queue = ref<Notification[]>([])
let nextId = 0

export function useNotifications() {
  function notify(kind: NotificationKind, message: string): void {
    queue.value = [...queue.value, { id: nextId++, kind, message }]
  }

  function dismiss(id: number): void {
    queue.value = queue.value.filter((item) => item.id !== id)
  }

  return {
    notifications: queue,
    notifySuccess: (message: string) => notify('success', message),
    notifyError: (message: string) => notify('error', message),
    notifyInfo: (message: string) => notify('info', message),
    dismiss,
  }
}
