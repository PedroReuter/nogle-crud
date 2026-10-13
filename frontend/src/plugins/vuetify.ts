import 'vuetify/styles'

import {
  mdiAccountCheckOutline,
  mdiAccountEdit,
  mdiAccountGroupOutline,
  mdiAccountOffOutline,
  mdiAccountPlus,
  mdiAccountSearch,
  mdiAccountSearchOutline,
  mdiAlertCircleOutline,
  mdiCardAccountDetailsOutline,
  mdiCheckCircleOutline,
  mdiClose,
  mdiContentSave,
  mdiFilterRemoveOutline,
  mdiInformationOutline,
  mdiEmailOutline,
  mdiEyeOffOutline,
  mdiEyeOutline,
  mdiLogout,
  mdiPencilOutline,
} from '@mdi/js'
import { createVuetify } from 'vuetify'
import { pt } from 'vuetify/locale'
import { aliases as mdiAliases, mdi } from 'vuetify/iconsets/mdi-svg'

/**
 * Tema da aplicação.
 *
 * Paleta sóbria, de painel administrativo: um azul-ardósia como cor de ação
 * e cinzas de fundo. O requisito pede uma interface que lembre SaaS de
 * qualidade "sem excesso de animações ou elementos decorativos", então as
 * cores servem para hierarquizar informação, não para decorar.
 *
 * As cores de estado (sucesso, erro, aviso) são deliberadamente distintas
 * entre si em luminosidade, e não só em matiz, para que a diferença
 * sobreviva ao daltonismo — que é também o motivo de cada aviso vir
 * acompanhado de ícone e texto, nunca de cor isolada.
 */
const nogleLight = {
  dark: false,
  colors: {
    background: '#f4f5f7',
    surface: '#ffffff',
    'surface-variant': '#eceef1',

    primary: '#2f5d8c',
    'primary-darken-1': '#24476b',
    secondary: '#546274',

    success: '#1f7a4c',
    error: '#b3261e',
    warning: '#8a5a00',
    info: '#2f5d8c',

    'on-background': '#1a1d21',
    'on-surface': '#1a1d21',
  },
}

export const vuetify = createVuetify({
  /**
   * Idioma dos textos internos do Vuetify.
   *
   * Sem isto, os componentes que trazem texto próprio — o rodapé da tabela,
   * a paginação e os rótulos acessíveis dos botões de navegação — aparecem
   * em inglês no meio de uma interface em português: "1-1 of 44",
   * "Next page". A mistura é visível na tela e, no caso dos rótulos ARIA,
   * é o que um leitor de tela anuncia.
   */
  locale: {
    locale: 'pt',
    fallback: 'pt',
    messages: { pt },
  },

  theme: {
    defaultTheme: 'nogleLight',
    themes: { nogleLight },
  },

  /**
   * Ícones como caminhos SVG, importados um a um, e não como fonte de
   * ícones.
   *
   * A aplicação usa quinze ícones. O pacote `@mdi/font` embarca as mais de
   * sete mil classes CSS do conjunto completo mais quatro arquivos de fonte
   * — cerca de 600 KB de CSS e 400 KB só no woff2 — independentemente de
   * quantos ícones sejam de fato usados. Com `@mdi/js` o bundler inclui
   * apenas os caminhos referenciados aqui.
   *
   * Os apelidos deixam o conjunto de ícones da aplicação declarado num só
   * lugar: o template escreve `icon="$edit"` em vez de repetir o nome do
   * ícone, e trocar o ícone de uma ação é uma linha neste arquivo.
   *
   * `mdiAliases` traz os apelidos internos do Vuetify ($next, $sortAsc e
   * companhia), de que a tabela e a paginação dependem.
   */
  icons: {
    defaultSet: 'mdi',
    sets: { mdi },
    aliases: {
      ...mdiAliases,

      appLogo: mdiAccountGroupOutline,
      customerAdd: mdiAccountPlus,
      customerEdit: mdiAccountEdit,
      edit: mdiPencilOutline,
      deactivate: mdiAccountOffOutline,
      reactivate: mdiAccountCheckOutline,
      save: mdiContentSave,
      close: mdiClose,
      searchName: mdiAccountSearch,
      searchCpf: mdiCardAccountDetailsOutline,
      filterClear: mdiFilterRemoveOutline,
      emptyResults: mdiAccountSearchOutline,
      success: mdiCheckCircleOutline,
      error: mdiAlertCircleOutline,
      info: mdiInformationOutline,
      showPassword: mdiEyeOutline,
      hidePassword: mdiEyeOffOutline,
      logout: mdiLogout,
      email: mdiEmailOutline,
    },
  },

  defaults: {
    // Densidade compacta e contornos discretos em toda a aplicação, em vez
    // de repetir as mesmas props em cada campo.
    VTextField: {
      variant: 'outlined',
      density: 'comfortable',
      hideDetails: 'auto',
    },
    VSelect: {
      variant: 'outlined',
      density: 'comfortable',
      hideDetails: 'auto',
    },
    VBtn: {
      variant: 'flat',
    },
    VCard: {
      rounded: 'lg',
    },
  },
})
