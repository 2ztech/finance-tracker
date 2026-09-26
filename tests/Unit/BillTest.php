<?php

declare(strict_types=1);

namespace Tests\Unit;

use Account;
use Bill;
use Expense;
use PaylaterPlan;
use TestDb;
use Tests\Support\AppTestCase;

final class BillTest extends AppTestCase
{
    private int $cat;
    private int $savings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cat = TestDb::categoryId();
        $this->savings = TestDb::makeSavings('TEST_Savings_A', 2000.0, '2026-01');
    }

    public function testCreditStatementAmountAndDueDate(): void
    {
        $cc = TestDb::makeCredit('TEST_Credit', 20, 5000.0);
        Expense::addTransaction($this->cat, 100.0, 'expense', 'buy', '2026-09-05', $cc);
        Expense::addTransaction($this->cat, 20.0, 'income', 'refund', '2026-09-06', $cc);
        Bill::sync($cc);

        $stmt = null;
        foreach (Bill::forAccount($cc) as $b) {
            if ($b['due_date'] === '2026-10-20') {
                $stmt = $b;
            }
        }
        $this->assertNotNull($stmt);
        $this->assertMoney(80.0, (float) $stmt['amount_due']);
    }

    public function testCreditFullPaymentClearsOutstanding(): void
    {
        $cc = TestDb::makeCredit('TEST_Credit', 20, 5000.0);
        Expense::addTransaction($this->cat, 80.0, 'expense', 'buy', '2026-09-05', $cc);
        Bill::sync($cc);
        Bill::payDue($cc, '2026-10-20', 80.0, $this->savings, '2026-10-20');

        $this->assertMoney(0.0, Account::balance($cc));
        $this->assertMoney(1920.0, Account::balance($this->savings));
        $stmt = null;
        foreach (Bill::forAccount($cc) as $b) {
            if ($b['due_date'] === '2026-10-20') {
                $stmt = $b;
            }
        }
        $this->assertSame('paid', $stmt['status']);
    }

    public function testCreditOverpaymentCreatesCreditBalance(): void
    {
        $cc = TestDb::makeCredit('TEST_Credit', 20, 5000.0);
        Expense::addTransaction($this->cat, 50.0, 'expense', 'buy', '2026-09-05', $cc);
        Bill::sync($cc);
        Bill::payDue($cc, '2026-10-20', 70.0, $this->savings, '2026-10-20');
        // Paid 20 more than owed -> negative outstanding (credit).
        $this->assertMoney(-20.0, Account::balance($cc));
    }

    public function testPaylaterPartialThenRepeatedThenFull(): void
    {
        $acc = TestDb::makePaylater('TEST_BNPL', 'cycle', 10, 1);
        PaylaterPlan::createPurchase($acc, $this->cat, 'item', '2026-09-05', 30.0, 3, null, 0);

        // Partial 5 of the 10 due in October.
        $this->assertMoney(5.0, Bill::payDue($acc, '2026-10-10', 5.0, $this->savings, '2026-10-11'));
        $oct = $this->billByDue($acc, '2026-10-10');
        $this->assertSame('partial', $oct['status']);
        $this->assertMoney(5.0, (float) $oct['paid_amount']);

        // Pay the remaining 5.
        $this->assertMoney(5.0, Bill::payDue($acc, '2026-10-10', 99.0, $this->savings, '2026-10-12'));
        $oct = $this->billByDue($acc, '2026-10-10');
        $this->assertSame('paid', $oct['status']);
        $this->assertMoney(20.0, Account::balance($acc));
    }

    public function testInvalidPaymentDoesNothing(): void
    {
        $acc = TestDb::makePaylater('TEST_BNPL', 'cycle', 10, 1);
        PaylaterPlan::createPurchase($acc, $this->cat, 'item', '2026-09-05', 30.0, 3, null, 0);
        $this->assertMoney(0.0, Bill::payDue($acc, '2026-10-10', 0.0, $this->savings, '2026-10-11'));
        $this->assertMoney(0.0, Bill::payDue($acc, '2026-10-10', -5.0, $this->savings, '2026-10-11'));
        $this->assertSame(0, TestDb::count('transfers'));
    }

    public function testBillOutstandingHelper(): void
    {
        $acc = TestDb::makePaylater('TEST_BNPL', 'cycle', 10, 1);
        PaylaterPlan::createPurchase($acc, $this->cat, 'item', '2026-09-05', 30.0, 3, null, 0);
        $this->assertMoney(30.0, Bill::outstanding($acc));
    }

    private function billByDue(int $accountId, string $due): array
    {
        foreach (Bill::forAccount($accountId) as $b) {
            if ($b['due_date'] === $due) {
                return $b;
            }
        }
        $this->fail("No bill for due date {$due}");
    }
}
