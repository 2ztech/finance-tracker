<?php

declare(strict_types=1);

namespace Tests\Unit;

use Settings;
use TestDb;
use Tests\Support\AppTestCase;
use Widgets;

final class WidgetsTest extends AppTestCase
{
    public function testCatalogContainsAllSupportedWidgets(): void
    {
        $catalog = Widgets::catalog();
        $keys = array_column($catalog, 'key');

        $this->assertCount(8, $catalog);
        foreach ([
            'income_expense',
            'spending_category',
            'spending_institution',
            'top_merchants',
            'credit_utilization',
            'net_worth',
            'recurring_oneoff',
            'savings_rate',
        ] as $key) {
            $this->assertContains($key, $keys);
        }
    }

    public function testDefaultLayoutIsValidAndHasFiveWidgets(): void
    {
        $layout = Widgets::defaultLayout();
        $this->assertSame(Widgets::VERSION, $layout['version']);
        $this->assertCount(5, $layout['widgets']);
        $this->assertSame([], Widgets::validate($layout));
    }

    public function testSaveLoadAndResetLayout(): void
    {
        $layout = Widgets::defaultLayout();
        $layout['widgets'][0]['size'] = 'wide';
        $layout['widgets'][0]['config'] = ['chart' => 'line'];

        $saved = Widgets::save($layout);
        $this->assertSame('wide', $saved['widgets'][0]['size']);
        $this->assertSame('line', $saved['widgets'][0]['config']['chart']);
        $this->assertNotFalse(Settings::get(Widgets::SETTING_KEY));

        $loaded = Widgets::load();
        $this->assertSame($saved, $loaded);

        $reset = Widgets::reset();
        $this->assertSame(Widgets::defaultLayout(), $reset);
        $this->assertSame($reset, Widgets::load());
    }

    public function testNormalizeFillsDefaults(): void
    {
        $normalized = Widgets::normalize([
            'version' => 1,
            'widgets' => [
                ['id' => 'w_one', 'key' => 'spending_category'],
            ],
        ]);

        $this->assertSame('half', $normalized['widgets'][0]['size']);
        $this->assertSame('doughnut', $normalized['widgets'][0]['config']['chart']);
        $this->assertSame(8, $normalized['widgets'][0]['config']['top']);
    }

    public function testEmptyLayoutIsAllowed(): void
    {
        $layout = ['version' => 1, 'widgets' => []];
        $this->assertSame([], Widgets::validate($layout));
        Widgets::save($layout);
        $this->assertSame(['version' => 1, 'widgets' => []], Widgets::load());
    }

    public function testValidationRejectsInvalidWidgets(): void
    {
        $base = ['id' => 'w1', 'key' => 'income_expense', 'size' => 'full', 'config' => ['chart' => 'bar']];

        $this->assertNotSame([], Widgets::validate(['version' => 2, 'widgets' => [$base]]));
        $this->assertNotSame([], Widgets::validate(['version' => 1, 'widgets' => [['key' => 'income_expense']]]));
        $this->assertNotSame([], Widgets::validate(['version' => 1, 'widgets' => [['id' => 'w1', 'key' => 'nope']]]));
        $this->assertNotSame([], Widgets::validate(['version' => 1, 'widgets' => [['id' => 'w1', 'key' => 'income_expense', 'size' => 'huge']]]));
        $this->assertNotSame([], Widgets::validate(['version' => 1, 'widgets' => [['id' => 'w1', 'key' => 'income_expense', 'config' => ['chart' => 'pie']]]]));
        $this->assertNotSame([], Widgets::validate(['version' => 1, 'widgets' => [['id' => 'w1', 'key' => 'top_merchants', 'config' => ['top' => 7]]]]));
        $this->assertNotSame([], Widgets::validate(['version' => 1, 'widgets' => [['id' => 'w1', 'key' => 'income_expense', 'config' => ['nope' => true]]]]));
        $this->assertNotSame([], Widgets::validate(['version' => 1, 'widgets' => [$base, $base]]));
    }

    public function testValidationRejectsTooManyWidgets(): void
    {
        $widgets = [];
        for ($i = 0; $i < Widgets::MAX_WIDGETS + 1; $i++) {
            $widgets[] = ['id' => 'w' . $i, 'key' => 'income_expense', 'size' => 'full', 'config' => ['chart' => 'bar']];
        }

        $this->assertNotSame([], Widgets::validate(['version' => 1, 'widgets' => $widgets]));
    }

    public function testInvalidStoredLayoutFallsBackToDefault(): void
    {
        Settings::set(Widgets::SETTING_KEY, '{bad json');
        $this->assertSame(Widgets::defaultLayout(), Widgets::load());

        Settings::set(Widgets::SETTING_KEY, json_encode(['version' => 1, 'widgets' => [['id' => 'x', 'key' => 'nope']]]));
        $this->assertSame(Widgets::defaultLayout(), Widgets::load());
    }
}
