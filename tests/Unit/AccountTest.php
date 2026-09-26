<?php

declare(strict_types=1);

namespace Tests\Unit;

use Account;
use Expense;
use TestDb;
use Tests\Support\AppTestCase;
use Transfer;

final class AccountTest extends AppTestCase
{
    private int $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cat = TestDb::categoryId();
    }

    public function testCreateAndFind(): void
    {
        $id = TestDb::makeSavings('TEST_Savings_A', 500.0, '2026-01');
        $acc = Account::find($id);
        $this->assertNotNull($acc);
        $this->assertSame('TEST_Savings_A', $acc['name']);
        $this->assertSame('savings', $acc['kind']);
        $this->assertMoney(500.0, (float) $acc['opening_balance']);
    }

    public function testSavingsBalance(): void
    {
        $id = TestDb::makeSavings('TEST_Savings_A', 1000.0, '2026-01');
        Expense::addTransaction($this->cat, 200.0, 'income', 'salary', '2026-02-10', $id);
        Expense::addTransaction($this->cat, 50.0, 'expense', 'lunch', '2026-02-11', $id);
        // 1000 + 200 - 50
        $this->assertMoney(1150.0, Account::balance($id));
    }

    public function testOpeningBalanceOnlyCountsFromStartMonth(): void
    {
        $id = TestDb::makeSavings('TEST_Savings_A', 100.0, '2026-03');
        // A transaction before the start month must be ignored.
        Expense::addTransaction($this->cat, 999.0, 'expense', 'old', '2026-01-15', $id);
        Expense::addTransaction($this->cat, 50.0, 'expense', 'new', '2026-03-15', $id);
        $this->assertMoney(50.0, Account::balance($id));
    }

    public function testLiabilityOutstanding(): void
    {
        $id = TestDb::makeCredit('TEST_Credit', 20, 1000.0);
        Expense::addTransaction($this->cat, 300.0, 'expense', 'purchase', '2026-02-01', $id);
        Expense::addTransaction($this->cat, 100.0, 'income', 'refund', '2026-02-02', $id);
        // Purchases increase debt, refunds reduce it.
        $this->assertMoney(200.0, Account::balance($id));
    }

    public function testCreditAvailableCredit(): void
    {
        $id = TestDb::makeCredit('TEST_Credit', 20, 1000.0);
        Expense::addTransaction($this->cat, 400.0, 'expense', 'purchase', '2026-02-01', $id);
        $outstanding = Account::balance($id);
        $this->assertMoney(400.0, $outstanding);
        $this->assertMoney(600.0, (float) Account::find($id)['credit_limit'] - $outstanding);
    }

    public function testDeleteBlockedWhenTransactionsExist(): void
    {
        $id = TestDb::makeSavings('TEST_Savings_A', 0.0, '2026-01');
        Expense::addTransaction($this->cat, 10.0, 'expense', 'x', '2026-02-01', $id);
        $this->assertFalse(Account::delete($id));
        $this->assertNotNull(Account::find($id));
    }

    public function testDeleteWithoutTransactions(): void
    {
        $id = TestDb::makeSavings('TEST_Savings_B', 0.0, '2026-01');
        $this->assertTrue(Account::delete($id));
        $this->assertNull(Account::find($id));
    }

    public function testArchiveAccount(): void
    {
        $id = TestDb::makeSavings('TEST_Savings_A', 10.0, '2026-01');
        $acc = Account::find($id);
        $acc['archived'] = 1;
        Account::update($id, $acc);
        $names = array_column(Account::all(), 'name');
        $this->assertNotContains('TEST_Savings_A', $names);
        $namesAll = array_column(Account::all(true), 'name');
        $this->assertContains('TEST_Savings_A', $namesAll);
    }

    public function testActiveAccountPersistence(): void
    {
        $id = TestDb::makeSavings('TEST_Savings_A', 0.0, '2026-01');
        Account::setActive($id);
        $this->assertSame($id, Account::activeId());
        $this->assertSame('TEST_Savings_A', Account::active()['name']);
    }

    public function testAccountIsolation(): void
    {
        $a = TestDb::makeSavings('TEST_Savings_A', 100.0, '2026-01');
        $b = TestDb::makeSavings('TEST_Savings_B', 100.0, '2026-01');
        Expense::addTransaction($this->cat, 40.0, 'expense', 'only-a', '2026-02-01', $a);
        $this->assertMoney(60.0, Account::balance($a));
        $this->assertMoney(100.0, Account::balance($b));
    }

    public function testNetWorthCombinesAssetsAndLiabilities(): void
    {
        $s = TestDb::makeSavings('TEST_Savings_A', 1000.0, '2026-01');
        $c = TestDb::makeCredit('TEST_Credit', 20, 5000.0);
        Expense::addTransaction($this->cat, 250.0, 'expense', 'buy', '2026-02-01', $c);
        // 1000 savings - 250 owed
        $this->assertMoney(750.0, Account::netWorth());
    }
}
