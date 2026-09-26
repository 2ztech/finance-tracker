<?php

declare(strict_types=1);

namespace Tests\Unit;

use Expense;
use TestDb;
use Tests\Support\AppTestCase;

final class RecurringTest extends AppTestCase
{
    private int $cat;
    private int $acc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cat = TestDb::categoryId();
        $this->acc = TestDb::makeSavings('TEST_Savings_A', 0.0, '2026-01');
    }

    public function testProcessCreatesAutoTransactions(): void
    {
        Expense::addCommitment('RENT', 500.0, 'expense', 15, $this->cat, '2026-01-01', '2026-02-28', $this->acc);
        Expense::processDueCommitments(true, true);

        $rows = TestDb::pdo()->query("SELECT date, amount, account_id FROM transactions WHERE description = '[Auto] RENT' ORDER BY date")->fetchAll();
        $this->assertCount(2, $rows);
        $this->assertSame('2026-01-15', $rows[0]['date']);
        $this->assertSame('2026-02-15', $rows[1]['date']);
        $this->assertSame($this->acc, (int) $rows[0]['account_id']);
        $this->assertMoney(500.0, (float) $rows[0]['amount']);
    }

    public function testAutoTransactionsAreTaggedWithCommitmentAndPeriod(): void
    {
        Expense::addCommitment('RENT', 500.0, 'expense', 15, $this->cat, '2026-01-01', '2026-02-28', $this->acc);
        Expense::processDueCommitments(true, true);
        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'RENT'");

        $tagged = TestDb::count('transactions', 'commitment_id = ? AND commitment_period = ?', [$id, '2026-01']);
        $this->assertSame(1, $tagged);
    }

    public function testProcessingIsIdempotent(): void
    {
        Expense::addCommitment('RENT', 500.0, 'expense', 15, $this->cat, '2026-01-01', '2026-02-28', $this->acc);
        Expense::processDueCommitments(true, true);
        Expense::processDueCommitments(true, true);
        $this->assertSame(2, TestDb::count('transactions'));
    }

    public function testEditingCommitmentPropagatesToTransactions(): void
    {
        Expense::addCommitment('RENT', 500.0, 'expense', 15, $this->cat, '2026-01-01', '2026-02-28', $this->acc);
        Expense::processDueCommitments(true, true);
        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'RENT'");

        Expense::updateCommitment($id, 'RENT', 650.0, 'expense', 15, $this->cat, '2026-01-01', '2026-02-28', $this->acc);
        $amounts = TestDb::pdo()->query("SELECT amount FROM transactions WHERE description = '[Auto] RENT'")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertCount(2, $amounts);
        $this->assertMoney(650.0, (float) $amounts[0]);
        $this->assertMoney(650.0, (float) $amounts[1]);
    }

    public function testEditingCommitmentCategoryReflectsInTransactions(): void
    {
        $food = TestDb::categoryId('Food & Dining');
        $fuel = TestDb::categoryId('Fuel & Transport');
        Expense::addCommitment('Y15 LOAN', 100.0, 'expense', 5, $food, '2026-01-01', '2026-01-31', $this->acc);
        Expense::processDueCommitments(true, true);
        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'Y15 LOAN'");

        Expense::updateCommitment($id, 'Y15 LOAN', 100.0, 'expense', 5, $fuel, '2026-01-01', '2026-01-31', $this->acc);

        $this->assertSame($fuel, (int) TestDb::scalar("SELECT category_id FROM transactions WHERE commitment_id = ? LIMIT 1", [$id]));
    }

    public function testDeletingCommitmentPreservesHistory(): void
    {
        Expense::addCommitment('RENT', 500.0, 'expense', 15, $this->cat, '2026-01-01', '2026-02-28', $this->acc);
        Expense::processDueCommitments(true, true);
        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'RENT'");

        Expense::deleteCommitment($id);

        // Transactions stay; the link is cleared by ON DELETE SET NULL.
        $this->assertSame(2, TestDb::count('transactions'));
        $this->assertSame(2, TestDb::count('transactions', 'commitment_id IS NULL'));
    }

    public function testArchivingStopsPostingAndRestoreResumes(): void
    {
        Expense::addCommitment('ARC', 10.0, 'expense', 1, $this->cat, '2026-01-01', null, $this->acc);
        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'ARC'");

        Expense::archiveCommitment($id);
        Expense::processDueCommitments(true, true);
        $this->assertSame(0, TestDb::count('transactions'));
        $this->assertCount(1, Expense::getArchivedCommitments($this->acc));
        $this->assertCount(0, Expense::getCommitments($this->acc));

        Expense::restoreCommitment($id);
        Expense::processDueCommitments(true, true);
        $this->assertGreaterThan(0, TestDb::count('transactions'));
    }

    public function testPostNowIsIdempotentPerPeriod(): void
    {
        $income = TestDb::categoryId('Salary');
        Expense::addCommitment('SALARY', 3000.0, 'income', 28, $income, date('Y-m-01'), null, $this->acc);
        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'SALARY'");

        $this->assertTrue(Expense::postCommitmentNow($id));
        $this->assertFalse(Expense::postCommitmentNow($id));
        $this->assertSame(1, TestDb::count('transactions', 'commitment_id = ?', [$id]));
        $this->assertSame(date('Y-m'), (string) TestDb::scalar("SELECT commitment_period FROM transactions WHERE commitment_id = ? LIMIT 1", [$id]));
    }

    public function testDeletedPostedTransactionBecomesUncoveredAgain(): void
    {
        // Model 1: the schedule is the source of truth. Deleting a posted
        // transaction frees the month so the scheduler (or Post now) can
        // cover it again.
        $income = TestDb::categoryId('Salary');
        Expense::addCommitment('SALARY', 3000.0, 'income', 28, $income, date('Y-m-01'), null, $this->acc);
        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'SALARY'");
        Expense::postCommitmentNow($id);

        $txnId = (int) TestDb::scalar("SELECT id FROM transactions WHERE commitment_id = ? LIMIT 1", [$id]);
        TestDb::pdo()->prepare("DELETE FROM transactions WHERE id = ?")->execute([$txnId]);

        $this->assertFalse(Expense::currentPeriodPosted($id));
        $this->assertTrue(Expense::postCommitmentNow($id));
    }

    public function testUnpaidRecurringIsAccountScoped(): void
    {
        $other = TestDb::makeSavings('TEST_Savings_B', 0.0, '2026-01');
        Expense::addCommitment('A', 50.0, 'expense', 5, $this->cat, '2026-01-01', '2026-12-31', $this->acc);
        Expense::addCommitment('B', 70.0, 'expense', 5, $this->cat, '2026-01-01', '2026-12-31', $other);

        $this->assertMoney(50.0, Expense::getUnpaidRecurring('expense', '06', '2026', $this->acc));
        $this->assertMoney(70.0, Expense::getUnpaidRecurring('expense', '06', '2026', $other));
        $this->assertMoney(120.0, Expense::getUnpaidRecurring('expense', '06', '2026', null));
    }

    public function testProjectedEndOfMonth(): void
    {
        $income = TestDb::categoryId('Salary');
        Expense::addCommitment('INC', 1000.0, 'income', 5, $income, '2026-01-01', '2026-12-31', $this->acc);
        Expense::addCommitment('BILL', 300.0, 'expense', 5, $this->cat, '2026-01-01', '2026-12-31', $this->acc);

        $this->assertMoney(700.0, Expense::projectedEndOfMonth($this->acc, '06', '2026'));
    }

    public function testDueDayClampedInShortMonth(): void
    {
        // Day 31 with a February end must clamp to the 28th, not create 2026-02-31.
        Expense::addCommitment('DAY31', 10.0, 'expense', 31, $this->cat, '2026-01-01', '2026-02-28', $this->acc);
        Expense::processDueCommitments(true, true);
        $dates = TestDb::pdo()->query("SELECT date FROM transactions WHERE description = '[Auto] DAY31' ORDER BY date")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertContains('2026-01-31', $dates);
        $this->assertContains('2026-02-28', $dates);
        foreach ($dates as $d) {
            $p = explode('-', $d);
            $this->assertTrue(checkdate((int) $p[1], (int) $p[2], (int) $p[0]), "invalid date {$d}");
        }
    }

    public function testCommitmentsScopedByAccount(): void
    {
        $other = TestDb::makeSavings('TEST_Savings_B', 0.0, '2026-01');
        Expense::addCommitment('A_ONLY', 10.0, 'expense', 5, $this->cat, '2026-01-01', '2026-01-31', $this->acc);
        Expense::addCommitment('B_ONLY', 10.0, 'expense', 5, $this->cat, '2026-01-01', '2026-01-31', $other);
        $this->assertCount(1, Expense::getCommitments($this->acc));
        $this->assertSame('A_ONLY', Expense::getCommitments($this->acc)[0]['name']);
    }

    public function testDefaultProcessIsForwardOnly(): void
    {
        Expense::addCommitment('FWD', 10.0, 'expense', 1, $this->cat, '2026-01-01', null, $this->acc);
        Expense::processDueCommitments(true); // default = forward-only

        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'FWD'");
        $this->assertSame(1, TestDb::count('transactions', 'commitment_id = ?', [$id]));
        $this->assertSame(date('Y-m'), (string) TestDb::scalar("SELECT commitment_period FROM transactions WHERE commitment_id = ? LIMIT 1", [$id]));
    }

    public function testEditDoesNotBackfillPastMonths(): void
    {
        Expense::addCommitment('GAP', 10.0, 'expense', 1, $this->cat, '2026-01-01', null, $this->acc);
        Expense::processDueCommitments(true, true); // build history
        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'GAP'");

        // Remove an earlier month, edit the schedule, then run the scheduler again.
        TestDb::pdo()->prepare("DELETE FROM transactions WHERE commitment_id = ? AND commitment_period = ?")->execute([$id, '2026-03']);
        Expense::updateCommitment($id, 'GAP', 12.0, 'expense', 1, $this->cat, '2026-01-01', null, $this->acc);
        Expense::processDueCommitments(true);

        $this->assertSame(0, TestDb::count('transactions', 'commitment_id = ? AND commitment_period = ?', [$id, '2026-03']));
    }

    public function testEmptyStartPostsCurrentMonthOnly(): void
    {
        Expense::addCommitment('NOSTART', 10.0, 'expense', 1, $this->cat, null, null, $this->acc);
        Expense::processDueCommitments(true, true);

        $this->assertSame(1, TestDb::count('transactions'));
        $this->assertSame(date('Y-m'), (string) TestDb::scalar("SELECT commitment_period FROM transactions LIMIT 1"));
    }

    public function testExpiredIsAutoArchived(): void
    {
        Expense::addCommitment('OLD', 10.0, 'expense', 1, $this->cat, '2026-01-01', '2026-02-28', $this->acc);
        Expense::processDueCommitments(true, true);
        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'OLD'");

        $this->assertSame(1, (int) TestDb::scalar("SELECT archived FROM commitments WHERE id = ?", [$id]));
        $this->assertCount(1, Expense::getArchivedCommitments($this->acc));
        $this->assertCount(0, Expense::getCommitments($this->acc));
    }

    public function testExpiryArchivesEvenWhenDailyGateAlreadyRan(): void
    {
        Expense::addCommitment('GATE', 10.0, 'expense', 1, $this->cat, '2026-01-01', '2026-02-28', $this->acc);
        \Settings::set('last_commitment_process', date('Y-m-d'));

        Expense::processDueCommitments(); // no force: posting is gated, archiving is not

        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'GATE'");
        $this->assertSame(1, (int) TestDb::scalar("SELECT archived FROM commitments WHERE id = ?", [$id]));
    }

    public function testRestoreExpiredReopens(): void
    {
        Expense::addCommitment('OLD2', 10.0, 'expense', 1, $this->cat, '2026-01-01', '2026-02-28', $this->acc);
        Expense::processDueCommitments(true, true);
        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'OLD2'");

        Expense::restoreCommitment($id);

        $row = TestDb::pdo()->query("SELECT archived, end_date FROM commitments WHERE id = $id")->fetch();
        $this->assertSame(0, (int) $row['archived']);
        $this->assertNull($row['end_date']);
        $this->assertCount(1, Expense::getCommitments($this->acc));
    }
}
