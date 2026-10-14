# Decisões de arquitetura

Este documento registra as decisões estruturais do projeto e o que elas
custam. Cada seção traz a alternativa considerada e o motivo de não ter sido
escolhida — uma decisão sem alternativa descartada não é decisão, é hábito.

---

## 1. Monólito modular

**Decisão.** Backend Laravel e frontend Vue em um único repositório, com
fronteiras claras entre validação, autorização, persistência e apresentação.

**Alternativa descartada.** Microsserviços.

**Por quê.** O domínio tem uma entidade. Não existe fronteira que justifique
processos separados, e separá-los acrescentaria latência de rede, consistência
eventual e complexidade operacional em troca de nada. O requisito é explícito
ao pedir que microsserviços não sejam adotados sem necessidade demonstrável.

**Custo.** Backend e frontend escalam juntos. Para esta aplicação, irrelevante.

---

## 2. SPA e API na mesma origem

**Decisão.** O navegador enxerga um único servidor: `/` entrega a interface e
`/api` a API. Em desenvolvimento, o servidor do Vite encaminha `/api` e
`/sanctum` ao Laravel; em produção, o Nginx serve o SPA compilado e repassa os
mesmos caminhos ao PHP-FPM.

**Alternativa descartada.** Frontend e API em origens distintas, com CORS.

**Por quê.** Esta é a decisão que sustenta a autenticação. Cookie de sessão
entre origens diferentes exigiria `SameSite=None`, que por sua vez exige
`Secure` — ou seja, HTTPS também em desenvolvimento — mais CORS com
credenciais e uma lista de origens permitidas. Mesma origem elimina os três
problemas de uma vez.

**Custo.** O ambiente de desenvolvimento depende do proxy estar configurado.
Um esquecimento aqui custou tempo: o proxy encaminhava `/api` mas não
`/sanctum`, onde fica o endpoint do cookie CSRF, e o login falhava com 419 sem
explicação óbvia.

---

## 3. Sessão com cookie, não token

**Decisão.** Sanctum em modo SPA. Nenhum token é emitido, e a tabela
`personal_access_tokens` não existe.

**Alternativa descartada.** JWT ou token pessoal guardado no `localStorage`.

**Por quê.** O cookie de sessão é `HttpOnly`, portanto inacessível a
JavaScript — o que elimina a via mais comum de roubo de credencial por XSS. Um
token no `localStorage` é legível por qualquer script que execute na página.
O requisito pede explicitamente que tokens sensíveis não sejam guardados ali.

**Custo.** O cookie é enviado automaticamente pelo navegador, o que abre a
porta para CSRF — e por isso toda operação de escrita exige o token CSRF que o
Laravel entrega como cookie e o axios reenvia como cabeçalho. É uma troca:
some uma classe de ataque, entra outra, e a segunda tem defesa madura e
embutida no framework.

---

## 4. Autorização como middleware do controller

**Decisão.** Cada endpoint declara sua verificação de permissão numa lista de
middleware, e a regra vive na `CustomerPolicy`.

**Alternativas descartadas.** `Gate::authorize()` no corpo do método, e
`authorizeResource()` no construtor.

**Por quê.** A verificação no corpo do método chega tarde demais: o Form
Request é resolvido na injeção de dependência, ou seja, **valida antes** de o
corpo ser alcançado. Um usuário sem permissão receberia 422 com os erros de
validação antes de receber 403 — revelando as regras do formulário a quem não
pode usá-lo. Um teste cobre exatamente esse caso.

`authorizeResource()` não cobre inativar e reativar, que não têm equivalente
no mapa padrão de um resource; metade das verificações implícitas com a outra
metade explícita seria pior de auditar do que todas visíveis numa lista.

**Custo.** Seis linhas de declaração em vez de uma. Em troca, a resposta a
"quem pode fazer o quê" cabe numa tela.

---

## 5. Sem Service, sem Repository

**Decisão.** O controller fala diretamente com o Eloquent.

**Alternativa descartada.** `CustomerService` e `CustomerRepository`.

**Por quê.** Cadastrar um cliente é validar e gravar. Uma classe que apenas
repassa `Customer::create()` acrescenta um arquivo a ler sem acrescentar o que
entender, e um `CustomerRepository` sobre o Eloquent reimplementa, pior, o que
o Eloquent já é. O requisito proíbe ambos sem necessidade.

O que tem substância **está** isolado:

| Responsabilidade | Onde vive |
|---|---|
| Dígitos verificadores do CPF | `App\Support\Cpf` |
| Normalização de entrada mascarada | `App\Support\Digits` |
| Montagem da consulta de listagem | escopos do `Customer` |
| Validação e normalização | Form Requests |
| Autorização | `CustomerPolicy` |
| Contrato de saída | `CustomerResource` |

**Custo.** Se surgir regra de negócio com peso — fluxo de aprovação,
integração com terceiros, efeitos colaterais — ela precisará de um Service, e
ele terá de ser criado então. É a troca correta: a estrutura acompanha o
problema em vez de antecipá-lo.

---

## 6. `is_active`, não `SoftDeletes`

**Decisão.** Um booleano com índice representa a situação do cliente.

**Alternativa descartada.** O trait `SoftDeletes` do Laravel.

**Por quê.** Inativar não é apagar. O requisito pede reativação e visualização
dos inativos, o que faz da situação uma informação de domínio, exibida na
interface e filtrável. `SoftDeletes` esconderia os registros de toda consulta
por padrão, exigindo `withTrashed()` espalhado pelo código, e misturaria dois
conceitos: "este cliente não está mais ativo" e "este registro foi removido".

**Custo.** Não há exclusão real. É exatamente o que o requisito pede.

---

## 7. Busca insensível a acentuação resolvida no banco

**Decisão.** Uma função `IMMUTABLE` que fixa o dicionário do `unaccent`, e um
índice GIN com `pg_trgm` sobre a expressão normalizada.

**Alternativas descartadas.** Filtrar em PHP após carregar os registros; usar
`ILIKE`; manter uma coluna desnormalizada com o nome sem acento.

**Por quê.** Filtrar em PHP inviabiliza a paginação server-side — seria
preciso carregar tudo para contar. `ILIKE` resolve a caixa mas não o acento:
`jose` não encontraria `José`. Uma coluna desnormalizada funcionaria, mas
exige manter dois campos em sincronia a cada escrita, e esquecer essa
sincronia é uma classe de bug que o índice funcional simplesmente não tem.

O obstáculo técnico: `unaccent()` é declarada `STABLE`, não `IMMUTABLE`, e o
PostgreSQL só aceita funções `IMMUTABLE` em índices. O invólucro que fixa o
dicionário explicitamente torna o resultado determinístico e legitimamente
indexável.

**Medição**, com 50 mil clientes:

| Consulta | Resultado |
|---|---|
| Busca seletiva por nome, com o índice GIN | 0,56 ms |
| A mesma, sem o índice | 47,5 ms |

**Custo.** Amarra a aplicação ao PostgreSQL. Como o PostgreSQL é requisito do
desafio, o custo é teórico — e a suíte de testes roda contra ele justamente
para que esses recursos sejam exercitados de verdade.

---

## 8. Índice separado para busca por prefixo de CPF

**Decisão.** Um índice `bpchar_pattern_ops` sobre `cpf`, além do índice de
unicidade.

**Por quê.** Esta decisão corrige uma suposição errada. A primeira versão
afirmava que o índice da constraint de unicidade atenderia tanto a igualdade
quanto a busca por prefixo. A medição desmentiu: o PostgreSQL só usa um btree
comum para `LIKE 'prefixo%'` quando a collation é `C` ou `POSIX`, e a do banco
é `C.UTF-8` — que ordena como C mas não é reconhecida como tal para essa
otimização.

| Consulta | Resultado |
|---|---|
| `cpf = '00000012345'` | 0,080 ms — índice de unicidade |
| `cpf LIKE '000000123%'` sem o índice novo | 4,980 ms — varredura sequencial |
| `cpf LIKE '000000123%'` com o índice novo | 0,103 ms |

**Custo.** Um índice a mais para manter na escrita. Vale pelo fator 48 na
leitura.

**Lição.** A suposição estava num comentário que afirmava o contrário. O
comentário foi corrigido junto com o código — um comentário errado é pior do
que nenhum, porque é lido como verdade.

---

## 9. Testes contra PostgreSQL, não SQLite

**Decisão.** A suíte aponta para um PostgreSQL real.

**Alternativa descartada.** SQLite em memória, o padrão do Laravel.

**Por quê.** O schema depende de `immutable_unaccent`, do índice GIN com
`pg_trgm` e de cinco constraints `CHECK`. O SQLite ignoraria os três em
silêncio: os testes passariam sem exercitar justamente a parte do schema mais
sujeita a quebrar. Testar contra o banco de produção é o que dá sentido a
afirmar que a busca sem acento funciona.

**Custo.** A suíte precisa de um PostgreSQL disponível, inclusive no CI — onde
ele sobe como serviço do workflow. Demora alguns segundos a mais e vale cada
um deles.

---

## 10. Sem Pinia

**Decisão.** O estado compartilhado vive em `ref` de escopo de módulo, dentro
de composables.

**Alternativa descartada.** Pinia.

**Por quê.** Existem exatamente dois estados compartilhados: o usuário
autenticado e a fila de avisos. Uma store traz devtools, convenções e uma
camada de indireção cujo benefício não se paga em duas variáveis. O requisito
pede Pinia apenas se houver necessidade real.

**Custo.** Sem inspeção por devtools e sem padrão imposto. Se surgir um
terceiro estado compartilhado com lógica própria, a conta muda — e a migração
é localizada, porque o acesso já passa por composables.

---

## 11. Limite de requisições em duas camadas

**Decisão.** Laravel limita por usuário autenticado; Nginx limita por IP.

**Por quê.** São ameaças diferentes. O limite do Laravel protege contra
extração em massa: a autorização impede o perfil de leitura de *alterar*
clientes, mas nada o impediria de percorrer a base inteira e levar CPF,
telefone e endereço de todos.

O limite do Nginx cobre o que o Laravel não alcança. A lista de prioridade do
framework executa a autenticação antes do limitador — de propósito, para que a
contagem possa ser por usuário —, de modo que uma requisição sem sessão recebe
401 sem nunca passar pelo limitador. Barrar enxurrada anônima no Nginx custa
muito menos do que iniciar o PHP para depois recusar.

**Alternativa descartada.** Inverter a ordem de prioridade no Laravel.
Trocaria a contagem por usuário por contagem por IP — penalizando todos os
usuários atrás de um mesmo endereço — e contrariaria uma decisão deliberada do
framework.

---

## 12. Código na imagem, storage em volume

**Decisão.** A imagem Docker carrega o código; apenas `storage` é volume.

**Por quê.** Um volume nomeado só é inicializado a partir da imagem quando
está vazio. Montar o código em volume faria com que, depois da primeira
subida, ele mantivesse a versão antiga — e `docker compose up --build`
continuaria executando o código anterior. É um erro silencioso e difícil de
diagnosticar.

O `storage` precisa persistir porque guarda a chave de criptografia gerada na
primeira subida; perdê-la invalidaria todas as sessões abertas.

**Custo.** Alterar o código exige reconstruir a imagem. É o comportamento
correto para um ambiente que se reconstrói, e por isso o `opcache` roda com a
verificação de data desligada.

---

## 13. Seeders independentes do Faker

**Decisão.** O seeder de demonstração gera os dados a partir de listas
próprias e de `Cpf::random()`. O factory, que usa Faker, serve apenas aos
testes.

**Por quê.** O Faker é dependência de desenvolvimento e não existe numa imagem
construída com `--no-dev`. Um seeder que dependesse dele falharia exatamente
onde precisa funcionar — e falhou, na primeira subida dos containers, com
`Call to undefined function fake()`.

A separação também é correta por si: o factory é infraestrutura de teste,
enquanto os dados de demonstração são parte da entrega.

**Alternativa descartada.** Promover o Faker a dependência de produção.
Carregaria uma biblioteca de geração de dados falsos na imagem só para semear.

**Custo.** Alguma duplicação entre factory e seeder. Aceitável: eles servem a
propósitos diferentes e podem divergir sem prejuízo.

---

## 14. Trilha de auditoria sem valores

**Decisão.** A trilha registra quem alterou, quando, e os **nomes** dos
campos alterados — nunca os valores.

**Alternativa descartada.** Guardar valor anterior e novo de cada campo, como
faz a maioria das bibliotecas de auditoria.

**Por quê.** Uma trilha com valores duplicaria CPF, telefone e endereço numa
segunda tabela. Pior: inviabilizaria o direito à eliminação, porque apagar o
cliente deixaria os dados dele vivos no histórico — e um histórico que
preserva o que o titular pediu para apagar é um problema jurídico, não uma
funcionalidade.

**Custo.** Não é possível reconstruir o estado anterior a partir da trilha.
Para o propósito de responsabilização — demonstrar que fulano alterou o
telefone deste cliente naquele momento — o nome do campo basta.

A trilha é alimentada por um Observer do Eloquent, e não por chamadas no
controller: auditoria esquecida é pior que auditoria ausente, porque dá a
impressão de registro completo onde há lacunas. Ligada ao ciclo de vida do
model, nenhum caminho escapa — nem seeder, nem comando de console, nem um
endpoint futuro que alguém acrescente sem lembrar dela.

---

## 15. Eliminação definitiva separada da inativação

**Decisão.** Existe um `DELETE`, mas só alcança cliente já inativo, exige
perfil de administrador e é registrado na trilha.

**Tensão com o requisito.** O desafio diz que a inativação não deve apagar o
registro — e ela não apaga. Esta é outra operação, com outro propósito:
quando o titular exerce o direito de eliminação previsto na LGPD, inativar
não basta, porque CPF, telefone e endereço continuariam armazenados.

**Por que em dois passos.** Exigir inativação prévia impede apagar cadastro em
uso por um clique errado, e dá oportunidade de reverter. É também a razão de
a permissão `forceDelete` existir separada de `update`: alterar cadastro e
apagá-lo definitivamente não deveriam andar juntas.

**O que sobrevive.** A trilha de auditoria permanece, com o evento de
eliminação. Como ela não guarda valores, o que resta é a prova de que um
registro existiu e foi eliminado — sem o dado pessoal que a eliminação
deveria remover.

---

## 16. Política de segurança de conteúdo restritiva

**Decisão.** `script-src 'self'`, sem nenhuma exceção. `style-src` permite
estilo embutido.

**Por quê.** O build do Vite não gera script nem estilo embutido no HTML, e a
aplicação não carrega nada de CDN — o que permitiu escrever uma política
restritiva de verdade. A única exceção é para estilo, e foi **medida, não
suposta**: com `style-src 'self'` o navegador bloqueia os atributos `style`
que o Vuetify usa para posicionar overlays, menus e transições, e o layout
quebra.

**Por que a exceção é aceitável.** Estilo embutido é bem menos perigoso que
script embutido: a exfiltração de dado por CSS depende de carregar recurso
externo, e `img-src 'self' data:` já bloqueia esse caminho. A exceção que
realmente importaria — `script-src 'unsafe-inline'` — não foi aberta.

**Verificação.** O fluxo completo foi exercitado sob a política, com o
console coletando violações: zero.

---

## 17. Testes end-to-end contra o ambiente Docker

**Decisão.** O Playwright aponta para `http://localhost:8080`, o ambiente
Docker, e não para o servidor de desenvolvimento.

**Por quê.** É o ambiente mais próximo do que será avaliado: Nginx no lugar do
proxy do Vite, `APP_ENV` de produção, caches de configuração e rota ligados,
e opcache sem verificação de data. Nenhuma dessas quatro condições existe no
desenvolvimento — e os defeitos deste projeto se concentraram justamente na
junção entre as peças.

**Custo.** Os testes exigem a aplicação no ar, e por isso ficam fora do
`npm run check`. Um comando de verificação que falha por ambiente externo
ensina a ignorar falhas.

**Sem repetição automática.** `retries: 0`: um teste que só passa na segunda
tentativa está escondendo instabilidade, não contornando-a.

---

## Fluxo de uma requisição

```text
Navegador
   │  cookie de sessão + X-XSRF-TOKEN
   ▼
Nginx ───── /           → SPA compilado (arquivos estáticos)
       └─── /api, /sanctum, /up → PHP-FPM
   │        limit_req por IP
   ▼
Laravel
   │  EnsureFrontendRequestsAreStateful  → ativa sessão e CSRF
   │  Authenticate:sanctum               → resolve o usuário
   │  ThrottleRequests                   → limite por usuário
   │  Authorize (Policy)                 → permissão do perfil
   │  Form Request                       → normaliza e valida
   │  Controller                         → Eloquent
   │  Resource                           → contrato de saída
   ▼
PostgreSQL
      constraints CHECK · unicidade · índices GIN e btree
```

A ordem importa: a autorização precede a validação, e a validação precede a
persistência. Cada camada rejeita o que a seguinte não deveria precisar
tratar.
