<?php

namespace Tests\Unit\Models;

use App\Models\StyleHint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class StyleHintTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /**
     * @dataProvider dataProvider
     */
    public function test_user_can_set_and_get_StyleHint_attributes(
        $country,
        $styleImageUrlFromApi,
        $styleImageUrlFromDb,
        $originalSourceUrlFromApi,
        $originalSourceUrlFromDb
    ) {
        $model = new StyleHint();

        $model->country = $country;
        $model->outfit_id = fake()->randomNumber(7, false);
        $model->style_image_url = $styleImageUrlFromApi;
        $model->original_source_url = $originalSourceUrlFromApi;

        $model->save();

        $this->assertEquals($styleImageUrlFromApi, $model->style_image_url);
        $this->assertEquals($originalSourceUrlFromApi, $model->original_source_url);

        $this->assertEquals($styleImageUrlFromDb, $model->getRawOriginal('style_image_url'));
        $this->assertEquals($originalSourceUrlFromDb, $model->getRawOriginal('original_source_url'));
    }

    public function dataProvider()
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
