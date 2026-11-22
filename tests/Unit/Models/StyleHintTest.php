<?php

namespace Tests\Unit\Models;

use App\Models\StyleHint;
use App\Repositories\StyleHintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StyleHintTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 穿搭圖與來源網址在資料庫存成精簡格式省空間，讀出來時還原成完整網址。
     *
     * @dataProvider compactUrlProvider
     */
    public function test_urls_are_stored_compactly_and_read_back_in_full(
        $country,
        $styleImageUrlFromApi,
        $styleImageUrlFromDb,
        $originalSourceUrlFromApi,
        $originalSourceUrlFromDb
    ): void {
        $model = new StyleHint;

        $model->country = $country;
        $model->outfit_id = '1234567';
        $model->style_image_url = $styleImageUrlFromApi;
        $model->original_source_url = $originalSourceUrlFromApi;

        $model->save();

        $this->assertEquals($styleImageUrlFromApi, $model->style_image_url);
        $this->assertEquals($originalSourceUrlFromApi, $model->original_source_url);

        $this->assertEquals($styleImageUrlFromDb, $model->getRawOriginal('style_image_url'));
        $this->assertEquals($originalSourceUrlFromDb, $model->getRawOriginal('original_source_url'));
    }

    /**
     * @dataProvider sourceOutfitIdProvider
     */
    public function test_saving_keeps_the_source_outfit_id_from_the_image_file_name(
        string $styleImageUrl,
        ?string $originalSourceUrl,
        ?string $expected
    ): void {
        $model = new StyleHint;
        $model->country = 'jp';
        $model->outfit_id = '16693251';
        $model->style_image_url = $styleImageUrl;
        $model->original_source_url = $originalSourceUrl;
        $model->save();

        $this->assertSame($expected, $model->fresh()->source_outfit_id);
    }

    public static function sourceOutfitIdProvider(): array
    {
        return [
            'stylehint' => [
                'https://api.fastretailing.com/ugc/v1/uq/jp/SR_IMAGES/ugc_stylehint_uq_jp_photo_991231_2043193',
                'https://www.stylehint.com/jp/ja/outfit/2043193',
                '2043193',
            ],
            'gu_stylehint' => [
                'https://api.fastretailing.com/ugc/v1/gu/jp/SR_IMAGES/ugc_stylehint_gu_jp_photo_991231_2043193',
                'https://www.stylehint.com/jp/ja/outfit/2043193',
                '2043193',
            ],
            'instagram_business' => [
                'https://api.fastretailing.com/ugc/v1/uq/us/SR_IMAGES/ugc_instagram-business_uq_us_photo_991231_2043193',
                'https://www.instagram.com/p/C/',
                'instagram-business:2043193',
            ],
            'olapic' => [
                'https://api.fastretailing.com/ugc/v1/uq/us/SR_IMAGES/ugc_olapic_uq_us_photo_991231_2043193',
                'https://www.instagram.com/p/C/',
                'olapic:2043193',
            ],
            'unrecognized_image_url' => [
                'https://example.com/photo.jpg',
                'https://www.stylehint.com/jp/ja/outfit/2043193',
                null,
            ],
            'no_source_link' => [
                'https://api.fastretailing.com/ugc/v1/uq/jp/SR_IMAGES/ugc_stylehint_uq_jp_photo_991231_2043193',
                null,
                null,
            ],
        ];
    }

    public function test_taiwan_and_japan_copies_of_one_outfit_share_the_source_outfit_id(): void
    {
        $repository = app(StyleHintRepository::class);
        $imageUrl = 'https://api.fastretailing.com/ugc/v1/uq/jp/SR_IMAGES/ugc_stylehint_uq_jp_photo_991231_2043193';

        $repository->saveStyleHintsFromUgc([(object) [
            'id_ugc_dist_content' => '16693239',
            'image_original' => $imageUrl,
            'link_to_url' => 'https://www.stylehint.com/jp/ja/outfit/2043193',
        ]]);
        $repository->saveStyleHints('jp', (object) [
            'outfitId' => '16693251',
            'styleImageUrl' => $imageUrl,
            'originalSourceUrl' => 'https://www.stylehint.com/jp/ja/outfit/2043193',
        ], []);

        $this->assertSame(
            ['jp' => '2043193', 'tw' => '2043193'],
            StyleHint::orderBy('country')->pluck('source_outfit_id', 'country')->all()
        );
    }

    public static function compactUrlProvider(): array
    {
        return [
            'uq_jp_stylehint' => [
                'country' => 'jp',
                'styleImageUrlFromApi' => 'https://api.fastretailing.com/ugc/v1/uq/jp/SR_IMAGES/ugc_stylehint_uq_jp_photo_991231_111111',
                'styleImageUrlFromDb' => 'jp991231',
                'originalSourceUrlFromApi' => 'https://www.stylehint.com/jp/ja/outfit/111111',
                'originalSourceUrlFromDb' => '111111',
            ],
            'gu_jp_stylehint' => [
                'country' => 'jp',
                'styleImageUrlFromApi' => 'https://api.fastretailing.com/ugc/v1/gu/jp/SR_IMAGES/ugc_stylehint_gu_jp_photo_991231_111111',
                'styleImageUrlFromDb' => 'jp991231gu',
                'originalSourceUrlFromApi' => 'https://www.stylehint.com/jp/ja/outfit/111111',
                'originalSourceUrlFromDb' => '111111',
            ],
            'uq_us_stylehint' => [
                'country' => 'us',
                'styleImageUrlFromApi' => 'https://api.fastretailing.com/ugc/v1/uq/us/SR_IMAGES/ugc_stylehint_uq_us_photo_991231_111111',
                'styleImageUrlFromDb' => 'us991231',
                'originalSourceUrlFromApi' => 'https://www.stylehint.com/us/en/outfit/111111',
                'originalSourceUrlFromDb' => '111111',
            ],
            'gu_us_stylehint' => [
                'country' => 'us',
                'styleImageUrlFromApi' => 'https://api.fastretailing.com/ugc/v1/gu/us/SR_IMAGES/ugc_stylehint_gu_us_photo_991231_111111',
                'styleImageUrlFromDb' => 'us991231gu',
                'originalSourceUrlFromApi' => 'https://www.stylehint.com/us/en/outfit/111111',
                'originalSourceUrlFromDb' => '111111',
            ],
            'uq_us_instagram-business' => [
                'country' => 'us',
                'styleImageUrlFromApi' => 'https://api.fastretailing.com/ugc/v1/uq/us/SR_IMAGES/ugc_instagram-business_uq_us_photo_991231_111111',
                'styleImageUrlFromDb' => 'us991231Igbiz111111',
                'originalSourceUrlFromApi' => 'https://www.instagram.com/p/C/',
                'originalSourceUrlFromDb' => 'https://www.instagram.com/p/C/',
            ],
            'uq_us_olapic' => [
                'country' => 'us',
                'styleImageUrlFromApi' => 'https://api.fastretailing.com/ugc/v1/uq/us/SR_IMAGES/ugc_olapic_uq_us_photo_991231_111111',
                'styleImageUrlFromDb' => 'us991231Olapic111111',
                'originalSourceUrlFromApi' => 'https://www.instagram.com/p/C/',
                'originalSourceUrlFromDb' => 'https://www.instagram.com/p/C/',
            ],
        ];
    }
}
