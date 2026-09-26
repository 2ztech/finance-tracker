<?php

declare(strict_types=1);

/** Small offline icon palette for common Malaysian finance brands and account types. */
final class AccountIcon
{
    /** @return array{mark:string, background:string, foreground:string} */
    public static function for(string $name, string $kind, string $colorHex = ''): array
    {
        $name = strtolower($name);
        $brands = [
            'maybank' => ['M', '#fff5c2', '#a47a00'],
            'cimb' => ['C', '#ffebed', '#d92332'],
            'tng' => ['T', '#e8efff', '#1749b8'],
            'touch n go' => ['T', '#e8efff', '#1749b8'],
            'grab' => ['G', '#e6f8ed', '#008c57'],
            'atome' => ['A', '#f1facd', '#526e00'],
            'tiktok' => ['♪', '#20212a', '#ffffff'],
            'shopee' => ['S', '#fff0e9', '#e4502c'],
        ];
        foreach ($brands as $needle => $colors) {
            if (str_contains($name, $needle)) {
                return ['mark' => $colors[0], 'background' => $colors[1], 'foreground' => $colors[2]];
            }
        }

        if (preg_match('/^#[0-9a-f]{6}$/i', $colorHex)) {
            $mark = match ($kind) {
                'credit' => 'C',
                'paylater' => 'P',
                default => 'B',
            };
            return ['mark' => $mark, 'background' => 'color-mix(in srgb, ' . $colorHex . ' 14%, white)', 'foreground' => $colorHex];
        }

        return match ($kind) {
            'credit' => ['mark' => 'C', 'background' => '#fff0e3', 'foreground' => '#b8651b'],
            'paylater' => ['mark' => 'P', 'background' => '#f0ecff', 'foreground' => '#7655c8'],
            default => ['mark' => 'B', 'background' => '#eaf1ff', 'foreground' => '#3155c8'],
        };
    }
}
