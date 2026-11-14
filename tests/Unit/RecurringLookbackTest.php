<?php

declare(strict_types=1);

namespace Tests\Unit;

use Expense;
use TestDb;
use Tests\Support\AppTestCase;

final class RecurringLookbackTest extends AppTestCase
{
    private int $acc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->acc = TestDb::makeSavings('TEST_LOOKBACK', 0.0, '2026-01');
    }

    private function previousMonthDate(int $day): string
    {
        $month = date('Y-m', strtotime('first day of last month'));
        $max = (int) date('t', strtotime($month . '-01'));
        return sprintf('%s-%02d', $month, min($day, $max));
    }

    public function testDailyRunBackfillsPreviousMonth(): void
    {
        // Due on the 30th; no posts. Forward-only alone would miss it once the
        // month rolls over; the daily lookback should create it.
        Expense::addCommitment('WFH', 100.0, 'income', 30, null, '2026-09-01', null, $this->acc);
        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'WFH'");

        Expense::processDueCommitments(false, false, 1);

        $periods = TestDb::pdo()
            ->query("SELECT commitment_period FROM transactions WHERE commitment_id = {$id}")
            ->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertContains(date('Y-m', strtotime('first day of last month')), $periods);
    }

    public function testUnlinkedAutoTransactionIsAdoptedDuringBackfill(): void
    {
        Expense::addCommitment('WFH', 100.0, 'income', 30, null, '2026-09-01', null, $this->acc);
        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'WFH'");

        // Simulate the manual entry the user added (matching the auto name) with
        // no commitment link.
        $orphanDate = $this->previousMonthDate(30);
        TestDb::pdo()->prepare("INSERT INTO transactions (category_id, amount, type, description, date, account_id, commitment_id, commitment_period) VALUES (NULL, 100.0, 'income', '[Auto] WFH', ?, ?, NULL, NULL)")
            ->execute([$orphanDate, $this->acc]);
        $orphanId = (int) TestDb::pdo()->lastInsertId();

        Expense::processDueCommitments(false, false, 1);

        $row = TestDb::pdo()->query("SELECT commitment_id, commitment_period FROM transactions WHERE id = {$orphanId}")->fetch();
        $this->assertSame($id, (int) $row['commitment_id']);
        $this->assertSame(date('Y-m', strtotime('first day of last month')), (string) $row['commitment_period']);
        // No duplicate was inserted for the same month.
        $this->assertSame(1, TestDb::count('transactions', 'commitment_id = ?', [$id]));
    }
}
