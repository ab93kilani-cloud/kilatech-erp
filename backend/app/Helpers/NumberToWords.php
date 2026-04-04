<?php

namespace App\Helpers;

class NumberToWords
{
    private static $units = [
        0 => "zéro", 1 => "un", 2 => "deux", 3 => "trois", 4 => "quatre",
        5 => "cinq", 6 => "six", 7 => "sept", 8 => "huit", 9 => "neuf",
        10 => "dix", 11 => "onze", 12 => "douze", 13 => "treize", 14 => "quatorze",
        15 => "quinze", 16 => "seize", 17 => "dix-sept", 18 => "dix-huit", 19 => "dix-neuf"
    ];

    private static $tens = [
        20 => "vingt", 30 => "trente", 40 => "quarante", 50 => "cinquante", 
        60 => "soixante", 70 => "soixante-dix", 80 => "quatre-vingt", 90 => "quatre-vingt-dix"
    ];

    public static function convert($number)
    {
        if (!is_numeric($number)) return "Montant invalide";

        $number = number_format($number, 2, '.', '');
        list($integerPart, $decimalPart) = explode('.', $number);

        $words = self::convertInteger((int)$integerPart);

        if ((int)$decimalPart > 0) {
            $words .= ' MAD et ' . self::convertInteger((int)$decimalPart) . ' centimes';
        } else {
            $words .= ' MAD';
        }

        return trim($words);
    }

    private static function convertInteger($number)
    {
        if ($number < 20) {
            return self::$units[$number];
        }

        if ($number < 100) {
            $ten = floor($number / 10) * 10;
            $unit = $number % 10;

            if ($ten == 70 || $ten == 90) {
                $ten -= 10;
                $unit += 10;
            }

            $word = self::$tens[$ten];
            if ($unit > 0) {
                $separator = ($unit == 1 && $ten != 80) ? " et " : "-";
                // Cas special 71 -> soixante et onze
                if ($unit == 11 && $ten == 60) $separator = " et ";
                $word .= $separator . self::$units[$unit];
            } else if ($ten == 80) {
                $word .= "s";
            }
            return $word;
        }

        if ($number < 1000) {
            $hundreds = floor($number / 100);
            $rest = $number % 100;

            $word = ($hundreds == 1) ? "cent" : self::$units[$hundreds] . " cent";
            if ($rest == 0 && $hundreds > 1) $word .= "s";
            
            if ($rest > 0) {
                $word .= " " . self::convertInteger($rest);
            }
            return $word;
        }

        if ($number < 1000000) {
            $thousands = floor($number / 1000);
            $rest = $number % 1000;

            $word = ($thousands == 1) ? "mille" : self::convertInteger($thousands) . " mille";
            
            if ($rest > 0) {
                $word .= " " . self::convertInteger($rest);
            }
            return $word;
        }

        if ($number < 1000000000) {
            $millions = floor($number / 1000000);
            $rest = $number % 1000000;

            $word = ($millions == 1) ? "un million" : self::convertInteger($millions) . " millions";
            
            if ($rest > 0) {
                $word .= " " . self::convertInteger($rest);
            }
            return $word;
        }

        return (string)$number;
    }
}
