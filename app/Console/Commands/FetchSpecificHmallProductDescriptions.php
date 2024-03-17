<?php

namespace App\Console\Commands;

use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use App\Models\HmallProduct;
use App\Services\HmallProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchSpecificHmallProductDescriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hmall-product-description:fetch-specific
                            {product_codes* : The product codes of the products}
                            {--brand=UNIQLO : The brand of the products}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch specific Hmall Product descriptions from SPU';

    /**
     * Execute the console command.
     */
    public function handle(HmallProductService $hmallProductService)
    {
        $productCodes = $this->argument('product_codes');
        $brand = $this->option('brand');

        $this->info("Fetching Hmall product descriptions for {$brand}...");
        AppTaskStarting::dispatch(class_basename(__CLASS__), $brand, null, ['product_codes' => $productCodes]);

        $hmallProducts = HmallProduct::whereIn('product_code', $productCodes)
            ->where('brand', $brand)
            ->whereNull('stockout_at')
            ->select(['id', 'product_code'])
            ->get();

        $this->table(
            ['id', 'product_code'],
            $hmallProducts->toArray()
        );

        Log::debug('Specific HmallProduct', ['ids' => $hmallProducts->pluck('id')->toArray()]);

        $this->withProgressBar($hmallProducts, function (HmallProduct $hmallProduct) use (
            $hmallProductService,
            $brand,
        ) {
            $hmallProductService->fetchHmallProductDescriptions($hmallProduct, $brand, false);
        });

        $this->newLine();

        AppTaskFinished::dispatch(class_basename(__CLASS__), $brand, null, ['product_codes' => $productCodes]);
        $this->info("Fetched Hmall product descriptions for {$brand}");

        return 0;
    }
}
