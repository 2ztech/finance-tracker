<?php

declare(strict_types=1);

namespace Tests\Unit;

use Auth;
use Expense;
use Maintenance;
use TestDb;
use Tests\Support\AppTestCase;

final class MaintenanceTest extends AppTestCase
{
    public function testFreshDatabaseDoesNotCreateAUserAccount(): void
    {
        self::assertSame(0, TestDb::count('accounts'));
        self::assertSame(0, TestDb::count('transactions'));
        self::assertSame(0, TestDb::count('transfers'));
        self::assertSame(0, TestDb::count('bills'));
        self::assertSame(0, TestDb::count('paylater_plans'));
        self::assertSame(0, TestDb::count('commitments'));
        self::assertSame(0, TestDb::count('budgets'));
        self::assertGreaterThan(0, TestDb::count('categories'));
    }

    public function testLegacyAccountMigrationStillAttachesExistingFinancialData(): void
    {
        self::assertTrue(Auth::setupFirstUser('legacy-upgrade-user', 'upgrade-test-password'));
        $categoryId = TestDb::categoryId();
        self::assertTrue(Expense::addTransaction($categoryId, 12.34, 'expense', 'Legacy transaction', '2026-09-01'));
        TestDb::pdo()->exec("DELETE FROM settings WHERE key = 'accounts_seeded'");

        Maintenance::run();

        $accountId = TestDb::accountId('Maybank');
        self::assertGreaterThan(0, $accountId);
        self::assertSame($accountId, (int) TestDb::scalar("SELECT account_id FROM transactions WHERE description = 'Legacy transaction'"));
    }
}
