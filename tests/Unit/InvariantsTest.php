<?php

declare(strict_types=1);

namespace Tests\Unit;

use Account;
use Bill;
use Expense;
use PaylaterPlan;
use TestDb;
use Tests\Support\AppTestCase;
use Transfer;

/**
 * Financial invariant tests: properties that must always hold regardless of
 * the operation sequence.
 */
final class InvariantsTest extends AppTestCase
{
    private int $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cat = TestDb::categoryId();
    }

    public function testTransferInvariant(): void
    {
        $a = TestDb::makeSavings('TEST_Savings_A', 500.0, '2026-01');
        $b = TestDb::makeSavings('TEST_Savings_B', 500.0, '2026-01');
        Transfer::create($a, $b, 137.77, '2026-02-01', 'inv');
        $effect = (Account::balance($a) - 500.0) + (Account::balance($b) - 500.0);
        $this->assertMoney(0.0, $effect);
    }

    public function testDeleteReversesFinancialEffect(): void
    {
        $a = TestDb::makeSavings('TEST_Savings_A', 500.0, '2026-01');
        Expense::addTransaction($this->cat, 88.88, 'expense', 'x', '2026-02-01', $a);
        $after = Account::balance($a);
        $id = (int) TestDb::scalar('SELECT id FROM transactions LIMIT 1');
        Expense::deleteTransaction($id);
        $this->assertMoney(500.0, Account::balance($a));
        $this->assertNotEquals($after, Account::balance($a));
    }

    public function testPaymentInvariantForLiability(): void
    {
        $sav = TestDb::makeSavings('TEST_Savings_A', 1000.0, '2026-01');
        $bnpl = TestDb::makePaylater('TEST_BNPL', 'cycle', 10, 1);
        $plan = PaylaterPlan::createPurchase($bnpl, $this->cat, 'P1', '2026-09-05', 300.0, 3, null, 0);
        $original = Account::balance($bnpl); // 300

        $due = PaylaterPlan::installments($plan)[0]['due_date'];
        Bill::payDue($bnpl, $due, 100.0, $sav, $due);

        // New charge added after the payment.
        PaylaterPlan::createPurchase($bnpl, $this->cat, 'P2', '2026-09-20', 50.0, 1, null, 0);

        $remaining = Account::balance($bnpl);
        $this->assertMoney($original - 100.0 + 50.0, $remaining); // 250
    }

    public function testRefundNeverMakesRemainingNegative(): void
    {
        $bnpl = TestDb::makePaylater('TEST_BNPL', 'per_purchase', null, 0);
        $plan = PaylaterPlan::createPurchase($bnpl, $this->cat, 'R', '2026-09-01', 100.0, 4, null, 0);
        PaylaterPlan::refund($plan, 250.0, '2026-09-05');
        $this->assertGreaterThanOrEqual(0.0, PaylaterPlan::remaining($plan));
    }

    public function testAccountIsolation(): void
    {
        $a = TestDb::makeSavings('TEST_Savings_A', 100.0, '2026-01');
        $b = TestDb::makeSavings('TEST_Savings_B', 100.0, '2026-01');
        $bnpl = TestDb::makeCredit('TEST_Credit', 20, 1000.0);

        Expense::addTransaction($this->cat, 30.0, 'expense', 'a', '2026-02-01', $a);
        Expense::addTransaction($this->cat, 10.0, 'expense', 'c', '2026-02-01', $bnpl);

        $this->assertMoney(70.0, Account::balance($a));
        $this->assertMoney(100.0, Account::balance($b));
        $this->assertMoney(10.0, Account::balance($bnpl));
    }

    public function testBillTotalEqualsSumOfInstallments(): void
    {
        $bnpl = TestDb::makePaylater('TEST_BNPL', 'cycle', 10, 1);
        PaylaterPlan::createPurchase($bnpl, $this->cat, 'A', '2026-09-05', 30.0, 3, null, 0);
        PaylaterPlan::createPurchase($bnpl, $this->cat, 'B', '2026-09-05', 100.0, 4, null, 0);

        $billTotal = 0.0;
        foreach (Bill::forAccount($bnpl) as $b) {
            $billTotal += (float) $b['amount_due'];
        }
        $this->assertMoney(130.0, $billTotal);
    }
}
