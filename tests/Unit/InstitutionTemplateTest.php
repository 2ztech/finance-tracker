<?php

declare(strict_types=1);

namespace Tests\Unit;

use InstitutionTemplate;
use Tests\Support\AppTestCase;

final class InstitutionTemplateTest extends AppTestCase
{
    public function testCatalogHasInstitutionsWithValidSections(): void
    {
        $all = InstitutionTemplate::all();
        $this->assertNotEmpty($all);

        foreach ($all as $key => $institution) {
            $this->assertSame($key, $institution['key']);
            $this->assertNotSame('', (string) $institution['default_name'], $key);
            $this->assertNotEmpty($institution['sections'], $key);
            foreach ($institution['sections'] as $section) {
                $this->assertContains($section['kind'], ['savings', 'credit', 'paylater'], $key . '/' . $section['key']);
                $this->assertNotEmpty($section['questions'], $key . '/' . $section['key']);
            }
        }
    }

    public function testGrabIsOneAccountWithThreeSections(): void
    {
        $grab = InstitutionTemplate::find('grab');
        $this->assertNotNull($grab);
        $this->assertSame(['wallet', 'postpaid', 'instalments'], array_column($grab['sections'], 'key'));
        $this->assertSame('savings', $grab['sections'][0]['kind']);
        $this->assertSame('paylater', $grab['sections'][1]['kind']);
        $this->assertSame('paylater', $grab['sections'][2]['kind']);
    }

    public function testAtomeHasCardAndPayLaterWithSeparateLimits(): void
    {
        $built = InstitutionTemplate::apply('atome', [
            'sections' => [
                'card' => ['enabled' => '1', 'credit_limit' => '1000', 'bill_date' => '16'],
                'paylater' => ['enabled' => '1', 'credit_limit' => '500'],
            ],
        ]);

        $this->assertNotNull($built);
        $this->assertSame('Atome', $built['institution']['name']);
        $this->assertNull($built['institution']['icon_data']);
        $this->assertCount(2, $built['sections']);

        $card = $built['sections'][0];
        $this->assertSame('credit', $card['kind']);
        $this->assertSame(15, $card['statement_day']);
        $this->assertSame(26, $card['due_day']);
        $this->assertSame(1000.0, $card['credit_limit']);
        $this->assertSame(0.0, $card['opening_balance']);

        $paylater = $built['sections'][1];
        $this->assertSame('paylater', $paylater['kind']);
        $this->assertSame('per_purchase', $paylater['bnpl_mode']);
        $this->assertSame(500.0, $paylater['credit_limit']);
        $this->assertSame(0.0, $paylater['opening_balance']);
    }

    public function testUncheckedProductsAreNotCreated(): void
    {
        $this->assertNull(InstitutionTemplate::apply('grab', []));

        $built = InstitutionTemplate::apply('grab', [
            'sections' => [
                'wallet' => ['enabled' => '1', 'balance' => '25', 'make_main' => '1'],
                'postpaid' => ['enabled' => '0'],
                'instalments' => ['enabled' => '0'],
            ],
        ]);

        $this->assertNotNull($built);
        $this->assertCount(1, $built['sections']);
        $this->assertSame('GrabPay Wallet', $built['sections'][0]['name']);
        $this->assertSame(1, $built['sections'][0]['is_primary']);
    }

    public function testTikTokStatementChoiceSetsDueDay(): void
    {
        $built = InstitutionTemplate::apply('tiktok', [
            'sections' => [
                'paylater' => ['enabled' => '1', 'name' => 'My TikTok', 'choice' => '21'],
            ],
        ]);

        $this->assertNotNull($built);
        $section = $built['sections'][0];
        $this->assertSame('My TikTok', $section['name']);
        $this->assertSame(21, $section['statement_day']);
        $this->assertSame(1, $section['due_day']);
        $this->assertSame('cycle', $section['bnpl_mode']);
    }

    public function testTikTokHasWalletPayLaterAndFinancing(): void
    {
        $tiktok = InstitutionTemplate::find('tiktok');
        $this->assertNotNull($tiktok);
        $this->assertSame(['wallet', 'paylater', 'financing'], array_column($tiktok['sections'], 'key'));

        $built = InstitutionTemplate::apply('tiktok', [
            'sections' => [
                'wallet' => ['enabled' => '1', 'balance' => '15'],
                'paylater' => ['enabled' => '1', 'credit_limit' => '500', 'choice' => '1'],
                'financing' => ['enabled' => '1', 'name' => 'TikTok Financing'],
            ],
        ]);

        $this->assertNotNull($built);
        $this->assertCount(3, $built['sections']);
        $this->assertSame('savings', $built['sections'][0]['kind']);
        $this->assertSame('cycle', $built['sections'][1]['bnpl_mode']);
        $this->assertSame(10, $built['sections'][1]['due_day']);
        $this->assertSame('TikTok Financing', $built['sections'][2]['name']);
        $this->assertSame('cycle', $built['sections'][2]['bnpl_mode']);
        $this->assertSame('bnpl', $built['sections'][1]['paylater_role']);
        $this->assertSame('financing', $built['sections'][2]['paylater_role']);
    }

    public function testShopeeHasSloanAsAFinancingSection(): void
    {
        $shopee = InstitutionTemplate::find('shopee');
        $this->assertNotNull($shopee);
        $this->assertSame(['wallet', 'spaylater', 'sloan'], array_column($shopee['sections'], 'key'));

        $built = InstitutionTemplate::apply('shopee', [
            'sections' => [
                'sloan' => ['enabled' => '1', 'credit_limit' => '5000'],
            ],
        ]);

        $this->assertNotNull($built);
        $this->assertCount(1, $built['sections']);
        $section = $built['sections'][0];
        $this->assertSame('Shopee SLoan', $section['name']);
        $this->assertSame('financing', $section['paylater_role']);
        $this->assertSame(5000.0, $section['credit_limit']);
        $this->assertSame(0.0, $section['opening_balance']);
    }

    public function testSavingsDefaultsAndSingleMain(): void
    {
        $built = InstitutionTemplate::apply('maybank', [
            'sections' => [
                'savings' => ['balance' => '250', 'make_main' => '1'],
            ],
        ]);

        $this->assertNotNull($built);
        $this->assertSame('Maybank', $built['institution']['name']);
        $section = $built['sections'][0];
        $this->assertSame('savings', $section['kind']);
        $this->assertSame(250.0, $section['opening_balance']);
        $this->assertSame(1, $section['is_primary']);
        $this->assertSame(date('Y-m'), $section['start_month']);
    }

    public function testUnknownInstitutionIsRejected(): void
    {
        $this->assertNull(InstitutionTemplate::apply('does_not_exist', []));
    }

    public function testBundledLogosResolve(): void
    {
        $this->assertNotNull(InstitutionTemplate::logoData('tiktok'));
        $this->assertNotNull(InstitutionTemplate::logoDataForName('Atome Card'));
        $this->assertSame('/assets/brands/tiktok.ico', InstitutionTemplate::logoUrl('tiktok'));
        $this->assertNotNull(InstitutionTemplate::logoUrlForName('Maybank'));
        $this->assertNull(InstitutionTemplate::logoUrl('definitely-not-a-brand'));
    }
}
