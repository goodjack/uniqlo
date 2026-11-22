<?php

namespace App\Console\Commands;

use App\Enums\Brand;
use App\Events\AppTaskFailed;
use App\Models\HmallProduct;
use App\Services\HmallProductDetailService;
use Illuminate\Console\Command;

class FetchHmallProductDetails extends Command
{
    protected $signature = 'hmall-product-detail:fetch
        {brand? : UNIQLO 或 GU，不給就兩個品牌都跑}
        {--product=* : 只抓這幾件（網路商店編號），不管排程}
        {--priority-limit= : 這一輪優先組最多幾件}
        {--rotation-limit= : 這一輪輪替組最多幾件}';

    protected $description = '更新商品頁的相簿、影片與商品資訊';

    public function handle(HmallProductDetailService $service): int
    {
        $brands = $this->argument('brand') === null
            ? array_map(fn (Brand $brand) => $brand->value, Brand::cases())
            : [Brand::from(strtoupper($this->argument('brand')))->value];

        $stopped = [];

        foreach ($brands as $brand) {
            $summary = $this->option('product') === []
                ? $service->fetchDue($brand, $this->limitOption('priority-limit'), $this->limitOption('rotation-limit'))
                : $service->fetchProducts(HmallProduct::with('detail')
                    ->where('brand', $brand)
                    ->whereIn('product_code', $this->option('product'))
                    ->get());

            $this->info(sprintf(
                '%s：抓了 %d 件，內容有變 %d、官網沒有 %d、失敗 %d',
                $brand,
                $summary['checked'],
                $summary['changed'],
                $summary['missing'],
                $summary['failed'],
            ));

            if ($summary['stopped'] !== null) {
                $this->error("{$brand} 提早停下：{$summary['stopped']}");
                $stopped[] = "{$brand}：{$summary['stopped']}";

                // 被擋時另一個品牌也不跑；只是這個品牌的商品一直失敗就換下一個品牌
                if ($summary['blocked']) {
                    break;
                }
            }
        }

        if ($stopped !== []) {
            AppTaskFailed::dispatch(class_basename(__CLASS__), null, null, ['stopped' => $stopped]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function limitOption(string $name): ?int
    {
        $value = $this->option($name);

        return $value === null ? null : max(0, (int) $value);
    }
}
