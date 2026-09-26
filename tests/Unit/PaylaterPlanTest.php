<?php

declare(strict_types=1);

namespace Tests\Unit;

use Account;
use Bill;
use PaylaterPlan;
use TestDb;
use Tests\Support\AppTestCase;

final class PaylaterPlanTest extends AppTestCase
{
    private int $cat;
    private int $savings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cat = TestDb::categoryId();
        $this->savings = TestDb::makeSavings('TEST_Savings_A', 1000.0, '2026-01');
    }

    private function cycleAccount(): int
    {
        // Shopee/TikTok style: due on the 10th, first due next cycle.
        return TestDb::makePaylater('TEST_BNPL', 'cycle', 10, 1);
    }

    private function perPurchaseAccount(): int
    {
        // Grab/Atome BNPL style: first due at purchase.
        return TestDb::makePaylater('TEST_BNPL2', 'per_purchase', null, 0);
    }

    public function testCreatePlanBuildsInstalments(): void
    {
        $acc = $this->cycleAccount();
        $plan = PaylaterPlan::createPurchase($acc, $this->cat, 'item', '2026-09-05', 30.0, 3, null, 0);
        $this->assertGreaterThan(0, $plan);

        $inst = PaylaterPlan::installments($plan);
        $this->assertCount(3, $inst);
        $this->assertSame('2026-10-10', $inst[0]['due_date']);
        $this->assertSame('2026-11-10', $inst[1]['due_date']);
        $this->assertSame('2026-12-10', $inst[2]['due_date']);
        $this->assertMoney(10.0, (float) $inst[0]['amount']);
    }

    public function testCycleFirstDueIsNextCycle(): void
    {
        $acc = $this->cycleAccount();
        $plan = PaylaterPlan::createPurchase($acc, $this->cat, 'item', '2026-09-20', 15.0, 1, null, 0);
        $inst = PaylaterPlan::installments($plan);
        // Purchased after the 10th -> still billed next cycle on the 10th.
        $this->assertSame('2026-10-10', $inst[0]['due_date']);
    }

    public function testPerPurchaseFirstDueAtPurchaseDate(): void
    {
        $acc = $this->perPurchaseAccount();
        $plan = PaylaterPlan::createPurchase($acc, $this->cat, 'grab item', '2026-09-14', 80.0, 4, null, 0);
        $inst = PaylaterPlan::installments($plan);
        $this->assertSame('2026-09-14', $inst[0]['due_date']);
        $this->assertSame('2026-12-14', $inst[3]['due_date']);
        $this->assertMoney(20.0, (float) $inst[0]['amount']);
    }

    public function testRoundingLastInstalmentAbsorbsRemainder(): void
    {
        $acc = $this->perPurchaseAccount();
        $plan = PaylaterPlan::createPurchase($acc, $this->cat, 'cents', '2026-09-01', 100.0, 3, null, 0);
        $inst = PaylaterPlan::installments($plan);
        $sum = 0.0;
        foreach ($inst as $i) {
            $sum += (float) $i['amount'];
        }
        $this->assertMoney(100.0, $sum);
        $this->assertMoney(33.33, (float) $inst[0]['amount']);
        $this->assertMoney(33.34, (float) $inst[2]['amount']);
    }

    public function testInterestFromCashPrice(): void
    {
        $acc = $this->perPurchaseAccount();
        // 3 x 10 = 30 total, cash price 27 -> 3 interest.
        $plan = PaylaterPlan::createPurchase($acc, $this->cat, 'financed', '2026-09-01', 30.0, 3, 27.0, 0);
        $p = PaylaterPlan::find($plan);
        $this->assertMoney(3.0, (float) $p['interest']);
        // Purchase expense (27) + financing expense (3) = 30 liability.
        $this->assertMoney(30.0, Account::balance($acc));
    }

    public function testPlanLiabilityEqualsTotal(): void
    {
        $acc = $this->cycleAccount();
        PaylaterPlan::createPurchase($acc, $this->cat, 'item', '2026-09-05', 30.0, 3, null, 0);
        $this->assertMoney(30.0, Account::balance($acc));
    }

    public function testPartialThenFullPaymentCompletion(): void
    {
        $acc = $this->cycleAccount();
        $plan = PaylaterPlan::createPurchase($acc, $this->cat, 'item', '2026-09-05', 30.0, 3, null, 0);

        $nov = '2026-11-10';
        // Partial 4 of the 10 due.
        $applied = Bill::payDue($acc, $nov, 4.0, $this->savings, '2026-11-11');
        $this->assertMoney(4.0, $applied);
        $this->assertMoney(26.0, Account::balance($acc));
        $this->assertSame('active', PaylaterPlan::find($plan)['status']);
        $this->assertMoney(26.0, PaylaterPlan::remaining($plan));

        // Settle the rest.
        $settled = PaylaterPlan::settle($plan, $this->savings, '2026-11-20');
        $this->assertMoney(26.0, $settled);
        $this->assertMoney(0.0, PaylaterPlan::remaining($plan));
        $this->assertSame('completed', PaylaterPlan::find($plan)['status']);
    }

    public function testBillAggregatesCyclePurchases(): void
    {
        $acc = $this->cycleAccount();
        // RM15 pay-in-full + RM30 over 3 -> Oct bill should be 15 + 10.
        PaylaterPlan::createPurchase($acc, $this->cat, 'A', '2026-09-05', 15.0, 1, null, 1);
        PaylaterPlan::createPurchase($acc, $this->cat, 'B', '2026-09-05', 30.0, 3, null, 0);

        $oct = null;
        foreach (Bill::forAccount($acc) as $b) {
            if ($b['due_date'] === '2026-10-10') {
                $oct = $b;
            }
        }
        $this->assertNotNull($oct);
        $this->assertMoney(25.0, (float) $oct['amount_due']);
    }

    public function testRefundReducesRemainingWithoutNegative(): void
    {
        $acc = $this->perPurchaseAccount();
        $plan = PaylaterPlan::createPurchase($acc, $this->cat, 'refundable', '2026-09-01', 100.0, 4, null, 0);
        PaylaterPlan::refund($plan, 40.0, '2026-09-05');
        $this->assertMoney(60.0, Account::balance($acc));
        $this->assertGreaterThanOrEqual(0.0, PaylaterPlan::remaining($plan));

        // Over-refund becomes a credit, remaining never negative.
        PaylaterPlan::refund($plan, 100.0, '2026-09-06');
        $this->assertMoney(0.0, PaylaterPlan::remaining($plan));
        $this->assertLessThanOrEqual(0.0, Account::balance($acc));
    }

    public function testEditPlanRegeneratesInstalmentsAndBills(): void
    {
        $acc = $this->cycleAccount();
        $plan = PaylaterPlan::createPurchase($acc, $this->cat, 'wrong', '2026-09-05', 30.0, 3, null, 0);
        PaylaterPlan::syncFromTransaction($plan, 60.0, 'wrong', '2026-09-05');

        $this->assertMoney(60.0, (float) PaylaterPlan::find($plan)['total_payable']);
        $inst = PaylaterPlan::installments($plan);
        $this->assertMoney(20.0, (float) $inst[0]['amount']);
        $oct = null;
        foreach (Bill::forAccount($acc) as $b) {
            if ($b['due_date'] === '2026-10-10') {
                $oct = $b;
            }
        }
        $this->assertMoney(20.0, (float) $oct['amount_due']);
    }

    public function testCancelRemovesPlanAndBills(): void
    {
        $acc = $this->cycleAccount();
        $plan = PaylaterPlan::createPurchase($acc, $this->cat, 'gone', '2026-09-05', 30.0, 3, null, 0);
        PaylaterPlan::cancel($plan);
        $this->assertNull(PaylaterPlan::find($plan));
        $this->assertSame(0, TestDb::count('paylater_installments'));
        $this->assertSame(0, TestDb::count('bills', 'account_id = ?', [$acc]));
    }

    public function testReconcileRemovesOrphanPlan(): void
    {
        $acc = $this->cycleAccount();
        $plan = PaylaterPlan::createPurchase($acc, $this->cat, 'orphan', '2026-09-05', 30.0, 3, null, 0);
        // Simulate the historic bug: purchase transaction deleted, plan left behind.
        TestDb::pdo()->exec("DELETE FROM transactions WHERE plan_id = {$plan}");
        $removed = PaylaterPlan::reconcileAccount($acc);
        $this->assertSame(1, $removed);
        $this->assertNull(PaylaterPlan::find($plan));
    }

    public function testDueDayClampedForShortMonths(): void
    {
        // Due day 31 must not create an invalid February date.
        $acc = TestDb::makePaylater('TEST_BNPL3', 'cycle', 31, 1);
        $plan = PaylaterPlan::createPurchase($acc, $this->cat, 'clamp', '2026-01-15', 20.0, 2, null, 0);
        $inst = PaylaterPlan::installments($plan);
        $this->assertSame('2026-02-28', $inst[0]['due_date']);
    }
}
