<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;
use TestDb;

abstract class AppTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestDb::reset();
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        TestDb::cleanup();
        parent::tearDown();
    }

    protected function assertMoney(float $expected, float $actual, string $message = ''): void
    {
        $this->assertSame(round($expected, 2), round($actual, 2), $message);
    }
}
