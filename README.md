# Pedidos de Viagem Corporativa

Microsserviço em **Laravel 13** para gerenciar pedidos de viagem corporativa. Permite:

- criar, consultar e listar pedidos, com filtros por status, destino e período;
- aprovar ou cancelar pedidos (só administrador);
- notificar o solicitante sempre que o pedido é aprovado ou cancelado.

Além do que o desafio pede, o projeto traz:

- um **front-end em Vue** para usar o sistema pelo navegador;
- uma **coleção do Postman** pronta para importar.

| | |
|---|---|
| Linguagem / framework | PHP 8.4 · Laravel 13 |
| Banco de dados | MySQL 8.4 |
| Autenticação | Laravel Sanctum (token Bearer) |
| Fila / notificações | Fila `database` + worker dedicado · Mailpit (caixa de e-mail de desenvolvimento) |
| Testes | PHPUnit (56 testes) · coleção Postman/Newman (55 verificações) |
| Front-end (extra) | Vue 3 + Vite |
| Ambiente | Docker Compose |

---

## 1. Como rodar

**Pré-requisito:** só o [Docker Desktop](https://docs.docker.com/get-docker/). PHP, Composer e Node **não** precisam estar instalados na máquina.

```bash
git clone <url-do-repositorio> travel-orders-service
cd travel-orders-service
docker compose up -d --build
docker compose exec app php artisan db:seed
```

Na primeira subida, o container `app` faz sozinho:

1. instala as dependências (`composer install`);
2. cria o `.env` a partir do `.env.example` e gera a `APP_KEY`;
3. roda as migrations.

O container `frontend` instala as dependências do Vue. A primeira subida leva alguns minutos; as seguintes são rápidas.

| O quê | Onde |
|---|---|
| **Front-end** | http://localhost:5173 |
| API | http://localhost:8000/api/v1 |
| Health check | http://localhost:8000/up |
| E-mails enviados (Mailpit) | http://localhost:8025 |
| MySQL (DBeaver, Workbench…) | `localhost:3307` · banco `viagens_corporativas` · usuário `viagens` · senha `segredo` |

### Usuários de demonstração

Todos usam a senha `senha123`. Na tela de login do front há botões de acesso rápido.

| E-mail | Quem é |
|---|---|
| `admin@viagens.test` | Carla Mendes, **administradora** (aprova e cancela) |
| `ana@viagens.test` | Ana Souza, colaboradora |
| `bruno@viagens.test` | Bruno Lima, colaborador |

Cada colaborador começa com 5 pedidos: 3 solicitados, 1 aprovado e 1 cancelado.

### Testes

```bash
docker compose exec app php artisan test
```

Os testes usam um banco separado (`viagens_corporativas_teste`, criado pelo script em `docker/mysql/init`). Rodar os testes nunca apaga os dados da demonstração.

### Postman

Importe `postman/pedidos-viagem.postman_collection.json` e clique em **Run collection**. O guia completo está em [postman/README.md](postman/README.md).

---

## 2. API

Base: `http://localhost:8000/api/v1`. Nas rotas protegidas, envie `Authorization: Bearer <token>`. Todas as respostas são JSON, e os campos e mensagens estão em português.

### Autenticação

| Método | Rota | O que faz |
|---|---|---|
| POST | `/auth/cadastro` | Cria um colaborador e devolve o token (`nome`, `email`, `senha`, `senha_confirmacao`) |
| POST | `/auth/login` | Devolve o token (`email`, `senha`) |
| POST | `/auth/logout` | Revoga o token atual |
| GET | `/auth/perfil` | Dados do usuário logado |

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ana@viagens.test","senha":"senha123"}'
```

### Pedidos de viagem

| Método | Rota | Quem pode |
|---|---|---|
| POST | `/pedidos-viagem` | Qualquer usuário logado |
| GET | `/pedidos-viagem` | Colaborador vê os seus; administrador vê todos |
| GET | `/pedidos-viagem/{id}` | Dono do pedido ou administrador |
| PATCH | `/pedidos-viagem/{id}/status` | Só administrador, e nunca no próprio pedido |
| GET | `/notificacoes` | Avisos recebidos pelo usuário logado |

**Criar pedido**

```bash
curl -X POST http://localhost:8000/api/v1/pedidos-viagem \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"destino":"Lisboa, Portugal","data_ida":"2026-12-10","data_volta":"2026-12-15"}'
```

```json
{
  "data": {
    "id": "01a1b2ea-e9ce-721d-a8db-cda6efe9e993",
    "solicitante": { "id": 2, "nome": "Ana Souza" },
    "destino": "Lisboa, Portugal",
    "data_ida": "2026-12-10",
    "data_volta": "2026-12-15",
    "status": "solicitado",
    "status_rotulo": "Solicitado",
    "criado_em": "2026-11-07T16:51:42-03:00",
    "atualizado_em": "2026-11-07T16:51:42-03:00"
  }
}
```

**Aprovar ou cancelar** (administrador)

```bash
curl -X PATCH http://localhost:8000/api/v1/pedidos-viagem/{id}/status \
  -H "Authorization: Bearer $TOKEN_ADMIN" -H "Content-Type: application/json" \
  -d '{"status":"aprovado"}'   # ou "cancelado"
```

**Filtros da listagem.** Todos são opcionais e podem ser combinados. As datas vão no formato `AAAA-MM-DD`.

| Parâmetro | Efeito |
|---|---|
| `status` | `solicitado`, `aprovado` ou `cancelado` |
| `destino` | Busca parcial, sem diferenciar maiúsculas (`?destino=lisboa`) |
| `criado_de` / `criado_ate` | Pedidos **feitos** dentro do período |
| `viagem_de` / `viagem_ate` | Viagens que acontecem **inteiras** dentro do período (ida ≥ início e volta ≤ fim) |
| `por_pagina` / `pagina` | Paginação (padrão 15, máximo 100) |

```
GET /pedidos-viagem?status=aprovado&destino=lisboa&viagem_de=2026-12-01&viagem_ate=2026-12-31
```

### Códigos de resposta

| Código | Quando |
|---|---|
| 200 / 201 / 204 | Deu certo |
| 401 | Sem token, token inválido ou e-mail/senha errados |
| 403 | Sem permissão (ex.: solicitante tentando aprovar o próprio pedido) |
| 404 | Pedido não encontrado |
| 422 | Dado inválido **ou** regra de negócio violada (ex.: cancelar pedido aprovado) |
| 429 | Muitas requisições em pouco tempo |

```json
{
  "message": "Este pedido já foi aprovado e, por isso, não pode mais ser cancelado.",
  "status_atual": "aprovado",
  "status_solicitado": "cancelado"
}
```

---

## 3. Regras de negócio

```
solicitado ──► aprovado    (fim da linha)
     │
     └──────► cancelado   (fim da linha)
```

- Todo pedido nasce como `solicitado`.
- Só **administradores** mudam o status. Um administrador **não** muda o status do **próprio** pedido, para ninguém aprovar a própria viagem.
- Pedido **aprovado não pode ser cancelado**, e pedido cancelado não pode ser aprovado.
- O nome do solicitante vem do **usuário logado**, nunca do corpo da requisição. Se o JSON trouxer `status`, `usuario_id` ou `nome_solicitante`, esses campos são ignorados.
- A data de ida não pode estar no passado, e a volta não pode ser antes da ida. "Hoje" segue o horário de Brasília.
- Ao aprovar ou cancelar, o solicitante é avisado por **e-mail** e por uma notificação no banco (consultável em `GET /notificacoes` e na tela de Notificações do front).

---

## 4. Como o código está organizado

```
app/
├── Enums/StatusPedido.php                 # os 3 status + quais mudanças são permitidas
├── Exceptions/MudancaDeStatusInvalida.php # "não pode cancelar pedido aprovado" etc.
├── Http/
│   ├── Controllers/Api/V1/                # controllers enxutos, sem regra de negócio
│   ├── Middleware/ForcarRespostaJson.php
│   ├── Requests/                          # validação (e autorização) da entrada
│   └── Resources/                         # formato do JSON de saída
├── Models/                                # PedidoViagem (filtros), Usuario
├── Notifications/StatusDoPedidoAlterado.php
├── Policies/PedidoViagemPolicy.php        # quem pode ver/aprovar/cancelar
└── Services/PedidoViagemService.php       # casos de uso (transação, trava, notificação)

frontend/      # Vue 3 (extra)
postman/       # coleção pronta para importar
lang/pt_BR/    # mensagens de validação em português
```

### Banco de dados

| Tabela | Para quê |
|---|---|
| `usuarios` | Colaboradores e administradores (`nome`, `email`, `senha`, `administrador`) |
| `pedidos_viagem` | Os pedidos (`usuario_id`, `nome_solicitante`, `destino`, `data_ida`, `data_volta`, `status`) |
| `notifications`, `jobs`, `cache`, `personal_access_tokens` | Tabelas internas do Laravel e do Sanctum |

Tudo o que é do **negócio** está em português: tabelas, colunas, classes, rotas, JSON e mensagens. As tabelas de **infraestrutura** do framework mantêm o nome padrão em inglês. O próprio Laravel e os pacotes dependem desses nomes, e renomeá-los só traria risco.

Algumas palavras continuam em inglês porque são convenções do framework: `index`/`store`/`show` nos controllers, `toArray`, `rules`, `authorize`, além das chaves `message`, `errors`, `data`, `links` e `meta` das respostas. Quem conhece Laravel reconhece esses nomes na hora.

### Decisões e porquês

| Decisão | Motivo |
|---|---|
| **Regra de status no Enum e no Model**, não no controller | Vale para qualquer porta de entrada (API, comando, job) e é testável sem HTTP. |
| **Service** para os casos de uso | Concentra transação, trava e notificação; o controller só traduz HTTP. |
| **Policy** para autorização | Separa "quem pode" de "o que é válido". A autorização roda **antes** da validação, então quem não tem permissão recebe 403. |
| **Exceção de domínio** virando 422 no `bootstrap/app.php` | O domínio não conhece HTTP. A exceção não vai para o log de erros, porque é um "não" esperado e não um bug. |
| **`SELECT … FOR UPDATE`** ao mudar status | Se dois administradores aprovarem e cancelarem ao mesmo tempo, o segundo vê o status já alterado e é recusado. |
| **Notificação após o commit e enfileirada** | O colaborador nunca recebe aviso de algo que foi desfeito, e um SMTP lento não deixa a API lenta (até 3 tentativas). |
| **UUID v7** como ID do pedido | Não expõe quantos pedidos existem, não dá para "chutar" IDs e ainda é ordenável no tempo (bom para índice). |
| **`status` como texto + Enum do PHP** (em vez de `ENUM` do MySQL) | Criar um status novo não exige `ALTER TABLE`. |
| **API Resources** | Separam o JSON público da estrutura da tabela. |
| **Versionamento `/api/v1`** | Permite evoluir a API sem quebrar quem já a consome. |
| **Sanctum** | Pacote oficial do Laravel. Tokens guardados como hash e **revogáveis** no logout. |
| **Limite de requisições** | 60 por minuto por usuário na API; 10 por minuto por e-mail+IP no login/cadastro, contra força bruta. |
| **Testes no MySQL**, não no SQLite | O mesmo banco de produção garante o mesmo comportamento de `LIKE`, datas e travas. |
| **Fuso `America/Sao_Paulo`** | Em UTC, às 22h já é "amanhã", e a validação "ida não pode ser no passado" erraria para o usuário brasileiro. |
| **Containers separados** | Nginx (HTTP), PHP-FPM (aplicação), worker (fila) e MySQL reiniciam e escalam de forma independente. Health checks garantem a ordem de subida. |
| **`vendor` num volume do Docker** | No Windows, ler os ~7 mil arquivos do `vendor` da pasta montada deixava cada requisição com ~3 s. No volume, cai para ~40 ms. |

---

## 5. Front-end (extra)

Uma interface simples em **Vue 3** que consome a API, para demonstrar o fluxo completo:

- login com botões de acesso rápido para os usuários de demonstração;
- lista de pedidos com todos os filtros e paginação. O administrador vê os pedidos de todos e tem os botões **Aprovar** e **Cancelar**;
- formulário de novo pedido, mostrando os erros de validação da API embaixo de cada campo;
- página de detalhe do pedido;
- tela de notificações.

O Vite repassa as chamadas `/api` para o Laravel (proxy), então não é preciso configurar CORS. Esconder botões no front é só conforto visual: quem garante as regras é o back-end.

Código em `frontend/src`: `api.js` (única porta de conversa com a API), `sessao.js` (token do usuário logado), `paginas/` e `componentes/`.

---

## 6. Testes

**56 testes / 175 verificações (PHPUnit):**

- **Unit:** as 9 combinações de mudança de status, usando *data provider*.
- **Feature (HTTP):**
  - autenticação;
  - criação: validações, datas e tentativa de forjar solicitante ou status;
  - consulta: dono, administrador, outra pessoa e 404;
  - listagem: isolamento por usuário, cada filtro, filtros combinados, paginação e filtros inválidos;
  - mudança de status: aprovar, cancelar, aprovado→cancelado bloqueado, permissões, autoaprovação bloqueada e 403 antes de 422;
  - notificação: destinatário, canais, conteúdo, fila e nenhum envio quando a operação falha;
  - teste ponta a ponta da notificação chegando em `GET /notificacoes`.

**Coleção Postman:** 25 requisições e 55 verificações, que também rodam pelo Newman (veja `postman/README.md`).

---

## 7. Comandos úteis

```bash
docker compose logs -f app queue frontend           # acompanhar logs
docker compose exec app php artisan migrate:fresh --seed   # zerar o banco e popular de novo
docker compose exec app php artisan queue:restart   # depois de mudar código (o worker guarda o código em memória)
docker compose exec app ./vendor/bin/pint           # padronizar o estilo do código
docker compose down -v                              # derrubar tudo e apagar os volumes (banco e dependências)
```
