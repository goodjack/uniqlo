<?php

namespace Tests\Unit\Support;

use App\Support\OfficialProductDetail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfficialProductDetailTest extends TestCase
{
    public function test_every_main_image_is_kept_in_both_sizes(): void
    {
        $detail = OfficialProductDetail::fromResponse($this->stub('hmall-product-detail-gu.json'));

        $this->assertCount(12, $detail->mainImages);
        $this->assertSame([
            'medium' => '/hmall/test/u0000000053291/main/first/561/1.jpg',
            'large' => '/hmall/test/u0000000053291/main/first/1000/1.jpg',
        ], $detail->mainImages[0]);
        $this->assertSame('/hmall/test/u0000000053291/main/other11/1000/12.jpg', $detail->mainImages[11]['large']);
        $this->assertSame([16, 0, 0, 0, 0], $detail->ratingLevels);
        $this->assertSame('柬埔寨製', $detail->originPlace);
        $this->assertSame([], $detail->videos);
        $this->assertSame([
            'instruction' => '/hmall/test/u0000000053291/zh_TW/instructionH5.html',
            'features' => '/hmall/test/u0000000053291/zh_TW/features.html',
            'material_care' => '/hmall/test/u0000000053291/zh_TW/materialCare.html',
        ], $detail->fragmentPaths);
    }

    public function test_a_product_without_origin_or_some_fragments_leaves_them_empty(): void
    {
        $body = $this->stub('hmall-product-detail-uniqlo.json');
        $body['resp'][0]['spuInfo']['desc']['materialCare'] = null;

        $detail = OfficialProductDetail::fromResponse($body);

        $this->assertCount(5, $detail->mainImages);
        $this->assertNull($detail->originPlace);
        $this->assertNull($detail->fragmentPaths['material_care']);
    }

    public function test_videos_come_from_the_largest_size(): void
    {
        $body = $this->stub('hmall-product-detail-uniqlo.json');
        $body['resp'][0]['imageInfo']['video'] = [
            '561' => ['/hmall/test/u0000000053924/video/561/2.mp4'],
            '1000' => ['/hmall/test/u0000000053924/video/1000/2.mp4'],
        ];

        $detail = OfficialProductDetail::fromResponse($body);

        $this->assertSame(['/hmall/test/u0000000053924/video/1000/2.mp4'], $detail->videos);
    }

    public function test_paths_outside_the_official_sites_are_dropped(): void
    {
        $body = $this->stub('hmall-product-detail-uniqlo.json');
        $body['resp'][0]['imageInfo']['main']['1000'] = [
            '/hmall/test/u0000000053924/main/first/1000/1.jpg',
            'https://evil.example.com/a.jpg',
            '//evil.example.com/b.jpg',
            'javascript:alert(1)',
        ];
        $body['resp'][0]['spuInfo']['desc']['features'] = 'https://evil.example.com/features.html';

        $detail = OfficialProductDetail::fromResponse($body);

        $this->assertSame(['/hmall/test/u0000000053924/main/first/1000/1.jpg'], array_column($detail->mainImages, 'large'));
        $this->assertNull($detail->fragmentPaths['features']);
    }

    public static function missingResponses(): array
    {
        return [
            'not successful' => [['success' => false, 'resp' => []]],
            'empty resp' => [['success' => true, 'resp' => []]],
            'not an array' => ['oops'],
        ];
    }

    #[DataProvider('missingResponses')]
    public function test_a_missing_product_is_null(mixed $body): void
    {
        $this->assertNull(OfficialProductDetail::fromResponse($body));
    }

    public function test_rating_levels_that_are_not_five_counts_are_ignored(): void
    {
        $body = $this->stub('hmall-product-detail-uniqlo.json');
        $body['resp'][0]['spuInfo']['summary']['levels'] = [1, 2, 3];

        $this->assertNull(OfficialProductDetail::fromResponse($body)->ratingLevels);
    }

    public function test_features_keep_each_image_and_its_text_without_the_tracking_scripts(): void
    {
        $features = OfficialProductDetail::parseFeatures(
            '<script>var x = "不該出現";</script>'.file_get_contents(base_path('tests/stubs/hmall-product-features-uniqlo.html'))
        );

        $this->assertSame([
            [
                'image' => 'https://www.uniqlo.com/tw/hmall/test/u0000000053924/features/1.jpg',
                'text' => '外觀如棉質般的「AIRism」材質，即使流汗也能保持乾爽膚觸。穿上瞬間立即冰涼舒適。',
            ],
            [
                'image' => 'https://www.uniqlo.com/tw/hmall/test/u0000000053924/features/2.jpg',
                'text' => '稍高的圓領設計，打造簡約清新風格。',
            ],
        ], $features);
    }

    public function test_material_care_in_the_gu_format(): void
    {
        $sections = OfficialProductDetail::parseMaterialCare(file_get_contents(base_path('tests/stubs/hmall-product-material-care-gu.html')));

        $this->assertSame(['商品材質', '洗滌說明'], array_column($sections, 'title'));
        $this->assertSame('55%聚丙烯腈纖維．38%嫘縈．7%彈性纖維', $sections[0]['content']);
        $this->assertStringStartsWith("洗衣機/弱水流（水溫40度）。不可烘乾。在陰涼處懸掛晾乾。最高溫度攝氏110度熨燙。不可使用乾燥機。\n深色衣物", $sections[1]['content']);
    }

    public function test_material_care_in_the_uniqlo_format(): void
    {
        $sections = OfficialProductDetail::parseMaterialCare(file_get_contents(base_path('tests/stubs/hmall-product-material-care-uniqlo.html')));

        $this->assertSame(['商品材質', '洗滌方式'], array_column($sections, 'title'));
        $this->assertSame('大身: 53% 棉, 30% 聚酯纖維( 再生聚酯纖維 ), 17% 聚酯纖維/ 羅紋部分: 73% 棉, 27% 聚酯纖維', $sections[0]['content']);
        $this->assertCount(8, explode("\n", $sections[1]['content']));
    }

    public function test_material_care_on_one_line_with_self_closing_breaks(): void
    {
        $this->assertSame([
            ['title' => '商品材質', 'content' => '皮革( 牛革 )'],
            ['title' => '洗滌方式', 'content' => '本商品會染色。'],
        ], OfficialProductDetail::parseMaterialCare('<strong>商品材質</strong><br />皮革( 牛革 )<br > <br > <strong>洗滌方式</strong><br />本商品會染色。'));
    }

    public function test_empty_fragments_parse_to_nothing(): void
    {
        $this->assertSame([], OfficialProductDetail::parseFeatures(''));
        $this->assertSame([], OfficialProductDetail::parseMaterialCare('  '));
    }

    private function stub(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/stubs/{$name}")), true);
    }
}
