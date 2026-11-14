<?php

declare(strict_types=1);

namespace Tests\Unit;

use Account;
use Expense;
use TestDb;
use Tests\Support\AppTestCase;

final class RecurringTransferTest extends AppTestCase
{
    private int $from;
    private int $to;

    protected function setUp(): void
    {
        parent::setUp();
        $this->from = TestDb::makeSavings('RT_FROM', 100.0, date('Y-m'));
        $this->to = TestDb::makeSavings('RT_TO', 0.0, date('Y-m'));
    }

    private function addAutoTransfer(): int
    {
        Expense::addCommitment('Auto topup', 20.0, 'transfer', 1, null, date('Y-m-01'), null, $this->from, $this->to);
        return (int) TestDb::scalar("SELECT id FROM commitments WHERE name = 'Auto topup'");
    }

    public function testForecastIncludesUnpaidTransferOutAndIn(): void
    {
        $this->addAutoTransfer();
        $month = date('m');
        $year = date('Y');

        $fromForecast = Expense::projectedEomBreakdown($this->from, $month, $year);
        $this->assertMoney(20.0, $fromForecast['transfer_out']);
        $this->assertMoney(0.0, $fromForecast['transfer_in']);
        $this->assertMoney(80.0, $fromForecast['eom']);

        $toForecast = Expense::projectedEomBreakdown($this->to, $month, $year);
        $this->assertMoney(20.0, $toForecast['transfer_in']);
        $this->assertMoney(20.0, $toForecast['eom']);

        $this->assertSame(['in' => 20.0, 'out' => 0.0], Expense::getUnpaidTransfers($month, $year, $this->to));
    }

    public function testProcessingPostsATransferOnce(): void
    {
        $commitmentId = $this->addAutoTransfer();

        Expense::processDueCommitments(true);

        $transfer = TestDb::pdo()->query("SELECT * FROM transfers WHERE commitment_id = {$commitmentId}")->fetch();
        $this->assertNotFalse($transfer);
        $this->assertSame('[Auto] Auto topup', $transfer['description']);
        $this->assertSame('internal', $transfer['kind']);
        $this->assertSame($this->from, (int) $transfer['from_account_id']);
        $this->assertSame($this->to, (int) $transfer['to_account_id']);
        $this->assertSame(date('Y-m'), $transfer['commitment_period']);
        $this->assertMoney(20.0, (float) $transfer['amount']);

        $this->assertTrue(Expense::currentPeriodPosted($commitmentId));
        $this->assertFalse(Expense::postCommitmentNow($commitmentId));

        // Posting moves the balances; the forecast no longer double counts.
        $this->assertMoney(80.0, Account::balance($this->from));
        $this->assertMoney(20.0, Account::balance($this->to));
        $this->assertSame(['in' => 0.0, 'out' => 0.0], Expense::getUnpaidTransfers(date('m'), date('Y'), $this->from));
    }

    public function testPostNowCreatesTheTransferAndEditPropagates(): void
    {
        $commitmentId = $this->addAutoTransfer();

        $this->assertTrue(Expense::postCommitmentNow($commitmentId));
        $this->assertTrue(Expense::currentPeriodPosted($commitmentId));

        Expense::updateCommitment($commitmentId, 'Auto topup edited', 30.0, 'transfer', 1, null, date('Y-m-01'), null, $this->from, $this->to);

        $transfer = TestDb::pdo()->query("SELECT * FROM transfers WHERE commitment_id = {$commitmentId}")->fetch();
        $this->assertSame('[Auto] Auto topup edited', $transfer['description']);
        $this->assertMoney(30.0, (float) $transfer['amount']);
        $this->assertMoney(70.0, Account::balance($this->from));
        $this->assertMoney(30.0, Account::balance($this->to));
    }

    public function testDeletingTheScheduleKeepsTransferHistory(): void
    {
        $commitmentId = $this->addAutoTransfer();
        Expense::processDueCommitments(true);

        Expense::deleteCommitment($commitmentId);

        $transfer = TestDb::pdo()->query("SELECT * FROM transfers WHERE from_account_id = {$this->from}")->fetch();
        $this->assertNotFalse($transfer);
        $this->assertNull($transfer['commitment_id']);
        $this->assertMoney(20.0, (float) $transfer['amount']);
    }
}
