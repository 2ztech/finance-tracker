<?php

declare(strict_types=1);

namespace Tests\Unit;

use Analytics;
use AnalyticsApi;
use TestDb;
use Tests\Support\AppTestCase;
use Widgets;

final class AnalyticsApiTest extends AppTestCase
{
    private string $token = 'test-csrf-token';

    public function testUnauthenticatedRequestReturnsJson401(): void
    {
        $response = AnalyticsApi::dispatch('GET', '/api/analytics', [], [], [], false, $this->token);

        $this->assertSame(401, $response['status']);
        $this->assertFalse($response['body']['ok']);
        $this->assertSame('unauthorized', $response['body']['error']['code']);
    }

    public function testGetReturnsStateEnvelope(): void
    {
        $institution = TestDb::makeInstitution('Maybank');
        TestDb::makeSection($institution, [
            'name' => 'Maybank Savings',
            'kind' => 'savings',
            'opening_balance' => 100,
            'start_month' => date('Y-m'),
        ]);

        $response = AnalyticsApi::dispatch(
            'GET',
            '/api/analytics',
            ['range' => 'last_6'],
            [],
            [],
            true,
            $this->token
        );

        $this->assertSame(200, $response['status']);
        $body = $response['body'];
        $this->assertTrue($body['ok']);
        $this->assertSame('MYR', $body['currency']);
        $this->assertSame($this->token, $body['csrf_token']);
        $this->assertSame(0, $body['filters']['institution_id']);
        $this->assertCount(5, $body['widgets']);
        $this->assertSame('income_expense', $body['widgets'][0]['key']);
        $this->assertArrayHasKey('data', $body['widgets'][0]);
        $this->assertCount(5, $body['layout']['widgets']);
        $this->assertCount(8, $body['catalog']);
        $this->assertSame('Maybank', $body['filters']['institutions'][0]['name']);
        $this->assertSame('last_6', $body['filters']['range']['preset']);
    }

    public function testInstitutionFilterIsApplied(): void
    {
        $a = TestDb::makeInstitution('Maybank');
        $b = TestDb::makeInstitution('Grab');
        $accountA = TestDb::makeSection($a, ['name' => 'Maybank Savings', 'kind' => 'savings', 'opening_balance' => 0, 'start_month' => date('Y-m')]);
        $accountB = TestDb::makeSection($b, ['name' => 'Grab Wallet', 'kind' => 'savings', 'opening_balance' => 0, 'start_month' => date('Y-m')]);
        $food = TestDb::categoryId('Food & Dining');
        \Expense::addTransaction($food, 100, 'expense', 'A', date('Y-m-d'), $accountA);
        \Expense::addTransaction($food, 25, 'expense', 'B', date('Y-m-d'), $accountB);

        $response = AnalyticsApi::dispatch(
            'GET',
            '/api/analytics',
            ['range' => 'last_6', 'institution_id' => (string) $a],
            [],
            [],
            true,
            $this->token
        );

        $category = $response['body']['widgets'][1];
        $this->assertSame($a, $response['body']['filters']['institution_id']);
        $this->assertMoney(100, (float) $category['data']['total']);
    }

    public function testSaveLayoutRequiresCsrf(): void
    {
        $response = AnalyticsApi::dispatch(
            'POST',
            '/api/analytics/layout',
            [],
            ['layout' => Widgets::defaultLayout()],
            ['x-csrf-token' => 'wrong'],
            true,
            $this->token
        );

        $this->assertSame(403, $response['status']);
        $this->assertSame('csrf_failed', $response['body']['error']['code']);
    }

    public function testSaveAndResetLayout(): void
    {
        $layout = Widgets::defaultLayout();
        $layout['widgets'][0]['size'] = 'wide';
        $layout['widgets'][0]['config'] = ['chart' => 'line'];

        $saved = AnalyticsApi::dispatch(
            'POST',
            '/api/analytics/layout',
            [],
            ['layout' => $layout],
            ['x-csrf-token' => $this->token],
            true,
            $this->token
        );
        $this->assertSame(200, $saved['status']);
        $this->assertSame('wide', $saved['body']['layout']['widgets'][0]['size']);
        $this->assertSame('wide', Widgets::load()['widgets'][0]['size']);

        $reset = AnalyticsApi::dispatch(
            'POST',
            '/api/analytics/layout/reset',
            [],
            [],
            ['x-csrf-token' => $this->token],
            true,
            $this->token
        );
        $this->assertSame(200, $reset['status']);
        $this->assertSame(Widgets::defaultLayout(), $reset['body']['layout']);
        $this->assertSame(Widgets::defaultLayout(), Widgets::load());
    }

    public function testInvalidLayoutReturns422WithDetails(): void
    {
        $response = AnalyticsApi::dispatch(
            'POST',
            '/api/analytics/layout',
            [],
            ['layout' => ['version' => 1, 'widgets' => [['id' => 'x', 'key' => 'nope']]]],
            ['x-csrf-token' => $this->token],
            true,
            $this->token
        );

        $this->assertSame(422, $response['status']);
        $this->assertSame('invalid_layout', $response['body']['error']['code']);
        $this->assertArrayHasKey('details', $response['body']['error']);
    }

    public function testInvalidRangeAndInstitutionReturn422(): void
    {
        $badRange = AnalyticsApi::dispatch('GET', '/api/analytics', ['range' => 'nope'], [], [], true, $this->token);
        $this->assertSame(422, $badRange['status']);

        $badInstitution = AnalyticsApi::dispatch('GET', '/api/analytics', ['institution_id' => '99999'], [], [], true, $this->token);
        $this->assertSame(422, $badInstitution['status']);
    }

    public function testMethodAndRouteErrors(): void
    {
        $post = AnalyticsApi::dispatch('POST', '/api/analytics', [], [], [], true, $this->token);
        $this->assertSame(405, $post['status']);
        $this->assertSame('GET', $post['allow']);

        $unknown = AnalyticsApi::dispatch('GET', '/api/analytics/nope', [], [], [], true, $this->token);
        $this->assertSame(404, $unknown['status']);
    }

    public function testRangeAndFilterAreReusable(): void
    {
        $range = Analytics::range(['range' => 'this_month']);
        $filter = Analytics::filter([]);
        $this->assertSame(1, count($range['months']));
        $this->assertSame(0, $filter['institution_id']);
    }
}
