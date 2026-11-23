# Decisões técnicas

O que escolhi, por quê, e o que fica de lado em cada escolha.

## Stack

Symfony, PostgreSQL e React, que é o que a Arctron usa. Laravel resolveria igual, mas avaliar o desafio no stack do time diz mais sobre como eu trabalharia no dia a dia de vocês.

## Camadas (Domain, Application, Infrastructure, UI)

A regra de negócio fica em `Domain`, em PHP puro. `Purchase::register()` é o único jeito de criar uma compra e já valida tudo (referência, itens, desconto), então não existe compra inválida em memória.

`Application` tem os casos de uso e depende só de interfaces (`PurchaseRepository`, `CommandBus`, `PurchaseSearch`). Isso permitiu testar o registro de compra inteiro com repositórios em memória, sem banco.

O mapeamento do Doctrine está em XML e não em atributos nas entidades, para o domínio não importar nada do ORM. A única dependência que ficou foi `doctrine/collections` na coleção de itens, que é uma biblioteca pequena e sem acoplamento com banco.

Não fui mais longe do que isso (sem bounded contexts separados, sem eventos de domínio) porque o problema não pede. A estrutura está pronta para crescer se precisar.

## Escrita pelo ORM, leitura em SQL

As compras são gravadas pelo Doctrine ORM, que cuida de cascade, identidade e transação. A busca e os agregados são queries SQL diretas pelo DBAL (`Infrastructure/ReadModel`).

Motivo: o dashboard só lê e agrega. Hidratar entidades para somar valores seria mais lento e esconderia as queries. Em SQL todas as contas rodam no PostgreSQL (`SUM`, `AVG`, `COUNT DISTINCT`), e a listagem busca os itens da página inteira numa query só, sem N+1.

É uma separação no estilo CQRS, mas no mesmo banco. Se o volume crescer, dá para trocar o read model por uma view materializada ou uma tabela de agregados sem mexer na escrita.

## Command bus

A API, o worker do WooCommerce e o comando de seed registram compras pelo mesmo comando (`RegisterPurchase`) e o mesmo handler. As regras de negócio existem em um lugar só.

O bus é o Symfony Messenger atrás de uma interface `CommandBus` da camada de aplicação. O wrapper desembrulha a `HandlerFailedException` do Messenger para a exceção original chegar no controller (por exemplo, `InvalidPurchase` vira 422).

## Idempotência pela referência

O enunciado diz que o segundo envio com a mesma referência deve ser ignorado. São duas camadas:

1. O handler consulta se a referência já existe e, se existir, devolve "já registrada" sem fazer nada.
2. Se duas requisições chegarem ao mesmo tempo e as duas passarem pela consulta, a constraint `UNIQUE` em `purchases.reference` barra a segunda. O repositório traduz essa violação específica em `PurchaseAlreadyRegistered` e o handler trata como "já registrada".

A consulta prévia é a otimização do caso comum. Quem garante a regra é o banco.

Reenvio responde `200` com `"status": "ignored"` e os dados da compra original, não `409`. Para quem integra, reenviar não é erro (é o caso típico de retentativa depois de timeout), e o cliente consegue saber o que aconteceu.

## Dinheiro em centavos

Valores ficam em `BIGINT` em centavos e passam pelo value object `Money`. Float acumula erro de arredondamento (`0.1 + 0.2`), e num dashboard que soma milhares de compras isso aparece.

Na API os valores entram e saem como decimal (`"150.90"`), que é o formato do enunciado e do WooCommerce. A saída é string, não número, para nenhum cliente JSON converter para float no meio do caminho.

## Cliente pelo CPF, produto pelo nome

O enunciado manda nome, e-mail e CPF. Desses, o único que identifica uma pessoa é o CPF (nome repete, e-mail muda), então ele é a chave. Nome e e-mail são atualizados com o dado mais recente.

O produto só tem nome no payload, então o nome normalizado (minúsculo, sem espaços repetidos) é a identidade. Cada item da compra guarda uma cópia do nome e o preço unitário do momento da compra, para o histórico não mudar se o produto for renomeado ou mudar de preço.

Limitação conhecida: dois produtos diferentes com o mesmo nome viram um só. Com SKU no payload (o WooCommerce tem), seria a identidade certa.

## Regras que escolhi não aplicar

**Não exijo `valor = produtos - desconto`.** O valor pago pode incluir frete e taxas. No pedido de exemplo do WooCommerce: produtos R$ 399,80, desconto R$ 30,00, frete R$ 25,00, total R$ 394,80. Se eu validasse a conta, recusaria um pedido real.

**Exijo `desconto <= produtos`.** Desconto maior que o valor dos produtos não faz sentido em nenhum cenário.

## WooCommerce

**Por que WooCommerce.** É open source e roda inteiro no Docker. Quem for avaliar sobe com um comando, sem criar conta em lugar nenhum e sem depender de uma loja de teste minha estar no ar.

**Webhook com fila.** O webhook só valida a assinatura e publica no RabbitMQ, respondendo `202`. O registro acontece no worker. Assim:

- o WooCommerce recebe resposta rápida (ele desativa webhooks que falham repetidamente);
- se o banco cair, o pedido não se perde, fica na fila e é retentado 3 vezes com espera crescente (2s, 6s, 18s);
- dado inválido (sem CPF, por exemplo) vai direto para o transporte `failed` com `UnrecoverableMessageHandlingException`, porque tentar de novo não resolve. Lá ele pode ser inspecionado e reprocessado com `messenger:failed:retry`.

A mensagem na fila é o pedido cru do WooCommerce, não o comando já convertido. Se o mapeamento tiver um bug, dá para corrigir o código e reprocessar a mensagem original.

**Assinatura.** O WooCommerce assina o corpo com HMAC-SHA256 e manda em base64 no header `X-WC-Webhook-Signature`. A comparação usa `hash_equals` para não vazar informação por tempo de resposta. Sem secret configurado, tudo é recusado.

**Status.** Só `processing` e `completed` viram compra. Assino `order.created` e `order.updated` porque o pedido costuma nascer `pending` e mudar de status depois. Como o `updated` dispara várias vezes, a idempotência pela referência `WC-{número}` faz o trabalho.

**Preço unitário.** O campo `price` do item no WooCommerce já vem com o cupom rateado. Como o desconto é guardado separado, uso `subtotal / quantidade` (antes do desconto), senão o desconto seria contado duas vezes.

**CPF.** O WooCommerce não tem CPF nativo. Lojas brasileiras usam plugins que gravam em `billing.cpf` ou no meta `_billing_cpf`. O mapper procura nos dois. No ambiente de dev um mu-plugin adiciona o campo no checkout.

**Detalhes do ambiente de dev** (no mu-plugin): o WordPress bloqueia requisições para hosts internos, então o host `api` da rede Docker é liberado; e a entrega do webhook é síncrona, para não depender do WP-Cron.

## Agregados: como cada média é calculada

O enunciado lista as métricas mas não define as fórmulas, então deixei explícito:

| Métrica | Fórmula |
| --- | --- |
| Valor médio de compras | média do valor pago |
| Valor médio de descontos | média do desconto entre **todas** as compras (compra sem desconto conta como zero) |
| Valor total de produtos | soma de quantidade x preço unitário |
| Valor médio de produtos | média, por compra, do valor dos produtos |
| Total por cliente | soma do valor pago pelo cliente |
| Média por cliente | valor pago médio por compra daquele cliente |
| Total por produto | soma de quantidade x preço unitário daquele produto |
| Média por produto | total do produto / número de compras em que ele aparece. Também devolvo o preço unitário médio |

Todos aceitam filtro de período. Os rankings são ordenados pelo total e limitados (padrão 10, máximo 50).

## Fuso horário

Datas são `TIMESTAMP WITH TIME ZONE`. Data sem fuso no payload (`"2026-11-20"`) é interpretada em `APP_TIMEZONE`. O filtro `date_to=2026-11-30` inclui o dia 30 inteiro, convertido internamente para `< 2026-12-01T00:00-03:00`. O agrupamento por dia do gráfico também usa o fuso da operação, senão uma compra às 22h entraria no dia seguinte (UTC).

## Validação em duas camadas

O payload HTTP (`RegisterPurchasePayload`) valida formato com o Validator do Symfony: campo obrigatório, tipo, e-mail. Erros voltam todos juntos, com o campo de cada um.

O domínio valida as regras de negócio. Ele não confia na camada de cima, porque compras também chegam pelo worker e pelo seed.

## Frontend

- **Estado na URL.** Filtros, página e período ficam na query string. Dá para compartilhar o link de uma busca e o botão voltar do navegador funciona.
- **TanStack Query** para cache e estados de carregamento/erro. Ao trocar de página a lista anterior continua na tela, esmaecida, em vez de piscar.
- **Proxy do Vite** para `/api` em dev e nginx em produção. O frontend e a API ficam na mesma origem, então não precisei configurar CORS.
- **Sem biblioteca de componentes.** São duas telas; CSS próprio com variáveis deixa o bundle menor e o visual sob controle.
- A visão geral abre com o total vendido no período em destaque e uma frase com o contexto (compras, clientes, itens), seguida dos totais e médias, gráfico diário e rankings.

## Testes

- Unitários para o que tem regra: `Money`, `Cpf`, `Purchase`, o handler de registro (com repositórios em memória, inclusive simulando a condição de corrida), filtros, mapeamento do WooCommerce e assinatura.
- Funcionais pela API de verdade contra o PostgreSQL de teste, porque o SQL dos agregados é onde um erro seria mais caro e menos visível. Os testes de estatística conferem cada número com um cenário montado à mão.
- No frontend, utilitários e as duas telas com `fetch` mockado.

## O que eu faria antes de ir para produção

- **Autenticação na API.** Hoje qualquer um com acesso à rede registra e consulta compras. Para integração entre sistemas, API key por cliente ou OAuth2 client credentials; para o dashboard, login com o SSO da empresa.
- **LGPD.** O dashboard mostra CPF e e-mail completos. Mascarar por padrão (`529.***.***-25`), registrar quem consultou e definir política de retenção.
- **Busca textual em escala.** `ILIKE '%texto%'` não usa índice B-tree. Com volume, índice trigram (`pg_trgm`) nas colunas de nome, e-mail e referência.
- **Agregados em escala.** As queries são boas até alguns milhões de linhas. Acima disso, tabela de agregados diários atualizada na gravação, ou view materializada.
- **Observabilidade.** Logs já saem em JSON no stderr em prod. Faltam métricas (tamanho da fila, tempo de processamento, taxa de falha do webhook) e alerta quando o transporte `failed` acumular mensagens.
- **Rate limit** no endpoint público de compras e no webhook.
- **Reconciliação com o WooCommerce.** Um job periódico que consulta a API REST da loja e reenvia pedidos que não chegaram (webhook desativado, loja fora do ar).
- **CI** rodando testes, análise estática (PHPStan) e build das imagens. Deixei um workflow do GitHub Actions com os testes como ponto de partida.
