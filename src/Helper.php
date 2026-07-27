<?php

declare(strict_types=1);

final class Helper
{
    /** @return array{year: string, month: string, reqMonth: string, prevMonth: string, nextMonth: string, currentDisplay: string} */
    public static function parseMonth(): array
    {
        $reqMonth = $_GET['month'] ?? date('Y-m');
        $parts = explode('-', $reqMonth);

        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            $year = $parts[0];
            $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
        } else {
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
