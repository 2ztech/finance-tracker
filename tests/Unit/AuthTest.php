<?php

declare(strict_types=1);

namespace Tests\Unit;

use Auth;
use TestDb;
use Tests\Support\AppTestCase;

final class AuthTest extends AppTestCase
{
    public function testSetupFirstUserAndLogin(): void
    {
        $this->assertFalse(Auth::hasUsers());
        $this->assertTrue(Auth::setupFirstUser('admin', 'secret123'));
        $this->assertTrue(Auth::hasUsers());

        $this->assertTrue(Auth::attemptLogin('admin', 'secret123'));
        $this->assertTrue(Auth::isLoggedIn());
        $this->assertSame('admin', $_SESSION['username']);
    }

    public function testInvalidLoginRejected(): void
    {
        Auth::setupFirstUser('admin', 'secret123');
        $this->assertFalse(Auth::attemptLogin('admin', 'wrong'));
        $this->assertFalse(Auth::attemptLogin('nobody', 'secret123'));
    }

    public function testLoginRateLimit(): void
    {
        Auth::setupFirstUser('admin', 'secret123');
        $ip = '203.0.113.9';
        Auth::clearLoginAttempts($ip);
        $this->assertFalse(Auth::isRateLimited($ip));
        for ($i = 0; $i < 5; $i++) {
            Auth::recordFailedLogin($ip);
        }
        $this->assertTrue(Auth::isRateLimited($ip));
        Auth::clearLoginAttempts($ip);
        $this->assertFalse(Auth::isRateLimited($ip));
    }

    public function testUpdateCredentialsRequiresOldPassword(): void
    {
        Auth::setupFirstUser('admin', 'secret123');
        $id = (int) TestDb::scalar("SELECT id FROM users WHERE username = 'admin'");
        $this->assertFalse(Auth::updateCredentials($id, 'admin', 'wrong', 'newpass'));
    }

    public function testUpdatePasswordThenLogin(): void
    {
        Auth::setupFirstUser('admin', 'secret123');
        $id = (int) TestDb::scalar("SELECT id FROM users WHERE username = 'admin'");
        $this->assertTrue(Auth::updateCredentials($id, 'admin', 'secret123', 'newpass456'));
        $this->assertTrue(Auth::attemptLogin('admin', 'newpass456'));
        $this->assertFalse(Auth::attemptLogin('admin', 'secret123'));
    }

    public function testDuplicateUsernameRejected(): void
    {
        Auth::setupFirstUser('admin', 'secret123');
        $db = TestDb::pdo();
        $db->prepare("INSERT INTO users (username, password_hash) VALUES ('other', ?)")->execute([password_hash('x', PASSWORD_DEFAULT)]);
        $adminId = (int) TestDb::scalar("SELECT id FROM users WHERE username = 'admin'");
        $this->assertFalse(Auth::updateCredentials($adminId, 'other', 'secret123', null));
    }
}
