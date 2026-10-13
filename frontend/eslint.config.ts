import js from '@eslint/js'
import prettier from '@vue/eslint-config-prettier'
import pluginVue from 'eslint-plugin-vue'
import tseslint from 'typescript-eslint'

/**
 * Configuração do ESLint.
 *
 * Composta a partir de `typescript-eslint` e `eslint-plugin-vue` em vez do
 * preset `@vue/eslint-config-typescript`. O preset arrastava `fast-glob`,
 * `micromatch` e `braces`, e o `braces` tem um aviso de segurança de
 * exaustão de pilha sem versão corrigida (GHSA-vfj7-8cjw-p6xm): todas as
 * versões estão no intervalo vulnerável. A cadeia é de desenvolvimento e
 * não chega ao navegador, mas compor a configuração à mão custa dez linhas
 * e zera o aviso — e deixa explícito o que está sendo verificado.
 */
export default tseslint.config(
  {
    ignores: ['dist/**', 'coverage/**', 'node_modules/**'],
  },

  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...pluginVue.configs['flat/recommended'],

  {
    files: ['**/*.vue'],
    languageOptions: {
      parserOptions: {
        // Os blocos <script setup lang="ts"> precisam do parser do
        // TypeScript; sem isto o ESLint tenta lê-los como JavaScript.
        parser: tseslint.parser,
      },
    },
  },

  {
    rules: {
      // Variável iniciada por `_` é descarte intencional — o padrão em
      // desestruturação que remove uma chave do objeto.
      '@typescript-eslint/no-unused-vars': [
        'error',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
      ],

      // Componentes de página e layout têm nome de uma palavra por
      // convenção; a regra existe para evitar colisão com elementos HTML,
      // que `App` e `CustomersView` não têm.
      'vue/multi-word-component-names': 'off',
    },
  },

  {
    // A infraestrutura de testes precisa de `any` para contornar os tipos
    // condicionais do Vue Test Utils; a restrição continua valendo no
    // código de aplicação.
    files: ['tests/**/*.ts'],
    rules: {
      '@typescript-eslint/no-explicit-any': 'off',
    },
  },

  prettier,
)
