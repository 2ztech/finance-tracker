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
        Expense::processDueCommitments(true);

        $rows = TestDb::pdo()->query("SELECT date, amount, account_id FROM transactions WHERE description = '[Auto] RENT' ORDER BY date")->fetchAll();
        $this->assertCount(2, $rows);
        $this->assertSame('2026-01-15', $rows[0]['date']);
        $this->assertSame('2026-02-15', $rows[1]['date']);
        $this->assertSame($this->acc, (int) $rows[0]['account_id']);
        $this->assertMoney(500.0, (float) $rows[0]['amount']);
    }

    public function testProcessingIsIdempotent(): void
    {
        Expense::addCommitment('RENT', 500.0, 'expense', 15, $this->cat, '2026-01-01', '2026-02-28', $this->acc);
        Expense::processDueCommitments(true);
        Expense::processDueCommitments(true);
        $this->assertSame(2, TestDb::count('transactions'));
    }

    public function testEditingCommitmentPropagatesToTransactions(): void
    {
        Expense::addCommitment('RENT', 500.0, 'expense', 15, $this->cat, '2026-01-01', '2026-02-28', $this->acc);
        Expense::processDueCommitments(true);
        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'RENT'");

        Expense::updateCommitment($id, 'RENT', 650.0, 'expense', 15, $this->cat, '2026-01-01', '2026-02-28', $this->acc);
        $amounts = TestDb::pdo()->query("SELECT amount FROM transactions WHERE description = '[Auto] RENT'")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertCount(2, $amounts);
        $this->assertMoney(650.0, (float) $amounts[0]);
        $this->assertMoney(650.0, (float) $amounts[1]);
    }

    public function testDeletingCommitmentRemovesItsTransactions(): void
    {
        Expense::addCommitment('RENT', 500.0, 'expense', 15, $this->cat, '2026-01-01', '2026-02-28', $this->acc);
        Expense::processDueCommitments(true);
        $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'RENT'");
        Expense::deleteCommitment($id);
        $this->assertSame(0, TestDb::count('transactions'));
    }

    public function testDueDayClampedInShortMonth(): void
    {
        // Day 31 with a February end must clamp to the 28th, not create 2026-02-31.
        Expense::addCommitment('DAY31', 10.0, 'expense', 31, $this->cat, '2026-01-01', '2026-02-28', $this->acc);
        Expense::processDueCommitments(true);
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
}
