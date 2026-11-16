<?php

declare(strict_types=1);

namespace Tests\Unit;

use Analytics;
use Expense;
use TestDb;
use Tests\Support\AppTestCase;
use Transfer;

final class AnalyticsTest extends AppTestCase
{
    private int $institutionA;
    private int $institutionB;
    private int $savingsA;
    private int $savingsB;
    private int $food;
    private int $salary;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->date = date('Y-m-d');
        $month = date('Y-m');
        $this->food = TestDb::categoryId('Food & Dining');
        $this->salary = TestDb::categoryId('Salary');
        $this->institutionA = TestDb::makeInstitution('Maybank');
        $this->institutionB = TestDb::makeInstitution('Grab');
        $this->savingsA = TestDb::makeSection($this->institutionA, [
            'name' => 'Maybank Savings',
            'kind' => 'savings',
            'opening_balance' => 1000,
            'start_month' => $month,
        ]);
        $this->savingsB = TestDb::makeSection($this->institutionB, [
            'name' => 'Grab Wallet',
            'kind' => 'savings',
            'opening_balance' => 0,
            'start_month' => $month,
        ]);
    }

    private function addTransaction(int $accountId, string $type, float $amount, string $description, ?int $categoryId = null): void
    {
        $ok = Expense::addTransaction(
            $categoryId ?? ($type === 'income' ? $this->salary : $this->food),
            $amount,
            $type,
            $description,
            $this->date,
            $accountId
        );
        $this->assertTrue($ok);
    }

    public function testRangePresetsAndCustomRange(): void
    {
        $range = Analytics::range(['range' => 'last_3']);
        $this->assertSame('last_3', $range['preset']);
        $this->assertCount(3, $range['months']);
        $this->assertSame(date('Y-m', strtotime('first day of -2 months')), $range['months'][0]['value']);
        $this->assertSame(date('Y-m'), $range['months'][2]['value']);
        $this->assertSame(date('Y-m-01', strtotime('first day of -2 months')), $range['from']);
        $this->assertSame(date('Y-m-t'), $range['to']);

        $custom = Analytics::range([
            'range' => 'custom',
            'from' => '2026-01-01',
            'to' => '2026-03-31',
        ]);
        $this->assertCount(3, $custom['months']);
        $this->assertSame('2026-01', $custom['months'][0]['value']);
        $this->assertSame('2026-03', $custom['months'][2]['value']);
    }

    public function testRangeRejectsInvalidInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Analytics::range(['range' => 'nope']);
    }

    public function testRangeRejectsReversedCustomDates(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Analytics::range(['range' => 'custom', 'from' => '2026-03-01', 'to' => '2026-01-01']);
    }

    public function testInstitutionFilterValidation(): void
    {
        $filter = Analytics::filter(['institution_id' => (string) $this->institutionA]);
        $this->assertSame($this->institutionA, $filter['institution_id']);
        $this->assertSame('Maybank', $filter['institution_name']);

        $this->expectException(\InvalidArgumentException::class);
        Analytics::filter(['institution_id' => '99999']);
    }

    public function testSpendingByCategoryExcludesTransfersAndRespectsInstitution(): void
    {
        $this->addTransaction($this->savingsA, 'expense', 100, 'Grab ride');
        $this->addTransaction($this->savingsB, 'expense', 25, 'Grab food');
        Transfer::create($this->savingsA, $this->savingsB, 50, $this->date, 'Top up');

        $range = Analytics::range(['range' => 'last_6']);
        $all = Analytics::build('spending_category', ['top' => 8], $range, Analytics::filter([]));
        $this->assertMoney(125, (float) $all['total']);

        $filter = Analytics::filter(['institution_id' => (string) $this->institutionA]);
        $maybank = Analytics::build('spending_category', ['top' => 8], $range, $filter);
        $this->assertMoney(100, (float) $maybank['total']);
    }

    public function testSpendingByInstitutionSwitchesToSectionMode(): void
    {
        $this->addTransaction($this->savingsA, 'expense', 100, 'Maybank expense');
        $this->addTransaction($this->savingsB, 'expense', 25, 'Grab expense');

        $range = Analytics::range(['range' => 'last_6']);
        $all = Analytics::build('spending_institution', [], $range, Analytics::filter([]));
        $this->assertSame('institution', $all['mode']);
        $this->assertCount(2, $all['items']);
        $this->assertMoney(125, (float) $all['total']);

        $filter = Analytics::filter(['institution_id' => (string) $this->institutionA]);
        $sections = Analytics::build('spending_institution', [], $range, $filter);
        $this->assertSame('section', $sections['mode']);
        $this->assertCount(1, $sections['items']);
        $this->assertSame('Maybank Savings', $sections['items'][0]['name']);
        $this->assertMoney(100, (float) $sections['items'][0]['total']);
    }

    public function testTopMerchantsAggregatesAndReturnsOtherTotal(): void
    {
        $this->addTransaction($this->savingsA, 'expense', 10, 'Grab');
        $this->addTransaction($this->savingsA, 'expense', 5, 'GRAB');
        $this->addTransaction($this->savingsA, 'expense', 7, 'Shopee');
        $this->addTransaction($this->savingsA, 'expense', 3, 'Netflix');
        $this->addTransaction($this->savingsA, 'expense', 2, 'Spotify');
        $this->addTransaction($this->savingsA, 'expense', 1, 'Steam');
        $this->addTransaction($this->savingsA, 'expense', 4, 'Other Merchant');
        $this->addTransaction($this->savingsA, 'expense', 4, '   ');

        $range = Analytics::range(['range' => 'last_6']);
        $data = Analytics::build('top_merchants', ['top' => 5], $range, Analytics::filter([]));

        $this->assertMoney(32, (float) $data['total']);
        $this->assertCount(5, $data['items']);
        $this->assertSame('Grab', $data['items'][0]['name']);
        $this->assertMoney(15, (float) $data['items'][0]['total']);
        $this->assertSame(2, $data['items'][0]['count']);
        $this->assertMoney(1, (float) $data['other_total']);
    }

    public function testCreditUtilizationUsesOutstandingAndLimit(): void
    {
        $credit = TestDb::makeCredit('Maybank Visa', 20, 1000);
        $this->addTransaction($credit, 'expense', 250, 'Purchase');

        $range = Analytics::range(['range' => 'last_6']);
        $data = Analytics::build('credit_utilization', [], $range, Analytics::filter([]));

        $this->assertCount(1, $data['items']);
        $this->assertSame('Maybank Visa', $data['items'][0]['account_name']);
        $this->assertMoney(250, (float) $data['items'][0]['outstanding']);
        $this->assertMoney(750, (float) $data['items'][0]['available']);
        $this->assertSame(25.0, $data['items'][0]['used_pct']);
        $this->assertMoney(1000, (float) $data['totals']['limit']);
        $this->assertMoney(250, (float) $data['totals']['outstanding']);
    }

    public function testNetWorthMatchesAccountNetWorth(): void
    {
        $this->addTransaction($this->savingsA, 'income', 500, 'Salary');
        $this->addTransaction($this->savingsA, 'expense', 100, 'Food');
        $credit = TestDb::makeCredit('Visa', 20, 1000);
        $this->addTransaction($credit, 'expense', 200, 'Purchase');

        $range = Analytics::range(['range' => 'last_6']);
        $data = Analytics::build('net_worth', [], $range, Analytics::filter([]));

        $this->assertSame(3, $data['account_count']);
        $this->assertCount(6, $data['values']);
        $this->assertMoney(\Account::netWorth(), (float) $data['values'][5]);
        $this->assertMoney(1200, (float) $data['values'][5]);
    }

    public function testRecurringVersusOneOff(): void
    {
        Expense::addCommitment('Rent', 300, 'expense', 1, $this->food, date('Y-m-01'), null, $this->savingsA);
        $commitmentId = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'Rent'");
        $db = TestDb::pdo();
        $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date, account_id, commitment_id, commitment_period) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$this->food, 300, 'expense', 'Rent', $this->date, $this->savingsA, $commitmentId, date('Y-m')]);
        $this->addTransaction($this->savingsA, 'expense', 100, 'One-off');

        $range = Analytics::range(['range' => 'last_6']);
        $data = Analytics::build('recurring_oneoff', [], $range, Analytics::filter([]));

        $this->assertMoney(300, (float) $data['recurring']);
        $this->assertMoney(100, (float) $data['one_off']);
        $this->assertSame(1, $data['recurring_count']);
        $this->assertSame(1, $data['one_off_count']);
        $this->assertMoney(400, (float) $data['total']);
    }

    public function testSavingsRate(): void
    {
        $this->addTransaction($this->savingsA, 'income', 1000, 'Salary');
        $this->addTransaction($this->savingsA, 'expense', 400, 'Food');

        $range = Analytics::range(['range' => 'last_6']);
        $data = Analytics::build('savings_rate', [], $range, Analytics::filter([]));

        $this->assertMoney(1000, (float) $data['totals']['income']);
        $this->assertMoney(400, (float) $data['totals']['expenses']);
        $this->assertSame(60.0, $data['totals']['rate_pct']);
        $this->assertSame(60.0, $data['rate_pct'][5]);
        $this->assertNull($data['rate_pct'][0]);
    }

    public function testEmptyWidgets(): void
    {
        $range = Analytics::range(['range' => 'last_6']);
        $filter = Analytics::filter([]);

        $income = Analytics::build('income_expense', [], $range, $filter);
        $this->assertTrue(Analytics::isEmpty('income_expense', $income));

        $credit = Analytics::build('credit_utilization', [], $range, $filter);
        $this->assertTrue(Analytics::isEmpty('credit_utilization', $credit));

        $savings = Analytics::build('savings_rate', [], $range, $filter);
        $this->assertTrue(Analytics::isEmpty('savings_rate', $savings));
    }
}
