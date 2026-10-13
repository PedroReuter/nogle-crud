/**
 * Preparação do ambiente de testes.
 *
 * O jsdom não implementa `ResizeObserver`, `matchMedia` nem `visualViewport`,
 * e vários componentes do Vuetify os consultam ao montar. Sem estes stubs, o
 * erro que aparece é "ResizeObserver is not defined", que não diz nada sobre
 * o teste em si.
 */
import ResizeObserver from 'resize-observer-polyfill'
import { vi } from 'vitest'

globalThis.ResizeObserver = ResizeObserver

Object.defineProperty(globalThis, 'matchMedia', {
  writable: true,
  value: vi.fn().mockImplementation((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })),
})

// O Vuetify usa `visualViewport` para posicionar overlays (menus, diálogos).
Object.defineProperty(globalThis, 'visualViewport', {
  writable: true,
  value: { width: 1280, height: 800, addEventListener: vi.fn(), removeEventListener: vi.fn() },
})
