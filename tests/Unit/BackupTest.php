<?php

declare(strict_types=1);

namespace Tests\Unit;

use Backup;
use Expense;
use TestDb;
use Tests\Support\AppTestCase;

final class BackupTest extends AppTestCase
{
    public function testConsistentCopyIsValidSqlite(): void
    {
        $dest = sys_get_temp_dir() . '/bk_' . bin2hex(random_bytes(4)) . '.db';
        $this->assertTrue(Backup::createConsistentCopy($dest));
        $magic = substr((string) file_get_contents($dest), 0, 16);
        $this->assertSame("SQLite format 3\x00", $magic);

        $check = Backup::validateSqliteFile($dest);
        $this->assertTrue($check['ok'], $check['error']);
        @unlink($dest);
    }

    public function testValidateRejectsNonSqlite(): void
    {
        $f = sys_get_temp_dir() . '/bogus_' . bin2hex(random_bytes(4)) . '.db';
        file_put_contents($f, 'this is not a database');
        $check = Backup::validateSqliteFile($f);
        $this->assertFalse($check['ok']);
        @unlink($f);
    }

    public function testValidateRejectsSqliteMissingTables(): void
    {
        $f = sys_get_temp_dir() . '/empty_' . bin2hex(random_bytes(4)) . '.db';
        $pdo = new \PDO('sqlite:' . $f);
        $pdo->exec('CREATE TABLE foo (id INTEGER)');
        $check = Backup::validateSqliteFile($f);
        $this->assertFalse($check['ok']);
        @unlink($f);
    }

    public function testBackupInvariantReproducesState(): void
    {
        $acc = TestDb::makeSavings('TEST_Savings_A', 100.0, '2026-01');
        Expense::addTransaction(TestDb::categoryId(), 42.0, 'expense', 'x', '2026-02-01', $acc);

        $dest = sys_get_temp_dir() . '/inv_' . bin2hex(random_bytes(4)) . '.db';
        Backup::createConsistentCopy($dest);

        $copy = new \PDO('sqlite:' . $dest);
        $count = (int) $copy->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
        $this->assertSame(1, $count);
        $amount = (float) $copy->query('SELECT amount FROM transactions LIMIT 1')->fetchColumn();
        $this->assertMoney(42.0, $amount);
        @unlink($dest);
    }

    public function testBackupCurrentReturnsPath(): void
    {
        TestDb::makeSavings('TEST_Savings_A', 5.0, '2026-01');
        $path = Backup::backupCurrent(3);
        $this->assertNotNull($path);
        $this->assertFileExists($path);
        $this->assertTrue(Backup::validateSqliteFile($path)['ok']);
        @unlink($path);
    }
}
