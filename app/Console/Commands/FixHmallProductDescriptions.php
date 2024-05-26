<?php

namespace App\Console\Commands;

use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use App\Models\HmallProduct;
use App\Presenters\HmallProductPresenter;
use App\Services\HmallProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixHmallProductDescriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hmall-product-description:fix
                            {--brand=UNIQLO : The brand of the products}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix Hmall Product descriptions';

    /**
     * Execute the console command.
     */
    public function handle(HmallProductService $hmallProductService, HmallProductPresenter $hmallProductPresenter)
    {
        $brand = $this->option('brand');

        $this->info("Fixing Hmall product descriptions for {$brand}...");
        AppTaskStarting::dispatch(class_basename(__CLASS__), $brand);

        $shortDescriptionProducts = $this->getShortDescriptionProducts($brand, $hmallProductPresenter);

        $this->table(
            ['id', 'product_code'],
            $shortDescriptionProducts->map(function ($hmallProduct) {
                return ['id' => $hmallProduct->id, 'product_code' => $hmallProduct->product_code];
            })->toArray()
        );

        Log::debug(
            'There are '
            . $shortDescriptionProducts->count()
            . " short description {$brand} Hmall Products, ids: "
            . json_encode($shortDescriptionProducts->pluck('id')->toArray())
        );

        $this->withProgressBar($shortDescriptionProducts, function (HmallProduct $hmallProduct) use (
            $hmallProductService,
            $brand,
        ) {
            $hmallProductService->fetchHmallProductDescriptions($hmallProduct, $brand, false);
        });

        $shortDescriptionProducts = $this->getShortDescriptionProducts($brand, $hmallProductPresenter);

        Log::debug(
            'Fixed descriptions. But there are still '
            . $shortDescriptionProducts->count()
            . " short description {$brand} Hmall Products, ids: "
            . json_encode($shortDescriptionProducts->pluck('id')->toArray())
        );

        $this->newLine();

        AppTaskFinished::dispatch(class_basename(__CLASS__), $brand);
        $this->info("Fixed Hmall product descriptions for {$brand}");

        return 0;
    }

    private function getShortDescriptionProducts(string $brand, HmallProductPresenter $hmallProductPresenter)
    {
        $shortDescriptionProducts = collect([]);

        HmallProduct::query()
            ->where('brand', $brand)
            ->select(['id', 'product_code', 'instruction'])
            ->orderBy('id', 'desc')
            ->chunk(100, function ($hmallProducts) use ($hmallProductPresenter, $shortDescriptionProducts) {
                foreach ($hmallProducts as $hmallProduct) {
                    $description = $hmallProductPresenter->getDescription($hmallProduct);
                    if (strlen($description) < 50) {
                        $shortDescriptionProducts->push($hmallProduct);
                    }
                }
            });

        return $shortDescriptionProducts;
    }
}
