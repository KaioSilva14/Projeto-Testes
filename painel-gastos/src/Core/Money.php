<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Valores monetarios circulam sempre como centavos (int) para evitar
 * erros de arredondamento de ponto flutuante.
 */
final class Money
{
    /**
     * Converte entrada do usuario em centavos.
     *
     * Aceita "1.234,56" (pt-BR), "1,234.56" (en-US), "1234.56" e "R$ 99,90".
     * Um separador unico seguido de exatamente 3 digitos e tratado como
     * separador de milhar ("1.500" -> 150000 centavos).
     *
     * Retorna null quando a entrada nao representa um numero.
     */
    public static function toCents(string $input): ?int
    {
        $raw = preg_replace('/[^0-9,.-]/', '', trim($input)) ?? '';

        if ($raw === '' || $raw === '-') {
            return null;
        }

        $negative = str_starts_with($raw, '-');
        $raw      = ltrim($raw, '-');

        if ($raw === '') {
            return null;
        }

        $lastComma = strrpos($raw, ',');
        $lastDot   = strrpos($raw, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimal = $lastComma > $lastDot ? ',' : '.';
        } elseif ($lastComma !== false) {
            $decimal = ',';
        } elseif ($lastDot !== false) {
            $decimal = '.';
        } else {
            $decimal = '';
        }

        if ($decimal !== '') {
            $position    = (int) strrpos($raw, $decimal);
            $decimals    = strlen($raw) - $position - 1;
            $occurrences = substr_count($raw, $decimal);

            // Separador unico com 3 casas -> milhar, nao decimal.
            if ($occurrences === 1 && $decimals === 3) {
                $decimal = '';
            }
        }

        if ($decimal === '') {
            $normalized = preg_replace('/[,.]/', '', $raw) ?? '0';
        } else {
            $position    = (int) strrpos($raw, $decimal);
            $integerPart = preg_replace('/[,.]/', '', substr($raw, 0, $position)) ?? '0';
            $decimalPart = substr($raw, $position + 1);
            $normalized  = ($integerPart === '' ? '0' : $integerPart) . '.' . $decimalPart;
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        $cents = (int) round(((float) $normalized) * 100);

        return $negative ? -$cents : $cents;
    }

    /** Formata centavos como moeda completa: "R$ 1.234,56". */
    public static function format(int $cents): string
    {
        $symbol = (string) Config::get('app.currency', 'R$');

        return $symbol . ' ' . self::formatPlain($cents);
    }

    /** Formata centavos sem simbolo: "1.234,56". */
    public static function formatPlain(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }

    /** Formato aceito por inputs numericos e CSV: "1234.56". */
    public static function toInputValue(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** Versao compacta para rotulos de grafico: "R$ 1,2 mil". */
    public static function formatCompact(int $cents): string
    {
        $value  = $cents / 100;
        $symbol = (string) Config::get('app.currency', 'R$');

        if (abs($value) >= 1000000) {
            return $symbol . ' ' . number_format($value / 1000000, 1, ',', '.') . ' mi';
        }

        if (abs($value) >= 1000) {
            return $symbol . ' ' . number_format($value / 1000, 1, ',', '.') . ' mil';
        }

        return self::format($cents);
    }
}
