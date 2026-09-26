<?php

declare(strict_types=1);

namespace Tests\Unit;

use Account;
use TestDb;
use Tests\Support\AppTestCase;
use Transfer;

final class TransferTest extends AppTestCase
{
    private int $a;
    private int $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = TestDb::makeSavings('TEST_Savings_A', 1000.0, '2026-01');
        $this->b = TestDb::makeSavings('TEST_Savings_B', 500.0, '2026-01');
    }

    public function testValidTransferMovesMoney(): void
    {
        Transfer::create($this->a, $this->b, 200.0, '2026-02-10', 'move', 'internal');
        $this->assertMoney(800.0, Account::balance($this->a));
        $this->assertMoney(700.0, Account::balance($this->b));
    }

    public function testTransferInvariantSumIsZero(): void
    {
        $before = Account::balance($this->a) + Account::balance($this->b);
        Transfer::create($this->a, $this->b, 123.45, '2026-02-10', 'move');
        $after = Account::balance($this->a) + Account::balance($this->b);
        $this->assertMoney($before, $after);
    }

    public function testDeleteTransferReversesEffect(): void
    {
        $id = Transfer::create($this->a, $this->b, 200.0, '2026-02-10', 'move');
        Transfer::delete($id);
        $this->assertMoney(1000.0, Account::balance($this->a));
        $this->assertMoney(500.0, Account::balance($this->b));
    }

    public function testSameAccountTransferRejected(): void
    {
        $this->assertSame(0, Transfer::create($this->a, $this->a, 10.0, '2026-02-10', 'x'));
        $this->assertSame(0, TestDb::count('transfers'));
    }

    public function testZeroAmountRejected(): void
    {
        $this->assertSame(0, Transfer::create($this->a, $this->b, 0.0, '2026-02-10', 'x'));
        $this->assertSame(0, TestDb::count('transfers'));
    }

    public function testNegativeAmountRejected(): void
    {
        $this->assertSame(0, Transfer::create($this->a, $this->b, -50.0, '2026-02-10', 'x'));
        $this->assertSame(0, TestDb::count('transfers'));
    }

    public function testNonexistentAccountRejected(): void
    {
        $this->assertSame(0, Transfer::create($this->a, 999999, 10.0, '2026-02-10', 'x'));
        $this->assertSame(0, Transfer::create(999999, $this->b, 10.0, '2026-02-10', 'x'));
        $this->assertSame(0, TestDb::count('transfers'));
    }

    public function testTransferKindAndListing(): void
    {
        Transfer::create($this->a, $this->b, 10.0, '2026-02-10', 'bill', 'bill_payment');
        $rows = Transfer::forAccountBetween($this->a, '2026-02-01', '2026-02-28');
        $this->assertCount(1, $rows);
        $this->assertSame('bill_payment', $rows[0]['kind']);
    }

    public function testTransferRespectsDateRange(): void
    {
        Transfer::create($this->a, $this->b, 10.0, '2026-02-10', 'feb');
        Transfer::create($this->a, $this->b, 20.0, '2026-03-10', 'mar');
        $this->assertCount(1, Transfer::forAccountBetween($this->a, '2026-02-01', '2026-02-28'));
        $this->assertCount(1, Transfer::forAccountBetween($this->a, '2026-03-01', '2026-03-31'));
    }
}
