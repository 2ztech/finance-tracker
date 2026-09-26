<?php

declare(strict_types=1);

namespace Tests\Unit;

use Budget;
use Expense;
use TestDb;
use Tests\Support\AppTestCase;

final class BudgetTest extends AppTestCase
{
    public function testSetAndGetBudget(): void
    {
        $cat = TestDb::categoryId('Food & Dining');
        Budget::set($cat, 450.0);
        $all = Budget::getAll();
        $this->assertArrayHasKey($cat, $all);
        $this->assertMoney(450.0, (float) $all[$cat]['amount']);
    }

    public function testSetTwiceUpdatesRatherThanDuplicates(): void
    {
        $cat = TestDb::categoryId('Food & Dining');
        Budget::set($cat, 450.0);
        Budget::set($cat, 500.0);
        $this->assertSame(1, TestDb::count('budgets'));
        $this->assertMoney(500.0, (float) Budget::get($cat)['amount']);
    }

    public function testDeleteBudget(): void
    {
        $cat = TestDb::categoryId('Food & Dining');
        Budget::set($cat, 450.0);
        Budget::delete($cat);
        $this->assertSame(0, TestDb::count('budgets'));
        $this->assertNull(Budget::get($cat));
    }

    public function testOnlyExpenseCategoriesHaveBudgets(): void
    {
        $income = TestDb::categoryId('Salary');
        Budget::set($income, 100.0); // not an expense category
        $this->assertArrayNotHasKey($income, Budget::getAll());
    }

    public function testBudgetAggregatesAcrossAccounts(): void
    {
        $cat = TestDb::categoryId('Food & Dining');
        $a = TestDb::makeSavings('TEST_Savings_A', 0.0, '2026-01');
        $b = TestDb::makeSavings('TEST_Savings_B', 0.0, '2026-01');
        Budget::set($cat, 100.0);
        Expense::addTransaction($cat, 10.0, 'expense', 'a-food', '2026-02-03', $a);
        Expense::addTransaction($cat, 15.0, 'expense', 'b-food', '2026-02-04', $b);

        $total = 0.0;
        foreach (Expense::getExpensesByCategory('02', '2026') as $row) {
            if ((int) $row['category_id'] === $cat) {
                $total += (float) $row['total'];
            }
        }
        $this->assertMoney(25.0, $total);
        $this->assertMoney(25.0, $total - 0.0); // percentage base check (25/100)
        $this->assertSame(25.0, round($total / 100.0 * 100, 1));
    }
}
