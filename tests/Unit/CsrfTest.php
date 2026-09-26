<?php

declare(strict_types=1);

namespace Tests\Unit;

use Csrf;
use Tests\Support\AppTestCase;

final class CsrfTest extends AppTestCase
{
    public function testTokenIsStableHex(): void
    {
        $_SESSION = [];
        $t = Csrf::token();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $t);
        $this->assertSame($t, Csrf::token());
    }

    public function testGetRequestsAreAlwaysValid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertTrue(Csrf::isValid());
    }

    public function testPostWithMatchingTokenIsValid(): void
    {
        $_SESSION = [];
        $t = Csrf::token();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => $t];
        $this->assertTrue(Csrf::isValid());
    }

    public function testPostWithWrongTokenIsRejected(): void
    {
        $_SESSION = [];
        Csrf::token();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'deadbeef'];
        $this->assertFalse(Csrf::isValid());
    }

    public function testPostWithoutSessionTokenIsRejected(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'anything'];
        $this->assertFalse(Csrf::isValid());
    }
}
