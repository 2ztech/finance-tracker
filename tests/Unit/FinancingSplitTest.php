<?php

declare(strict_types=1);

namespace Tests\Unit;

use Bill;
use FinancingSplit;
use Institution;
use TestDb;
use Tests\Support\AppTestCase;

final class FinancingSplitTest extends AppTestCase
{
    private function addPlan(int $accountId, string $kind, string $description, float $amount, string $firstDue): int
    {
        $db = TestDb::pdo();
        $tx = $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date, account_id) VALUES (?, ?, 'expense', ?, ?, ?)");
        $tx->execute([TestDb::categoryId(), $amount, $description, $firstDue, $accountId]);
        $txnId = (int) $db->lastInsertId();

        $plan = $db->prepare("INSERT INTO paylater_plans
            (account_id, purchase_txn_id, total_payable, months, installment_amount, first_due_date, status, created_at, kind)
            VALUES (?, ?, ?, 1, ?, ?, 'active', ?, ?)");
        $plan->execute([$accountId, $txnId, $amount, $amount, $firstDue, date('Y-m-d H:i:s'), $kind]);
        $planId = (int) $db->lastInsertId();

        $db->prepare("UPDATE transactions SET plan_id = ? WHERE id = ?")->execute([$planId, $txnId]);
        return $planId;
    }

    private function runSplit(): void
    {
        $db = TestDb::pdo();
        $db->prepare("DELETE FROM settings WHERE key = 'financing_split_v1'")->execute();
        FinancingSplit::run($db);
    }

    public function testCombinedSectionCreatesFinancingSectionAndMovesPlans(): void
    {
        $institutionId = TestDb::makeInstitution('TikTok');
        $sourceId = TestDb::makeSection($institutionId, [
            'name' => 'TikTok PayLater',
            'kind' => 'paylater',
            'bnpl_mode' => 'cycle',
            'paylater_role' => 'both',
        ]);

        $purchasePlan = $this->addPlan($sourceId, 'purchase', 'Purchase', 50.0, '2026-11-10');
        $financingPlan = $this->addPlan($sourceId, 'financing', 'Financing Loan', 268.0, '2026-11-05');

        $db = TestDb::pdo();
        $db->prepare("INSERT INTO paylater_installments (plan_id, seq, due_date, amount, paid_amount, status) VALUES (?, 1, ?, ?, ?, 'paid')")
            ->execute([$financingPlan, '2026-11-05', 268.0, 268.0]);
        $db->prepare("INSERT INTO bill_payment_allocations (account_id, due_date, paid_date, amount, transfer_id, created_at) VALUES (?, ?, ?, ?, NULL, ?)")
            ->execute([$sourceId, '2026-11-05', '2026-11-05', 268.0, date('Y-m-d H:i:s')]);

        $this->runSplit();

        $sections = Institution::sections($institutionId);
        $this->assertCount(2, $sections);
        $financingSection = null;
        foreach ($sections as $section) {
            if (($section['paylater_role'] ?? '') === 'financing') {
                $financingSection = $section;
            }
        }
        $this->assertNotNull($financingSection);
        $this->assertSame('TikTok Personal Financing', $financingSection['name']);
        $this->assertSame('bnpl', TestDb::scalar("SELECT paylater_role FROM accounts WHERE id = ?", [$sourceId]));

        $this->assertSame((int) $financingSection['id'], (int) TestDb::scalar("SELECT account_id FROM paylater_plans WHERE id = ?", [$financingPlan]));
        $this->assertSame($sourceId, (int) TestDb::scalar("SELECT account_id FROM paylater_plans WHERE id = ?", [$purchasePlan]));
        $this->assertSame(
            (int) $financingSection['id'],
            (int) TestDb::scalar("SELECT account_id FROM transactions WHERE description = 'Financing Loan'")
        );

        // Paid installment/allocation travelled to the new section.
        $this->assertSame(1, (int) TestDb::scalar(
            "SELECT COUNT(*) FROM bill_payment_allocations WHERE account_id = ? AND due_date = '2026-11-05'",
            [(int) $financingSection['id']]
        ));
        Bill::sync((int) $financingSection['id']);
        $this->assertSame('paid', (string) TestDb::scalar(
            "SELECT status FROM bills WHERE account_id = ? AND due_date = '2026-11-05'",
            [(int) $financingSection['id']]
        ));
        $this->assertMoney(268.0, (float) TestDb::scalar(
            "SELECT paid_amount FROM bills WHERE account_id = ? AND due_date = '2026-11-05'",
            [(int) $financingSection['id']]
        ));
    }

    public function testReusesAnExistingFinancingSection(): void
    {
        $institutionId = TestDb::makeInstitution('TikTok');
        $sourceId = TestDb::makeSection($institutionId, [
            'name' => 'TikTok PayLater',
            'kind' => 'paylater',
            'bnpl_mode' => 'cycle',
            'paylater_role' => 'both',
        ]);
        $targetId = TestDb::makeSection($institutionId, [
            'name' => 'My Financing',
            'kind' => 'paylater',
            'bnpl_mode' => 'per_purchase',
            'paylater_role' => 'financing',
        ]);

        $this->addPlan($sourceId, 'purchase', 'Purchase', 50.0, '2026-11-10');
        $financingPlan = $this->addPlan($sourceId, 'financing', 'Financing Loan', 268.0, '2026-11-05');

        $this->runSplit();

        $this->assertCount(2, Institution::sections($institutionId));
        $this->assertSame($targetId, (int) TestDb::scalar("SELECT account_id FROM paylater_plans WHERE id = ?", [$financingPlan]));
        $this->assertSame('bnpl', TestDb::scalar("SELECT paylater_role FROM accounts WHERE id = ?", [$sourceId]));
    }

    public function testFinancingOnlySectionIsMarkedFinancingWithoutANewSection(): void
    {
        $institutionId = TestDb::makeInstitution('TikTok');
        $sourceId = TestDb::makeSection($institutionId, [
            'name' => 'Legacy Financing',
            'kind' => 'paylater',
            'bnpl_mode' => 'cycle',
            'paylater_role' => 'bnpl',
        ]);
        $this->addPlan($sourceId, 'financing', 'Financing Loan', 268.0, '2026-11-05');

        $this->runSplit();

        $this->assertCount(1, Institution::sections($institutionId));
        $this->assertSame('financing', TestDb::scalar("SELECT paylater_role FROM accounts WHERE id = ?", [$sourceId]));
    }

    public function testSplitRunsOnce(): void
    {
        $institutionId = TestDb::makeInstitution('TikTok');
        $sourceId = TestDb::makeSection($institutionId, [
            'name' => 'TikTok PayLater',
            'kind' => 'paylater',
            'bnpl_mode' => 'cycle',
            'paylater_role' => 'both',
        ]);
        $this->addPlan($sourceId, 'purchase', 'Purchase', 50.0, '2026-11-10');
        $this->addPlan($sourceId, 'financing', 'Financing Loan', 268.0, '2026-11-05');

        $this->runSplit();
        $countAfterFirst = count(Institution::sections($institutionId));

        // Marker is now set, so a second run must not create another section.
        FinancingSplit::run(TestDb::pdo());
        $this->assertSame($countAfterFirst, count(Institution::sections($institutionId)));
    }
}
