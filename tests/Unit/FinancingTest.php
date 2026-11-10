<?php

declare(strict_types=1);

namespace Tests\Unit;

use Account;
use Bill;
use Expense;
use PaylaterPlan;
use TestDb;
use Tests\Support\AppTestCase;

final class FinancingTest extends AppTestCase
{
    private int $cat;
    private int $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cat = TestDb::categoryId();
        $this->cash = TestDb::makeSavings('TEST_CASH', 1000.0, '2026-01');
    }

    private function billByDue(int $accountId, string $due): ?array
    {
        foreach (Bill::forAccount($accountId) as $b) {
            if ((string) $b['due_date'] === $due) {
                return $b;
            }
        }
        return null;
    }

    public function testCreateFinancingMovesCashAndSchedulesBorrowDay(): void
    {
        $provider = TestDb::makePaylater('TEST_FIN_ACC', 'cycle', 10, 1);

        $planId = PaylaterPlan::createFinancing($provider, 'TikTok Financing', 1608.0, 6, 1485.0, $this->cash, '2026-10-05');
        $this->assertGreaterThan(0, $planId);

        $plan = TestDb::pdo()->query("SELECT * FROM paylater_plans WHERE id = {$planId}")->fetch();
        $this->assertSame('financing', $plan['kind']);
        $this->assertMoney(1608.0, (float) $plan['total_payable']);
        $this->assertMoney(1485.0, (float) $plan['cash_price']);
        $this->assertMoney(123.0, (float) $plan['interest']);

        // Cash lands in the savings account; liability equals the repayable sum.
        $this->assertMoney(2485.0, Account::balance($this->cash));
        $this->assertMoney(1608.0, Account::balance($provider));

        // Installments follow the borrow-day (5th), starting next month.
        $dates = TestDb::pdo()->query("SELECT due_date FROM paylater_installments WHERE plan_id = {$planId} ORDER BY seq")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(
            ['2026-11-05', '2026-12-05', '2027-01-05', '2027-02-05', '2027-03-05', '2027-04-05'],
            $dates
        );
    }

    public function testFinancingBillsAreSeparateAndClassified(): void
    {
        $provider = TestDb::makePaylater('TEST_FIN_BILL', 'cycle', 10, 1);
        PaylaterPlan::createFinancing($provider, 'Loan', 600.0, 3, 600.0, $this->cash, '2026-10-05');

        $bill = $this->billByDue($provider, '2026-11-05');
        $this->assertNotNull($bill, 'financing bill due on borrow day');
        $this->assertMoney(200.0, (float) $bill['amount_due']);
        $this->assertSame('financing', Bill::kindOf($provider, '2026-11-05'));

        $rows = Bill::details($provider, $bill);
        $this->assertCount(1, $rows);
        $this->assertSame('Personal Financing', explode(' · ', (string) $rows[0]['meta'])[0]);
    }

    public function testCycleOneOffPurchaseFoldsIntoMonthlyStatement(): void
    {
        $provider = TestDb::makePaylater('TEST_ONE_OFF', 'cycle', 10, 1);
        // A plain charge (no installment plan) in October.
        Expense::addTransaction($this->cat, 50.0, 'expense', 'One-off charge', '2026-10-20', $provider);
        Bill::sync($provider);

        $bill = $this->billByDue($provider, '2026-11-10');
        $this->assertNotNull($bill, 'cycle statement due on the 10th');
        $this->assertMoney(50.0, (float) $bill['amount_due']);
        $this->assertSame('2026-10-01', $bill['period_start']);
        $this->assertSame('2026-10-31', $bill['period_end']);
        $this->assertSame('bnpl', Bill::kindOf($provider, '2026-11-10'));

        $rows = Bill::details($provider, $bill);
        $this->assertCount(1, $rows);
        $this->assertSame('One-off charge', $rows[0]['title']);
    }

    public function testMovingPlanBetweenBnplAndFinancing(): void
    {
        $provider = TestDb::makePaylater('TEST_MOVE', 'cycle', 10, 1);
        $planId = PaylaterPlan::createPurchase($provider, $this->cat, 'Legacy Loan', '2026-10-05', 600.0, 3);
        Bill::sync($provider);

        // Defaults to BNPL.
        $this->assertCount(1, PaylaterPlan::forAccountByKind($provider, 'purchase'));
        $this->assertCount(0, PaylaterPlan::forAccountByKind($provider, 'financing'));
        $this->assertSame('bnpl', Bill::kindOf($provider, '2026-11-10'));

        // Move to Personal Financing.
        $this->assertTrue(PaylaterPlan::setKind($planId, 'financing'));
        $this->assertCount(0, PaylaterPlan::forAccountByKind($provider, 'purchase'));
        $this->assertCount(1, PaylaterPlan::forAccountByKind($provider, 'financing'));
        $this->assertSame('financing', Bill::kindOf($provider, '2026-11-10'));

        // Move back to BNPL.
        $this->assertTrue(PaylaterPlan::setKind($planId, 'purchase'));
        $this->assertSame('bnpl', Bill::kindOf($provider, '2026-11-10'));
    }

    public function testLegacyCycleInstallmentsBundleOntoTheTenth(): void
    {
        $provider = TestDb::makePaylater('TEST_BUNDLE', 'cycle', 10, 1);
        $first = PaylaterPlan::createPurchase($provider, $this->cat, 'Purchase A', '2026-10-05', 100.0, 1, null, 0);
        $second = PaylaterPlan::createPurchase($provider, $this->cat, 'Purchase B', '2026-10-15', 100.0, 1, null, 0);
        // Simulate legacy due dates (before the due-day was enforced).
        TestDb::pdo()->exec("UPDATE paylater_installments SET due_date = '2026-11-05' WHERE plan_id = {$first}");
        TestDb::pdo()->exec("UPDATE paylater_installments SET due_date = '2026-11-15' WHERE plan_id = {$second}");

        Bill::sync($provider);

        $bills = Bill::forAccount($provider);
        $this->assertCount(1, $bills, 'cycle installments in a month share one statement');
        $this->assertSame('2026-11-10', $bills[0]['due_date']);
        $this->assertMoney(200.0, (float) $bills[0]['amount_due']);
        $this->assertCount(2, Bill::details($provider, $bills[0]));
    }

    public function testGrabPerPurchaseKeepsIndividualDates(): void
    {
        $grab = TestDb::makePaylater('TEST_GRAB', 'per_purchase', null, 0);
        PaylaterPlan::createPurchase($grab, $this->cat, 'GPU', '2026-10-11', 1157.8, 5, null, 0);

        $dates = TestDb::pdo()->query("SELECT due_date FROM paylater_installments ORDER BY seq")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['2026-10-11', '2026-11-11', '2026-12-11', '2027-01-11', '2027-02-11'], $dates);
        // Still one bill per installment date.
        $bills = Bill::forAccount($grab);
        $this->assertCount(5, $bills);
    }
}
