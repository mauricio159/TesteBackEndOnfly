# Pedidos de Viagem Corporativa

Microsserviço em **Laravel 13** para gerenciar pedidos de viagem corporativa. Permite:

- criar, consultar e listar pedidos, com filtros por status, destino e período;
- aprovar ou cancelar pedidos (só administrador);
- notificar o solicitante sempre que o pedido é aprovado ou cancelado.

Além do que o desafio pede, o projeto traz:

- **auditoria da decisão:** registra quem aprovou ou cancelou cada pedido, e quando;
- **gestão da equipe:** o administrador cadastra colaboradores e outros administradores (não há cadastro público), e um administrador aprova as viagens do outro;
- **pedidos atrasados destacados**, com o tempo de espera, e **lembrete automático** aos administradores;
- um **front-end em Vue** para usar o sistema pelo navegador;
- uma **coleção do Postman** pronta para importar.

| | |
|---|---|
| Linguagem / framework | PHP 8.4 · Laravel 13 |
| Banco de dados | MySQL 8.4 |
| Autenticação | Laravel Sanctum (token Bearer) |
| Fila / notificações | Fila `database` + worker dedicado · Mailpit (caixa de e-mail de desenvolvimento) |
| Tarefas agendadas | Scheduler do Laravel em container próprio (lembrete de hora em hora) |
| Testes | PHPUnit (77 testes) · coleção Postman/Newman (78 verificações) |
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
| `diretor@viagens.test` | Diego Ramos, **administrador** (pode aprovar as viagens da Carla) |
| `ana@viagens.test` | Ana Souza, colaboradora |
| `bruno@viagens.test` | Bruno Lima, colaborador |

Cada colaborador começa com 5 pedidos em todos os status. Um deles está parado há dias e aparece destacado como **atrasado**. A Carla tem uma viagem para Brasília esperando decisão: ela não pode aprovar a própria, mas o Diego pode.

Os lembretes de pedidos atrasados são gerados de hora em hora. Para gerar na hora, durante a demonstração:

```bash
docker compose exec app php artisan pedidos:lembrar-pendentes
```

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
| GET | `/notificacoes` | Avisos recebidos pelo usuário logado (decisões e lembretes) |
| GET | `/usuarios` | Só administrador: lista a equipe (filtro opcional `?perfil=colaborador` ou `administrador`) |
| POST | `/usuarios` | Só administrador: cadastra um colaborador ou, com `"administrador": true`, outro administrador (`nome`, `email`, `senha`, `senha_confirmacao`) |

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
    "decisao": null,
    "tempo_aguardando": "há 1 segundo",
    "atrasado": false,
    "criado_em": "2026-11-07T16:51:42-03:00",
    "atualizado_em": "2026-11-07T16:51:42-03:00"
  }
}
```

| Campo | Significado |
|---|---|
| `decisao` | `null` enquanto aguarda. Depois de aprovado/cancelado: `{"por": {"id": 1, "nome": "Carla Mendes"}, "em": "2026-11-07T17:39:00-03:00"}` |
| `tempo_aguardando` | Há quanto tempo espera decisão ("há 3 dias e 5 horas"); `null` se já foi decidido |
| `atrasado` | `true` quando está "solicitado" há mais de `PEDIDO_ALERTA_APOS_HORAS` (padrão 48h) |

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
| `atrasados=1` | Só pedidos parados além do prazo, esperando decisão |
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
- Ao aprovar ou cancelar, o solicitante é avisado por **e-mail** e por uma notificação no banco (consultável em `GET /notificacoes` e na tela de Notificações do front). O aviso informa **quem decidiu**.
- Toda decisão grava **quem** aprovou/cancelou e **quando** (`decidido_por_id`, `decidido_em`). Uma tentativa recusada (ex.: cancelar um aprovado) não altera esse registro.
- **Não existe cadastro público:** só o **administrador** cadastra pessoas, sejam colaboradores ou outros administradores, pela tela Equipe ou por `POST /usuarios`. A pessoa cadastrada entra com a senha inicial definida pelo administrador. Com dois ou mais administradores, um aprova as viagens do outro.
- Pedido **solicitado há mais de 48 horas** é considerado **atrasado**: aparece destacado no front, entra no filtro `atrasados=1` e gera um **lembrete** para os administradores. O solicitante não recebe o lembrete do próprio pedido, porque não pode aprová-lo. O lembrete se repete a cada 24h enquanto ninguém decidir. Os dois prazos são configuráveis no `.env` (`PEDIDO_ALERTA_APOS_HORAS` e `PEDIDO_REPETIR_LEMBRETE_APOS_HORAS`).

---

## 4. Como o código está organizado

```
app/
├── Console/Commands/LembrarPedidosPendentes.php  # lembrete de pedidos parados (agendado)
├── Enums/StatusPedido.php                 # os 3 status + quais mudanças são permitidas
├── Exceptions/MudancaDeStatusInvalida.php # "não pode cancelar pedido aprovado" etc.
├── Http/
│   ├── Controllers/Api/V1/                # controllers enxutos, sem regra de negócio
│   ├── Middleware/ForcarRespostaJson.php
│   ├── Requests/                          # validação (e autorização) da entrada
│   └── Resources/                         # formato do JSON de saída
├── Models/                                # PedidoViagem (filtros, atraso), Usuario
├── Notifications/                         # StatusDoPedidoAlterado, LembretePedidoPendente
├── Policies/                              # PedidoViagemPolicy, UsuarioPolicy
└── Services/PedidoViagemService.php       # casos de uso (transação, trava, notificação)

config/pedidos.php  # prazos de atraso e de repetição do lembrete
routes/console.php  # agenda do lembrete (de hora em hora)
frontend/      # Vue 3 (extra)
postman/       # coleção pronta para importar
lang/pt_BR/    # mensagens de validação em português
```

### Banco de dados

| Tabela | Para quê |
|---|---|
| `usuarios` | Colaboradores e administradores (`nome`, `email`, `senha`, `administrador`) |
| `pedidos_viagem` | Os pedidos (`usuario_id`, `nome_solicitante`, `destino`, `data_ida`, `data_volta`, `status`), a decisão (`decidido_por_id`, `decidido_em`) e o controle de lembrete (`ultimo_lembrete_em`) |
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
| **Limite de requisições** | 60 por minuto por usuário na API; 10 por minuto por e-mail+IP no login, contra força bruta. |
| **Testes no MySQL**, não no SQLite | O mesmo banco de produção garante o mesmo comportamento de `LIKE`, datas e travas. |
| **Fuso `America/Sao_Paulo`** | Em UTC, às 22h já é "amanhã", e a validação "ida não pode ser no passado" erraria para o usuário brasileiro. |
| **Containers separados** | Nginx (HTTP), PHP-FPM (aplicação), worker (fila), agendador e MySQL reiniciam e escalam de forma independente. Health checks garantem a ordem de subida. |
| **Decisão em duas colunas** (`decidido_por_id`, `decidido_em`), sem tabela de histórico | Aprovado e cancelado são status finais, então cada pedido tem no máximo uma decisão. Se um dia o pedido puder voltar de status, aí sim vale uma tabela de histórico. |
| **Migration nova** para a auditoria, em vez de editar a antiga | É assim que se evolui um banco em produção: quem já tem dados roda só a mudança. |
| **"Atrasado" calculado na hora**, não gravado | Depende do relógio. Uma coluna "atrasado" ficaria desatualizada; calcular a partir de `criado_em` está sempre certo e usa o índice existente. |
| **Lembrete por comando agendado** + container `scheduler` | É o jeito do Laravel para tarefas periódicas (equivale ao cron). O comando também pode ser rodado na mão para demonstração. |
| **Lembrete só no canal database** e no máximo 1 a cada 24h por pedido | Com vários pedidos parados, um e-mail por pedido viraria spam. `ultimo_lembrete_em` garante que não se repita a cada hora. |
| **Sem cadastro público; o administrador cadastra a equipe** | Viagem corporativa envolve dinheiro da empresa, então quem decide quem tem acesso é a empresa, não quem chega de fora. Também elimina o risco de alguém se autopromover a administrador. O primeiro administrador vem do seeder (ou é criado direto no banco). |
| **`vendor` num volume do Docker** | No Windows, ler os ~7 mil arquivos do `vendor` da pasta montada deixava cada requisição com ~3 s. No volume, cai para ~40 ms. |

---

## 5. Front-end (extra)

Uma interface simples em **Vue 3** que consome a API, para demonstrar o fluxo completo:

- login com botões de acesso rápido para os usuários de demonstração;
- lista de pedidos com todos os filtros e paginação. O administrador vê os pedidos de todos e tem os botões **Aprovar** e **Cancelar**, com confirmação na própria linha;
- **pedidos atrasados destacados** (linha laranja, "⚠ aguardando há 3 dias e 5 h", tempo atualizado sozinho), aviso no topo com o total e atalho "Ver atrasados";
- coluna **"Decidido por"** e, no detalhe, "Aprovado por / Aprovado em";
- formulário de novo pedido, mostrando os erros de validação da API embaixo de cada campo;
- tela de notificações, com decisões e **⏰ lembretes**;
- tela **Equipe** (só para admins): lista colaboradores e administradores e cadastra novas pessoas, escolhendo o perfil.

O Vite repassa as chamadas `/api` para o Laravel (proxy), então não é preciso configurar CORS. Esconder botões no front é só conforto visual: quem garante as regras é o back-end.

Código em `frontend/src`: `api.js` (única porta de conversa com a API), `sessao.js` (token do usuário logado), `paginas/` e `componentes/`.

---

## 6. Testes

**77 testes / 250 verificações (PHPUnit):**

- **Unit:** as 9 combinações de mudança de status, usando *data provider*.
- **Feature (HTTP):**
  - autenticação;
  - criação: validações, datas e tentativa de forjar solicitante ou status;
  - consulta: dono, administrador, outra pessoa e 404;
  - listagem: isolamento por usuário, cada filtro, filtros combinados, paginação e filtros inválidos;
  - mudança de status: aprovar, cancelar, aprovado→cancelado bloqueado, permissões, autoaprovação bloqueada e 403 antes de 422;
  - notificação: destinatário, canais, conteúdo, fila e nenhum envio quando a operação falha;
  - teste ponta a ponta da notificação chegando em `GET /notificacoes`;
  - auditoria: quem e quando ficam gravados, aparecem na consulta/listagem e não são sobrescritos por tentativa recusada;
  - equipe: administrador cadastra colaborador e administrador, a pessoa cadastrada entra e pede viagem, colaborador não cadastra ninguém (403), validação, listagem/filtro por perfil, cadastro público inexistente (404) e o novo admin aprovando a viagem de quem o cadastrou;
  - atrasados: marcação, texto do tempo de espera, limite configurável, pedido decidido nunca atrasado e filtro `atrasados=1`;
  - lembretes: quem recebe, o solicitante não recebe o do próprio pedido, sem repetição antes de 24h e agendamento de hora em hora.

**Coleção Postman:** 36 requisições e 78 verificações, que também rodam pelo Newman (veja `postman/README.md`).

---

## 7. Comandos úteis

```bash
docker compose logs -f app queue scheduler frontend # acompanhar logs
docker compose exec app php artisan pedidos:lembrar-pendentes   # gerar os lembretes agora
docker compose exec app php artisan schedule:list   # ver as tarefas agendadas
docker compose exec app php artisan migrate:fresh --seed   # zerar o banco e popular de novo
docker compose exec app php artisan queue:restart   # depois de mudar código (o worker guarda o código em memória)
docker compose exec app ./vendor/bin/pint           # padronizar o estilo do código
docker compose down -v                              # derrubar tudo e apagar os volumes (banco e dependências)
```
