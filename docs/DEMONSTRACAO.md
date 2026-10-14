# Roteiro do vídeo demonstrativo

Roteiro para gravar a demonstração exigida na entrega. Cobre todos os
requisitos originais e os diferenciais, em uma sequência que não exige voltar
atrás.

**Duração estimada:** 6 a 8 minutos.

---

## Antes de gravar

```bash
cd nogle-crud

# Estado limpo, para que a demonstração comece do zero e os dados estejam
# previsíveis. CUIDADO: apaga o banco de demonstração.
docker compose down -v
docker compose up --build -d

# Aguarde a aplicação responder antes de começar a gravar.
until curl -sf http://localhost:8080/up >/dev/null; do sleep 2; done
echo "pronto"
```

Confira antes de iniciar:

- [ ] `docker compose ps` mostra os três serviços como `healthy`
- [ ] http://localhost:8080 abre a tela de login
- [ ] A janela do navegador está em tamanho de desktop
- [ ] Nenhuma aba com dados pessoais reais está visível
- [ ] O zoom do navegador está em 100%

---

## Roteiro

### 1 · Abertura — 30s

Mostre a tela de login.

> "Esta é a aplicação de gerenciamento de clientes do desafio da Nogle.
> Backend em Laravel 13, frontend em Vue 3 com Vuetify, banco PostgreSQL, e
> tudo rodando em Docker. O que está na tela foi subido com um único comando."

Mencione que a autenticação usa sessão com cookie, sem token no navegador.

---

### 2 · Autenticação e perfis — 45s

Entre com **`admin@nogle.test` / `senha123`**.

Aponte o cabeçalho: nome do usuário e perfil identificados.

> "O perfil fica visível junto do nome porque é ele que determina quais ações
> aparecem."

---

### 3 · Listagem — 45s

Mostre a tabela e aponte as quatro colunas exigidas pelo requisito: **nome,
CPF, telefone e endereço completo**, mais situação e ações.

Aponte o contador de registros e a paginação.

> "A paginação é server-side: o backend devolve uma página por vez, não a
> lista inteira. A ordenação é restrita a uma lista fechada de colunas — pedir
> ordenação por qualquer outra coisa é rejeitado pela API."

Clique num cabeçalho para ordenar e mostre a lista reordenando.

---

### 4 · Filtros — 1min

Este é o trecho que mais diferencia a entrega. Faça na ordem:

1. Digite **`silva`** no filtro de nome. Mostre o resultado filtrando.
2. Limpe e digite **`jose avila`**, sem acento nenhum.

> "Encontra 'José Ávila Gonçalves'. A busca ignora acentuação e maiúsculas, e
> isso é resolvido no banco, com uma função imutável e um índice GIN — não
> carregando os registros para filtrar na aplicação, o que inviabilizaria a
> paginação."

3. Limpe e digite **`529`** no filtro de CPF. Mostre o resultado.
4. Agora combine: **nome `maria`** e **CPF `111`** ao mesmo tempo.

> "Os filtros são combináveis, e combinam com E — não com OU."

5. Troque a situação para **Somente inativos** e mostre que há registros lá.

---

### 5 · Cadastro com consulta de CEP — 1min30

Clique em **Novo cliente**.

Preencha, nesta ordem:

| Campo | Valor |
|---|---|
| Nome | `Mariana Teixeira Nogueira` |
| CPF | `52998224725` — digite sem pontuação |
| Telefone | `11988887777` |
| CEP | `01310100` |

Pare no CEP e deixe a consulta acontecer.

> "Ao completar o CEP, a aplicação consulta o ViaCEP e preenche cidade,
> estado, bairro e logradouro. Os campos continuam editáveis — o aviso diz
> isso explicitamente, porque endereços novos às vezes precisam de ajuste."

Aponte os campos preenchidos: Avenida Paulista, Bela Vista, São Paulo, SP.

Preencha o **Número** (`1578`) e salve. Mostre o aviso de sucesso e o cliente
aparecendo na lista.

---

### 6 · Validações — 1min

Abra **Novo cliente** de novo e demonstre as validações:

1. Clique em **Salvar** com tudo vazio.

> "Cada campo acusa seu próprio erro, com mensagem específica. O complemento
> é o único campo opcional."

2. Digite **`111.111.111-11`** no CPF.

> "É uma sequência que satisfaz o cálculo dos dígitos verificadores, mas não é
> um CPF atribuível. A validação rejeita explicitamente."

3. Digite o CPF do cliente que acabou de cadastrar: **`52998224725`**.
4. Preencha o resto e tente salvar.

> "O CPF duplicado é barrado. E não é só na interface: o banco tem constraint
> de unicidade, porque entre a checagem e a gravação existe uma janela em que
> duas requisições simultâneas passariam as duas."

5. Digite um CEP inexistente, como **`99999999`**.

> "CEP inexistente é tratado e não bloqueia o cadastro — dá para preencher o
> endereço à mão."

6. Feche o modal com o **X**.

> "Como havia alterações, ele pede confirmação antes de descartar."

---

### 7 · Edição — 45s

Clique no lápis de um cliente.

> "O mesmo componente atende cadastro e edição. O título muda, os campos vêm
> preenchidos, e o CPF vem com máscara."

Altere o nome e salve, **sem tocar no CPF**.

> "Salvar mantendo o próprio CPF funciona. Parece óbvio, mas é onde a
> validação de unicidade costuma falhar: ela precisa ignorar o próprio
> registro."

---

### 8 · Inativação e reativação — 45s

Clique no ícone de inativar de um cliente.

> "A confirmação diz qual cliente será inativado, pelo nome. E deixa claro que
> o registro é preservado."

Confirme. Mostre que o cliente sumiu da listagem de ativos.

Troque o filtro de situação para **Somente inativos** e mostre o cliente lá,
agora com o botão de reativar. Reative e mostre que voltou.

> "Inativar não apaga. O registro continua no banco, com a situação alterada,
> e pode ser reativado."

---

### 9 · Controle de permissões — 1min

Saia e entre com **`leitura@nogle.test` / `senha123`**.

Aponte, na ordem:

- O cabeçalho agora mostra o perfil **Leitura**
- O aviso explicando a limitação
- O botão **Novo cliente** não existe
- Os ícones de ação estão desabilitados
- A listagem e os filtros continuam funcionando

Agora o ponto mais importante — abra o console do navegador (F12) e cole:

```js
const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1])
const r = await fetch('/api/customers', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf },
  body: JSON.stringify({ name: 'Tentativa Direta', cpf: '11144477735', phone: '11988887777',
    postal_code: '01001000', state: 'SP', city: 'São Paulo', neighborhood: 'Sé',
    street: 'Praça da Sé', number: '1' }),
})
console.log(r.status, await r.json())
```

> "Contornando a interface por completo, direto na API: 403. Esconder o botão
> é conveniência; a garantia está na Policy do backend."

---

### 10 · Responsividade — 45s

Abra as ferramentas de desenvolvedor e troque para visualização de celular
(375px ou similar).

> "Em telas pequenas a tabela vira cards. Uma tabela de seis colunas, com o
> endereço completo, não cabe em 375 pixels sem rolagem lateral — e rolar na
> horizontal para ler cada linha é a pior leitura possível num celular."

Mostre que:

- Não há rolagem horizontal
- Os filtros ficam empilhados
- As ações têm rótulo visível, não apenas ícone
- O modal continua utilizável

---

### 11 · Fechamento técnico — 45s

Volte ao desktop e mostre, no terminal:

```bash
cd backend && composer check
```

> "223 testes no backend, formatação verificada e análise estática em nível 8.
> No frontend são mais 209 testes. É o mesmo conjunto que o GitHub Actions
> executa a cada push."

Mostre rapidamente o repositório: `README.md`, `docs/ARCHITECTURE.md`,
`docs/AI_USAGE.md` e `database/schema.sql`.

> "O script SQL de criação das tabelas está no repositório, e há uma
> verificação automatizada que confere que ele produz exatamente o mesmo
> esquema das migrations."

Encerre mencionando o documento de uso de IA.

---

## Caso algo falhe durante a gravação

| Sintoma | Causa provável | Solução |
|---|---|---|
| Login devolve 419 | Cookie de CSRF ausente | Recarregue a página |
| Login devolve "muitas tentativas" | Limite de 5 tentativas atingido | Aguarde 1 minuto |
| CEP não preenche | ViaCEP indisponível ou sem rede | Preencha à mão — o comportamento está tratado e pode ser demonstrado como tal |
| Lista vazia | Seeders não executaram | `docker compose exec app php artisan db:seed --force` |
| Porta 8080 ocupada | Outro serviço | Ajuste `APP_PORT` no `.env` e suba de novo |

---

## Após gravar

- [ ] Publique o vídeo e acrescente o link no `README.md`
- [ ] Confirme que nenhum dado pessoal real apareceu na gravação
- [ ] Confirme que nenhuma credencial além das de demonstração apareceu
