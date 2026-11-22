<?php

namespace App\Console\Commands;

use App\Models\HmallProduct;
use App\Services\OutfitStatsService;
use App\Support\TaskNotes;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

class CacheOutfitStats extends Command
{
    protected $signature = 'style-hint:cache-stats
        {--all : 連不在架上的商品一起算}
        {--product=* : 只算這幾件（官網商品編號）}';

    protected $description = '重算商品頁的穿搭統計並存進快取';

    /**
     * 每種錯誤在 log 裡列出的商品代號上限，其餘只記件數。
     */
    private const LOGGED_CODES = 20;

    /**
     * 預設只算在架上的商品：下架商品不會再有新穿搭，保留上次算好的結果。
     *
     * 少數商品失敗只回報、照樣回成功，不擋每日排程最後的清快取；超過一半失敗代表程式本身壞了，
     * 回傳失敗讓排程的失敗通知看得到。
     */
    public function handle(OutfitStatsService $service, TaskNotes $taskNotes): int
    {
        $productCodes = $this->option('product');
        $seenCodes = [];
        $computed = 0;
        $withStats = 0;
        /** @var array<string, array{error: string, codes: array<int, string>}> 以錯誤分組，同一種錯誤只回報一次 */
        $failures = [];

        HmallProduct::query()
            ->select(['id', 'code', 'name'])
            ->whereNotNull('code')
            ->when(! $this->option('all') && $productCodes === [], fn ($query) => $query->whereNull('stockout_at'))
            ->when($productCodes !== [], fn ($query) => $query->whereIn('product_code', $productCodes))
            ->chunkById(500, function (Collection $products) use ($service, &$seenCodes, &$computed, &$withStats, &$failures) {
                foreach ($products as $product) {
                    // 穿搭以商品代號對應，同代號的商品只算一次
                    if (isset($seenCodes[$product->code])) {
                        continue;
                    }
                    $seenCodes[$product->code] = true;

                    try {
                        $computed++;
                        if ($service->refresh($product) !== null) {
                            $withStats++;
                        }
                    } catch (Throwable $e) {
                        $error = get_class($e).': '.$e->getMessage();

                        if (! isset($failures[$error])) {
                            $failures[$error] = ['error' => $error, 'codes' => []];
                            report($e);
                        }

                        $failures[$error]['codes'][] = $product->code;
                    }
                }
            });

        $failed = array_sum(array_map(fn (array $failure) => count($failure['codes']), $failures));

        foreach ($failures as $failure) {
            logger()->error('Outfit stats failed', [
                'error' => $failure['error'],
                'count' => count($failure['codes']),
                'codes' => array_slice($failure['codes'], 0, self::LOGGED_CODES),
            ]);
        }

        $this->info("算了 {$computed} 件商品，{$withStats} 件有穿搭統計，{$failed} 件失敗");

        if ($failed * 2 <= $computed) {
            return self::SUCCESS;
        }

        $taskNotes->put($this->getName(), null, "{$computed} 件中 {$failed} 件失敗");

        return self::FAILURE;
    }
}
