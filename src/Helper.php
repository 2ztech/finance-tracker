<?php

declare(strict_types=1);

final class Helper
{
    /** @return array{year: string, month: string, reqMonth: string, prevMonth: string, nextMonth: string, currentDisplay: string} */
    public static function parseMonth(): array
    {
        $reqMonth = $_GET['month'] ?? date('Y-m');
        $parts = explode('-', $reqMonth);

        $valid = false;
        if (count($parts) === 2 && ctype_digit($parts[0]) && ctype_digit($parts[1])) {
            $y = (int) $parts[0];
            $mo = (int) $parts[1];
            if ($y >= 1970 && $y <= 2999 && $mo >= 1 && $mo <= 12) {
                $year = (string) $y;
                $month = str_pad((string) $mo, 2, '0', STR_PAD_LEFT);
                $reqMonth = "$year-$month";
                $valid = true;
            }
        }

        if (!$valid) {
            $year = date('Y');
            $month = date('m');
            $reqMonth = "$year-$month";
        }

        return [
            'year'           => $year,
            'month'          => $month,
            'reqMonth'       => $reqMonth,
            'prevMonth'      => date('Y-m', strtotime($reqMonth . '-01 -1 month')),
            'nextMonth'      => date('Y-m', strtotime($reqMonth . '-01 +1 month')),
            'currentDisplay' => date('F Y', strtotime($reqMonth . '-01')),
        ];
    }
}
