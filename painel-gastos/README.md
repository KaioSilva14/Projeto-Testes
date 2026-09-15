# Painel de Controle de Gastos

Sistema financeiro pessoal em PHP: registra despesas, consome dados externos
(CSV) e gera relatórios visuais de gastos mensais.

Escrito em PHP puro, sem framework e **sem Composer** — só precisa de PHP 8.1+
com as extensões `pdo_sqlite`, `json` e `mbstring` (todas padrão na
distribuição oficial do PHP para Windows).

---

## Como rodar

```bash
# 1. Criar o banco (opcional: a app cria sozinha no primeiro acesso)
php database/migrate.php

# 2. Popular com 12 meses de dados de demonstração
php database/seed.php

# 3. Subir o servidor
php -S localhost:8000 -t public
```

Abra <http://localhost:8000> e entre com:

| Campo   | Valor               |
| ------- | ------------------- |
| E-mail  | `demo@painel.local` |
| Senha   | `senha123`          |

Ou clique em **Criar conta** — uma conta nova já nasce com seis categorias
básicas.

### Rodando em XAMPP / Apache

Aponte o `DocumentRoot` (ou um alias) para a pasta `public/`. O `.htaccess`
incluído já envia todas as rotas para o front controller. A aplicação detecta
sozinha se está publicada em um subdiretório (por exemplo
`http://localhost/painel-gastos/public/`) e monta as URLs de acordo.

### Comandos úteis

```bash
php database/migrate.php --fresh    # apaga o banco e recria do zero
php database/seed.php --reset       # refaz os dados de demonstração
php database/seed.php --meses=24    # gera 24 meses de histórico
```

---

## O que o sistema faz

**Painel** — total do mês, média diária, projeção de fechamento, variação
contra o mês anterior, uso do orçamento, e quatro gráficos: evolução diária
(barras + acumulado), composição por categoria (rosca), últimos 12 meses
(barras com linha de média) e formas de pagamento (barras horizontais).

**Despesas** — CRUD completo com filtro por intervalo de datas, categoria,
forma de pagamento e busca textual; ordenação por qualquer coluna, paginação
e exportação do resultado filtrado em CSV.

**Categorias** — criação, renomeação e cor (usada nos gráficos), com o total
gasto no mês ao lado de cada uma. Categorias com lançamentos não podem ser
excluídas, para preservar o histórico.

**Orçamentos** — teto de gasto por categoria e por competência, salvos em um
único formulário, com barra de progresso e alerta visual em 80% e 100%. Há um
atalho para copiar os valores do mês anterior.

**Relatórios** — comparativo categoria × mês (com totais, médias e variação
mês a mês), gráfico de tendência das cinco maiores categorias, ranking dos dez
maiores gastos, distribuição por forma de pagamento e exportação em CSV.

**Importação de CSV** — upload de arquivo ou colagem de texto. Tolera o que
costuma sair de banco e planilha: BOM UTF-8, separador `,` ou `;`, cabeçalho
com acento ou em maiúsculas, datas em `dd/mm/aaaa` ou `aaaa-mm-dd`, valores em
formato brasileiro ou americano e despesas com sinal negativo. Linhas
inválidas não cancelam a importação — são listadas com o número da linha.

---

## Estrutura

```
painel-gastos/
├── config/config.php          Configuração central (env sobrescreve)
├── database/
│   ├── schema.sql             Esquema SQLite
│   ├── migrate.php            CLI: aplica o schema
│   └── seed.php               CLI: dados de demonstração
├── public/                    Raiz web — só isto precisa ser exposto
│   ├── index.php              Front controller + tabela de rotas
│   ├── .htaccess              Rewrite para Apache
│   └── assets/
│       ├── css/app.css        Folha única (tema claro e escuro)
│       └── js/
│           ├── charts.js      Gráficos (Chart.js via CDN)
│           └── app.js         Interações da interface
├── src/
│   ├── bootstrap.php          Autoloader PSR-4 + inicialização
│   ├── Core/                  Infraestrutura sem regra de negócio
│   │   ├── Auth.php           Sessão autenticada
│   │   ├── Config.php         Config::get('app.name')
│   │   ├── Csrf.php           Token anti-CSRF
│   │   ├── Database.php       Camada fina sobre PDO/SQLite
│   │   ├── Migrator.php       Aplica o schema
│   │   ├── Money.php          Centavos ↔ texto
│   │   ├── Period.php         Competência "YYYY-MM"
│   │   ├── Request.php        Leitura da requisição
│   │   ├── Response.php       HTML, JSON, redirect, download
│   │   ├── Router.php         Rotas + travas auth/guest
│   │   ├── Session.php        Sessão, flash e repopulação
│   │   ├── Url.php            URLs cientes do subdiretório
│   │   ├── Validator.php      Validação encadeável
│   │   └── View.php           Templates PHP com layout
│   ├── Repositories/          Acesso a dados (SQL fica aqui)
│   ├── Services/              Regras de negócio
│   │   ├── ChartService.php   Payloads dos gráficos
│   │   ├── CsvExporter.php    Geração de CSV
│   │   ├── CsvImporter.php    Leitura de CSV
│   │   ├── DemoSeeder.php     Dados fictícios
│   │   └── ReportService.php  KPIs e comparativos
│   ├── Controllers/           Um por área da aplicação
│   └── Support/
│       └── ExpenseFilter.php  Filtros da listagem → SQL
├── views/                     Templates, um diretório por área
└── storage/                   Banco SQLite (fora da raiz web)
```

---

## API JSON

Os gráficos são alimentados por endpoints próprios, o que permite recarregar
dados sem recarregar a página. Todos exigem sessão autenticada e retornam
apenas dados do usuário logado.

| Rota                     | Parâmetros            | Conteúdo                        |
| ------------------------ | --------------------- | ------------------------------- |
| `/api/resumo`            | `mes`                 | Indicadores do mês              |
| `/api/categorias`        | `mes`                 | Total por categoria             |
| `/api/diario`            | `mes`                 | Gasto por dia e acumulado       |
| `/api/mensal`            | `mes`, `meses`        | Série mensal e média            |
| `/api/formas-pagamento`  | `mes`                 | Total por forma de pagamento    |
| `/api/orcamentos`        | `mes`                 | Orçado × gasto por categoria    |
| `/api/tendencia`         | `de`, `ate`, `top`    | Séries mensais por categoria    |

Exemplo:

```bash
curl -b cookies.txt "http://localhost:8000/api/categorias?mes=2026-09"
```

No front-end, adicionar um gráfico a uma página se resume a um canvas — o
`charts.js` descobre, busca e desenha sozinho:

```html
<canvas data-chart="categorias" data-url="/api/categorias?mes=2026-09"></canvas>
```

---

## Decisões de projeto

**Dinheiro em centavos (`INTEGER`).** Nenhum valor monetário passa por `float`
no banco nem nas contas. `Money::toCents()` converte a entrada do usuário e
`Money::format()` devolve o texto — a conversão para `float` só acontece na
borda, ao montar o JSON do gráfico.

**SQLite.** O objetivo é clonar e rodar. O SQL é padrão o bastante para
migrar para MySQL/Postgres mexendo só em `Core/Database.php` (as exceções são
o `UPSERT ... ON CONFLICT` em `BudgetRepository` e `lower()` nos índices
únicos).

**SQL só nos repositórios.** Todo método recebe o `user_id` e o aplica no
`WHERE`: o isolamento entre contas é garantido na camada de dados, não apenas
nos controllers. Toda consulta usa parâmetros; onde `LIMIT`/`OFFSET` aparecem
interpolados, os valores já foram convertidos para `int` em PHP, porque o
driver SQLite trataria o valor vinculado como texto.

**Ordenação por lista branca.** A coluna e a direção vindas da query string
são mapeadas em `ExpenseFilter::SORTABLE` antes de entrar no `ORDER BY`.

**CSRF em tudo.** O `Router` valida o token em toda requisição POST, antes de
o controller rodar. Não há como esquecer em uma rota nova.

**Variação percentual pode ser `null`.** Quando a base é zero, a tela mostra
"—" em vez de um crescimento infinito. O mesmo vale para o uso de orçamento
sem orçamento definido.

**Datas como texto ISO.** `YYYY-MM-DD` ordena corretamente em ordem
lexicográfica, o que deixa os índices e os filtros de intervalo simples.

**Sem dependências no navegador além do Chart.js.** Se o CDN não responder, os
gráficos mostram um aviso e o resto da página continua funcionando. As tabelas
trazem os mesmos números dos gráficos.

---

## Limitações conhecidas

- A importação quebra linhas por `\n`, então um campo entre aspas contendo
  quebra de linha seria dividido em duas linhas. Extratos bancários não usam
  esse recurso; tratar o caso exigiria ler o arquivo via `fgetcsv`.
- Não há recuperação de senha nem edição de perfil pela interface.
- O seed usa `mt_srand` com semente fixa, então roda sempre igual — é dado de
  demonstração, não amostra estatística.
- Sem testes automatizados.

## Licença

Uso livre para estudo.
