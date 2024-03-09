<?php

namespace App\Console\Commands;

use App\Repositories\ProductRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
        Log::debug('SetProductCaches start');

        $productRepository->setLimitedOfferProductsCache();
        $productRepository->setSaleProductsCache();
        $productRepository->setMostReviewedProductsCache();
        $productRepository->setNewProductsCache();
        $productRepository->setStockoutProductsCache();
        $productRepository->setMultiBuyProductsCache();

        Log::debug('SetProductCaches end');
        $this->info('Set product caches');
    }
}
