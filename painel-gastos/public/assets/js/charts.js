/**
 * Renderizacao dos graficos.
 *
 * Cada <canvas data-chart="tipo" data-url="/api/..."> e descoberto no DOM,
 * tem seus dados buscados na API JSON e e desenhado pelo construtor
 * correspondente. Adicionar um grafico novo em uma view exige apenas o canvas
 * com o data-chart certo -- nenhuma alteracao neste arquivo.
 */
(function () {
    'use strict';

    if (typeof window.Chart === 'undefined') {
        // Chart.js vem de CDN; sem rede, mostra aviso em vez de quebrar a tela.
        document.querySelectorAll('[data-chart]').forEach(function (canvas) {
            showMessage(canvas, 'Nao foi possivel carregar a biblioteca de graficos.');
        });
        return;
    }

    var MOEDA = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

    /** Le os tokens do CSS para que os graficos sigam o tema claro/escuro. */
    function css(name, fallback) {
        var value = getComputedStyle(document.documentElement)
            .getPropertyValue(name)
            .trim();

        return value || fallback;
    }

    var cores = {
        texto: css('--text-muted', '#64748b'),
        grade: css('--border', '#e2e8f0'),
        primaria: css('--primary', '#2563eb'),
        perigo: css('--danger', '#dc2626'),
        sucesso: css('--success', '#16a34a'),
        alerta: css('--warning', '#d97706'),
        superficie: css('--surface', '#ffffff'),
    };

    Chart.defaults.font.family = css('--font', 'system-ui, sans-serif');
    Chart.defaults.font.size = 12;
    Chart.defaults.color = cores.texto;
    Chart.defaults.animation.duration = 400;
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, .92)';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.displayColors = true;

    function formatMoeda(valor) {
        return MOEDA.format(Number(valor) || 0);
    }

    /** Eixo de valores compacto: "R$ 1,2 mil" em vez do numero cheio. */
    function eixoMoeda(valor) {
        var numero = Number(valor) || 0;

        if (Math.abs(numero) >= 1000000) {
            return 'R$ ' + (numero / 1000000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi';
        }

        if (Math.abs(numero) >= 1000) {
            return 'R$ ' + (numero / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil';
        }

        return 'R$ ' + numero.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
    }

    function showMessage(canvas, texto) {
        var wrapper = canvas.parentElement;

        if (!wrapper || wrapper.querySelector('.chart__message')) {
            return;
        }

        canvas.style.display = 'none';

        var aviso = document.createElement('p');
        aviso.className = 'chart__message';
        aviso.textContent = texto;
        wrapper.appendChild(aviso);
    }

    /** Sem nenhum valor > 0 nao ha grafico para desenhar. */
    function vazio(valores) {
        if (!Array.isArray(valores) || valores.length === 0) {
            return true;
        }

        return valores.every(function (valor) {
            return !valor;
        });
    }

    var eixoValor = {
        beginAtZero: true,
        border: { display: false },
        grid: { color: cores.grade, drawTicks: false },
        ticks: { callback: eixoMoeda, padding: 6 },
    };

    var eixoCategoria = {
        border: { display: false },
        grid: { display: false },
    };

    // ------------------------------------------------------------------
    // Construtores por tipo de grafico
    // ------------------------------------------------------------------

    var construtores = {
        /** Linha dupla: gasto do dia e acumulado do mes. */
        diario: function (canvas, dados) {
            if (vazio(dados.diario)) {
                showMessage(canvas, 'Nenhum gasto registrado neste mes.');
                return null;
            }

            return new Chart(canvas, {
                type: 'line',
                data: {
                    labels: dados.labels,
                    datasets: [
                        {
                            label: 'Acumulado',
                            data: dados.acumulado,
                            borderColor: cores.primaria,
                            backgroundColor: 'rgba(37, 99, 235, .12)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            borderWidth: 2,
                            order: 2,
                        },
                        {
                            label: 'Gasto do dia',
                            data: dados.diario,
                            borderColor: cores.alerta,
                            backgroundColor: cores.alerta,
                            type: 'bar',
                            borderRadius: 3,
                            order: 1,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: { x: eixoCategoria, y: eixoValor },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: {
                            callbacks: {
                                title: function (itens) {
                                    var indice = itens[0].dataIndex;
                                    return dados.datas[indice] || itens[0].label;
                                },
                                label: function (item) {
                                    return item.dataset.label + ': ' + formatMoeda(item.parsed.y);
                                },
                            },
                        },
                    },
                },
            });
        },

        /** Rosca de participacao por categoria. */
        categorias: function (canvas, dados) {
            if (vazio(dados.valores)) {
                showMessage(canvas, 'Sem despesas para distribuir entre categorias.');
                return null;
            }

            return new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: dados.labels,
                    datasets: [{
                        data: dados.valores,
                        backgroundColor: dados.cores,
                        borderColor: cores.superficie,
                        borderWidth: 2,
                        hoverOffset: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '58%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { usePointStyle: true, boxWidth: 8, padding: 10 },
                        },
                        tooltip: {
                            callbacks: {
                                label: function (item) {
                                    var detalhe = (dados.detalhes || [])[item.dataIndex] || {};
                                    var percentual = detalhe.percentual != null
                                        ? ' (' + detalhe.percentual.toLocaleString('pt-BR') + '%)'
                                        : '';

                                    return ' ' + item.label + ': ' + formatMoeda(item.parsed) + percentual;
                                },
                                afterLabel: function (item) {
                                    var detalhe = (dados.detalhes || [])[item.dataIndex] || {};

                                    return detalhe.lancamentos
                                        ? detalhe.lancamentos + ' lancamento(s)'
                                        : '';
                                },
                            },
                        },
                    },
                },
            });
        },

        /** Barras dos ultimos meses, com o mes atual destacado. */
        mensal: function (canvas, dados) {
            if (vazio(dados.valores)) {
                showMessage(canvas, 'Sem historico suficiente para comparar meses.');
                return null;
            }

            var destaque = dados.chaves.map(function (chave) {
                return chave === dados.destaque ? cores.primaria : 'rgba(100, 116, 139, .55)';
            });

            var media = dados.media || 0;

            return new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: dados.labels,
                    datasets: [
                        {
                            label: 'Total do mes',
                            data: dados.valores,
                            backgroundColor: destaque,
                            borderRadius: 4,
                        },
                        {
                            label: 'Media do periodo',
                            data: dados.labels.map(function () {
                                return media;
                            }),
                            type: 'line',
                            borderColor: cores.perigo,
                            borderDash: [5, 4],
                            borderWidth: 1.5,
                            pointRadius: 0,
                            fill: false,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { x: eixoCategoria, y: eixoValor },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: {
                            callbacks: {
                                label: function (item) {
                                    return item.dataset.label + ': ' + formatMoeda(item.parsed.y);
                                },
                            },
                        },
                    },
                },
            });
        },

        /** Barras horizontais por forma de pagamento. */
        formas: function (canvas, dados) {
            if (vazio(dados.valores)) {
                showMessage(canvas, 'Sem pagamentos registrados neste mes.');
                return null;
            }

            return new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: dados.labels,
                    datasets: [{
                        label: 'Total',
                        data: dados.valores,
                        backgroundColor: cores.primaria,
                        borderRadius: 4,
                        barThickness: 18,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { x: eixoValor, y: eixoCategoria },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (item) {
                                    var quantidade = (dados.contagem || [])[item.dataIndex];

                                    return formatMoeda(item.parsed.x)
                                        + (quantidade ? ' em ' + quantidade + ' lancamento(s)' : '');
                                },
                            },
                        },
                    },
                },
            });
        },

        /** Barras agrupadas: orcado x gasto. */
        orcamentos: function (canvas, dados) {
            if (vazio(dados.orcado)) {
                showMessage(canvas, 'Nenhum orcamento definido para este mes.');
                return null;
            }

            return new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: dados.labels,
                    datasets: [
                        {
                            label: 'Orcado',
                            data: dados.orcado,
                            backgroundColor: 'rgba(100, 116, 139, .45)',
                            borderRadius: 4,
                        },
                        {
                            label: 'Gasto',
                            data: dados.gasto,
                            // Vermelho quando a categoria estourou o orcamento.
                            backgroundColor: dados.uso.map(function (percentual) {
                                if (percentual >= 100) {
                                    return cores.perigo;
                                }

                                return percentual >= 80 ? cores.alerta : cores.sucesso;
                            }),
                            borderRadius: 4,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { x: eixoCategoria, y: eixoValor },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: {
                            callbacks: {
                                label: function (item) {
                                    return item.dataset.label + ': ' + formatMoeda(item.parsed.y);
                                },
                                afterBody: function (itens) {
                                    var uso = dados.uso[itens[0].dataIndex];

                                    return uso
                                        ? 'Uso: ' + uso.toLocaleString('pt-BR') + '%'
                                        : '';
                                },
                            },
                        },
                    },
                },
            });
        },

        /** Linhas multiplas: tendencia das maiores categorias. */
        tendencia: function (canvas, dados) {
            if (!dados.series || dados.series.length === 0) {
                showMessage(canvas, 'Sem dados suficientes para calcular tendencia.');
                return null;
            }

            return new Chart(canvas, {
                type: 'line',
                data: {
                    labels: dados.labels,
                    datasets: dados.series.map(function (serie) {
                        return {
                            label: serie.nome,
                            data: serie.valores,
                            borderColor: serie.cor,
                            backgroundColor: serie.cor,
                            tension: 0.3,
                            borderWidth: 2,
                            pointRadius: 2,
                            pointHoverRadius: 5,
                            fill: false,
                        };
                    }),
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: { x: eixoCategoria, y: eixoValor },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: {
                            callbacks: {
                                label: function (item) {
                                    return item.dataset.label + ': ' + formatMoeda(item.parsed.y);
                                },
                            },
                        },
                    },
                },
            });
        },
    };

    // ------------------------------------------------------------------
    // Inicializacao
    // ------------------------------------------------------------------

    function render(canvas) {
        var tipo = canvas.dataset.chart;
        var url = canvas.dataset.url;
        var construtor = construtores[tipo];

        if (!construtor || !url) {
            return;
        }

        fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (resposta) {
                if (!resposta.ok) {
                    throw new Error('HTTP ' + resposta.status);
                }

                return resposta.json();
            })
            .then(function (dados) {
                construtor(canvas, dados);
            })
            .catch(function (erro) {
                showMessage(canvas, 'Nao foi possivel carregar os dados deste grafico.');
                console.error('[painel-gastos] grafico "' + tipo + '":', erro);
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('canvas[data-chart]').forEach(render);
    });
}());
