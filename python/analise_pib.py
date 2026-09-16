"""Análise avançada da evolução do PIB por país.

Fonte padrão: World Bank World Development Indicators.
Indicadores:
- NY.GDP.MKTP.CD: GDP (current US$)
- NY.GDP.MKTP.KD.ZG: GDP growth (annual %)
- NY.GDP.PCAP.CD: GDP per capita (current US$)

Uso:
    python analise_pib.py
    python analise_pib.py --paises BRA USA CHN IND DEU --anos 50
    python analise_pib.py --paises BRA ARG MEX --saida resultados_latam
"""

from __future__ import annotations

import argparse
import json
import logging
import math
import time
from pathlib import Path
from typing import Any

import numpy as np
import pandas as pd
import requests
import plotly.express as px
import plotly.graph_objects as go
from plotly.subplots import make_subplots

WORLD_BANK_API = "https://api.worldbank.org/v2/country/{countries}/indicator/{indicator}"
INDICADORES = {
    "pib": "NY.GDP.MKTP.CD",
    "crescimento": "NY.GDP.MKTP.KD.ZG",
    "pib_per_capita": "NY.GDP.PCAP.CD",
}
PAISES_PADRAO = ["BRA", "USA", "CHN", "IND", "DEU", "JPN", "GBR", "FRA"]
NOME_INDICADOR = {
    "pib": "PIB corrente (US$)",
    "crescimento": "Crescimento anual do PIB (%)",
    "pib_per_capita": "PIB per capita corrente (US$)",
}

logging.basicConfig(level=logging.INFO, format="%(levelname)s: %(message)s")
logger = logging.getLogger(__name__)


def buscar_world_bank(indicador: str, paises: list[str], ano_inicial: int, ano_final: int) -> pd.DataFrame:
    """Baixa todos os anos de um indicador na API oficial do Banco Mundial."""
    pais_param = ";".join(paises)
    url = WORLD_BANK_API.format(countries=pais_param, indicator=indicador)
    parametros = {
        "format": "json",
        "per_page": 20000,
        "date": f"{ano_inicial}:{ano_final}",
    }
    logger.info("Baixando %s para %s...", indicador, ", ".join(paises))
    resposta = requests.get(url, params=parametros, timeout=60)
    resposta.raise_for_status()
    conteudo = resposta.json()
    if not isinstance(conteudo, list) or len(conteudo) < 2:
        raise RuntimeError(f"Resposta inesperada da API para o indicador {indicador}.")

    registros = conteudo[1]
    linhas = []
    for registro in registros:
        valor = registro.get("value")
        if valor is None:
            continue
        linhas.append({
            "pais_codigo": registro.get("countryiso3code"),
            "pais": registro.get("country", {}).get("value"),
            "ano": int(registro["date"]),
            "valor": float(valor),
        })
    if not linhas:
        raise RuntimeError(f"Nenhum dado encontrado para {indicador}.")
    return pd.DataFrame(linhas)


def carregar_dados(paises: list[str], ano_inicial: int, ano_final: int, pasta_cache: Path) -> pd.DataFrame:
    """Baixa os três indicadores, combina-os e mantém cópias locais em CSV."""
    pasta_cache.mkdir(parents=True, exist_ok=True)
    tabelas = []
    chave = "_".join(sorted(paises))
    for nome, codigo in INDICADORES.items():
        arquivo_cache = pasta_cache / f"{nome}_{chave}_{ano_inicial}_{ano_final}.csv"
        if arquivo_cache.exists():
            tabela = pd.read_csv(arquivo_cache)
            logger.info("Usando cache: %s", arquivo_cache.name)
        else:
            tabela = buscar_world_bank(codigo, paises, ano_inicial, ano_final)
            tabela.to_csv(arquivo_cache, index=False)
            time.sleep(0.2)
        tabela = tabela.rename(columns={"valor": nome})
        tabelas.append(tabela)

    dados = tabelas[0]
    for tabela in tabelas[1:]:
        dados = dados.merge(tabela[["pais_codigo", "pais", "ano", tabela.columns[-1]]],
                            on=["pais_codigo", "pais", "ano"], how="outer")
    return dados.sort_values(["pais", "ano"]).reset_index(drop=True)


def calcular_metricas(dados: pd.DataFrame) -> tuple[pd.DataFrame, pd.DataFrame]:
    """Calcula variações, CAGR, médias, máximos e volatilidade por país."""
    dados = dados.copy()
    dados["pib_anterior"] = dados.groupby("pais_codigo")["pib"].shift(1)
    dados["variacao_pib"] = dados["pib"] / dados["pib_anterior"] - 1
    dados["log_pib"] = np.log(dados["pib"].where(dados["pib"] > 0))

    def resumo(grupo: pd.DataFrame) -> pd.Series:
        grupo = grupo.sort_values("ano")
        primeiro = grupo.dropna(subset=["pib"]).iloc[0]
        ultimo = grupo.dropna(subset=["pib"]).iloc[-1]
        anos = int(ultimo["ano"] - primeiro["ano"])
        cagr = ((ultimo["pib"] / primeiro["pib"]) ** (1 / anos) - 1) if anos > 0 and primeiro["pib"] > 0 else np.nan
        return pd.Series({
            "pais": grupo["pais"].iloc[0],
            "ano_inicial": int(primeiro["ano"]),
            "ano_final": int(ultimo["ano"]),
            "pib_inicial_usd": primeiro["pib"],
            "pib_final_usd": ultimo["pib"],
            "crescimento_total_pct": (ultimo["pib"] / primeiro["pib"] - 1) * 100,
            "cagr_pct": cagr * 100,
            "media_crescimento_anual_pct": grupo["crescimento"].mean(),
            "volatilidade_crescimento_pct": grupo["crescimento"].std(),
            "maior_pib_usd": grupo["pib"].max(),
            "ano_maior_pib": int(grupo.loc[grupo["pib"].idxmax(), "ano"]),
            "pib_per_capita_final_usd": ultimo["pib_per_capita"],
        })

    # Sem `include_groups`, o código também funciona em versões do pandas anteriores à 2.2.
    resumo_paises = dados.groupby("pais_codigo", group_keys=False).apply(resumo).reset_index()
    resumo_paises["ranking_pib_final"] = resumo_paises["pib_final_usd"].rank(ascending=False, method="min").astype(int)
    resumo_paises["ranking_cagr"] = resumo_paises["cagr_pct"].rank(ascending=False, method="min").astype(int)
    return dados, resumo_paises.sort_values("ranking_pib_final")


def moeda(valor: float) -> str:
    if pd.isna(valor):
        return "N/D"
    if abs(valor) >= 1e12:
        return f"US$ {valor / 1e12:.2f} tri"
    if abs(valor) >= 1e9:
        return f"US$ {valor / 1e9:.2f} bi"
    return f"US$ {valor / 1e6:.2f} mi"


def criar_graficos(dados: pd.DataFrame, resumo: pd.DataFrame, pasta: Path) -> dict[str, str]:
    """Gera gráficos HTML interativos e retorna seus nomes."""
    pasta.mkdir(parents=True, exist_ok=True)
    arquivos: dict[str, str] = {}

    fig = px.line(dados, x="ano", y="pib", color="pais", markers=True,
                  title="Evolução do PIB corrente por país",
                  labels={"ano": "Ano", "pib": "PIB (US$)", "pais": "País"})
    fig.update_yaxes(tickprefix="US$ ", separatethousands=True)
    fig.update_layout(template="plotly_white", hovermode="x unified")
    arquivos["evolucao_pib"] = "evolucao_pib.html"
    fig.write_html(pasta / arquivos["evolucao_pib"], include_plotlyjs="cdn")

    fig = px.line(dados, x="ano", y="crescimento", color="pais", markers=True,
                  title="Crescimento anual do PIB",
                  labels={"ano": "Ano", "crescimento": "Crescimento (%)", "pais": "País"})
    fig.add_hline(y=0, line_dash="dash", line_color="gray")
    fig.update_layout(template="plotly_white", hovermode="x unified")
    arquivos["crescimento"] = "crescimento_pib.html"
    fig.write_html(pasta / arquivos["crescimento"], include_plotlyjs="cdn")

    ranking = resumo.sort_values("pib_final_usd", ascending=True)
    fig = px.bar(ranking, x="pib_final_usd", y="pais", orientation="h", color="cagr_pct",
                 title="Ranking do PIB no último ano disponível",
                 labels={"pib_final_usd": "PIB (US$)", "pais": "País", "cagr_pct": "CAGR (%)"},
                 color_continuous_scale="Viridis")
    fig.update_xaxes(tickprefix="US$ ", separatethousands=True)
    fig.update_layout(template="plotly_white")
    arquivos["ranking"] = "ranking_pib.html"
    fig.write_html(pasta / arquivos["ranking"], include_plotlyjs="cdn")

    anos = sorted(dados["ano"].dropna().unique())
    selecionados = [anos[0], anos[len(anos) // 2], anos[-1]] if len(anos) >= 3 else anos
    comparacao = dados[dados["ano"].isin(selecionados)]
    fig = px.bar(comparacao, x="pais", y="pib_per_capita", color="ano", barmode="group",
                 title="PIB per capita: comparação temporal",
                 labels={"pais": "País", "pib_per_capita": "PIB per capita (US$)", "ano": "Ano"})
    fig.update_layout(template="plotly_white")
    arquivos["per_capita"] = "pib_per_capita.html"
    fig.write_html(pasta / arquivos["per_capita"], include_plotlyjs="cdn")
    return arquivos


def gerar_dashboard(dados: pd.DataFrame, resumo: pd.DataFrame, graficos: dict[str, str], pasta: Path) -> None:
    """Cria um dashboard HTML com os gráficos incorporados."""
    ultimo_ano = int(dados["ano"].max())
    maior = resumo.iloc[0]
    cards = f"""
    <div class='cards'>
      <div><b>{len(resumo)}</b><span>países analisados</span></div>
      <div><b>{ultimo_ano}</b><span>último ano disponível</span></div>
      <div><b>{maior['pais']}</b><span>maior PIB em {ultimo_ano}</span></div>
      <div><b>{maior['cagr_pct']:.2f}%</b><span>CAGR do líder</span></div>
    </div>"""
    tabela = resumo.copy()
    tabela["pib_final"] = tabela["pib_final_usd"].map(moeda)
    tabela["cagr"] = tabela["cagr_pct"].map(lambda x: f"{x:.2f}%")
    tabela["crescimento"] = tabela["crescimento_total_pct"].map(lambda x: f"{x:.2f}%")
    linhas = "".join(
        f"<tr><td>{int(r.ranking_pib_final)}</td><td>{r.pais}</td><td>{r.pib_final}</td><td>{r.cagr}</td><td>{r.crescimento}</td></tr>"
        for r in tabela.itertuples()
    )
    html = f"""<!doctype html><html lang='pt-BR'><head><meta charset='utf-8'><title>Dashboard de PIB</title>
    <style>body{{font-family:Arial,sans-serif;background:#f4f7fb;color:#172033;max-width:1200px;margin:auto;padding:28px}}h1{{margin-bottom:4px}}.sub{{color:#667085}}.cards{{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:25px 0}}.cards div{{background:#fff;border-radius:12px;padding:18px;box-shadow:0 3px 12px #17203314}}.cards b{{display:block;font-size:24px;color:#315efb;margin-bottom:7px}}.cards span{{color:#667085;font-size:13px}}.grafico{{background:white;border-radius:12px;padding:8px;margin:18px 0;box-shadow:0 3px 12px #17203314}}table{{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}}th,td{{padding:12px;text-align:left;border-bottom:1px solid #e7ebf2}}th{{background:#edf2ff}}@media(max-width:700px){{.cards{{grid-template-columns:repeat(2,1fr)}}}}</style></head><body>
    <h1>Análise da evolução do PIB</h1><p class='sub'>Dados oficiais do Banco Mundial. Valores correntes em dólares americanos.</p>{cards}
    <div class='grafico'><iframe src='{graficos['evolucao_pib']}' width='100%' height='520' frameborder='0'></iframe></div>
    <div class='grafico'><iframe src='{graficos['crescimento']}' width='100%' height='520' frameborder='0'></iframe></div>
    <div class='grafico'><iframe src='{graficos['ranking']}' width='100%' height='520' frameborder='0'></iframe></div>
    <div class='grafico'><iframe src='{graficos['per_capita']}' width='100%' height='520' frameborder='0'></iframe></div>
    <h2>Resumo por país</h2><table><thead><tr><th>Ranking</th><th>País</th><th>PIB final</th><th>CAGR</th><th>Variação total</th></tr></thead><tbody>{linhas}</tbody></table>
    <p class='sub'>Fonte: World Development Indicators — Banco Mundial. O resultado depende do último ano disponível na API.</p></body></html>"""
    (pasta / "dashboard.html").write_text(html, encoding="utf-8")


def gerar_relatorio(dados: pd.DataFrame, resumo: pd.DataFrame, pasta: Path, arquivos_graficos: dict[str, str]) -> None:
    """Gera um relatório Markdown com conclusões principais e referências."""
    lider_pib = resumo.iloc[0]
    lider_cagr = resumo.sort_values("cagr_pct", ascending=False).iloc[0]
    maior_volatilidade = resumo.sort_values("volatilidade_crescimento_pct", ascending=False).iloc[0]
    texto = f"""# Relatório de análise do PIB

## Síntese

A análise cobre **{len(resumo)} países** entre **{int(dados.ano.min())} e {int(dados.ano.max())}**. No último ano disponível, **{lider_pib.pais}** apresentou o maior PIB entre os países selecionados, com aproximadamente **{moeda(lider_pib.pib_final_usd)}**. O maior crescimento anual composto no período foi observado em **{lider_cagr.pais}**, com **{lider_cagr.cagr_pct:.2f}% ao ano**.

Os valores do PIB corrente estão em dólares americanos. Por isso, a variação observada combina mudanças na produção econômica, nos preços e nas taxas de câmbio. O indicador de crescimento anual, por sua vez, usa uma série de crescimento real do PIB e deve ser interpretado separadamente do valor nominal em dólares.

## Principais resultados

- **Maior PIB no último ano disponível:** {lider_pib.pais}, {moeda(lider_pib.pib_final_usd)}.
- **Maior CAGR no período:** {lider_cagr.pais}, {lider_cagr.cagr_pct:.2f}% ao ano.
- **Maior volatilidade do crescimento anual:** {maior_volatilidade.pais}, {maior_volatilidade.volatilidade_crescimento_pct:.2f} pontos percentuais de desvio-padrão.
- **Critério de comparação:** o ranking usa o último ano com dados disponíveis para todos os países retornados pela API.

## Arquivos gerados

O programa produz um dashboard interativo, quatro gráficos individuais e tabelas CSV:

- `dashboard.html`: painel completo para abrir no navegador.
- `evolucao_pib.html`: série temporal do PIB corrente.
- `crescimento_pib.html`: crescimento anual do PIB.
- `ranking_pib.html`: ranking do PIB final.
- `pib_per_capita.html`: comparação do PIB per capita.
- `dados_tratados.csv`: base combinada e métricas anuais.
- `resumo_por_pais.csv`: CAGR, variação total, volatilidade e rankings.

## Limitações

O PIB corrente não deve ser usado sozinho para medir bem-estar. Comparações entre países também podem exigir PIB em paridade do poder de compra, PIB per capita real, inflação, população e outros indicadores sociais. A série pode conter valores ausentes e revisões históricas feitas pela fonte original.

## Referências

[1]: https://data.worldbank.org/indicator/NY.GDP.MKTP.CD "World Bank — GDP (current US$)"
[2]: https://data.worldbank.org/indicator/NY.GDP.MKTP.KD.ZG "World Bank — GDP growth (annual %)"
[3]: https://data.worldbank.org/indicator/NY.GDP.PCAP.CD "World Bank — GDP per capita (current US$)"
[4]: https://api.worldbank.org/v2/indicator/NY.GDP.MKTP.CD?format=json "World Bank Indicators API"
"""
    (pasta / "relatorio.md").write_text(text, encoding="utf-8")


def executar(paises: list[str], anos: int, saida: Path) -> None:
    ano_final = pd.Timestamp.now().year - 1
    ano_inicial = ano_final - anos + 1
    cache = saida / "cache"
    dados = carregar_dados(paises, ano_inicial, ano_final, cache)
    dados, resumo = calcular_metricas(dados)
    saida.mkdir(parents=True, exist_ok=True)
    dados.to_csv(saida / "dados_tratados.csv", index=False)
    resumo.to_csv(saida / "resumo_por_pais.csv", index=False)
    graficos = criar_graficos(dados, resumo, saida)
    gerar_dashboard(dados, resumo, graficos, saida)
    gerar_relatorio(dados, resumo, saida, graficos)
    logger.info("Concluído. Abra: %s", (saida / 'dashboard.html').resolve())


def argumentos() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Analisa a evolução do PIB por país usando dados do Banco Mundial.")
    parser.add_argument("--paises", nargs="+", default=PAISES_PADRAO, help="Códigos ISO3, por exemplo BRA USA CHN.")
    parser.add_argument("--anos", type=int, default=50, help="Quantidade de anos históricos. Padrão: 50.")
    parser.add_argument("--saida", type=Path, default=Path("resultados_pib"), help="Pasta dos resultados.")
    return parser.parse_args()


if __name__ == "__main__":
    args = argumentos()
    if args.anos < 2:
        raise SystemExit("O parâmetro --anos deve ser maior ou igual a 2.")
    executar([p.upper() for p in args.paises], args.anos, args.saida)
