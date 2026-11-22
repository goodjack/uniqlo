<?php

namespace Tests\Feature\Console\Commands;

use App\Models\HmallProduct;
use App\Services\OutfitStatsService;
use App\Support\TaskNotes;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class CacheOutfitStatsTest extends TestCase
{
    use RefreshDatabase;

    private int $nextId = 0;

    public function test_it_caches_stats_for_products_on_sale_only(): void
    {
        $this->createProduct('450001');
        $this->createProduct('450002', ['stockout_at' => now()->subDay()]);
        $this->createProduct('450003');
        $this->seedOutfits('450001', OutfitStatsService::MIN_OUTFITS);
        $this->seedOutfits('450002', OutfitStatsService::MIN_OUTFITS);
        $this->seedOutfits('450003', 1);

        $this->artisan('style-hint:cache-stats')->assertSuccessful();

        $this->assertSame(OutfitStatsService::MIN_OUTFITS, $this->statsOf('450001')['outfits']);
        $this->assertNull($this->statsOf('450002'));
        $this->assertNull($this->statsOf('450003'));
    }

    public function test_the_all_option_includes_products_no_longer_on_sale(): void
    {
        $this->createProduct('450002', ['stockout_at' => now()->subDay()]);
        $this->seedOutfits('450002', OutfitStatsService::MIN_OUTFITS);

        $this->artisan('style-hint:cache-stats', ['--all' => true])->assertSuccessful();

        $this->assertNotNull($this->statsOf('450002'));
    }

    public function test_the_product_option_limits_the_run_to_those_product_codes(): void
    {
        $this->createProduct('450001');
        $this->createProduct('450002');
        $this->seedOutfits('450001', OutfitStatsService::MIN_OUTFITS);
        $this->seedOutfits('450002', OutfitStatsService::MIN_OUTFITS);

        $this->artisan('style-hint:cache-stats', ['--product' => ['u450002']])->assertSuccessful();

        $this->assertNull($this->statsOf('450001'));
        $this->assertNotNull($this->statsOf('450002'));
    }

    public function test_detected_items_of_the_same_kind_as_the_product_are_left_out(): void
    {
        $this->createProduct('450001', ['name' => '喇叭牛仔褲']);
        $this->seedOutfits('450001', OutfitStatsService::MIN_OUTFITS, detected: [
            ['category' => 'bottoms', 'color' => 'blue'],
            ['category' => 'shoes', 'color' => 'black'],
        ]);

        $this->artisan('style-hint:cache-stats')->assertSuccessful();

        $this->assertSame(['shoes'], array_column($this->statsOf('450001')['detected_pairs'], 'category'));
    }

    public function test_a_few_failing_products_do_not_fail_the_scheduled_step(): void
    {
        $this->createProducts(['450001', '450002', '450003']);
        $this->failFor(['450001']);

        $this->artisan('style-hint:cache-stats')
            ->expectsOutputToContain('2 件有穿搭統計，1 件失敗')
            ->assertSuccessful();

        $this->assertNull(app(TaskNotes::class)->pull('style-hint:cache-stats', null));
    }

    /**
     * 多數商品都失敗代表程式本身壞了，要讓每日排程的失敗通知看得到。
     */
    public function test_most_products_failing_fails_the_step_with_a_note_for_the_schedule(): void
    {
        $this->createProducts(['450001', '450002', '450003']);
        $this->failFor(['450001', '450002']);

        $this->artisan('style-hint:cache-stats')->assertFailed();

        $this->assertSame('3 件中 2 件失敗', app(TaskNotes::class)->pull('style-hint:cache-stats', null));
    }

    public function test_every_product_failing_fails_the_step(): void
    {
        $this->createProducts(['450001']);
        $this->failFor(['450001']);

        $this->artisan('style-hint:cache-stats')->assertFailed();
    }

    public function test_the_same_error_is_reported_once_however_many_products_hit_it(): void
    {
        $this->createProducts(['450001', '450002', '450003', '450004']);
        $this->failFor(['450001', '450002', '450003'], sameErrorFor: ['450001', '450002']);

        $reported = [];
        $this->mock(ExceptionHandler::class, function ($mock) use (&$reported) {
            $mock->shouldReceive('report')->andReturnUsing(function (Throwable $e) use (&$reported) {
                $reported[] = $e->getMessage();
            });
        });

        $this->artisan('style-hint:cache-stats')->run();

        $this->assertSame(['同一個錯誤', '測試用的失敗 450003'], $reported);
    }

    private function statsOf(string $code): ?array
    {
        return app(OutfitStatsService::class)->get(HmallProduct::where('code', $code)->firstOrFail());
    }

    /**
     * @param  array<int, string>  $codes
     */
    private function createProducts(array $codes): void
    {
        foreach ($codes as $code) {
            $this->createProduct($code);
        }
    }

    /**
     * 這幾件商品算統計時丟例外，其他件照常回結果。
     *
     * @param  array<int, string>  $failingCodes
     * @param  array<int, string>  $sameErrorFor  這幾件丟一模一樣的錯誤
     */
    private function failFor(array $failingCodes, array $sameErrorFor = []): void
    {
        $this->mock(OutfitStatsService::class, function ($mock) use ($failingCodes, $sameErrorFor) {
            $mock->shouldReceive('refresh')->andReturnUsing(function (HmallProduct $product) use ($failingCodes, $sameErrorFor) {
                if (in_array($product->code, $sameErrorFor, true)) {
                    throw new RuntimeException('同一個錯誤');
                }

                if (in_array($product->code, $failingCodes, true)) {
                    throw new RuntimeException("測試用的失敗 {$product->code}");
                }

                return ['outfits' => OutfitStatsService::MIN_OUTFITS];
            });
        });
    }

    /**
     * @param  array<int, array{category: string, color: string}>|null  $detected
     */
    private function seedOutfits(string $code, int $count, ?array $detected = null): void
    {
        foreach (range(1, $count) as $_) {
            $id = ++$this->nextId;

            DB::table('style_hints')->insert([
                'id' => $id,
                'outfit_id' => "outfit-{$id}",
                'country' => 'tw',
                'source_outfit_id' => "s{$id}",
                'gender' => 2,
                'model_height' => '160',
                'detected_items' => $detected === null ? null : json_encode($detected),
            ]);
            DB::table('style_hint_items')->insert([
                'style_hint_id' => $id,
                'code' => $code,
                'original_product_id' => "{$code}-09",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createProduct(string $code, array $attributes = []): HmallProduct
    {
        return HmallProduct::unguarded(fn () => HmallProduct::create(array_merge([
            'brand' => 'UNIQLO',
            'name' => "測試商品 {$code}",
            'code' => $code,
            'product_code' => "u{$code}",
            'gender' => '女裝',
            'sex' => '女裝',
            'identity' => '[]',
            'stock' => 'Y',
            'min_price' => 490,
            'lowest_record_price' => 490,
            'highest_record_price' => 490,
        ], $attributes)));
    }
}
