<?php

declare(strict_types=1);

namespace Tests\Unit;

use Account;
use Expense;
use PaylaterPlan;
use TestDb;
use Tests\Support\AppTestCase;

/**
 * Decimal-safety tests: financial totals must remain exact to two places and
 * never drift through repeated floating-point arithmetic.
 */
final class PrecisionTest extends AppTestCase
{
    public function testClassicFloatingPointSum(): void
    {
        $acc = TestDb::makeSavings('TEST_Savings_A', 0.0, '2026-01');
        $cat = TestDb::categoryId();
        Expense::addTransaction($cat, 0.1, 'expense', 'a', '2026-02-01', $acc);
        Expense::addTransaction($cat, 0.2, 'expense', 'b', '2026-02-01', $acc);
        $this->assertMoney(-0.3, Account::balance($acc));
    }

    public function testManySmallAmountsStayExact(): void
    {
        $acc = TestDb::makeSavings('TEST_Savings_A', 0.0, '2026-01');
        $cat = TestDb::categoryId();
        for ($i = 0; $i < 100; $i++) {
            Expense::addTransaction($cat, 0.01, 'expense', "x{$i}", '2026-02-01', $acc);
        }
        $this->assertMoney(-1.0, Account::balance($acc));
    }

    public function testThirdDivisionTotalsExactly(): void
    {
        $acc = TestDb::makePaylater('TEST_BNPL', 'per_purchase', null, 0);
        $plan = PaylaterPlan::createPurchase($acc, TestDb::categoryId(), 'div', '2026-09-01', 100.0, 3, null, 0);
        $sum = 0.0;
        foreach (PaylaterPlan::installments($plan) as $i) {
            $sum += (float) $i['amount'];
        }
        $this->assertMoney(100.0, $sum);
        $this->assertMoney(100.0, Account::balance($acc));
    }

    public function testLargeAmountsStayExact(): void
    {
        $acc = TestDb::makeSavings('TEST_Savings_A', 0.0, '2026-01');
        Expense::addTransaction(TestDb::categoryId(), 1234567.89, 'expense', 'big', '2026-02-01', $acc);
        $this->assertMoney(-1234567.89, Account::balance($acc));
    }
}
