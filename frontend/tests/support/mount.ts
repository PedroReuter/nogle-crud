import { DOMWrapper, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach } from 'vitest'
import type { Component } from 'vue'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { pt } from 'vuetify/locale'

/**
 * Infraestrutura de montagem dos testes de componente.
 *
 * Duas particularidades do Vuetify precisam ser tratadas aqui, e tratá-las
 * num só lugar evita que cada arquivo de teste as redescubra:
 *
 * 1. Os componentes são registrados explicitamente. O `vite-plugin-vuetify`
 *    resolve componentes analisando o template em tempo de build, o que não
 *    acontece sob o Vitest.
 *
 * 2. Diálogos e menus renderizam seu conteúdo via `<Teleport>` para um
 *    container no fim do `body`, fora da árvore do componente. Por isso as
 *    buscas usam `screen`, que cobre o documento inteiro, e não o wrapper —
 *    que estaria sempre vazio. Substituir o teleporte por um stub não serve:
 *    o stub do Vue Test Utils não renderiza os filhos.
 */

const mounted: VueWrapper[] = []

/**
 * Os tipos de `ComponentMountingOptions` do Vue Test Utils são condicionais
 * sobre o componente e não sobrevivem a um wrapper genérico. Tipar o
 * parâmetro de forma frouxa aqui é preferível a espalhar casts por cada
 * arquivo de teste; o que de fato garante a correção dos testes é a
 * execução, não a inferência sobre o objeto de montagem.
 */
type MountOptions = Record<string, any>

export function mountWithVuetify(component: Component, options: MountOptions = {}): VueWrapper {
  const vuetify = createVuetify({
    components,
    directives,
    locale: { locale: 'pt', fallback: 'pt', messages: { pt } },
  })

  const wrapper = mount(component as never, {
    ...options,
    global: {
      ...options.global,
      plugins: [vuetify, ...(options.global?.plugins ?? [])],
    },
    attachTo: document.body,
  })

  mounted.push(wrapper)

  return wrapper
}

/**
 * Busca em todo o documento, alcançando o conteúdo teleportado dos modais.
 *
 * É um `DOMWrapper` do Vue Test Utils, então oferece a mesma API do wrapper
 * — `setValue`, `trigger`, `text` — sobre elementos fora da árvore montada.
 */
export function screen(): DOMWrapper<HTMLElement> {
  return new DOMWrapper(document.body)
}

/** Aguarda as promessas pendentes e o próximo ciclo de renderização. */
export async function flush(): Promise<void> {
  await new Promise((resolve) => setTimeout(resolve, 0))
}

// Desmonta e limpa o documento entre os testes. Sem isto, o conteúdo
// teleportado de um teste permaneceria no body e seria encontrado pelo
// seguinte, que passaria ou falharia pelo motivo errado.
afterEach(() => {
  while (mounted.length > 0) {
    mounted.pop()?.unmount()
  }

  document.body.innerHTML = ''
})
