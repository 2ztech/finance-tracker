<?php

declare(strict_types=1);

namespace Tests\Unit;

use Account;
use Expense;
use TestDb;
use Tests\Support\AppTestCase;

final class TransactionTest extends AppTestCase
{
    private int $cat;
    private int $acc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cat = TestDb::categoryId();
        $this->acc = TestDb::makeSavings('TEST_Savings_A', 0.0, '2026-01');
    }

    public function testAddIncomeAndExpense(): void
    {
        $this->assertTrue(Expense::addTransaction($this->cat, 100.0, 'income', 'in', '2026-02-01', $this->acc));
        $this->assertTrue(Expense::addTransaction($this->cat, 30.0, 'expense', 'out', '2026-02-02', $this->acc));
        $this->assertSame(2, TestDb::count('transactions'));
        $this->assertMoney(70.0, Account::balance($this->acc));
    }

    public function testMonthlyFiltering(): void
    {
        Expense::addTransaction($this->cat, 10.0, 'expense', 'feb', '2026-02-05', $this->acc);
        Expense::addTransaction($this->cat, 20.0, 'expense', 'mar', '2026-03-05', $this->acc);
        $feb = Expense::getTransactions('02', '2026', $this->acc);
        $this->assertCount(1, $feb);
        $this->assertSame('feb', $feb[0]['description']);
    }

    public function testAccountScopedListing(): void
    {
        $other = TestDb::makeSavings('TEST_Savings_B', 0.0, '2026-01');
        Expense::addTransaction($this->cat, 10.0, 'expense', 'a', '2026-02-01', $this->acc);
        Expense::addTransaction($this->cat, 10.0, 'expense', 'b', '2026-02-01', $other);
        $this->assertCount(1, Expense::getTransactions('02', '2026', $this->acc));
        $this->assertCount(1, Expense::getTransactions('02', '2026', $other));
        $this->assertCount(2, Expense::getTransactions('02', '2026'));
    }

    public function testUpdateTransactionChangesAmountAndAccount(): void
    {
        Expense::addTransaction($this->cat, 10.0, 'expense', 'x', '2026-02-01', $this->acc);
        $id = (int) TestDb::scalar('SELECT id FROM transactions LIMIT 1');
        $other = TestDb::makeSavings('TEST_Savings_B', 100.0, '2026-01');
        Expense::updateTransaction($id, $this->cat, 25.0, 'expense', 'x2', '2026-02-03', $other);

        $this->assertMoney(0.0, Account::balance($this->acc));
        $this->assertMoney(75.0, Account::balance($other));
    }

    public function testDeleteTransactionReversesEffect(): void
    {
        Expense::addTransaction($this->cat, 55.0, 'expense', 'x', '2026-02-01', $this->acc);
        $this->assertMoney(-55.0, Account::balance($this->acc));
        $id = (int) TestDb::scalar('SELECT id FROM transactions LIMIT 1');
        Expense::deleteTransaction($id);
        $this->assertMoney(0.0, Account::balance($this->acc));
        $this->assertSame(0, TestDb::count('transactions'));
    }

    public function testAmountsRoundedToTwoDecimals(): void
    {
        Expense::addTransaction($this->cat, 0.335, 'expense', 'round', '2026-02-01', $this->acc);
        $stored = (float) TestDb::scalar('SELECT amount FROM transactions LIMIT 1');
        // SQLite stores REAL; the app should still total to a 2dp figure.
        $this->assertMoney(-0.34, Account::balance($this->acc));
    }

    public function testCategoryAssignmentPersists(): void
    {
        $groceries = TestDb::categoryId('Groceries');
        Expense::addTransaction($groceries, 12.0, 'expense', 'veg', '2026-02-01', $this->acc);
        $row = Expense::getTransactions('02', '2026', $this->acc)[0];
        $this->assertSame('Groceries', $row['category_name']);
    }
}
