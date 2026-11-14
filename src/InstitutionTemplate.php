<?php

declare(strict_types=1);

/**
 * Guided institution presets.
 *
 * An institution (the user-facing "Account") owns one or more sections
 * (Wallet, PayLater, Card, Savings). The wizard asks only human questions;
 * each section's technical fields (BNPL mode, offsets, partial payments) are
 * preset here and applied server-side.
 */
final class InstitutionTemplate
{
    /** @return array<int, array<string, mixed>> */
    public static function groups(): array
    {
        return [
            [
                'key' => 'ewallet',
                'label' => 'E-wallets & BNPL',
                'institutions' => [
                    self::institution('tiktok', 'TikTok', 'TikTok', '#FE2C55', 'tiktok', [
                        self::section('wallet', 'Wallet', 'TikTok Wallet', 'savings', [], [
                            self::qName(),
                            self::qMoney('balance', "What's your current balance?"),
                            self::qMain(),
                        ]),
                        self::section('paylater', 'PayLater', 'TikTok PayLater', 'paylater', [
                            'bnpl_mode' => 'cycle',
                            'first_due_offset' => 1,
                            'allow_partial' => 0,
                            'role' => 'bnpl',
                        ], [
                            self::qName(),
                            self::qMoney('credit_limit', "What's your credit limit?", true),
                            self::qChoice('choice', 'When do you get your statement?', [
                                ['value' => '1', 'label' => '1st of the month', 'due' => '10th', 'set' => ['statement_day' => 1, 'due_day' => 10], 'confirm' => 'Your payment is due on the 10th.'],
                                ['value' => '21', 'label' => '21st of the month', 'due' => '1st (next month)', 'set' => ['statement_day' => 21, 'due_day' => 1], 'confirm' => 'Your payment is due on the 1st of the following month.'],
                            ]),
                        ]),
                        self::section('financing', 'Personal Financing', 'TikTok Personal Financing', 'paylater', [
                            'bnpl_mode' => 'cycle',
                            'first_due_offset' => 1,
                            'allow_partial' => 0,
                            'role' => 'financing',
                        ], [
                            self::qName(),
                            self::qMoney('credit_limit', "What's your financing limit?", true),
                            self::qInfo('Add financing plans from the Financing page.'),
                        ]),
                    ]),
                    self::institution('grab', 'Grab', 'Grab', '#00B14F', 'grab', [
                        self::section('wallet', 'Wallet', 'GrabPay Wallet', 'savings', [], [
                            self::qName(),
                            self::qMoney('balance', "What's your current balance?"),
                            self::qMain(),
                        ]),
                        self::section('postpaid', 'Postpaid', 'Grab PayLater Postpaid', 'paylater', [
                            'bnpl_mode' => 'cycle',
                            'due_day' => 7,
                            'first_due_offset' => 1,
                            'allow_partial' => 0,
                            'role' => 'bnpl',
                        ], [
                            self::qName(),
                            self::qMoney('credit_limit', "What's your credit limit?", true),
                            self::qInfo('Grab Postpaid is due on the 7th of each month.'),
                        ]),
                        self::section('instalments', 'Instalments', 'Grab PayLater Instalments', 'paylater', [
                            'bnpl_mode' => 'per_purchase',
                            'first_due_offset' => 0,
                            'allow_partial' => 0,
                            'role' => 'bnpl',
                        ], [
                            self::qName(),
                            self::qMoney('credit_limit', "What's your credit limit?", true),
                            self::qInfo('Each purchase gets its own instalment schedule.'),
                        ]),
                    ]),
                    self::institution('shopee', 'Shopee', 'Shopee', '#EE4D2D', 'shopee', [
                        self::section('wallet', 'Wallet', 'ShopeePay Wallet', 'savings', [], [
                            self::qName(),
                            self::qMoney('balance', "What's your current balance?"),
                            self::qMain(),
                        ]),
                        self::section('spaylater', 'SPayLater', 'Shopee SPayLater', 'paylater', [
                            'bnpl_mode' => 'cycle',
                            'first_due_offset' => 1,
                            'allow_partial' => 0,
                            'role' => 'bnpl',
                        ], [
                            self::qName(),
                            self::qMoney('credit_limit', "What's your credit limit?", true),
                            self::qChoice('choice', 'When is your payment due?', [
                                ['value' => '1', 'label' => '1st of the month', 'due' => '1st', 'set' => ['due_day' => 1], 'confirm' => 'Your payment is due on the 1st.'],
                                ['value' => '10', 'label' => '10th of the month', 'due' => '10th', 'set' => ['due_day' => 10], 'confirm' => 'Your payment is due on the 10th.'],
                            ]),
                        ]),
                        self::section('sloan', 'SLoan', 'Shopee SLoan', 'paylater', [
                            'bnpl_mode' => 'cycle',
                            'first_due_offset' => 1,
                            'allow_partial' => 0,
                            'role' => 'financing',
                        ], [
                            self::qName(),
                            self::qMoney('credit_limit', "What's your SLoan credit limit?", true),
                            self::qInfo('Add loan withdrawals from the Financing page.'),
                        ]),
                    ]),
                    self::institution('tng', 'Touch \'n Go', 'Touch \'n Go eWallet', '#0057B8', 'tng', [
                        self::section('wallet', 'Wallet', 'Touch \'n Go eWallet', 'savings', [], [
                            self::qName(),
                            self::qMoney('balance', "What's your current balance?"),
                            self::qMain(),
                        ]),
                    ]),
                    self::institution('atome', 'Atome', 'Atome', '#84CC16', 'atome', [
                        self::section('card', 'Card', 'Atome Card', 'credit', [
                            'first_due_offset' => 1,
                            'allow_partial' => 0,
                        ], [
                            self::qName(),
                            self::qMoney('credit_limit', "What's your credit limit?", true),
                            self::qBillDate('When does your bill come out each month?', 10),
                        ]),
                        self::section('paylater', 'PayLater', 'Atome PayLater', 'paylater', [
                            'bnpl_mode' => 'per_purchase',
                            'first_due_offset' => 0,
                            'allow_partial' => 0,
                            'role' => 'bnpl',
                        ], [
                            self::qName(),
                            self::qMoney('credit_limit', "What's your credit limit?", true),
                            self::qInfo('Each purchase gets its own instalment schedule.'),
                        ]),
                        self::section('icash', 'i-Cash', 'Atome i-Cash', 'paylater', [
                            'bnpl_mode' => 'cycle',
                            'first_due_offset' => 1,
                            'allow_partial' => 0,
                            'role' => 'financing',
                        ], [
                            self::qName(),
                            self::qMoney('credit_limit', "What's your i-Cash credit limit?", true),
                            self::qInfo('Add cash financing from the Financing page.'),
                        ]),
                    ]),
                ],
            ],
            [
                'key' => 'banks',
                'label' => 'Malaysian banks',
                'institutions' => [
                    self::bank('maybank', 'Maybank', '#F2C200'),
                    self::bank('cimb', 'CIMB', '#EC1C24'),
                    self::bank('bankislam', 'Bank Islam', '#007A53'),
                    self::bank('rhb', 'RHB', '#005EB8'),
                    self::bank('bsn', 'BSN', '#00529B'),
                    self::bank('hongleong', 'Hong Leong', '#D71920'),
                ],
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $sections */
    private static function institution(string $key, string $label, string $defaultName, string $color, string $logo, array $sections): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'default_name' => $defaultName,
            'color_hex' => $color,
            'logo' => $logo,
            'sections' => $sections,
        ];
    }

    private static function bank(string $key, string $name, string $color): array
    {
        return self::institution($key, $name, $name, $color, $key, [
            self::section('savings', 'Savings', $name . ' Savings', 'savings', ['allow_multiple' => true], [
                self::qName(),
                self::qMoney('balance', "What's your current balance?"),
                self::qMain(),
            ]),
        ]);
    }

    /** @param array<string, mixed> $presets @param array<int, array<string, mixed>> $questions */
    private static function section(string $key, string $label, string $defaultName, string $kind, array $presets, array $questions): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'default_name' => $defaultName,
            'kind' => $kind,
            'presets' => $presets,
            'questions' => $questions,
        ];
    }

    private static function qName(): array
    {
        return ['type' => 'name'];
    }

    private static function qMoney(string $field, string $label, bool $optional = false): array
    {
        return ['type' => 'money', 'field' => $field, 'label' => $label, 'optional' => $optional];
    }

    private static function qMain(): array
    {
        return ['type' => 'main'];
    }

    private static function qInfo(string $text): array
    {
        return ['type' => 'info', 'text' => $text];
    }

    private static function qDay(string $field, string $label): array
    {
        return ['type' => 'day', 'field' => $field, 'label' => $label];
    }

    /** @param array<int, array<string, mixed>> $options */
    private static function qChoice(string $field, string $label, array $options): array
    {
        return ['type' => 'choice', 'field' => $field, 'label' => $label, 'options' => $options];
    }

    private static function qBillDate(string $label, int $dueOffset): array
    {
        return ['type' => 'bill_date', 'field' => 'bill_date', 'label' => $label, 'due_offset' => $dueOffset];
    }

    /**
     * Generic section types for custom institutions or products not in the
     * provider catalog.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function genericSections(): array
    {
        return [
            self::section('generic_savings', 'Savings / Wallet', 'Savings', 'savings', ['allow_multiple' => true], [
                self::qName(),
                self::qMoney('balance', "What's your current balance?"),
                self::qMain(),
            ]),
            self::section('generic_credit', 'Credit card', 'Credit card', 'credit', ['allow_multiple' => true], [
                self::qName(),
                self::qMoney('credit_limit', "What's your credit limit?", true),
                self::qDay('statement_day', 'When does your statement close each month?'),
                self::qDay('due_day', 'When is your payment due each month?'),
            ]),
            self::section('generic_paylater_monthly', 'PayLater · monthly bill', 'PayLater', 'paylater', [
                'bnpl_mode' => 'cycle',
                'first_due_offset' => 1,
                'allow_partial' => 0,
                'role' => 'bnpl',
                'allow_multiple' => false,
            ], [
                self::qName(),
                self::qMoney('credit_limit', "What's your credit limit?", true),
                self::qDay('due_day', 'When is your payment due each month?'),
            ]),
            self::section('generic_paylater_instalments', 'PayLater · instalments', 'PayLater', 'paylater', [
                'bnpl_mode' => 'per_purchase',
                'first_due_offset' => 0,
                'allow_partial' => 0,
                'role' => 'bnpl',
                'allow_multiple' => false,
            ], [
                self::qName(),
                self::qMoney('credit_limit', "What's your credit limit?", true),
                self::qInfo('Each purchase gets its own instalment schedule.'),
            ]),
            self::section('generic_financing', 'Personal Financing / Loan', 'Financing', 'paylater', [
                'bnpl_mode' => 'cycle',
                'first_due_offset' => 1,
                'allow_partial' => 0,
                'role' => 'financing',
                'allow_multiple' => false,
            ], [
                self::qName(),
                self::qMoney('credit_limit', "What's your credit limit?", true),
                self::qInfo('Add financing plans from the Financing page.'),
            ]),
        ];
    }

    /** Match an institution name to a catalog template (e.g. “Maybank 2”). */
    public static function findByName(?string $name): ?array
    {
        if ($name === null || $name === '') {
            return null;
        }
        $needle = strtolower($name);
        foreach (self::all() as $template) {
            if (str_contains($needle, strtolower((string) $template['key']))
                || str_contains($needle, strtolower((string) $template['default_name']))) {
                return $template;
            }
        }
        return null;
    }

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $out = [];
        foreach (self::groups() as $group) {
            foreach ($group['institutions'] as $institution) {
                $institution['group_key'] = $group['key'];
                $institution['group_label'] = $group['label'];
                $out[$institution['key']] = $institution;
            }
        }
        return $out;
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Bundled brand icon for an institution.
     *
     * @return array{data:string,mime:string}|null
     */
    public static function logoData(?string $logo): ?array
    {
        if ($logo === null || $logo === '') {
            return null;
        }
        $slug = preg_replace('/[^a-z0-9_-]/i', '', $logo);
        if ($slug === null || $slug === '') {
            return null;
        }
        $mimes = ['ico' => 'image/x-icon', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'webp' => 'image/webp'];
        foreach ($mimes as $ext => $mime) {
            $file = dirname(__DIR__) . '/public/assets/brands/' . $slug . '.' . $ext;
            if (is_file($file)) {
                $bytes = file_get_contents($file);
                if ($bytes !== false) {
                    return ['data' => base64_encode($bytes), 'mime' => $mime];
                }
            }
        }
        return null;
    }

    /** Best-effort bundled logo for a migrated institution by name. */
    public static function logoDataForName(?string $name): ?array
    {
        if ($name === null || $name === '') {
            return null;
        }
        $needle = strtolower($name);
        foreach (self::all() as $t) {
            if (str_contains($needle, strtolower($t['key'])) || str_contains($needle, strtolower($t['default_name']))) {
                return self::logoData($t['logo'] ?? null);
            }
        }
        return null;
    }

    /** Relative web path for a bundled logo (preferred over embedding bytes). */
    public static function logoUrl(?string $logo): ?string
    {
        if ($logo === null || $logo === '') {
            return null;
        }
        $slug = preg_replace('/[^a-z0-9_-]/i', '', $logo);
        if ($slug === null || $slug === '') {
            return null;
        }
        foreach (['ico', 'png', 'svg', 'webp'] as $ext) {
            if (is_file(dirname(__DIR__) . '/public/assets/brands/' . $slug . '.' . $ext)) {
                return '/assets/brands/' . $slug . '.' . $ext;
            }
        }
        return null;
    }

    /** Best-effort bundled logo URL for a migrated institution by name. */
    public static function logoUrlForName(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }
        $needle = strtolower($name);
        foreach (self::all() as $t) {
            if (str_contains($needle, strtolower($t['key'])) || str_contains($needle, strtolower($t['default_name']))) {
                return self::logoUrl($t['logo'] ?? null);
            }
        }
        return null;
    }

    /**
     * Build institution + section creation data from the wizard payload.
     *
     * @param array<string, mixed> $input
     * @return array{institution:array<string,mixed>,sections:array<int,array<string,mixed>>}|null
     */
    public static function apply(string $key, array $input): ?array
    {
        $t = self::find($key);
        if ($t === null) {
            return null;
        }

        $answers = is_array($input['sections'] ?? null) ? $input['sections'] : [];
        $single = count($t['sections']) === 1;
        $sections = [];
        $sort = 0;
        $mainAssigned = false;

        foreach ($t['sections'] as $def) {
            $in = is_array($answers[$def['key']] ?? null) ? $answers[$def['key']] : [];
            $enabled = $single || self::bool($in['enabled'] ?? null);
            if (!$enabled) {
                continue;
            }

            $data = [
                'name' => trim((string) ($in['name'] ?? $def['default_name'])),
                'kind' => $def['kind'],
                'template_key' => $key . ':' . $def['key'],
                'color_hex' => $t['color_hex'],
                'icon_data' => null,
                'icon_mime' => 'image/png',
                'bnpl_mode' => $def['presets']['bnpl_mode'] ?? null,
                'statement_day' => $def['presets']['statement_day'] ?? null,
                'due_day' => $def['presets']['due_day'] ?? null,
                'first_due_offset' => (int) ($def['presets']['first_due_offset'] ?? 1),
                'allow_partial' => (int) ($def['presets']['allow_partial'] ?? 0),
                'paylater_role' => $def['kind'] === 'paylater' ? ($def['presets']['role'] ?? 'bnpl') : null,
                'no_interest_months' => null,
                'credit_limit' => self::money($in['credit_limit'] ?? null),
                'opening_balance' => 0.0,
                'start_month' => $def['kind'] === 'savings' ? date('Y-m') : null,
                'is_primary' => 0,
                'sort_order' => $sort++,
            ];

            if ($def['kind'] === 'savings') {
                $data['opening_balance'] = self::money($in['balance'] ?? null) ?? 0.0;
                // Only one main account can exist; the first chosen wins.
                if (!$mainAssigned && self::bool($in['make_main'] ?? null)) {
                    $data['is_primary'] = 1;
                    $mainAssigned = true;
                }
            } else {
                // Liability outstanding accrues from transactions; never seed it
                // from the wizard (a positive ledger opening would show negative).
                $data['opening_balance'] = 0.0;
            }

            foreach ($def['questions'] as $q) {
                if (($q['type'] ?? '') === 'choice' && isset($in['choice'])) {
                    $selected = (string) $in['choice'];
                    foreach ($q['options'] as $option) {
                        if ((string) $option['value'] === $selected) {
                            foreach ($option['set'] as $field => $value) {
                                $data[$field] = $value;
                            }
                            break;
                        }
                    }
                }
                if (($q['type'] ?? '') === 'bill_date' && isset($in['bill_date'])) {
                    $bill = max(1, min(31, (int) $in['bill_date']));
                    $data['statement_day'] = max(1, $bill - 1);
                    $data['due_day'] = min(31, $bill + (int) ($q['due_offset'] ?? 0));
                }
            }

            // Clamp/validate the fields the app understands.
            $data['statement_day'] = self::day($data['statement_day']);
            $data['due_day'] = self::day($data['due_day']);
            if ($data['name'] === '') {
                $data['name'] = (string) $def['default_name'];
            }

            $sections[] = $data;
        }

        if ($sections === []) {
            return null;
        }

        return [
            'institution' => [
                'name' => (string) $t['default_name'],
                'color_hex' => (string) $t['color_hex'],
                // Bundled logos are served from /assets/brands, not stored per row.
                'icon_data' => null,
                'icon_mime' => 'image/png',
            ],
            'sections' => $sections,
        ];
    }

    private static function day(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $day = (int) $value;
        return ($day >= 1 && $day <= 31) ? $day : null;
    }

    private static function money(mixed $value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }

    private static function bool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'on';
    }
}
