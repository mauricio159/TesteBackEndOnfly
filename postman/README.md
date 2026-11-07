# Coleção do Postman

Um único arquivo, pronto para importar: **`pedidos-viagem.postman_collection.json`**.

## Como usar (2 minutos)

1. Suba o projeto e popule o banco (veja o README principal):
   ```bash
   docker compose up -d --build
   docker compose exec app php artisan db:seed
   ```
2. No Postman: **Import** → arraste o arquivo `pedidos-viagem.postman_collection.json`.
3. Escolha uma das opções:
   - **Rodar tudo de uma vez:** clique com o botão direito na coleção → **Run collection** → **Run**. São 36 requisições com 78 verificações automáticas, executadas em ordem.
   - **Ir passo a passo:** abra as pastas e envie as requisições **de cima para baixo**. Comece sempre pelos três logins da pasta *1. Autenticação*.

Não é preciso configurar ambiente, copiar token nem colar ID: os scripts fazem isso sozinhos.

## O que tem em cada pasta

| Pasta | O que demonstra |
|---|---|
| 1. Autenticação | Login dos 3 usuários do seeder, prova de que não existe cadastro público (404), senha errada (401) e perfil |
| 2. Pedidos de viagem | Criar pedido, validações (422), consultar por ID e listar com cada filtro do desafio: status, destino, período da viagem, período de criação, filtros combinados e paginação |
| 3. Aprovação e cancelamento | Solicitante tentando aprovar o próprio pedido (403), colega tentando ver pedido alheio (403), admin aprovando (200, com **quem e quando** registrados), **cancelar pedido aprovado bloqueado (422)** e cancelamento de pedido ainda solicitado (200) |
| 4. Notificações | A Ana encontra o aviso de aprovação em `GET /notificacoes` |
| 5. Equipe e pedidos atrasados | Carla cadastra uma colaboradora (que já consegue entrar) e um novo administrador, colaboradora não consegue cadastrar ninguém (403), listagem da equipe, a Carla não aprova a própria viagem (403), mas o **novo administrador aprova**; listagem só dos **atrasados** e lembretes |
| 6. Encerrar sessão | Logout (204) e prova de que o token deixou de valer (401) |

## Como os scripts funcionam

O Postman roda JavaScript antes (**Pre-request**) e depois (**Post-response / Tests**) de cada requisição. Para ver os scripts, abra a requisição e clique na aba **Scripts**. Todos estão comentados.

**1. Guardar o token depois do login** (*Post-response* dos logins):
```js
const resposta = pm.response.json();
pm.collectionVariables.set('token_ana', resposta.token);
```
As outras requisições usam `Authorization: Bearer {{token_ana}}` (aba *Authorization*). Por isso, depois do login, tudo funciona sem copiar e colar.

**2. Guardar o ID do pedido criado** (*Post-response* de "Criar pedido"):
```js
pm.collectionVariables.set('pedido_id', pm.response.json().data.id);
```
Consultar, aprovar e cancelar usam `{{pedido_id}}` na URL.

**3. Datas sempre no futuro** (*Pre-request* da coleção, roda antes de tudo):
```js
pm.collectionVariables.set('data_ida', daquiA(30));   // hoje + 30 dias
pm.collectionVariables.set('data_volta', daquiA(35)); // hoje + 35 dias
```
A API recusa data de ida no passado. Calculando a data na hora, a coleção funciona em qualquer dia.

**4. Verificações automáticas** (*Post-response*):
```js
pm.test('Recusado pela regra de negócio (422)', () => pm.response.to.have.status(422));
```
Cada `pm.test` aparece como ✓ ou ✗ na aba **Test Results**. Elas confirmam que a API responde o que o desafio exige.

## Variáveis da coleção

Clique na coleção → aba **Variables**.

| Variável | Para que serve |
|---|---|
| `base_url` | Endereço da API (`http://localhost:8000/api/v1`). Mude aqui se usar outra porta. |
| `token_admin`, `token_ana`, `token_bruno` | Preenchidas pelos logins |
| `pedido_id`, `segundo_pedido_id`, `pedido_carla_id` | Preenchidas ao criar pedidos |
| `email_nova_colaboradora`, `email_novo_admin`, `token_novo_admin` | Preenchidas ao cadastrar as novas pessoas da equipe |
| `data_ida`, `data_volta` | Calculadas automaticamente |

## Rodar pela linha de comando (opcional)

Dá para executar a mesma coleção sem abrir o Postman, com o [Newman](https://github.com/postmanlabs/newman) via Docker:

```bash
docker run --rm --network pedidos-viagem_default -v "${PWD}/postman:/etc/newman" postman/newman:6-alpine run pedidos-viagem.postman_collection.json --env-var "base_url=http://nginx/api/v1"
```

> O login tem limite de 10 tentativas por minuto (proteção contra força bruta). Se rodar a coleção muitas vezes seguidas e receber **429**, espere um minuto.
