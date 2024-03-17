<?php

namespace App\Console\Commands;

use App\Events\AppTaskFinished;
use App\Events\AppTaskStarting;
use App\Repositories\ProductRepository;
use Illuminate\Console\Command;

class SetProductCaches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set product caches';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(ProductRepository $productRepository)
    {
        $this->info('Setting product caches...');
        AppTaskStarting::dispatch(class_basename(__CLASS__));

        $productRepository->setLimitedOfferProductsCache();
        $productRepository->setSaleProductsCache();
        $productRepository->setMostReviewedProductsCache();
        $productRepository->setNewProductsCache();
        $productRepository->setStockoutProductsCache();
        $productRepository->setMultiBuyProductsCache();

        AppTaskFinished::dispatch(class_basename(__CLASS__));
        $this->info('Set product caches');
    }
}
