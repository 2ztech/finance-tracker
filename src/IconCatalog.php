<?php

declare(strict_types=1);

final class IconCatalog
{
    private const PATHS = [
        'food' => '<path d="M3 3v7a4 4 0 004 4h1v7M7 3v4m4-4v4M16 3v18m0-18c3 2 4 5 4 8h-4"/>',
        'groceries' => '<path d="M4 8h16l-1 13H5L4 8zm4 0 4-5 4 5m-7 4v5m6-5v5"/>',
        'shopping' => '<path d="M5 8h14l1 13H4L5 8zm4 0a3 3 0 016 0"/>',
        'transport' => '<path d="M5 17l-1 3m15-3 1 3M4 17h16l-2-9H6l-2 9zm3-9 1-4h8l1 4M7 13h.01M17 13h.01"/>',
        'home' => '<path d="m3 11 9-8 9 8m-2-2v12H5V9m5 12v-7h4v7"/>',
        'utilities' => '<path d="m13 2-9 12h7l-1 8 10-13h-7l1-7z"/>',
        'entertainment' => '<path d="M6 10h12a4 4 0 014 4v3a3 3 0 01-5 2l-2-2H9l-2 2a3 3 0 01-5-2v-3a4 4 0 014-4zm2-3 1-4m7 4-1-4M7 14h4m-2-2v4m6-1h.01m3-2h.01"/>',
        'health' => '<path d="M12 21s-8-4.5-8-11a4.5 4.5 0 018-3 4.5 4.5 0 018 3c0 6.5-8 11-8 11zM12 8v6m-3-3h6"/>',
        'education' => '<path d="m2 9 10-5 10 5-10 5L2 9zm4 3v5c4 3 8 3 12 0v-5m4-3v7"/>',
        'clothing' => '<path d="m8 4 4 2 4-2 5 4-3 4-2-1v10H8V11l-2 1-3-4 5-4z"/>',
        'pet' => '<path d="M12 20c-4 0-7-2-7-5 0-2 2-4 4-5l3-5 3 5c2 1 4 3 4 5 0 3-3 5-7 5zM5 4h.01M9 3h.01M16 3h.01M20 5h.01"/>',
        'travel' => '<path d="m3 14 8-3V4a1 1 0 012 0v7l8 3v2l-8-1v4l2 1v1l-3-1-3 1v-1l2-1v-4l-8 1v-2z"/>',
        'gift' => '<path d="M3 10h18v11H3V10zm-1-4h20v4H2V6zm10 0v15M12 6H7a2 2 0 110-4c3 0 5 4 5 4zm0 0h5a2 2 0 100-4c-3 0-5 4-5 4z"/>',
        'subscription' => '<path d="M4 5h16v14H4zM10 9l5 3-5 3V9z"/>',
        'salary' => '<path d="M4 19V5m0 14h16M7 15l4-4 3 2 5-6m-4 0h4v4"/>',
        'work' => '<path d="M3 7h18v14H3V7zm5 0V4h8v3M3 12h18m-11 0v2h4v-2"/>',
        'investment' => '<path d="M4 19V5m0 14h16M7 15l4-5 3 3 5-7m-4 0h4v4"/>',
        'transfer' => '<path d="M4 8h15l-4-4m5 12H5l4 4"/>',
        'loan' => '<path d="M4 5h16v14H4zM8 10h8m-8 4h5m5-9-2-2"/>',
        'other' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/>',
    ];

    public static function keys(): array
    {
        return array_keys(self::PATHS);
    }

    public static function has(string $key): bool
    {
        return isset(self::PATHS[$key]);
    }

    public static function defaultForName(string $name): string
    {
        $name = strtolower($name);
        foreach ([
            'food' => ['food', 'dining', 'makan'],
            'groceries' => ['grocer', 'market'],
            'shopping' => ['shop', 'clothing', 'retail'],
            'transport' => ['transport', 'fuel', 'travel', 'motorcycle'],
            'home' => ['home', 'rent'],
            'utilities' => ['utilit', 'internet', 'phone'],
            'entertainment' => ['entertain', 'subscription', 'music', 'video'],
            'health' => ['health', 'medical', 'clinic'],
            'education' => ['education', 'school', 'course'],
            'pet' => ['pet'],
            'salary' => ['salary', 'income', 'wage'],
            'work' => ['work', 'business'],
            'investment' => ['invest'],
            'transfer' => ['transfer', 'refund', 'reimburse'],
            'loan' => ['loan', 'debt', 'paylater'],
        ] as $key => $needles) {
            foreach ($needles as $needle) if (str_contains($name, $needle)) return $key;
        }
        return 'other';
    }

    public static function svg(string $key, string $class = 'h-5 w-5'): string
    {
        $path = self::PATHS[$key] ?? self::PATHS['other'];
        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
    }
}
