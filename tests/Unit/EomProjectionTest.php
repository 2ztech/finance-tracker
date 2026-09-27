<?php

declare(strict_types=1);

namespace Tests\Unit;

use Account;
use Bill;
use Expense;
use PaylaterPlan;
use Settings;
use TestDb;
use Tests\Support\AppTestCase;

final class EomProjectionTest extends AppTestCase
{
    private int $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = TestDb::categoryId();
    }

    public function testProjectionIncludesDatedCashActivityAndOnlyBillsDueByMonthEnd(): void
    {
        $savings = TestDb::makeSavings('TEST_EOM_Main', 1000.0, '2026-01');
        $credit = Account::create([
            'name' => 'TEST_EOM_Atome', 'kind' => 'credit', 'statement_day' => 15, 'due_day' => 26,
        ]);
        Expense::addTransaction($this->category, 100.0, 'expense', 'September cash expense', '2026-09-20', $savings);
        Expense::addTransaction($this->category, 300.0, 'expense', 'Future October cash expense', '2026-10-01', $savings);
        Expense::addTransaction($this->category, 200.0, 'expense', 'Atome September statement', '2026-09-15', $credit);
        Expense::addTransaction($this->category, 400.0, 'expense', 'Atome October statement', '2026-09-16', $credit);

        $this->assertMoney(700.0, Expense::projectedEndOfMonth($savings, '09', '2026'));
        $this->assertMoney(700.0, Expense::getEOMProjection('09', '2026', $savings));
        $this->assertMoney(0.0, Expense::projectedEndOfMonth($savings, '10', '2026'));
    }

    public function testPaymentAfterViewedMonthDoesNotEraseHistoricalLiability(): void
    {
        $savings = TestDb::makeSavings('TEST_EOM_History', 1000.0, '2026-01');
        $credit = Account::create([
            'name' => 'TEST_EOM_History_Atome', 'kind' => 'credit', 'statement_day' => 15, 'due_day' => 26,
        ]);
        Expense::addTransaction($this->category, 100.0, 'expense', 'September cash expense', '2026-09-20', $savings);
        Expense::addTransaction($this->category, 200.0, 'expense', 'September card statement', '2026-09-15', $credit);
        Bill::sync($credit);

        Bill::payDue($credit, '2026-09-26', 200.0, $savings, '2026-10-05');

        $this->assertMoney(700.0, Expense::projectedEndOfMonth($savings, '09', '2026'));
        $this->assertMoney(700.0, Expense::projectedEndOfMonth($savings, '10', '2026'));
    }

    public function testProjectionIncludesPaylaterInstallmentsDueBySelectedMonthEnd(): void
    {
        $savings = TestDb::makeSavings('TEST_EOM_PayLater', 500.0, '2026-01');
        $paylater = TestDb::makePaylater('TEST_EOM_PL', 'per_purchase', null, 0);
        PaylaterPlan::createPurchase($paylater, $this->category, 'September purchase', '2026-09-20', 90.0, 3, null, 0);

        $this->assertMoney(470.0, Expense::projectedEndOfMonth($savings, '09', '2026'));
        $this->assertMoney(440.0, Expense::projectedEndOfMonth($savings, '10', '2026'));
    }

    public function testUnpostedRecurringFromPriorMonthsCarriesIntoLaterEom(): void
    {
        Settings::set('tracking_start_month', '2026-01');
        $savings = TestDb::makeSavings('TEST_EOM_Carry', 500.0, '2026-01');
        Expense::addCommitment('MISSED_SEPTEMBER', 286.12, 'expense', 29, $this->category, '2026-09-01', '2026-09-30', $savings);

        $this->assertMoney(213.88, Expense::projectedEndOfMonth($savings, '09', '2026'));
        $this->assertMoney(213.88, Expense::projectedEndOfMonth($savings, '10', '2026'));
    }

    /**
     * Real-world acceptance: Maybank + Atome Card, September 2026 snapshot.
     * Sep EOM must be 987.46 and Oct EOM 2,134.94.
     */
    public function testAcceptanceSeptemberAndOctoberProjection(): void
    {
        Settings::set('tracking_start_month', '2026-09');
        $main = TestDb::makeSavings('TEST_ACC_MAYBANK', 1273.58, '2026-01');
        // Posted history is written to a separate account so it frees the
        // recurring schedules without disturbing Maybank's balance.
        $history = TestDb::makeSavings('TEST_ACC_HISTORY', 0.0, '2026-01');

        $expenses = [
            ['Y15 LOAN', 280.0, 2, '2025-10-01', null, true],
            ['UNIFI', 136.75, 27, '2026-03-01', '2027-03-31', true],
            ['YOUTUBE', 20.9, 27, null, null, true],
            ['SPAYLATER', 140.79, 30, '2026-06-01', '2026-11-30', false],
            ['AIRCOND', 55.0, 7, '2026-07-01', '2027-03-31', true],
            ['RX_9060', 231.56, 11, '2026-08-01', '2027-02-28', true],
            ['DIGI', 74.2, 27, '2026-09-01', '2027-08-31', true],
            ['TIKTOK_FIN', 145.33, 29, '2026-04-01', '2026-09-30', false],
        ];
        $insert = TestDb::pdo()->prepare("INSERT INTO transactions (category_id, amount, type, description, date, account_id, commitment_id, commitment_period) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($expenses as [$name, $amount, $day, $start, $end, $posted]) {
            Expense::addCommitment($name, $amount, 'expense', $day, $this->category, $start, $end, $main);
            if ($posted) {
                $id = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = ?", [$name]);
                $insert->execute([$this->category, $amount, 'expense', 'posted 2026-09', '2026-09-15', $history, $id, '2026-09']);
            }
        }
        Expense::addCommitment('GAJI', 2561.05, 'income', 26, $this->category, '2026-09-01', null, $main);
        $gajiId = (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'GAJI'");
        $insert->execute([$this->category, 2561.05, 'income', 'posted 2026-09', '2026-09-15', $history, $gajiId, '2026-09']);

        // Atome Card: cut-off 15, due 26. Both purchases are on/after the 16th,
        // so they roll into the October bill (200 + 274.37 = 474.37).
        $atome = Account::create(['name' => 'TEST_ACC_ATOME', 'kind' => 'credit', 'statement_day' => 15, 'due_day' => 26]);
        Expense::addTransaction($this->category, 200.0, 'expense', 'Atome Sep 16', '2026-09-16', $atome);
        Expense::addTransaction($this->category, 274.37, 'expense', 'Atome Sep 20', '2026-09-20', $atome);

        $sep = Expense::projectedEomBreakdown($main, '09', '2026');
        $this->assertMoney(1273.58, $sep['balance']);
        $this->assertMoney(0.0, $sep['recurring_income']);
        $this->assertMoney(286.12, $sep['recurring_expense']);
        $this->assertMoney(0.0, $sep['bills']);
        $this->assertMoney(987.46, $sep['eom']);

        $oct = Expense::projectedEomBreakdown($main, '10', '2026');
        $this->assertMoney(2561.05, $oct['recurring_income']);
        $this->assertMoney(1225.32, $oct['recurring_expense']);
        $this->assertMoney(474.37, $oct['bills']);
        $this->assertMoney(2134.94, $oct['eom']);
    }
}
