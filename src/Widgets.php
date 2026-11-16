<?php

declare(strict_types=1);

/**
 * Widget catalog and analytics layout validation.
 *
 * The catalog is the contract shared with the UI: it describes every supported
 * widget, its config fields and its default size. Layouts are stored as JSON in
 * the existing settings key/value table.
 */
final class Widgets
{
    public const VERSION = 1;
    public const MAX_WIDGETS = 12;
    public const SETTING_KEY = 'analytics_layout';
    public const SIZES = ['half', 'wide', 'full'];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $index = null;

    /** @return array<int, array<string, mixed>> */
    public static function catalog(): array
    {
        $chartField = static fn (array $options, string $default): array => [
            'name' => 'chart',
            'label' => 'Chart type',
            'type' => 'select',
            'options' => $options,
            'default' => $default,
        ];
        $topField = static fn (int $default): array => [
            'name' => 'top',
            'label' => 'Number of items',
            'type' => 'select',
            'options' => [
                ['value' => 5, 'label' => 'Top 5'],
                ['value' => 8, 'label' => 'Top 8'],
                ['value' => 10, 'label' => 'Top 10'],
            ],
            'default' => $default,
        ];

        return [
            [
                'key' => 'income_expense',
                'label' => 'Income vs Expenses',
                'group' => 'Income',
                'description' => 'Monthly income and expenses across the selected range.',
                'type' => 'chart',
                'scope' => 'transactions',
                'range_semantics' => 'period',
                'default_size' => 'full',
                'sizes' => self::SIZES,
                'default_config' => ['chart' => 'bar'],
                'config_fields' => [$chartField([
                    ['value' => 'bar', 'label' => 'Bar'],
                    ['value' => 'line', 'label' => 'Line'],
                ], 'bar')],
            ],
            [
                'key' => 'spending_category',
                'label' => 'Spending by Category',
                'group' => 'Spending',
                'description' => 'Expense totals grouped by category.',
                'type' => 'chart',
                'scope' => 'transactions',
                'range_semantics' => 'period',
                'default_size' => 'half',
                'sizes' => self::SIZES,
                'default_config' => ['chart' => 'doughnut', 'top' => 8],
                'config_fields' => [
                    $chartField([
                        ['value' => 'doughnut', 'label' => 'Doughnut'],
                        ['value' => 'bar', 'label' => 'Bar'],
                    ], 'doughnut'),
                    $topField(8),
                ],
            ],
            [
                'key' => 'spending_institution',
                'label' => 'Spending by Institution',
                'group' => 'Institutions',
                'description' => 'Expense totals by institution; switches to sections when one institution is selected.',
                'type' => 'chart',
                'scope' => 'transactions',
                'range_semantics' => 'period',
                'default_size' => 'half',
                'sizes' => self::SIZES,
                'default_config' => [],
                'config_fields' => [],
            ],
            [
                'key' => 'top_merchants',
                'label' => 'Top Merchants',
                'group' => 'Spending',
                'description' => 'Largest expense descriptions across the selected range.',
                'type' => 'chart',
                'scope' => 'transactions',
                'range_semantics' => 'period',
                'default_size' => 'half',
                'sizes' => self::SIZES,
                'default_config' => ['top' => 8],
                'config_fields' => [$topField(8)],
            ],
            [
                'key' => 'credit_utilization',
                'label' => 'Credit / PayLater Utilization',
                'group' => 'Liabilities',
                'description' => 'Outstanding balances against credit limits as of the range end.',
                'type' => 'chart',
                'scope' => 'accounts',
                'range_semantics' => 'snapshot',
                'default_size' => 'half',
                'sizes' => self::SIZES,
                'default_config' => [],
                'config_fields' => [],
            ],
            [
                'key' => 'net_worth',
                'label' => 'Net Worth Trend',
                'group' => 'Trends',
                'description' => 'Month-end net worth across the selected accounts.',
                'type' => 'chart',
                'scope' => 'accounts',
                'range_semantics' => 'timeline',
                'default_size' => 'full',
                'sizes' => self::SIZES,
                'default_config' => [],
                'config_fields' => [],
            ],
            [
                'key' => 'recurring_oneoff',
                'label' => 'Recurring vs One-off',
                'group' => 'Spending',
                'description' => 'Expense split between recurring schedules and one-off purchases.',
                'type' => 'chart',
                'scope' => 'transactions',
                'range_semantics' => 'period',
                'default_size' => 'half',
                'sizes' => self::SIZES,
                'default_config' => ['chart' => 'doughnut'],
                'config_fields' => [$chartField([
                    ['value' => 'doughnut', 'label' => 'Doughnut'],
                    ['value' => 'bar', 'label' => 'Bar'],
                ], 'doughnut')],
            ],
            [
                'key' => 'savings_rate',
                'label' => 'Savings Rate',
                'group' => 'Trends',
                'description' => 'Monthly share of income left after expenses.',
                'type' => 'chart',
                'scope' => 'transactions',
                'range_semantics' => 'timeline',
                'default_size' => 'full',
                'sizes' => self::SIZES,
                'default_config' => [],
                'config_fields' => [],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function index(): array
    {
        if (self::$index === null) {
            self::$index = [];
            foreach (self::catalog() as $widget) {
                self::$index[(string) $widget['key']] = $widget;
            }
        }
        return self::$index;
    }

    /** @param string $key */
    public static function find(string $key): ?array
    {
        return self::index()[$key] ?? null;
    }

    /** @return array{version:int,widgets:array<int, array<string, mixed>>} */
    public static function defaultLayout(): array
    {
        return [
            'version' => self::VERSION,
            'widgets' => [
                self::instance('w_income_expense', 'income_expense', 'full', ['chart' => 'bar']),
                self::instance('w_spending_category', 'spending_category', 'half', ['chart' => 'doughnut', 'top' => 8]),
                self::instance('w_spending_institution', 'spending_institution', 'half', []),
                self::instance('w_top_merchants', 'top_merchants', 'half', ['top' => 8]),
                self::instance('w_credit_utilization', 'credit_utilization', 'half', []),
            ],
        ];
    }

    /** @return array{version:int,widgets:array<int, array<string, mixed>>} */
    public static function load(): array
    {
        $raw = Settings::get(self::SETTING_KEY);
        if (!is_string($raw) || trim($raw) === '') {
            return self::defaultLayout();
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::defaultLayout();
        }
        if (self::validate($decoded) !== []) {
            return self::defaultLayout();
        }
        return self::normalize($decoded);
    }

    /**
     * @param array<string, mixed> $layout
     * @return array{version:int,widgets:array<int, array<string, mixed>>}
     */
    public static function save(array $layout): array
    {
        $errors = self::validate($layout);
        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }
        $normalized = self::normalize($layout);
        Settings::set(self::SETTING_KEY, (string) json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $normalized;
    }

    /** @return array{version:int,widgets:array<int, array<string, mixed>>} */
    public static function reset(): array
    {
        $layout = self::defaultLayout();
        Settings::set(self::SETTING_KEY, (string) json_encode($layout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $layout;
    }

    /**
     * Strict validation used by the API before saving.
     *
     * @param array<string, mixed> $layout
     * @return array<int, string>
     */
    public static function validate(array $layout): array
    {
        $errors = [];
        if (($layout['version'] ?? null) !== self::VERSION) {
            $errors[] = 'layout.version must be ' . self::VERSION . '.';
        }
        if (!isset($layout['widgets']) || !is_array($layout['widgets'])) {
            $errors[] = 'layout.widgets must be an array.';
            return $errors;
        }
        if (count($layout['widgets']) > self::MAX_WIDGETS) {
            $errors[] = 'A layout can contain at most ' . self::MAX_WIDGETS . ' widgets.';
        }

        $ids = [];
        foreach (array_values($layout['widgets']) as $i => $widget) {
            $path = 'widgets[' . $i . ']';
            if (!is_array($widget)) {
                $errors[] = $path . ' must be an object.';
                continue;
            }

            $id = $widget['id'] ?? null;
            if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id)) {
                $errors[] = $path . '.id must be 1-40 letters, numbers, dashes or underscores.';
            } elseif (isset($ids[$id])) {
                $errors[] = $path . '.id must be unique.';
            } else {
                $ids[$id] = true;
            }

            $key = $widget['key'] ?? null;
            $definition = is_string($key) ? self::find($key) : null;
            if ($definition === null) {
                $errors[] = $path . '.key is not a supported widget.';
                continue;
            }

            if (isset($widget['size']) && (!is_string($widget['size']) || !in_array($widget['size'], self::SIZES, true))) {
                $errors[] = $path . '.size must be half, wide or full.';
            }

            $config = $widget['config'] ?? [];
            if (!is_array($config)) {
                $errors[] = $path . '.config must be an object.';
                continue;
            }
            self::validateConfig($key, $config, $path, $errors);
        }

        return $errors;
    }

    /**
     * Lenient normalization used after validation/loading. Missing size/config
     * fields are filled with catalog defaults.
     *
     * @param array<string, mixed> $layout
     * @return array{version:int,widgets:array<int, array<string, mixed>>}
     */
    public static function normalize(array $layout): array
    {
        $widgets = [];
        foreach (($layout['widgets'] ?? []) as $widget) {
            if (!is_array($widget)) {
                continue;
            }
            $key = (string) ($widget['key'] ?? '');
            $definition = self::find($key);
            if ($definition === null) {
                continue;
            }
            $widgets[] = [
                'id' => (string) ($widget['id'] ?? ''),
                'key' => $key,
                'size' => in_array($widget['size'] ?? null, self::SIZES, true)
                    ? (string) $widget['size']
                    : (string) $definition['default_size'],
                'config' => self::sanitizeConfig($key, is_array($widget['config'] ?? null) ? $widget['config'] : []),
            ];
        }

        return ['version' => self::VERSION, 'widgets' => $widgets];
    }

    /** @return array<string, mixed> */
    private static function instance(string $id, string $key, string $size, array $config): array
    {
        return ['id' => $id, 'key' => $key, 'size' => $size, 'config' => $config];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<int, string> $errors
     */
    private static function validateConfig(string $key, array $config, string $path, array &$errors): void
    {
        $definition = self::find($key);
        if ($definition === null) {
            return;
        }
        $allowed = [];
        foreach ($definition['config_fields'] as $field) {
            $allowed[] = (string) $field['name'];
            $name = (string) $field['name'];
            if (!array_key_exists($name, $config)) {
                continue;
            }
            $value = $config[$name];
            if ($name === 'chart') {
                $values = array_column($field['options'], 'value');
                if (!is_string($value) || !in_array($value, $values, true)) {
                    $errors[] = $path . '.config.chart is invalid.';
                }
            } elseif ($name === 'top') {
                $value = is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
                $values = array_map(static fn (array $option): int => (int) $option['value'], $field['options']);
                if ($value === null || !in_array($value, $values, true)) {
                    $errors[] = $path . '.config.top is invalid.';
                }
            }
        }

        foreach (array_keys($config) as $name) {
            if (!in_array((string) $name, $allowed, true)) {
                $errors[] = $path . '.config.' . $name . ' is not supported.';
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function sanitizeConfig(string $key, array $config): array
    {
        $definition = self::find($key);
        if ($definition === null) {
            return [];
        }
        $clean = [];
        foreach ($definition['config_fields'] as $field) {
            $name = (string) $field['name'];
            $default = $field['default'];
            $value = $config[$name] ?? $default;
            if ($name === 'chart') {
                $values = array_column($field['options'], 'value');
                $clean[$name] = is_string($value) && in_array($value, $values, true) ? $value : $default;
            } elseif ($name === 'top') {
                $candidate = is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : 0;
                $values = array_map(static fn (array $option): int => (int) $option['value'], $field['options']);
                $clean[$name] = in_array($candidate, $values, true) ? $candidate : $default;
            }
        }
        return $clean;
    }
}
