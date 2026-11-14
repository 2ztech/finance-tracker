<?php

declare(strict_types=1);

namespace Tests\Unit;

use Account;
use Database;
use Expense;
use Institution;
use TestDb;
use Tests\Support\AppTestCase;

final class InstitutionTest extends AppTestCase
{
    public function testOrphanAccountsAreBackfilledIntoInstitutions(): void
    {
        $db = TestDb::pdo();
        $db->exec("INSERT INTO accounts (name, kind, color_hex, opening_balance, archived, sort_order, created_at)
                   VALUES ('Legacy Maybank', 'savings', '#4f6ef7', 0, 0, 0, '2026-01-01 00:00:00')");
        $accountId = (int) $db->lastInsertId();

        Database::reset();
        Database::getConnection();

        $account = Account::find($accountId);
        $this->assertNotNull($account['institution_id']);
        $institution = Institution::find((int) $account['institution_id']);
        $this->assertSame('Legacy Maybank', $institution['name']);

        // Running the schema init again must not create a duplicate.
        Database::reset();
        Database::getConnection();
        $this->assertSame(1, TestDb::count('institutions'));
    }

    public function testGroupingAndSummary(): void
    {
        $institutionId = TestDb::makeInstitution('Grab');
        $wallet = TestDb::makeSection($institutionId, [
            'name' => 'GrabPay Wallet',
            'kind' => 'savings',
            'opening_balance' => 100.0,
            'start_month' => '2026-01',
        ]);
        $credit = TestDb::makeSection($institutionId, [
            'name' => 'Grab PayLater',
            'kind' => 'paylater',
            'bnpl_mode' => 'cycle',
            'due_day' => 7,
            'opening_balance' => 0.0,
        ]);
        Expense::addTransaction(TestDb::categoryId(), 40.0, 'expense', 'food', '2026-09-05', $credit);

        $grouped = Institution::grouped();
        $this->assertCount(1, $grouped);
        $this->assertSame('Grab', $grouped[0]['name']);
        $this->assertCount(2, $grouped[0]['sections']);

        $groups = Institution::groupAccounts(Account::all());
        $this->assertArrayHasKey('Grab', $groups);
        $this->assertCount(2, $groups['Grab']);

        $summary = Institution::summary($institutionId);
        $this->assertSame(100.0, $summary['assets']);
        $this->assertSame(40.0, $summary['owed']);
        $this->assertSame(60.0, $summary['net']);
        $this->assertSame(100.0, Account::balance($wallet));
    }

    public function testDefaultSectionPrefersWalletThenPaylater(): void
    {
        $institutionId = TestDb::makeInstitution('Priority');
        TestDb::makeSection($institutionId, [
            'name' => 'PayLater',
            'kind' => 'paylater',
            'bnpl_mode' => 'cycle',
            'paylater_role' => 'bnpl',
        ]);
        TestDb::makeSection($institutionId, [
            'name' => 'Financing',
            'kind' => 'paylater',
            'bnpl_mode' => 'cycle',
            'paylater_role' => 'financing',
        ]);
        $wallet = TestDb::makeSection($institutionId, ['name' => 'Wallet', 'kind' => 'savings']);

        $this->assertSame($wallet, (int) Institution::defaultSection($institutionId)['id']);
        $this->assertCount(2, Institution::liabilitySections($institutionId));

        $_SESSION = [];
        Institution::activate($institutionId);
        $this->assertSame($institutionId, (int) Institution::active()['id']);
        $this->assertSame($wallet, Account::activeId());
    }

    public function testLegacyFinancingPlansAreRecoveredOnImport(): void
    {
        $institutionId = TestDb::makeInstitution('Legacy TikTok');
        $accountId = TestDb::makeSection($institutionId, [
            'name' => 'Legacy TikTok',
            'kind' => 'paylater',
            'bnpl_mode' => 'cycle',
        ]);

        $db = TestDb::pdo();
        $tx = $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date, account_id) VALUES (?, ?, ?, ?, ?, ?)");
        $tx->execute([TestDb::categoryId(), 100.0, 'expense', 'Financing Loan', '2026-10-05', $accountId]);
        $financingTxn = (int) $db->lastInsertId();

        $plan = $db->prepare("INSERT INTO paylater_plans
            (account_id, purchase_txn_id, total_payable, months, installment_amount, first_due_date, status, created_at, kind)
            VALUES (?, ?, ?, ?, ?, ?, 'active', ?, 'purchase')");
        $plan->execute([$accountId, null, 50.0, 3, 16.67, '2026-11-10', '2026-10-01 00:00:00']);
        $plan->execute([$accountId, $financingTxn, 100.0, 3, 33.33, '2026-11-05', '2026-10-05 00:00:00']);

        $db->prepare("UPDATE accounts SET paylater_role = 'bnpl' WHERE id = ?")->execute([$accountId]);
        $db->prepare("DELETE FROM settings WHERE key = 'legacy_financing_backfill'")->execute();

        Database::reset();
        Database::getConnection();

        $this->assertSame('both', Account::find($accountId)['paylater_role']);
        $this->assertSame(1, (int) TestDb::scalar("SELECT COUNT(*) FROM paylater_plans WHERE account_id = ? AND kind = 'financing'", [$accountId]));
        $this->assertSame(1, (int) TestDb::scalar("SELECT COUNT(*) FROM paylater_plans WHERE account_id = ? AND kind = 'purchase'", [$accountId]));
    }

    public function testExistingCombinedPaylaterBackfillsToBoth(): void
    {
        $institutionId = TestDb::makeInstitution('Legacy');
        $accountId = TestDb::makeSection($institutionId, [
            'name' => 'Legacy PayLater',
            'kind' => 'paylater',
            'bnpl_mode' => 'cycle',
        ]);

        $db = TestDb::pdo();
        $db->prepare("INSERT INTO paylater_plans (account_id, total_payable, months, installment_amount, first_due_date, status, created_at, kind)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$accountId, 100.0, 3, 33.33, '2026-01-01', 'active', '2026-01-01 00:00:00', 'financing']);
        $db->prepare("UPDATE accounts SET paylater_role = NULL WHERE id = ?")->execute([$accountId]);

        Database::reset();
        Database::getConnection();

        $this->assertSame('both', Account::find($accountId)['paylater_role']);
    }

    public function testOnlyOneMainAccountIsKept(): void
    {
        $one = TestDb::makeSavings('One', 10.0);
        $two = TestDb::makeSavings('Two', 10.0);

        Account::setPrimary($one);
        Account::setPrimary($two);

        $this->assertSame($two, Account::primaryId());
        $this->assertSame(1, TestDb::count('accounts', 'is_primary = 1'));
    }

    public function testAccountCreateAutoCreatesAnInstitution(): void
    {
        $id = Account::create(['name' => 'Custom Wallet', 'kind' => 'savings']);
        $account = Account::find($id);

        $this->assertNotNull($account['institution_id']);
        $this->assertSame(1, TestDb::count('institutions'));
        $this->assertSame('Custom Wallet', Institution::forAccount($id)['name']);
    }

    public function testHasTransactionsAndDelete(): void
    {
        $institutionId = TestDb::makeInstitution('Empty');
        TestDb::makeSection($institutionId, ['name' => 'Empty Savings', 'kind' => 'savings']);

        $this->assertFalse(Institution::hasTransactions($institutionId));
        $this->assertTrue(Institution::delete($institutionId));
        $this->assertSame(0, TestDb::count('institutions'));
        $this->assertSame(0, TestDb::count('accounts'));

        $institutionId = TestDb::makeInstitution('Used');
        $section = TestDb::makeSection($institutionId, ['name' => 'Used Savings', 'kind' => 'savings']);
        Expense::addTransaction(TestDb::categoryId(), 5.0, 'expense', 'x', '2026-09-01', $section);

        $this->assertTrue(Institution::hasTransactions($institutionId));
    }
}
