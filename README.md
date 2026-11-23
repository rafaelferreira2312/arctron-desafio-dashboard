# Dashboard de compras

Serviço que recebe compras (pela API ou pelo WooCommerce), guarda no PostgreSQL e entrega busca com filtros e dados agregados para um dashboard em React.

| Parte | Tecnologia |
| --- | --- |
| Serviço | PHP 8.3, Symfony 7.3, Doctrine ORM 3 / DBAL 4 |
| Banco | PostgreSQL 16 |
| Fila | RabbitMQ 3.13 (Symfony Messenger) |
| Frontend | React 19, TypeScript, Vite, TanStack Query, Recharts |
| E-commerce | WooCommerce (WordPress 6) rodando no Docker |
| Testes | PHPUnit 11 (unitários e funcionais), Vitest + Testing Library |

## Como rodar

Pré-requisito: Docker com Docker Compose.

```bash
make up          # ou: docker compose up -d --build
make seed        # opcional: gera 200 compras fictícias para o dashboard ter volume
```

| O quê | Onde |
| --- | --- |
| Dashboard | http://localhost:5173 |
| API | http://localhost:8000 (`/health` para checar) |
| Loja WooCommerce | http://localhost:8080 — admin em `/wp-admin` com `admin` / `admin` |
| RabbitMQ | http://localhost:15672 — `guest` / `guest` |
| PostgreSQL | `localhost:5433` — `app` / `app`, banco `purchases` |

Na primeira subida o container `php` instala as dependências do Composer e roda as migrations, e o `woocommerce-setup` instala o WordPress e o WooCommerce. Isso leva alguns minutos. Para acompanhar:

```bash
make logs
```

Quando o setup terminar, os 3 pedidos de exemplo criados no WooCommerce já aparecem no dashboard, com a origem "WooCommerce".

## Arquitetura

```
                 POST /api/purchases                     GET /api/purchases
  Cliente da API ───────────────────┐        ┌────────── GET /api/statistics ◄── Frontend React
                                    ▼        │
                              ┌───────────────────┐
  WooCommerce ── webhook ───► │   API Symfony     │
  (order.created/updated)     │                   │
   assinado com HMAC          │  UI ─► Application│──► Command bus ─► RegisterPurchaseHandler ─► Domínio ─► PostgreSQL
                              │         ▲         │                                                        ▲
                              └─────────┼─────────┘                                                        │
                                        │ 202 Accepted                                                     │
                                        ▼                                                                  │
                                   RabbitMQ ──► worker (messenger:consume) ──► mesmo command bus ──────────┘
                                        │
                                        └─ falhou 3x ou dado inválido ─► transporte "failed" (tabela no Postgres)
```

O código do backend fica em `backend/src/Purchase`, separado em camadas:

```
Domain/          regras de negócio puras, sem framework
  Model/           Purchase, PurchaseItem, Customer, Product, Money, Cpf
  Repository/      interfaces dos repositórios
  Exception/
Application/     casos de uso
  Command/         RegisterPurchase + handler (escrita)
  Query/           filtros, período e interfaces de leitura
Infrastructure/  implementações concretas
  Doctrine/        repositórios com Doctrine ORM
  ReadModel/       busca e agregados em SQL (DBAL)
  WooCommerce/     assinatura do webhook, mapeamento do pedido, handler da fila
  Messenger/       command bus em cima do Symfony Messenger
UI/
  Http/            controllers, payloads de entrada, tratamento de erros
  Console/         comando de seed
```

O mapeamento do Doctrine está em XML (`backend/config/doctrine`), para as entidades do domínio não dependerem de atributos do ORM.

As decisões e o porquê de cada uma estão em [docs/DECISOES.md](docs/DECISOES.md).

## Regras de negócio

- **Referência única.** O segundo envio de uma compra com a mesma referência é ignorado: a API responde `200` com `"status": "ignored"` e os dados da compra original, sem alterar nada. Isso vale também para requisições simultâneas, porque quem decide no fim é a constraint `UNIQUE` do banco.
- **Cliente identificado pelo CPF.** O CPF é validado pelos dígitos verificadores. Se o cliente já existe, nome e e-mail são atualizados com o dado mais recente.
- **Produto identificado pelo nome**, sem diferenciar maiúsculas e espaços extras ("Mouse" e "  mouse " são o mesmo produto). Cada item guarda o nome e o preço do momento da compra.
- **Compra precisa de pelo menos um produto**, com quantidade maior que zero.
- **Desconto não pode ser maior que o total dos produtos.**
- **Valor pago não é recalculado.** O valor informado é o que o cliente pagou e pode incluir frete e taxas, então não é exigido que seja `produtos - desconto`.
- **Valores monetários não podem ser negativos** e são guardados em centavos (`BIGINT`), nunca em float.
- **Data sem fuso** é interpretada no fuso da operação (`APP_TIMEZONE`, padrão `America/Sao_Paulo`). Os filtros por data também usam esse fuso.
- **Do WooCommerce só entram pedidos `processing` ou `completed`.** Pedido pendente, cancelado ou com falha no pagamento não é compra.

## API

Todos os valores monetários são enviados e devolvidos como decimal com ponto (`"150.90"`). Erros seguem sempre o formato `{"error": "codigo", "message": "texto"}`.

### Registrar compra

```bash
curl -i -X POST http://localhost:8000/api/purchases \
  -H 'Content-Type: application/json' \
  -d '{
    "reference": "PED-1001",
    "amount": 299.80,
    "discount": 20.00,
    "date": "2026-11-20T14:30:00-03:00",
    "products": [
      { "name": "Teclado Mecânico", "quantity": 1, "unit_price": 219.90 },
      { "name": "Mouse", "quantity": 1, "unit_price": 99.90 }
    ],
    "customer": { "name": "Ana Silva", "email": "ana@example.com", "cpf": "529.982.247-25" },
    "notes": "Produto enviado para o cliente por SEDEX"
  }'
```

| Resposta | Quando |
| --- | --- |
| `201 Created` | Compra registrada. Header `Location` aponta para ela. |
| `200 OK` com `"status": "ignored"` | Referência já processada antes. |
| `422` `validation_failed` | Formato inválido (campo faltando, tipo errado). Lista os campos em `violations`. |
| `422` `invalid_purchase` | Regra de negócio violada (CPF inválido, desconto maior que os produtos etc). |
| `400` | JSON malformado. |

### Buscar compras

```bash
curl 'http://localhost:8000/api/purchases?customer_name=ana&date_from=2026-11-01&date_to=2026-11-30&amount_min=100'
```

| Parâmetro | Como filtra |
| --- | --- |
| `reference` | Contém o texto, sem diferenciar maiúsculas |
| `customer_name`, `customer_email` | Contém o texto, sem diferenciar maiúsculas |
| `customer_cpf` | Com ou sem máscara. CPF completo busca exato, parcial busca pelo começo |
| `date_from`, `date_to` | `AAAA-MM-DD`, os dois dias entram no intervalo |
| `amount_min`, `amount_max` | Valor pago, aceita `150.90` ou `150,90` |
| `page`, `per_page` | Paginação. Padrão 20 por página, máximo 100 |

Resposta: `{ "data": [...], "meta": { "page", "per_page", "total", "total_pages" } }`. Cada compra já vem com cliente e produtos.

Uma compra específica: `GET /api/purchases/{referencia}`.

### Dados agregados

```bash
curl 'http://localhost:8000/api/statistics?date_from=2026-11-01&date_to=2026-11-30&limit=10'
```

| Bloco | Conteúdo |
| --- | --- |
| `summary` | Quantidade de compras, clientes e itens; total e média de valor pago, de descontos e de produtos por compra |
| `by_customer` | Por cliente: quantidade, total e média das compras. Ordenado pelo total |
| `by_product` | Por produto: unidades, em quantas compras apareceu, total vendido, média por compra e preço unitário médio |
| `daily` | Quantidade e total vendido por dia (alimenta o gráfico) |

`date_from`, `date_to` e `limit` (tamanho dos rankings, padrão 10, máximo 50) são opcionais.

## Integração com o WooCommerce

O `docker compose up` sobe um WordPress com WooCommerce já configurado pelo script `woocommerce/setup.sh`:

1. Loja em reais, pagamento na entrega habilitado, 5 produtos e o cupom `black10`.
2. Um mu-plugin (`woocommerce/mu-plugins/arctron-dev.php`) adiciona o campo **CPF** no checkout, já que o WooCommerce não tem esse campo nativo.
3. Dois webhooks (`order.created` e `order.updated`) apontando para `http://api/webhooks/woocommerce`, assinados com o secret de `WOOCOMMERCE_WEBHOOK_SECRET`.
4. Três pedidos de exemplo, que disparam os webhooks e aparecem no dashboard.

O fluxo de um pedido:

1. O WooCommerce envia o pedido com o header `X-WC-Webhook-Signature` (HMAC-SHA256 do corpo em base64).
2. A API confere a assinatura. Se não bater, responde `401`.
3. Pedido válido vai para a fila no RabbitMQ e a API responde `202` na hora.
4. O worker consome a fila, converte o pedido (`OrderMapper`) e registra pelo mesmo caso de uso da API, com as mesmas regras.
5. Como o WooCommerce dispara `order.updated` várias vezes para o mesmo pedido, a referência `WC-{número}` garante que ele vira uma compra só.
6. Erro temporário (banco fora, por exemplo) tenta de novo 3 vezes com espera crescente. Pedido inválido (sem CPF, por exemplo) vai direto para o transporte `failed`, sem retentativa.

Para testar pela loja: abra http://localhost:8080, adicione um produto no carrinho, finalize com "Pagamento na entrega" informando um CPF válido (ex: `529.982.247-25`). O pedido aparece no dashboard em segundos. Ou gere mais pedidos de uma vez:

```bash
make woo-orders
```

Para ver e reprocessar mensagens que falharam:

```bash
docker compose exec php bin/console messenger:failed:show
docker compose exec php bin/console messenger:failed:retry
```

## Testes

```bash
make test        # backend: unitários + funcionais (cria o banco purchases_test)
make test-unit   # só os unitários, não precisa de banco
make front-test  # frontend
```

- **Unitários:** regras do domínio (`Money`, `Cpf`, `Purchase`, `Customer`), caso de uso de registro com repositórios em memória (incluindo a corrida de duas requisições com a mesma referência), filtros, mapeamento do pedido do WooCommerce e assinatura do webhook.
- **Funcionais:** a API de verdade contra o PostgreSQL: registro, idempotência, validação, cada filtro da busca, paginação, os números dos agregados e o fluxo do webhook até a compra gravada.
- **Frontend:** formatação, montagem dos filtros e as telas renderizando com a API mockada.

## Variáveis de ambiente

| Variável | Padrão | Para quê |
| --- | --- | --- |
| `DATABASE_URL` | Postgres do compose | Conexão com o banco |
| `MESSENGER_TRANSPORT_DSN` | RabbitMQ do compose | Fila dos pedidos do WooCommerce |
| `WOOCOMMERCE_WEBHOOK_SECRET` | `arctron-webhook-secret` | Secret do webhook (o mesmo nos dois lados) |
| `APP_TIMEZONE` | `America/Sao_Paulo` | Fuso para datas sem fuso e filtros por dia |
| `APP_SECRET` | valor de dev | Trocar em produção |

## Produção

O `backend/Dockerfile` tem um estágio `prod` (sem dependências de dev, autoload otimizado, OPcache sem revalidação, cache aquecido, logs em JSON no stderr) e o `frontend/Dockerfile` um estágio `prod` que serve o build estático no nginx com `/api` repassado para o serviço. O que eu adicionaria antes de colocar no ar está no fim de [docs/DECISOES.md](docs/DECISOES.md).
