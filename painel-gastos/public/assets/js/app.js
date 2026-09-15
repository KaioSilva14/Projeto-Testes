/**
 * Comportamentos de interface.
 *
 * Tudo aqui e progressivo: com JavaScript desativado as telas continuam
 * funcionando (o seletor de mes tem botao de envio em <noscript>, e os
 * formularios de exclusao simplesmente nao pedem confirmacao).
 */
(function () {
    'use strict';

    /** Fecha um aviso ao clicar no X. */
    function ligarDispensarAvisos() {
        document.addEventListener('click', function (evento) {
            var botao = evento.target.closest('[data-dismiss]');

            if (!botao) {
                return;
            }

            var alerta = botao.closest('.alert');

            if (alerta) {
                alerta.remove();
            }
        });
    }

    /** Envia o formulario assim que o mes e alterado. */
    function ligarAutoSubmit() {
        document.querySelectorAll('[data-auto-submit]').forEach(function (campo) {
            campo.addEventListener('change', function () {
                if (campo.form) {
                    campo.form.submit();
                }
            });
        });
    }

    /**
     * Pede confirmacao antes de enviar formularios destrutivos.
     *
     * A mensagem vem do atributo data-confirm, ja escapado pelo PHP.
     */
    function ligarConfirmacoes() {
        document.addEventListener('submit', function (evento) {
            var formulario = evento.target;

            if (!(formulario instanceof HTMLFormElement)) {
                return;
            }

            var mensagem = formulario.dataset.confirm;

            if (mensagem && !window.confirm(mensagem)) {
                evento.preventDefault();
            }
        });
    }

    /**
     * Normaliza o campo de valor ao sair do foco: "1234.5" vira "1234,50".
     * O servidor aceita os dois formatos; isso apenas deixa a tela coerente.
     */
    function ligarFormatacaoDeValor() {
        document.querySelectorAll('input[name="amount"], input[name^="orcamentos"]').forEach(function (campo) {
            campo.addEventListener('blur', function () {
                var bruto = campo.value.trim();

                if (bruto === '') {
                    return;
                }

                var normalizado = bruto.replace(/\s|R\$/g, '');

                // Decide qual separador e o decimal: o que aparece mais a direita.
                var ultimaVirgula = normalizado.lastIndexOf(',');
                var ultimoPonto = normalizado.lastIndexOf('.');
                var decimal = ultimaVirgula > ultimoPonto ? ',' : '.';

                var partes = normalizado.split(decimal);
                var inteiro = partes.slice(0, -1).join('') || partes[0];
                var fracao = partes.length > 1 ? partes[partes.length - 1] : '';

                inteiro = inteiro.replace(/[^0-9-]/g, '');
                fracao = fracao.replace(/[^0-9]/g, '');

                // Sem separador decimal ou com 3 digitos: trata como milhar.
                if (partes.length === 1 || fracao.length === 3) {
                    inteiro = normalizado.replace(/[^0-9-]/g, '');
                    fracao = '';
                }

                var numero = parseFloat(inteiro + '.' + (fracao || '0'));

                if (!isNaN(numero)) {
                    campo.value = numero.toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                }
            });
        });
    }

    /** Some com os avisos de sucesso depois de alguns segundos. */
    function ligarAutoFecharSucesso() {
        document.querySelectorAll('.alert--success').forEach(function (alerta) {
            window.setTimeout(function () {
                alerta.style.transition = 'opacity .4s';
                alerta.style.opacity = '0';
                window.setTimeout(function () {
                    alerta.remove();
                }, 400);
            }, 6000);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        ligarDispensarAvisos();
        ligarAutoSubmit();
        ligarConfirmacoes();
        ligarFormatacaoDeValor();
        ligarAutoFecharSucesso();
    });
}());
