# Uso de inteligência artificial no desenvolvimento

O desafio pede explicitamente que o uso de IA seja documentado de forma
objetiva. Este documento descreve o que de fato aconteceu — incluindo os
erros que a IA cometeu e o que foi necessário para encontrá-los.

> Tudo aqui é verificável no histórico do repositório. Os defeitos citados
> têm commit correspondente, e as medições citadas têm comando que as produz.

---

## Ferramenta e método

**Ferramenta:** Claude (Anthropic), via Claude Code, com acesso ao terminal,
ao banco de dados e a um navegador.

**Método:** o trabalho foi dividido em cinco etapas, definidas antes de
qualquer código ser escrito. A IA não avançava de uma etapa para a seguinte
sem autorização explícita do desenvolvedor, e cada etapa terminava com um
resumo do que fora implementado, quais comandos haviam sido executados e
quais eram os resultados.

| Etapa | Escopo |
|---|---|
| 1 | Fundação e backend — schema, API, validações |
| 2 | Interface — tela única, listagem, filtros, modais |
| 3 | Diferenciais — ViaCEP, autenticação, permissões |
| 4 | Infraestrutura e qualidade — Docker, CI, segurança, desempenho |
| 5 | Finalização — script SQL, documentação, demonstração |

A regra que mais afetou o resultado foi a do desenvolvedor: **nenhuma
funcionalidade é considerada concluída sem validação executada**. Na prática
isso significou que afirmações como "o Docker está configurado" não eram
aceitas sem `docker compose up` ter rodado — e foi exatamente aí que os
defeitos mais graves apareceram.

---

## O que foi feito com assistência de IA

Praticamente toda a escrita de código, de testes e desta documentação. Mais
especificamente:

- Modelagem do schema, incluindo constraints, índices e a função
  `immutable_unaccent`.
- Implementação da API, validações, Policies e autenticação.
- Implementação da interface, componentes, composables e camada de serviços.
- Escrita das suítes de teste — 223 testes no backend, 209 no frontend.
- Dockerfiles, configuração do Nginx e workflow de CI.
- Investigação e correção de todos os defeitos listados adiante.
- Medições de desempenho com volume sintético de 50 mil registros.

---

## Decisões que foram do desenvolvedor

Estas não partiram da IA, e algumas contrariaram a sugestão inicial dela:

1. **O faseamento em cinco etapas com autorização entre elas.** Foi o que
   impediu a IA de entregar tudo de uma vez e sem conferência intermediária.
2. **A exigência de validar antes de declarar concluído.** Origem direta dos
   achados da seção seguinte.
3. **Como resolver o acesso ao Docker.** Foram apresentadas quatro opções,
   com a contrapartida de segurança de cada uma declarada — entrar no grupo
   `docker` equivale a acesso root na máquina. A escolha foi do
   desenvolvedor.
4. **Não encerrar a Etapa 4 sem Docker executado.** A IA havia proposto
   seguir e registrar a pendência; o desenvolvedor pediu para resolver o
   acesso. Os três defeitos de infraestrutura só apareceram por causa disso.

---

## Sugestões da IA que foram rejeitadas ou modificadas

### Rejeitadas por escolha deliberada

| Sugestão inicial | O que foi feito | Motivo |
|---|---|---|
| Vuetify 4, por ser a versão mais recente | Vuetify 3.13 | O requisito nomeia "Vuetify 3", e o conhecimento da IA sobre a API da v4 é menos confiável — risco de código sutilmente errado |
| vue-router 5, por ser o mais recente | vue-router 4.6 | A v5 declara `pinia` e `@pinia/colada` como peers; é outro produto, não a evolução direta |
| Preset `@vue/eslint-config-typescript` | Configuração composta à mão | O preset arrastava `braces`, com aviso de segurança **sem versão corrigida**. Compor a configuração custou dez linhas e zerou o aviso |
| Promover o Faker a dependência de produção | Seeder sem Faker | Carregaria uma biblioteca de dados falsos na imagem de produção só para semear |
| `--classmap-authoritative` no autoloader | Apenas `--optimize` | O modo autoritativo faz o autoloader recusar classes fora do mapa, quebrando pacotes que geram classe em tempo de execução. Ganho marginal, risco real |

### Modificadas depois de confrontadas com a realidade

| Plano inicial da IA | O que a realidade mostrou | Correção |
|---|---|---|
| TypeScript 5.9, por conservadorismo | O template do Vite traz TS 6 pareado com `vue-tsc` 3.3, que é a combinação testada em conjunto | Mantido TS 6 |
| Pest 5 | Exige PHP 8.4; o ambiente tem 8.3 | Pest 4.7 |
| Índice de unicidade atenderia a busca por prefixo de CPF | Medição mostrou varredura sequencial | Índice `bpchar_pattern_ops` acrescentado |
| Código montado em volume no Docker | Volume nomeado só inicializa quando vazio — manteria código antigo | Código na imagem |
| Inverter a prioridade do middleware para o limite alcançar tráfego anônimo | Não surtiu efeito e contrariava decisão deliberada do framework | Tentativa revertida; limite por IP movido para o Nginx |

---

## Defeitos que a IA introduziu e a validação encontrou

Esta é a seção mais útil do documento. Todos os itens abaixo foram escritos
pela IA, pareciam corretos na leitura e **só foram descobertos ao executar**.

### Encontrados por teste automatizado

1. **`deactivate` e `reactivate` não funcionavam.** `is_active` está fora do
   `$fillable` de propósito, então `update(['is_active' => ...])` era
   descartado em silêncio pela proteção contra atribuição em massa.
2. **Resposta 404 vazava o namespace interno.** O tratamento estava
   registrado para `ModelNotFoundException`, mas o Laravel a converte em
   `NotFoundHttpException` antes de consultar os renderizadores — o handler
   nunca disparava. O mesmo erro se repetiu depois com a exceção de
   autorização.
3. **Resposta atrasada sobrescrevia a mais recente** na listagem. A guarda
   de cancelamento existia no caminho de erro, mas não no de sucesso.
4. **O limitador de requisições devolvia 500 em vez de 429.** O fechamento de
   resposta declarava retorno `Illuminate\Http\Response`, mas
   `response()->json()` devolve `JsonResponse`, que não estende aquela
   classe. **A análise estática em nível 8 não pegou; o teste pegou.**
5. **Validação acontecia antes da autorização.** Um usuário sem permissão
   recebia 422 com os erros do formulário antes de receber 403, revelando as
   regras a quem não pode usá-lo.

### Encontrados ao exercitar a aplicação no navegador

6. **O proxy do Vite não encaminhava `/sanctum`.** O endpoint do cookie CSRF
   fica fora do prefixo `/api`; o navegador recebia 404, o login seguia sem
   cookie e o Laravel recusava com 419.
7. **Rodapé da tabela em inglês** no meio de uma interface em português,
   porque o locale do Vuetify não havia sido configurado.
8. **A rota `/` respondia 500**, porque a view padrão do Laravel renderizava
   `@vite(...)` com assets que nunca seriam compilados num backend API-only.

### Encontrados ao subir os containers

9. **O build da imagem quebrava** com `composer: not found` — o
   `dump-autoload` rodava na imagem final, que não tem Composer.
10. **Os seeders quebravam e o container entrava em laço de reinício**, com
    `Call to undefined function fake()`.
11. **O Nginx descartava os cabeçalhos de segurança na página HTML.** A API
    recebia os quatro; `GET /` recebia zero. É a regra de herança do
    `add_header`: um bloco que declara qualquer `add_header` descarta todos
    os herdados — e o bloco de `/index.html` declarava o próprio
    `Cache-Control`. **A página mais exposta a clickjacking estava sem
    proteção.**
12. **O container do Nginx ficava marcado `unhealthy`** enquanto servia
    normalmente: `localhost` resolve para `::1` dentro do container e o
    servidor escutava apenas IPv4.

### Encontrados por medição

13. **Busca por prefixo de CPF fazia varredura sequencial** — 4,98 ms contra
    0,10 ms com o índice correto, em 50 mil registros. O comentário no código
    afirmava o contrário, e foi corrigido junto.

---

## Como o resultado foi validado

A validação não foi por leitura. Cada afirmação tem um comando que a produz:

| Verificação | Como |
|---|---|
| Algoritmo de CPF | 15 casos conhecidos mais 2.000 CPFs gerados, todos conferidos pelo próprio validador |
| Constraints do banco | 6 inserções inválidas, todas rejeitadas pelo PostgreSQL |
| Busca sem acento | Executada contra o banco e verificada ponta a ponta no navegador |
| Uso dos índices | `EXPLAIN ANALYZE` com 50 mil registros, com e sem cada índice |
| SQL Injection | 5 payloads reais no filtro; tabela intacta, curingas tratados como literais |
| CSRF | Requisição sem token, com token forjado e de outra origem |
| Autorização | Requisição forjada pelo console do navegador, a partir de uma sessão de leitura — recusada com 403 |
| Vazamento em erro | Quatro rotas de erro com `APP_DEBUG=false`, nenhuma com `trace`, `exception`, `file` ou `line` |
| Limite de login | Sete tentativas; bloqueio na sexta |
| Dados pessoais em log | Varredura do log: zero CPFs, zero senhas |
| ViaCEP | Contra o serviço real, incluindo CEP inexistente e condição de corrida |
| Responsividade | Verificação programática de elementos excedendo a largura em 375px: zero |
| Ambiente Docker | `down -v` seguido de `up`, mais 19 verificações funcionais |
| Script SQL | Comparação automatizada com o esquema das migrations |

---

## Limitações observadas na IA

Registradas porque são o contraponto honesto ao que foi produzido:

**Escreve código que parece certo e não executa.** Os treze defeitos acima
passaram por revisão de leitura — inclusive a da própria IA — sem serem
notados. Todos foram encontrados executando. Nenhuma quantidade de leitura
cuidadosa substituiu rodar o comando.

**Erra com mais frequência nas bordas entre sistemas.** Note onde os defeitos
se concentram: proxy do Vite, ordem de middleware do Laravel, herança de
`add_header` no Nginx, resolução de IPv6 no container, Composer entre
estágios de build. A lógica de negócio — validação de CPF, filtros,
paginação — saiu correta de primeira. O que quebra é a junção.

**Repete o mesmo erro em contextos diferentes.** O handler registrado para a
exceção errada aconteceu duas vezes, com três semanas de distância conceitual
entre elas: primeiro com `ModelNotFoundException`, depois com
`AuthorizationException`. A IA não generalizou a lição sozinha.

**Supõe com confiança.** A afirmação de que o índice de unicidade atenderia a
busca por prefixo estava escrita como fato, num comentário, sem ter sido
medida. Era falsa.

**Precisa de limite para não inflar a solução.** Sem a regra explícita contra
abstração desnecessária, a tendência inicial foi propor Service, Repository e
Pinia para um CRUD de uma entidade.

**A análise estática não substitui teste.** PHPStan em nível 8 reportou zero
erros sobre um código que devolvia 500 onde deveria devolver 429.

---

## Conclusão

A IA acelerou substancialmente a escrita — código, testes e documentação. Não
substituiu a necessidade de executar, medir e desconfiar.

O que tornou o resultado confiável não foi o modelo: foi o processo. Etapas
com autorização entre elas, recusa a declarar algo concluído sem comando
executado, e a decisão de resolver o acesso ao Docker em vez de registrar a
pendência — sem essa última, três defeitos de infraestrutura teriam sido
entregues intactos, incluindo uma imagem que simplesmente não constrói.
