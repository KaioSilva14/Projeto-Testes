# Análise do PIB por país

Este projeto analisa como o **Produto Interno Bruto (PIB)** evoluiu nos últimos 50 anos para vários países. O programa usa dados reais da API oficial do **Banco Mundial**, calcula métricas de crescimento e gera um dashboard interativo em HTML.

## O que o programa faz

O script baixa três indicadores da série World Development Indicators:

- **PIB corrente em US$** (`NY.GDP.MKTP.CD`);

- **Crescimento anual do PIB em %** (`NY.GDP.MKTP.KD.ZG`);

- **PIB per capita corrente em US$** (`NY.GDP.PCAP.CD`).

Em seguida, ele combina os dados, trata valores ausentes, calcula variação anual, crescimento anual composto (CAGR), média e volatilidade do crescimento. Também cria rankings por PIB final e por crescimento acumulado.

O resultado inclui um dashboard interativo com gráficos de linha, ranking e comparação de PIB per capita. Os dados baixados são armazenados em cache para evitar novas consultas desnecessárias.

## Estrutura

```
python/
├── analise_pib.py
├── requirements.txt
├── README.md
└── resultados_pib/          # criada ao executar
    ├── dashboard.html
    ├── relatorio.md
    ├── dados_tratados.csv
    ├── resumo_por_pais.csv
    ├── evolucao_pib.html
    ├── crescimento_pib.html
    ├── ranking_pib.html
    ├── pib_per_capita.html
    └── cache/
```

## Como executar no VS Code

Abra a pasta `python` no VS Code. Depois abra o terminal integrado com `Ctrl + `` e confirme que está dentro da pasta do projeto.

No Windows PowerShell, crie um ambiente virtual:

```
python -m venv .venv
```

Ative o ambiente:

```
.\.venv\Scripts\Activate.ps1
```

Se o PowerShell bloquear a ativação, use esta alternativa no mesmo terminal:

```
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
.\.venv\Scripts\Activate.ps1
```

Instale as bibliotecas:

```
python -m pip install --upgrade pip
pip install -r requirements.txt
```

Execute a análise padrão com oito países e aproximadamente 50 anos:

```
python analise_pib.py
```

Quando terminar, abra o dashboard:

```
start .\resultados_pib\dashboard.html
```

## Exemplos de execução

Analisar Brasil, Estados Unidos, China, Índia e Alemanha:

```
python analise_pib.py --paises BRA USA CHN IND DEU
```

Analisar países da América Latina e salvar em outra pasta:

```
python analise_pib.py --paises BRA ARG MEX CHL COL PER --anos 50 --saida resultados_latam
```

Analisar os últimos 30 anos:

```
python analise_pib.py --anos 30
```

## Códigos ISO3 usados

| País | Código |
| --- | --- |
| Brasil | `BRA` |
| Estados Unidos | `USA` |
| China | `CHN` |
| Índia | `IND` |
| Alemanha | `DEU` |
| Japão | `JPN` |
| Reino Unido | `GBR` |
| França | `FRA` |
| Argentina | `ARG` |
| México | `MEX` |

## Arquivos gerados

O arquivo `dashboard.html` reúne os gráficos e a tabela-resumo em uma página. Os arquivos HTML individuais podem ser abertos separadamente no navegador.

O `dados_tratados.csv` contém a série anual combinada. O `resumo_por_pais.csv` contém o resumo por país, incluindo PIB inicial e final, variação total, CAGR, volatilidade, ano do maior PIB e rankings.

O `relatorio.md` apresenta uma síntese automática dos resultados e as limitações da análise.

## Interpretação importante

O PIB corrente em dólares é uma medida nominal. Sua variação pode refletir produção, inflação e taxa de câmbio. Por isso, o projeto também mostra o crescimento anual real do PIB, que deve ser analisado separadamente.

O PIB total mede o tamanho da economia, não o bem-estar individual. Para comparações de qualidade de vida, considere também o PIB per capita, inflação, desemprego, desigualdade e paridade do poder de compra.

## Fonte dos dados

Os dados são obtidos diretamente do Banco Mundial:

- [1]

- [2]

- [3]

- [4]

[1]: https://data.worldbank.org/indicator/NY.GDP.MKTP.CD "World Bank — GDP (current US$ )"

[2]: https://data.worldbank.org/indicator/NY.GDP.MKTP.KD.ZG "World Bank — GDP growth (annual % )"

[3]: https://data.worldbank.org/indicator/NY.GDP.PCAP.CD "World Bank — GDP per capita (current US$ )"

[4]: https://api.worldbank.org/v2/indicator/NY.GDP.MKTP.CD?format=json "World Bank Indicators API"