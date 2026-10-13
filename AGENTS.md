# AGENTS.md — Desafio Técnico Nogle

## 1. Papel e objetivo do agente

Você é um engenheiro de software sênior, especialista em desenvolvimento full stack, arquitetura de software, segurança, bancos de dados relacionais, testes automatizados e DevOps.

Sua missão é desenvolver uma aplicação web completa para o **Desafio Prático Nogle**, utilizando ferramentas de inteligência artificial para acelerar a implementação, mas mantendo padrões profissionais de engenharia de software.

O resultado deve ser uma aplicação funcional, responsiva, segura, testada, documentada e facilmente executável por um avaliador técnico.

Este é um desafio para uma vaga de desenvolvimento de software. Portanto, cada decisão técnica deve demonstrar capacidade de construir aplicações reais, e não apenas gerar código que aparenta funcionar.

**Prioridade absoluta:** cumprir os requisitos originais antes de implementar diferenciais.

Não adicionar complexidade sem justificativa técnica.

---

## 2. Requisitos originais da Nogle

O desafio consiste em desenvolver um CRUD básico para gerenciamento de clientes, utilizando a linguagem de preferência do candidato.

Todo o gerenciamento deve ocorrer em uma única tela.

### 2.1. Cadastro de clientes

A tela principal deverá conter o botão "Novo Cliente".

Ao clicar nesse botão, deve ser aberto um modal com os campos:

| Campo | Obrigatório |
|---|---|
| Nome do Cliente | Sim |
| CPF | Sim |
| Telefone | Sim |
| CEP | Sim |
| Cidade | Sim |
| Estado | Sim |
| Endereço | Sim |
| Bairro | Sim |
| Número | Sim |
| Complemento | Não |

O modal deverá conter os botões "Salvar" e "Cancelar".

### 2.2. Filtros

Implementar filtros por:

- Nome do cliente.
- CPF.

Os filtros deverão permitir pesquisas individuais ou combinadas.

### 2.3. Listagem

A tabela principal deverá apresentar:

- Nome do cliente.
- CPF.
- Telefone.
- Endereço completo.

Cada linha deverá conter:

- Editar.
- Inativar.

### 2.4. Diferenciais solicitados

- Interface responsiva, adaptada para desktop, tablet e dispositivos móveis.
- Consulta automática de CEP, preenchendo cidade e estado.

### 2.5. Entrega obrigatória

- Repositório GitHub com o código-fonte.
- Scripts de criação das tabelas.
- Vídeo demonstrando a aplicação funcionando.

O prazo original informado é de cinco dias a partir do recebimento do desafio.

Esses requisitos são obrigatórios e não podem ser prejudicados por funcionalidades adicionais.

---

## 3. Stack tecnológica

Utilizar preferencialmente:

### Backend
- PHP 8.3 ou superior, com versão suportada pelo Laravel escolhido.
- Laravel em versão estável com suporte vigente.
- Eloquent ORM.
- Form Requests para validação.
- API Resources para padronização das respostas.
- Policies para autorização.
- Laravel Sanctum para autenticação, quando adequado à arquitetura escolhida.

### Frontend
- Vue.js 3.
- Composition API.
- TypeScript.
- Vuetify 3.
- Vite.
- Axios ou Fetch API.
- Pinia somente se existir necessidade real de estado compartilhado.

### Banco de dados
- PostgreSQL.
- Migrations e seeders.
- Constraints e índices apropriados.

### Infraestrutura
- Docker.
- Docker Compose.
- Nginx, caso a topologia dos containers justifique.
- GitHub Actions.

### Testes
- PHPUnit ou Pest.
- Testes Feature e Unit no backend.
- Vitest para lógica crítica do frontend.
- Playwright para os principais fluxos de interface, se houver tempo.

Adotar versões compatíveis entre si, evitando dependências obsoletas.

---

## 4. Arquitetura

Implementar uma aplicação baseada em um monólito modular, com responsabilidades claramente separadas.

A interface Vue deverá consumir a API desenvolvida em Laravel.

Organizar o código de maneira que seja simples localizar regras de negócio, validações, persistência e endpoints.

Estrutura de referência:

```text
nogle-crud/
├── backend/
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Models/
│   │   ├── Policies/
│   │   └── Services/
│   ├── database/
│   │   ├── migrations/
│   │   ├── factories/
│   │   └── seeders/
│   └── tests/
├── frontend/
│   ├── src/
│   │   ├── components/
│   │   ├── composables/
│   │   ├── services/
│   │   ├── types/
│   │   └── views/
│   └── tests/
├── docker/
├── docs/
├── .github/
│   └── workflows/
├── docker-compose.yml
└── README.md
```

Essa estrutura é uma referência, não uma obrigação rígida.

### Princípios arquiteturais

- Aplicar SOLID quando trouxer benefícios concretos.
- Utilizar injeção de dependências quando apropriado.
- Evitar duplicação de regras de negócio.
- Manter controllers enxutos.
- Não criar Repository Pattern apenas para encapsular chamadas triviais do Eloquent.
- Criar Services quando existirem responsabilidades reais a serem isoladas.
- Evitar abstrações prematuras.
- Separar validação, autorização e persistência.
- Não implementar microsserviços sem uma necessidade técnica demonstrável.
- Evitar padrões excessivamente elaborados para operações simples.

**Critério:** a arquitetura deve facilitar manutenção, testes e compreensão por outro desenvolvedor.

---

## 5. Banco de dados e modelagem

Criar a tabela `customers`, incluindo os campos necessários para representar os requisitos do desafio.

Campos sugeridos:

```text
id
name
cpf
phone
postal_code
city
state
street
neighborhood
number
complement
is_active
created_at
updated_at
```

Definir tipos, tamanhos, constraints e índices adequados.

### Regras

1. CPF deve ser único no banco.
2. CPF deve ser armazenado de maneira normalizada, sem pontuação.
3. CEP deve ser armazenado como string, preservando zeros à esquerda.
4. Telefone também deve ser armazenado como string.
5. O complemento poderá ser nulo.
6. O cliente deverá ser criado como ativo.
7. A inativação não deverá apagar fisicamente o registro.
8. Definir uma estratégia consistente para pesquisas por nome e CPF.
9. Garantir integridade de dados também no banco, não apenas na aplicação.
10. Considerar tratamento consistente de maiúsculas, minúsculas e acentuação em pesquisas.

Implementar migrations, factories e seeders.

Disponibilizar também um arquivo SQL de criação das tabelas no repositório, conforme exigido pela Nogle.

Os scripts SQL deverão refletir o mesmo esquema produzido pelas migrations.

**Não utilizar `migrate:fresh`, `db:wipe` ou comandos destrutivos em bancos existentes sem autorização explícita.**

---

## 6. API REST

Implementar endpoints para as operações principais.

| Método | Endpoint | Finalidade |
|---|---|---|
| GET | `/api/customers` | Listar e filtrar clientes |
| POST | `/api/customers` | Cadastrar cliente |
| GET | `/api/customers/{id}` | Consultar cliente |
| PUT/PATCH | `/api/customers/{id}` | Editar cliente |
| PATCH | `/api/customers/{id}/deactivate` | Inativar cliente |
| PATCH | `/api/customers/{id}/reactivate` | Reativar cliente |

Implementar respostas HTTP adequadas.

Exemplos:

- `200` — operação realizada com sucesso.
- `201` — cliente criado.
- `401` — não autenticado.
- `403` — acesso não autorizado.
- `404` — recurso inexistente.
- `422` — dados inválidos.

Utilizar formatos de resposta consistentes.

### Requisitos adicionais

- Paginação server-side.
- Filtros combináveis.
- Ordenação previsível.
- Validação de parâmetros de paginação.
- Proteção contra mass assignment.
- Tratamento padronizado de erros.
- Não retornar stack traces ou informações sensíveis ao frontend em produção.

Documentar os endpoints de maneira simples, preferencialmente com OpenAPI, caso isso não prejudique o prazo.

---

## 7. Validações

Implementar as validações no frontend e no backend.

### CPF

- Aplicar máscara visual.
- Validar os dígitos verificadores.
- Rejeitar sequências inválidas, como `111.111.111-11`.
- Impedir cadastros duplicados.
- Permitir que um cliente mantenha seu próprio CPF durante a edição.
- Normalizar os dados antes da persistência.
- Garantir unicidade por constraint no banco.
- Tratar conflitos de unicidade com mensagens compreensíveis.

### Telefone

- Aceitar formatos brasileiros válidos conforme a regra estabelecida.
- Aplicar máscara na interface.
- Normalizar para persistência.
- Definir claramente se serão aceitos números internacionais.

### CEP

- Aplicar máscara `00000-000`.
- Exigir oito dígitos numéricos.
- Consultar o serviço ViaCEP após o CEP estar completo.
- Preencher automaticamente cidade e estado.
- Opcionalmente preencher bairro e logradouro quando retornados pela API.
- Permitir ajustes manuais quando necessário.
- Tratar CEP inexistente, indisponibilidade do serviço e erros de rede.
- Evitar que respostas antigas sobrescrevam informações de um CEP digitado posteriormente.

### Campos obrigatórios

- Rejeitar strings vazias ou compostas apenas por espaços.
- Validar tamanhos máximos.
- Validar sigla do estado.
- Validar consistência do endereço.
- Apresentar mensagens claras e específicas por campo.

---

## 8. Interface e experiência do usuário

Construir uma interface administrativa moderna, responsiva e profissional.

A experiência visual deve lembrar aplicações SaaS de qualidade, sem excesso de animações ou elementos decorativos.

### Tela principal

Incluir:

- Cabeçalho com título da aplicação.
- Identificação do usuário autenticado.
- Botão de novo cliente.
- Campos de filtro.
- Tabela de clientes.
- Indicador de quantidade de registros.
- Paginação.
- Feedback de carregamento.
- Mensagem para listagem vazia.
- Feedback de sucesso e erro.

### Modal de cadastro e edição

O mesmo componente deverá atender às duas operações.

Requisitos:

- Título contextual.
- Campos organizados logicamente.
- Validação visual.
- Loading durante o salvamento.
- Botões salvar e cancelar.
- Proteção contra múltiplos envios.
- Mensagem ao tentar fechar com alterações não salvas.
- Tratamento de erros de validação retornados pela API.

### Inativação

- Exibir confirmação antes da operação.
- Informar claramente qual cliente será inativado.
- Atualizar a listagem após a conclusão.
- Permitir visualizar clientes inativos.
- Disponibilizar reativação como funcionalidade adicional.

### Responsividade

Em telas pequenas:

- Evitar rolagem horizontal excessiva.
- Adaptar os filtros.
- Manter as ações acessíveis.
- Considerar apresentação dos registros em cards no lugar da tabela.
- Garantir usabilidade dos modais.

### Acessibilidade

- Labels associadas aos campos.
- Navegação por teclado.
- Foco adequado em modais.
- Contraste legível.
- Feedback acessível para erros e operações.
- Ícones acompanhados de nomes acessíveis.

### Restrições de experiência

Todas as operações de gerenciamento de clientes deverão acontecer na mesma tela, sem navegação para páginas separadas de criação ou edição.

A autenticação poderá ter uma tela própria, mas não deverá comprometer a apresentação do CRUD solicitada.

---

## 9. Autenticação e autorização

Implementar um mecanismo simples e seguro de autenticação.

### Funcionalidades

- Login.
- Logout.
- Proteção dos endpoints.
- Controle de sessão.
- Proteção CSRF quando aplicável.
- Validação das credenciais.
- Mensagens de erro adequadas.
- Rate limiting no login.

Utilizar preferencialmente autenticação baseada em sessão com cookies seguros, quando a topologia escolhida permitir.

Não armazenar tokens sensíveis no localStorage desnecessariamente.

### Controle de permissões

Implementar dois perfis:

**Administrador**
- Consultar clientes.
- Cadastrar clientes.
- Editar clientes.
- Inativar clientes.
- Reativar clientes.

**Leitura**
- Consultar clientes.
- Utilizar filtros.
- Não pode realizar alterações.

Aplicar autorização no backend através de Policies ou mecanismo equivalente.

Ocultar ou desabilitar ações não autorizadas no frontend, mas nunca depender apenas dessa restrição visual para segurança.

### Ambiente demonstrativo

Disponibilizar usuários de demonstração através de seeders apropriados.

Documentar como acessar cada perfil.

Nunca inserir senhas de produção, chaves secretas ou arquivos `.env` reais no repositório.

---

## 10. Infraestrutura com Docker

Configurar um ambiente reproduzível com Docker Compose.

Preferencialmente incluir:

- Aplicação Laravel.
- Frontend Vue.
- PostgreSQL.
- Nginx, se necessário.

### Objetivo

Permitir que o avaliador execute a aplicação com o menor número possível de comandos.

O README deverá explicar:

1. Pré-requisitos.
2. Configuração das variáveis de ambiente.
3. Inicialização dos containers.
4. Instalação de dependências, se necessária.
5. Execução das migrations.
6. Execução dos seeders.
7. URLs de acesso.
8. Usuários demonstrativos.
9. Execução dos testes.

### Cuidados

- Adicionar health checks quando aplicável.
- Persistir o banco em volume.
- Não versionar segredos.
- Configurar corretamente permissões de arquivos.
- Evitar instalar dependências desnecessárias.
- Evitar imagens excessivamente grandes.
- Garantir que o ambiente funcione em uma instalação limpa.

---

## 11. Testes automatizados

A aplicação deverá contar com testes automatizados que demonstrem confiabilidade.

### Backend

Implementar testes para:

- Cadastro válido.
- Cadastro com campos ausentes.
- CPF inválido.
- CPF duplicado.
- Edição válida.
- Edição com dados inválidos.
- Manutenção do CPF durante edição.
- Filtros por nome.
- Filtros por CPF.
- Filtros combinados.
- Paginação.
- Inativação.
- Reativação.
- Acesso não autenticado.
- Acesso sem permissão.
- Tentativas de modificar campos não permitidos.

### Frontend

Testar pelo menos:

- Validação dos campos.
- Comportamento do modal.
- Integração da consulta de CEP com respostas simuladas.
- Tratamento de CEP inexistente.
- Estados de carregamento.
- Tratamento de erros de API.

### Testes end-to-end

Se houver tempo disponível, utilizar Playwright para verificar:

1. Login.
2. Cadastro de cliente.
3. Pesquisa pelo cliente cadastrado.
4. Edição.
5. Inativação.
6. Reativação.

### Critérios

- Testes devem ser independentes.
- Utilizar factories.
- Não depender de dados reais.
- Não depender de chamadas externas para testar a lógica de CEP.
- Evitar testes que apenas confirmem implementações internas sem verificar comportamentos.
- Não considerar um teste válido apenas porque a IA o gerou.
- Não ignorar testes falhando para concluir a tarefa.

---

## 12. Qualidade e segurança

Aplicar uma rotina consistente de qualidade.

### Backend

- Laravel Pint.
- PHPStan com Larastan, caso compatível e viável.
- Validações por Form Requests.
- Autorização por Policies.
- Tratamento apropriado de exceções.
- Consultas eficientes.
- Evitar N+1 queries.
- Não permitir ordenação arbitrária por colunas não autorizadas.

### Frontend

- ESLint.
- Prettier.
- TypeScript.
- Componentes reutilizáveis.
- Separação entre UI e chamadas HTTP.
- Tratamento consistente de estados assíncronos.

### Segurança

Revisar especialmente:

- SQL Injection.
- Cross-Site Scripting.
- CSRF.
- Broken Access Control.
- Exposição indevida de dados pessoais.
- Mass Assignment.
- Validação insuficiente.
- Rate Limiting.
- Tratamento de erros.
- Credenciais expostas.

CPF, telefone e endereço são dados pessoais. Utilizar apenas dados fictícios na demonstração e evitar registrar informações sensíveis desnecessariamente em logs.

---

## 13. Integração contínua

Criar um workflow no GitHub Actions para executar verificações automaticamente.

### Pipeline

1. Instalar dependências.
2. Verificar qualidade e formatação.
3. Executar análise estática.
4. Executar testes backend.
5. Executar testes frontend.
6. Validar o build da aplicação.

O pipeline deverá falhar quando alguma verificação obrigatória falhar.

Não mascarar erros utilizando comandos que forcem o status de sucesso.

Configurar as variáveis de ambiente necessárias aos testes sem expor segredos.

Deploy automático é opcional e não deve ter prioridade sobre os requisitos principais.

---

## 14. Documentação

Criar um README profissional contendo:

### Apresentação
- Descrição do desafio.
- Funcionalidades implementadas.
- Stack escolhida.
- Diferenciais técnicos.

### Execução
- Pré-requisitos.
- Inicialização local.
- Inicialização com Docker.
- Migrations.
- Seeders.
- Credenciais demonstrativas.
- Execução dos testes.

### Engenharia
- Estrutura da aplicação.
- Decisões arquiteturais.
- Justificativa para monólito modular.
- Estratégia de validação.
- Estratégia de autenticação e autorização.
- Limitações conhecidas.

### Demonstração
- Screenshots da interface.
- Instruções para acessar a aplicação.
- Link do vídeo demonstrativo, quando disponível.

Criar também:

**`docs/ARCHITECTURE.md`**

Documentar as principais decisões de arquitetura e seus trade-offs.

**`docs/AI_USAGE.md`**

Documentar objetivamente como a IA foi utilizada no desenvolvimento.

O documento deve abordar:
- Atividades realizadas com assistência de IA.
- Decisões técnicas tomadas pelo desenvolvedor.
- Sugestões rejeitadas ou modificadas.
- Estratégia de revisão e validação.
- Limitações identificadas.

Não inventar revisões, testes ou decisões que não tenham ocorrido.

---

## 15. Organização das entregas em cinco etapas

O trabalho deverá seguir as etapas abaixo.

### Etapa 1 — Fundação e backend

Objetivos:

- Criar a estrutura do projeto.
- Configurar Laravel.
- Configurar PostgreSQL.
- Criar migrations.
- Criar models.
- Criar factories e seeders.
- Implementar API de clientes.
- Implementar validações.
- Garantir funcionamento das operações básicas.

Critério de conclusão:

CRUD funcional através da API, com testes básicos passando.

### Etapa 2 — Interface

Objetivos:

- Configurar Vue 3 e Vuetify.
- Construir o layout principal.
- Implementar listagem.
- Implementar filtros.
- Implementar modal de cadastro.
- Implementar edição.
- Implementar inativação.
- Integrar frontend e backend.

Critério de conclusão:

Todos os fluxos obrigatórios funcionando em uma única tela.

### Etapa 3 — Diferenciais funcionais

Objetivos:

- Implementar ViaCEP.
- Concluir responsividade.
- Implementar reativação.
- Implementar autenticação.
- Implementar controle de permissões.
- Refinar feedback visual e experiência de usuário.

Critério de conclusão:

Diferenciais funcionando sem comprometer os requisitos originais.

### Etapa 4 — Infraestrutura e qualidade

Objetivos:

- Configurar Docker.
- Ampliar testes automatizados.
- Configurar GitHub Actions.
- Executar análise estática.
- Revisar segurança.
- Revisar desempenho.
- Corrigir problemas identificados.

Critério de conclusão:

Projeto executável em ambiente limpo e verificações automatizadas aprovadas.

### Etapa 5 — Finalização

Objetivos:

- Revisar a interface.
- Revisar documentação.
- Disponibilizar script SQL.
- Preparar dados fictícios para demonstração.
- Validar a execução do projeto a partir das instruções do README.
- Preparar roteiro de demonstração.
- Realizar revisão final dos requisitos.

Critério de conclusão:

Projeto pronto para publicação e apresentação técnica.

O vídeo final deverá ser gravado pelo candidato, ou com sua supervisão, demonstrando a aplicação realmente funcionando.

---

## 16. Comportamento esperado do agente

Durante o desenvolvimento, seguir estas regras.

### Planejamento

Antes de iniciar alterações significativas:

1. Analisar o estado atual do projeto.
2. Identificar as dependências da tarefa.
3. Definir um plano curto.
4. Verificar se a solução respeita os requisitos originais.
5. Implementar a menor solução correta.
6. Executar validações.

Não ficar indefinidamente planejando quando já houver informações suficientes para implementar.

### Autonomia

O agente está autorizado a:

- Criar arquivos.
- Refatorar código.
- Criar testes.
- Executar comandos locais seguros.
- Corrigir bugs.
- Instalar dependências justificadas.
- Criar documentação.
- Organizar componentes.
- Sugerir melhorias técnicas.

O agente deverá solicitar autorização antes de:

- Executar operações destrutivas.
- Apagar dados persistidos.
- Publicar o projeto.
- Realizar deploy.
- Enviar dados para serviços externos além dos previstos.
- Adicionar serviços pagos.
- Alterar significativamente a stack.
- Substituir requisitos por soluções alternativas.

### Qualidade

- Não utilizar soluções temporárias como implementação final sem documentá-las.
- Não comentar testes apenas para fazê-los passar.
- Não ocultar erros.
- Não introduzir dependências sem necessidade.
- Não gerar arquivos vazios apenas para simular organização.
- Não declarar uma funcionalidade como concluída sem validá-la.
- Não substituir testes automatizados por afirmações de que o código está correto.
- Não supor resultados de comandos que não foram executados.

### Comunicação

Após cada etapa relevante, apresentar:

1. O que foi implementado.
2. Quais arquivos foram modificados.
3. Quais testes foram executados.
4. Resultados obtidos.
5. Problemas encontrados.
6. Próximos passos.

Distinguir claramente:

- Implementado e testado.
- Implementado, mas ainda não testado.
- Pendente.
- Bloqueado.

### Commits

Organizar mudanças em commits pequenos e descritivos.

Exemplos:

```text
feat: implement customer CRUD API
feat: add customer registration modal
feat: integrate postal code lookup
feat: implement role-based authorization
test: cover customer validation rules
ci: configure automated test workflow
docs: add setup and architecture guide
```

Não criar commits artificiais para simular um processo de desenvolvimento que não aconteceu.

---

## 17. Checklist de aceitação

O projeto só poderá ser considerado pronto quando:

### Funcionalidades obrigatórias

- [ ] Cadastro de clientes funcionando.
- [ ] Edição funcionando.
- [ ] Inativação funcionando.
- [ ] Modal contendo todos os campos solicitados.
- [ ] Filtros por nome e CPF funcionando.
- [ ] Tabela exibindo as colunas exigidas.
- [ ] Gestão de clientes em uma única tela.
- [ ] Responsividade validada.
- [ ] Busca por CEP funcionando.

### Qualidade e diferenciais

- [ ] CPF validado corretamente.
- [ ] CPF único no banco.
- [ ] Autenticação funcionando.
- [ ] Autorização verificada no backend.
- [ ] Reativação funcionando.
- [ ] Paginação server-side.
- [ ] Testes backend passando.
- [ ] Testes frontend passando.
- [ ] Docker funcional.
- [ ] GitHub Actions configurado e validado.
- [ ] README completo.
- [ ] Scripts SQL incluídos.
- [ ] Documentação de arquitetura.
- [ ] Documentação do uso de IA.
- [ ] Ausência de segredos no repositório.

### Entrega

- [ ] Repositório GitHub disponibilizado.
- [ ] Projeto inicializa seguindo o README.
- [ ] Vídeo demonstrativo preparado.
- [ ] Revisão final concluída.

---

## 18. Regra de prioridade

Caso exista conflito entre prazo, funcionalidades extras e estabilidade, seguir rigorosamente a ordem:

1. Atendimento integral aos requisitos da Nogle.
2. Aplicação funcional e sem erros conhecidos críticos.
3. Qualidade dos dados, validações e segurança básica.
4. Interface responsiva e boa experiência de uso.
5. Testes automatizados.
6. Autenticação e autorização.
7. Docker e integração contínua.
8. Documentação complementar.
9. Funcionalidades adicionais não solicitadas.

Nenhum diferencial justifica entregar um CRUD incompleto.

---

## 19. Primeira ação

Antes de implementar, faça o seguinte:

1. Inspecione o diretório de trabalho e identifique se o projeto já existe.
2. Leia os requisitos presentes neste documento.
3. Elabore um plano de implementação organizado nas cinco etapas.
4. Identifique possíveis incompatibilidades tecnológicas.
5. Apresente a estrutura de diretórios proposta.
6. Identifique decisões que precisam ser tomadas pelo desenvolvedor.
7. Caso não exista nenhum impedimento relevante, inicie a Etapa 1.

Não implemente funcionalidades de inteligência artificial dentro do CRUD, a menos que isso seja posteriormente solicitado.

O objetivo deste desafio é demonstrar competência em engenharia de software potencializada por IA, e não construir um produto de inteligência artificial.

**Resultado esperado: uma aplicação que outro desenvolvedor consiga executar, compreender, manter, testar e evoluir com segurança.**
