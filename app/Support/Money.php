<?php

namespace App\Support;

/**
 * Ghana cedi formatting helpers for receipts and reports.
 */
class Money
{
    public static function format(float|int|string|null $amount): string
    {
        return 'GH₵ ' . number_format((float) $amount, 2);
    }

    /**
     * Amount in words for receipts, e.g. 1250.50 -> "One Thousand Two Hundred and Fifty
     * Ghana Cedis, Fifty Pesewas".
     */
    public static function words(float $amount): string
    {
        $amount = round(abs($amount), 2);
        $cedis = (int) floor($amount);
        $pesewas = (int) round(($amount - $cedis) * 100);

        $text = ($cedis === 0 ? 'Zero' : self::integerToWords($cedis)) . ' Ghana Cedi' . ($cedis === 1 ? '' : 's');

        if ($pesewas > 0) {
            $text .= ', ' . self::integerToWords($pesewas) . ' Pesewa' . ($pesewas === 1 ? '' : 's');
        }

        return $text . ' Only';
    }

    private static function integerToWords(int $number): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        if ($number < 20) {
            return $ones[$number];
        }

        if ($number < 100) {
            return $tens[intdiv($number, 10)] . ($number % 10 ? '-' . $ones[$number % 10] : '');
        }

        if ($number < 1000) {
            $rest = $number % 100;

            return $ones[intdiv($number, 100)] . ' Hundred' . ($rest ? ' and ' . self::integerToWords($rest) : '');
        }

        foreach ([1000000000 => 'Billion', 1000000 => 'Million', 1000 => 'Thousand'] as $size => $name) {
            if ($number >= $size) {
                $rest = $number % $size;

                return self::integerToWords(intdiv($number, $size)) . ' ' . $name
                    . ($rest ? ($rest < 100 ? ' and ' : ' ') . self::integerToWords($rest) : '');
            }
        }

        return (string) $number;
    }
}
